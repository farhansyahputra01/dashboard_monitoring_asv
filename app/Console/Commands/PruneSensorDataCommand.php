<?php

namespace App\Console\Commands;

use App\Models\SensorData;
use Illuminate\Console\Command;

/**
 * Buang pembacaan sensor lama.
 *
 * Kapal mengirim satu baris per detik: 86.400 baris per hari, sekitar 31 juta
 * per tahun. Di Raspberry Pi yang memakai kartu SD, ini bukan sekadar soal
 * ukuran tetapi umur kartunya.
 */
class PruneSensorDataCommand extends Command
{
    protected $signature = 'asv:prune-sensor-data
                            {--days=7 : Simpan data sebanyak berapa hari ke belakang}
                            {--chunk=5000 : Jumlah baris yang dihapus per batch}';

    protected $description = 'Hapus pembacaan sensor yang lebih tua dari jumlah hari tertentu';

    public function handle(): int
    {
        $days = max(1, (int)$this->option('days'));
        $chunk = max(100, (int)$this->option('chunk'));
        $cutoff = now()->subDays($days);

        $total = SensorData::where('created_at', '<', $cutoff)->count();

        if ($total === 0) {
            $this->info("Tidak ada data lebih tua dari {$days} hari.");
            return self::SUCCESS;
        }

        $this->info("Menghapus {$total} baris sebelum {$cutoff->toDateTimeString()}...");

        $deleted = 0;

        // Dihapus bertahap supaya tidak mengunci tabel lama-lama - kapal masih
        // menulis telemetri setiap detik saat perintah ini berjalan.
        do {
            $affected = SensorData::where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();

            $deleted += $affected;
        } while ($affected > 0);

        $this->info("Selesai. {$deleted} baris dihapus, tersisa " . SensorData::count() . " baris.");

        return self::SUCCESS;
    }
}
