<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MonitoringSetting;
use App\Models\SensorData;

class MonitoringController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        // Pembacaan terakhir dipakai sebagai nilai awal halaman, supaya setelah
        // refresh kartu tidak kosong sambil menunggu broadcast berikutnya.
        $latest = SensorData::latest('id')->first();

        // Riwayat titik GPS untuk peta jejak. Titik baru setelah ini
        // ditambahkan realtime lewat siaran SensorDataUpdated.
        $track = SensorData::recentTrack();

        // Riwayat posisi di peta lintasan (meter). Terpisah dari $track:
        // yang itu butuh GPS fix, yang ini butuh x/y - dan kapal bisa punya
        // posisi peta yang sah justru saat GPS-nya sedang tidak fix.
        $jejakLintasan = SensorData::recentLintasan($setting->active_track ?? null);

        return view('admin.monitoring.index',
            compact('setting', 'latest', 'track', 'jejakLintasan'));
    }

    /**
     * Ganti arena aktif - HANYA saat kapal berhenti.
     *
     * Kapal membaca pilihan ini SEKALI, waktu programnya dinyalakan, lalu
     * memakainya sepanjang lomba untuk menghitung posisi. Mengubahnya di
     * tengah jalan tidak membuat kapal ikut pindah - yang terjadi justru
     * kapal menghitung di arena yang satu sementara dashboard menggambar
     * arena yang lain, tanpa satu pun pesan galat.
     *
     * Karena itu urutannya ditegakkan di sini, bukan sekadar dianjurkan:
     *
     *     hentikan kapal  ->  pilih lintasan  ->  nyalakan kapal lagi
     *
     * Kendali kapal yang TIDAK terjangkau justru dianggap boleh: itu berarti
     * program kapal memang sedang mati, dan itulah keadaan terbaik untuk
     * mengganti arena.
     */
    public function updateTrack(Request $request)
    {
        $request->validate([
            'active_track' => 'required|in:A,B'
        ]);

        if ($this->kapalSedangBerjalan()) {
            return back()->withErrors([
                'active_track' => 'Lintasan tidak bisa diganti selagi kapal berjalan. '
                    . 'Tekan BERHENTI DARURAT dulu, ganti lintasannya, lalu nyalakan '
                    . 'ulang program di kapal supaya ia membaca pilihan yang baru.',
            ]);
        }

        $setting = MonitoringSetting::first();

        if (!$setting) {
            $setting = new MonitoringSetting();
        }

        $setting->active_track = $request->active_track;
        $setting->save();

        event(new \App\Events\ActiveTrackUpdated($setting->active_track));

        return redirect()
            ->route('admin.monitoring')
            ->with('success', 'Lintasan diperbarui. Nyalakan ulang program di kapal '
                . 'supaya ia memakai lintasan ini.');
    }

    /**
     * True hanya kalau kendali kapal menjawab DAN mengaku sedang jalan.
     *
     * Tidak terjangkau = program kapal mati = boleh ganti. Sengaja tidak
     * "fail closed" seperti endpoint telemetri: menolak penggantian lintasan
     * hanya karena kapal sedang dimatikan justru mengunci operator dari
     * pekerjaan yang memang harus dilakukan saat kapal mati.
     */
    private function kapalSedangBerjalan(): bool
    {
        try {
            $url = rtrim((string)config('asv.control_url'), '/') . '/control/status';

            $response = Http::timeout(config('asv.control_timeout', 2))
                ->acceptJson()
                ->get($url);

            if ($response->failed()) {
                return false;
            }

            return $response->json('stopped') === false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}