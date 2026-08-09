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
            if (!preg_match('/^COM\d+$/i', $comPort)) {
                $comPort = 'COM3'; // default fallback for Windows
            }

            $this->info("Opening $comPort at 115200 baud via PowerShell on Windows...");

            $psScript = "\$port = New-Object System.IO.Ports.SerialPort '$comPort', 115200, [System.IO.Ports.Parity]::None, 8, [System.IO.Ports.StopBits]::One; \$port.Open(); while (\$true) { try { if (\$port.BytesToRead -gt 0) { Write-Output \$port.ReadLine() } } catch {} Start-Sleep -Milliseconds 50 }";
            
            $descriptors = [
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];

            $process = proc_open("powershell -Command \"$psScript\"", $descriptors, $pipes);

            if (!is_resource($process)) {
                $this->error("Failed to launch PowerShell serial listener for $comPort.");
                return 1;
            }

            $this->info("Listening for data on $comPort...");

            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line !== false) {
                    $this->processData(trim($line));
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return 0;
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

    private function processData($data)
    {
        if (empty($data)) return;

        $this->line("Received: " . $data);

        // Expected format: temp#humidity#lat#lng#speed#alt#sat#heading#current#volt#percent
        $parts = explode('#', $data);

        if (count($parts) >= 11) {
            try {
                $sensorData = SensorData::create([
                    'temperature' => (float)$parts[0] !== -999.0 ? (float)$parts[0] : null,
                    'humidity' => (float)$parts[1] !== -999.0 ? (float)$parts[1] : null,
                    'latitude' => (float)$parts[2],
                    'longitude' => (float)$parts[3],
                    'speed' => (float)$parts[4],
                    'altitude' => (float)$parts[5],
                    'satellites' => (int)$parts[6],
                    'heading' => (float)$parts[7],
                    'current' => (float)$parts[8],
                    'voltage' => (float)$parts[9],
                    'battery_percent' => (float)$parts[10],
                ]);

                broadcast(new SensorDataUpdated($sensorData));
                $this->info("Data saved and broadcasted.");
            } catch (\Exception $e) {
                $this->error("Error saving data: " . $e->getMessage());
            }
        }
    }
}
