<?php

namespace App\Console\Commands;

use App\Models\SensorData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Periksa seluruh rantai telemetri dalam satu perintah.
 *
 * Rantainya panjang: ESP32 -> Python -> POST Laravel -> MySQL -> antrian ->
 * Reverb -> browser. Kalau layar diam, penyebabnya bisa di mana saja, dan
 * memeriksanya satu per satu lewat SSH itu lambat. Perintah ini menunjuk
 * langsung mata rantai yang putus.
 */
class DoctorCommand extends Command
{
    protected $signature = 'asv:doctor';

    protected $description = 'Periksa rantai telemetri: database, antrian, Reverb, aset, kamera, kendali';

    private array $problems = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('  PEMERIKSAAN SISTEM ASV');
        $this->line('  ' . str_repeat('=', 58));

        $this->checkDatabase();
        $this->checkIngest();
        $this->checkQueue();
        $this->checkReverb();
        $this->checkAssets();
        $this->checkBoatServices();

        $this->line('');

        if ($this->problems === []) {
            $this->info('  Semua pemeriksaan lolos.');
            $this->line('');
            return self::SUCCESS;
        }

        $this->line('  ' . str_repeat('=', 58));
        $this->error('  ' . count($this->problems) . ' masalah ditemukan:');
        $this->line('');

        foreach ($this->problems as $i => $problem) {
            $this->line('  ' . ($i + 1) . '. ' . $problem['what']);
            $this->line('     perbaikan: ' . $problem['fix']);
            $this->line('');
        }

