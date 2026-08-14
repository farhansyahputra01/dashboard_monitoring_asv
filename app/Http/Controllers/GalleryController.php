<?php

namespace App\Http\Controllers;

use App\Services\MissionImageMirror;

/**
 * Galeri foto misi.
 *
 * Foto ditulis program Python ke foldernya sendiri, lalu DICERMIN ke
 * storage/app/public supaya nginx bisa melayaninya langsung lewat symlink
 * public/storage. Alasan lengkapnya ada di MissionImageMirror.
 */
class GalleryController extends Controller
{
    public function __construct(private readonly MissionImageMirror $mirror)
    {
    }

    public function index()
    {
        return view('gallery.index', $this->data());
    }

    public function adminIndex()
    {
        return view('admin.gallery.index', $this->data());
    }

    private function data(): array
    {
        // Dicermin saat halaman dibuka supaya foto yang baru diambil kapal
        // langsung terlihat, tanpa menunggu penjadwal. Ongkosnya hanya satu
        // pembacaan daftar folder; berkas yang sudah ada tidak disalin ulang.
        $baru = $this->mirror->sync();

        return [
            'fotos' => $this->mirror->daftar(),
            'baru' => $baru,
            'tautanSiap' => $this->mirror->tautanSiap(),
        ];
    }
}
