<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Cermin foto misi: folder program Python -> storage/app/public.
 *
 * Kenapa dicermin, bukan dibaca langsung atau di-symlink:
 *
 * - Program Python menulis ke foldernya sendiri, di luar proyek Laravel.
 *   Symlink storage bawaan Laravel hanya bekerja untuk berkas di dalam
 *   storage/, jadi tidak bisa langsung dipakai.
 * - Membaca langsung lewat controller memaksa PHP mem-boot kerangka penuh
 *   untuk SETIAP gambar (terukur ~100 ms, dibanding ~8 ms kalau nginx yang
 *   melayani berkas statis).
 * - Mengarahkan symlink public/ ke folder Python berarti apa pun yang program
 *   lain tulis ke sana bisa diunduh siapa saja.
 *
 * Menyalin berkas baru ke storage menyelesaikan ketiganya: Python tidak perlu
 * diubah, nginx melayani langsung, dan yang terbuka ke publik hanya berkas
 * yang sudah lolos saringan nama.
 */
class MissionImageMirror
{
    /** Hanya berkas berpola "<warna>_<unix>.jpg" yang disalin. */
    private const POLA_NAMA = '/^[a-z]+_\d+\.(jpg|jpeg|png)$/i';

    public const SUB_FOLDER = 'mission_images';

    /**
     * Salin berkas yang belum ada di storage. Mengembalikan jumlah yang baru.
     */
    public function sync(): int
    {
        $sumber = $this->folderSumber();

        if ($sumber === null) {
            return 0;
        }

        $tujuan = storage_path('app/public/' . self::SUB_FOLDER);

        if (!is_dir($tujuan) && !mkdir($tujuan, 0775, true) && !is_dir($tujuan)) {
            return 0;
        }

        $baru = 0;

        foreach (scandir($sumber) ?: [] as $nama) {
            if (!preg_match(self::POLA_NAMA, $nama)) {
                continue;
            }

            $dari = $sumber . DIRECTORY_SEPARATOR . $nama;
            $ke = $tujuan . DIRECTORY_SEPARATOR . $nama;

            // Nama berkas memuat epoch pembuatannya, jadi isinya tidak pernah
            // berubah untuk nama yang sama - cukup periksa keberadaannya.
            if (is_file($ke)) {
                continue;
            }

            if (@copy($dari, $ke)) {
                $baru++;
            }
        }

        return $baru;
    }

    /**
     * Daftar foto di storage, terbaru lebih dulu.
     *
     * @return array<int, array{nama:string, warna:string, waktu:Carbon, url:string}>
     */
    public function daftar(): array
    {
        $folder = storage_path('app/public/' . self::SUB_FOLDER);

        if (!is_dir($folder)) {
            return [];
        }

        $hasil = [];

        foreach (scandir($folder) ?: [] as $nama) {
            if (!preg_match(self::POLA_NAMA, $nama)) {
                continue;
            }

            [$warna, $epoch] = explode('_', pathinfo($nama, PATHINFO_FILENAME), 2);

            $hasil[] = [
                'nama' => $nama,
                'warna' => strtolower($warna),
                // Waktu diambil dari NAMA berkas, bukan mtime: mtime berubah
                // begitu berkas disalin - termasuk oleh pencerminan ini.
                'waktu' => Carbon::createFromTimestamp((int) $epoch),
                'url' => asset('storage/' . self::SUB_FOLDER . '/' . $nama),
            ];
        }

        usort($hasil, fn ($a, $b) => $b['waktu'] <=> $a['waktu']);

        return $hasil;
    }

    /**
     * Apakah symlink public/storage sudah dibuat.
     */
    public function tautanSiap(): bool
    {
        return file_exists(public_path('storage'));
    }

    private function folderSumber(): ?string
    {
        $path = config('asv.mission_images_path');

        if (empty($path)) {
            return null;
        }

        $asli = realpath($path);

        return ($asli !== false && is_dir($asli)) ? $asli : null;
    }
}