        return self::FAILURE;
    }

    // ---------------------------------------------------------------

    private function checkDatabase(): void
    {
        $this->section('Database');

        try {
            DB::connection()->getPdo();
            $this->ok('koneksi ' . config('database.default') . ' / ' . DB::connection()->getDatabaseName());
        } catch (\Throwable $e) {
            $this->bad('koneksi database gagal: ' . $e->getMessage(),
                'periksa DB_* di .env, dan pastikan mysql berjalan (systemctl status mysql)');
            return;
        }

        $total = SensorData::count();
        $latest = SensorData::latest('id')->first();

        if ($latest === null) {
            $this->bad('tabel sensor_data KOSONG - belum ada telemetri yang masuk sama sekali',
                'jalankan program Python, lalu cek lognya: journalctl -u asv-vision -f');
            return;
        }

        // Carbon 3 mengembalikan selisih bertanda dan pecahan, jadi dibulatkan
        // dan diambil nilai mutlaknya. Baris "dari masa depan" bisa muncul kalau
        // zona waktu MySQL dan APP_TIMEZONE berbeda - itu ditandai terpisah.
        $diff = $latest->created_at->diffInSeconds(now(), false);
        $age = (int)abs($diff);

        $this->ok("sensor_data berisi {$total} baris");

        if ($diff < -5) {
            $this->bad('waktu baris terakhir ' . $age . ' detik di MASA DEPAN - zona waktu tidak sinkron',
                'samakan APP_TIMEZONE di .env dengan zona waktu MySQL, '
                . 'lalu periksa jam Raspberry Pi (timedatectl)');
            return;
        }


        if ($age <= 5) {
            $this->ok("data terakhir {$age} detik lalu - TELEMETRI MENGALIR");
        } elseif ($age < 300) {
            $this->bad("data terakhir {$age} detik lalu - telemetri BERHENTI",
                'program Python berhenti mengirim. Cek: journalctl -u asv-vision -n 50');
        } else {
            $this->bad('data terakhir ' . round($age / 60) . ' menit lalu - telemetri sudah lama berhenti',
                'program Python tidak berjalan. Cek: systemctl status asv-vision');
        }
    }

    private function checkIngest(): void
    {
        $this->section('Penerimaan telemetri');

        if (empty(config('asv.ingest_token'))) {
            $this->bad('ASV_INGEST_TOKEN kosong - endpoint MENOLAK semua kiriman (401)',
                'isi ASV_INGEST_TOKEN di .env, lalu kirim token yang sama ke program Python');
            return;
        }

        $this->ok('ASV_INGEST_TOKEN terisi');
        $this->hint('program Python harus mengirim header X-ASV-Token dengan nilai yang sama persis');
    }

    private function checkQueue(): void
    {
        $this->section('Antrian siaran');

        $broadcast = config('broadcasting.default');

        if ($broadcast !== 'reverb') {
            $this->bad("BROADCAST_CONNECTION = '{$broadcast}', bukan 'reverb'",
                'set BROADCAST_CONNECTION=reverb di .env');
        } else {
            $this->ok('BROADCAST_CONNECTION = reverb');
        }

        $queue = config('queue.default');
        $this->ok("QUEUE_CONNECTION = {$queue}");

        if ($queue === 'sync') {
            $this->hint('queue sync: siaran dikirim langsung, queue:work tidak diperlukan');
            return;
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            $this->bad('tabel jobs/failed_jobs tidak terbaca', 'jalankan: php artisan migrate');
            return;
        }

        // Telemetri 1 Hz. Kalau worker hidup, antrian selalu mendekati nol.
        if ($pending > 20) {
            $this->bad("{$pending} siaran menumpuk di antrian - queue:work TIDAK BERJALAN",
                'inilah sebabnya data masuk database tapi layar tidak berubah. '
                . 'Jalankan: systemctl start asv-queue  (atau php artisan queue:work)');
        } else {
            $this->ok("antrian bersih ({$pending} menunggu) - queue:work berjalan");
        }

        if ($failed > 0) {
            $this->bad("{$failed} siaran GAGAL diproses",
                'lihat sebabnya: php artisan queue:failed');
        }
    }

    private function checkReverb(): void
    {
        $this->section('Server Reverb');

        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        $port = (int)config('reverb.servers.reverb.port', 8080);

        // 0.0.0.0 berarti "semua antarmuka"; untuk mengetuknya pakai loopback.
        $probe = $host === '0.0.0.0' ? '127.0.0.1' : $host;

        if ($this->portOpen($probe, $port)) {
            $this->ok("Reverb mendengarkan di {$probe}:{$port}");
        } else {
            $this->bad("tidak ada yang mendengarkan di {$probe}:{$port}",
                'Reverb mati. Jalankan: systemctl start asv-reverb');
        }

        $clientPort = (int)config('broadcasting.connections.reverb.options.port', 443);
        $clientHost = config('broadcasting.connections.reverb.options.host');

        $this->hint("browser akan menyambung ke {$clientHost}:{$clientPort} "
            . '(REVERB_HOST / REVERB_PORT), bukan ke port server di atas');
    }

    private function checkAssets(): void
    {
        $this->section('Aset frontend');

        $manifest = public_path('build/manifest.json');

        if (!file_exists($manifest)) {
            $this->bad('public/build/manifest.json tidak ada - aset belum dibangun',
                'jalankan: npm run build');
            return;
        }

        $this->ok('manifest aset ada');

        $entries = json_decode((string)file_get_contents($manifest), true) ?: [];
        $js = $entries['resources/js/app.js']['file'] ?? null;

        if ($js === null) {
            $this->bad('entri resources/js/app.js tidak ada di manifest',
                'periksa input di vite.config.js, lalu npm run build ulang');
            return;
        }

        $built = (string)file_get_contents(public_path('build/' . $js));

        // Nilai VITE_* ditanam saat build. Kalau .env berubah tanpa build ulang,
        // browser diam-diam menyambung ke alamat lama.
        $host = (string)config('broadcasting.connections.reverb.options.host');
        $port = (string)config('broadcasting.connections.reverb.options.port');

        $hostBaked = $host === '' || str_contains($built, $host);
        $portBaked = str_contains($built, $port);

        if ($hostBaked && $portBaked) {
            $this->ok("aset terbangun memuat alamat Reverb sekarang ({$host}:{$port})");
        } else {
            $this->bad('aset terbangun TIDAK memuat REVERB_HOST/REVERB_PORT yang sekarang',
                'nilai VITE_* ditanam saat build. Setelah mengubah .env, WAJIB: npm run build');
        }
    }

    private function checkBoatServices(): void
    {
        $this->section('Layanan di kapal');

        $control = (string)config('asv.control_url');
        [$host, $port] = $this->splitUrl($control, 8000);

        if ($this->portOpen($host, $port)) {
            $this->ok("kendali kapal terjangkau di {$host}:{$port}");
        } else {
            $this->bad("kendali kapal TIDAK terjangkau di {$host}:{$port}",
                'program Python belum jalan atau stream_server.start() belum dipanggil. '
                . 'Tombol berhenti darurat tidak akan berfungsi.');
        }

        foreach (['atas', 'bawah'] as $cam) {
            $url = config("camera.streams.{$cam}");

            if (empty($url)) {
                $this->hint("kamera {$cam}: belum dikonfigurasi (dashboard menampilkan placeholder)");
                continue;
            }

            $this->ok("kamera {$cam}: {$url}");
        }
    }

    // ---------------------------------------------------------------

    private function portOpen(string $host, int $port): bool
    {
        $sock = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($sock === false) {
            return false;
        }

        fclose($sock);

        return true;
    }

    private function splitUrl(string $url, int $default): array
    {
        $parts = parse_url($url);

        return [$parts['host'] ?? '127.0.0.1', (int)($parts['port'] ?? $default)];
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("  <options=bold>{$title}</>");
    }

    private function ok(string $message): void
    {
        $this->line("    <fg=green>OK</>    {$message}");
    }

    private function bad(string $message, string $fix): void
    {
        $this->line("    <fg=red>GAGAL</> {$message}");
        $this->problems[] = ['what' => $message, 'fix' => $fix];
    }

    private function hint(string $message): void
    {
        $this->line("    <fg=gray>-</>     {$message}");
    }
}
