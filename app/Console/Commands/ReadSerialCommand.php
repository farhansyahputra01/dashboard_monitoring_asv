<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SensorData;
use App\Events\SensorDataUpdated;

class ReadSerialCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asv:read-serial {port=/dev/ttyUSB0 : The serial port to read from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read sensor data from ESP32 via serial port';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $port = $this->argument('port');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $comPort = strtoupper(trim($port));

            // Jangan diam-diam jatuh ke COM3: kalau portnya salah, lebih baik
            // berhenti dengan pesan jelas daripada "mendengarkan" port yang keliru.
            if (!preg_match('/^COM\d+$/i', $comPort)) {
                $this->error("Port '$port' bukan nama port Windows yang valid (contoh: COM5).");
                $this->showAvailablePorts();
                return 1;
            }

            $available = $this->availableWindowsPorts();
            if ($available !== null && !in_array($comPort, $available, true)) {
                $this->error("$comPort tidak terdeteksi di komputer ini.");
                $this->showAvailablePorts();
                return 1;
            }

            $this->info("Opening $comPort at 115200 baud via PowerShell on Windows...");

            // Catatan penting soal sintaks di bawah:
            // `New-Object <Type> a, b, [Enum]::X` memparsing argumen dalam
            // "argument mode", sehingga [System.IO.Ports.Parity]::None ikut
            // terbaca sebagai STRING dan konstruktornya gagal. Argumen enum
            // wajib dibungkus kurung supaya dievaluasi sebagai ekspresi.
            $psScript = '$ErrorActionPreference = \'Stop\'; '
                . 'try { '
                . '$p = New-Object System.IO.Ports.SerialPort -ArgumentList \'' . $comPort . '\', 115200, ([System.IO.Ports.Parity]::None), 8, ([System.IO.Ports.StopBits]::One); '
                // DTR dan RTS WAJIB di-set eksplisit sebelum Open(). Kalau
                // dibiarkan default, driver CP210x mempertahankan state
                // sebelumnya dan ESP32 ikut ter-reset saat port dibuka -
                // board masuk mode boot lalu diam total. Kombinasi false/false
                // dan true/true sama-sama aman; yang mematikan adalah saat
                // DTR dan RTS berbeda (itu memicu jalur reset/download board).
                . '$p.DtrEnable = $false; '
                . '$p.RtsEnable = $false; '
                . '$p.Open() '
                // Error sengaja ditulis ke STDOUT dengan penanda ASVERR, bukan
                // ke stderr. Di Windows, stream_select() PHP tidak bekerja
                // untuk pipe (hanya socket), jadi memantau dua pipe sekaligus
                // tidak reliabel - hanya baris pertama yang lolos. Dengan satu
                // aliran saja, PHP cukup membaca blocking seperti biasa.
                . '} catch { Write-Output (\'ASVERR Gagal membuka ' . $comPort . ': \' + $_.Exception.Message); exit 1 }; '
                // Baris dipotong manual dari ReadExisting(), BUKAN ReadLine().
                //
                // Dua hal yang wajib dipertahankan di loop ini, keduanya hasil
                // pengukuran pada adapter CP210x board ini:
                //
                // 1. ReadLine() mengembalikan potongan baris basi yang sama
                //    ribuan kali per detik (4350 pembacaan / 12 detik, hanya
                //    15 isi unik) sehingga tidak pernah ada baris utuh.
                // 2. ReadExisting() TANPA memeriksa BytesToRead lebih dulu
                //    juga mengulang isi buffer lama - ratusan "baris" dalam
                //    40 ms, laju yang mustahil pada 115200 baud. Dengan
                //    penjaga BytesToRead, hasilnya tepat 1 baris/detik sesuai
                //    TELEMETRY_INTERVAL_MS di firmware.
                . '$nl = [char]10; '
                . '$buf = \'\'; '
                . 'while ($true) { '
                . 'try { '
                . 'if ($p.BytesToRead -eq 0) { Start-Sleep -Milliseconds 50; continue } '
                . '$buf = $buf + $p.ReadExisting(); '
                // Jaga-jaga kalau newline tidak pernah datang (data rusak),
                // supaya buffer tidak tumbuh tanpa batas.
                . 'if ($buf.Length -gt 8192) { $buf = \'\' } '
                . '$i = $buf.IndexOf($nl); '
                . 'while ($i -ge 0) { '
                . '$line = $buf.Substring(0, $i).Trim(); '
                . '$buf = $buf.Substring($i + 1); '
                . 'if ($line.Length -gt 0) { Write-Output $line } '
                . '$i = $buf.IndexOf($nl) '
                . '} '
                . '} catch { Write-Output (\'ASVERR Koneksi serial terputus: \' + $_.Exception.Message); exit 1 } '
                . '}';

            $descriptors = [
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];

            // -NoProfile supaya profil PowerShell pengguna (yang bisa diblokir
            // ExecutionPolicy) tidak mencemari pipe dengan pesan error.
            $process = proc_open(
                'powershell -NoProfile -ExecutionPolicy Bypass -Command "' . $psScript . '"',
                $descriptors,
                $pipes
            );

            if (!is_resource($process)) {
                $this->error("Failed to launch PowerShell serial listener for $comPort.");
                return 1;
            }

            $this->info("Listening for data on $comPort...");

