<?php

namespace App\Console\Commands;

use App\Services\MissionImageMirror;
use Illuminate\Console\Command;

/**
 * Cermin foto misi dari folder program Python ke storage.
 *
 * Galeri juga mencermin sendiri saat halaman dibuka, jadi perintah ini
 * terutama berguna untuk penjadwal: foto tetap tersalin walau tidak ada
 * seorang pun yang membuka dashboard - misalnya saat lomba berlangsung dan
 * layar sedang menampilkan tab lain.
 */
class SyncMissionImagesCommand extends Command
{
    protected $signature = 'asv:sync-mission-images';

    protected $description = 'Salin foto misi baru dari folder program Python ke storage';

    public function handle(MissionImageMirror $mirror): int
    {
        if (!$mirror->tautanSiap()) {
            $this->error('public/storage belum ada. Jalankan: php artisan storage:link');
            return self::FAILURE;
        }

        $baru = $mirror->sync();

        $this->info($baru === 0
            ? 'Tidak ada foto baru.'
            : "{$baru} foto baru disalin ke storage.");

        return self::SUCCESS;
    }
}