            $exitCode = $this->pumpPipes($process, $pipes);

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if ($exitCode !== 0) {
                $this->showAvailablePorts();
            }

            return $exitCode;
        } else {
            $this->info("Configuring $port at 115200 baud on Linux...");
            @exec("stty -F $port 115200 raw -echo");

            $fp = @fopen($port, "r");
            if (!$fp) {
                $this->error("Could not open serial port: $port");
                return 1;
            }

            $this->info("Listening for data on $port...");

            while (!feof($fp)) {
                $data = fgets($fp);
                if ($data !== false) {
                    $this->processData(trim($data));
                }
                usleep(100000);
            }

            fclose($fp);
            return 0;
        }
    }

    /**
     * Baca keluaran proses PowerShell baris demi baris.
     *
     * Hanya stdout yang dipantau di dalam loop: skrip PowerShell menandai
     * pesan errornya dengan awalan ASVERR, karena stream_select() PHP tidak
     * bekerja untuk pipe di Windows (hanya socket) sehingga memantau stdout
     * dan stderr bersamaan membuat data tersendat - cuma baris pertama yang
     * lolos. Sisa stderr dibaca setelah proses berhenti, saat sudah tidak
     * mungkin memblokir.
     *
     * Semua pesan di sini adalah output terminal Artisan, tidak pernah dikirim
     * ke dashboard web.
     */
    private function pumpPipes($process, array $pipes): int
    {
        $sawError = false;

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);

            if ($line === false) {
                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'ASVERR ')) {
                $sawError = true;
                $this->error(substr($line, 7));
                continue;
            }

            $this->processData($line);
        }

        $stderr = trim((string)stream_get_contents($pipes[2]));

        if ($stderr !== '') {
            $sawError = true;
            $this->error($stderr);
        }

        $status = proc_get_status($process);

        return ($status['exitcode'] ?? 0) !== 0 || $sawError ? 1 : 0;
    }

    /**
     * Daftar COM port yang benar-benar ada. null bila tidak bisa dideteksi.
     */
    private function availableWindowsPorts(): ?array
    {
        // Penanda 'PORTS:' wajib ada supaya daftar kosong ("tidak ada port")
        // bisa dibedakan dari perintah yang gagal jalan. Tanpa penanda,
        // shell_exec sama-sama mengembalikan NULL untuk kedua kondisi itu.
        $output = @shell_exec(
            'powershell -NoProfile -Command "\'PORTS:\' + (@([System.IO.Ports.SerialPort]::getportnames()) -join \',\')"'
        );

        if (!is_string($output) || !str_contains($output, 'PORTS:')) {
            return null;
        }

        $list = trim(substr($output, strpos($output, 'PORTS:') + 6));

        return array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim($p)),
            explode(',', $list)
        )));
    }

    private function showAvailablePorts(): void
    {
        $ports = $this->availableWindowsPorts();

        if ($ports === null) {
            return;
        }

        if ($ports === []) {
            $this->warn('Tidak ada COM port terdeteksi. Pastikan ESP32 tercolok dan driver USB-serial (CP210x/CH340) terpasang.');
            return;
        }

        $this->line('Port yang tersedia: ' . implode(', ', $ports));
        $this->warn('Kalau port sudah benar tapi tetap gagal, kemungkinan port sedang dipakai program lain (Serial Monitor PlatformIO / Arduino IDE). Tutup dulu program itu.');
    }

    private function processData($data)
    {
        if (empty($data)) return;

        $this->line("Received: " . $data);

        // Expected format: temp#humidity#lat#lng#speed#alt#sat#heading#current#volt#percent
        $parts = explode('#', $data);

        if (count($parts) >= 11) {
            try {
                // Firmware memakai dua penanda "tidak valid" yang berbeda:
                // DHT11 mengirim -999, sedangkan GPS mengirim 0 untuk
                // lat/lng/speed/altitude selama gps.location.isValid() false.
                // Tanpa pemeriksaan ini, posisi belum-fix tersimpan sebagai
                // koordinat 0,0 yang terlihat seolah-olah valid.
                $latitude  = (float)$parts[2];
                $longitude = (float)$parts[3];
                $hasGpsFix = $latitude != 0.0 || $longitude != 0.0;

                $sensorData = SensorData::create([
                    'temperature' => (float)$parts[0] !== -999.0 ? (float)$parts[0] : null,
                    'humidity' => (float)$parts[1] !== -999.0 ? (float)$parts[1] : null,
                    'latitude' => $hasGpsFix ? $latitude : null,
                    'longitude' => $hasGpsFix ? $longitude : null,
                    'speed' => $hasGpsFix ? (float)$parts[4] : null,
                    'altitude' => $hasGpsFix ? (float)$parts[5] : null,
                    'satellites' => (int)$parts[6],
                    'heading' => (float)$parts[7],
                    'current' => (float)$parts[8],
                    'voltage' => (float)$parts[9],
                    'battery_percent' => (float)$parts[10],
                ]);

                broadcast(new SensorDataUpdated($sensorData));
                $this->info($hasGpsFix
                    ? "Data saved and broadcasted."
                    : "Data saved and broadcasted (GPS belum fix, posisi dikosongkan).");
            } catch (\Exception $e) {
                $this->error("Error saving data: " . $e->getMessage());
            }
        }
    }
}
