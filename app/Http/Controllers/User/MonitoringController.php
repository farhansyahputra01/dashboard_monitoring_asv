<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;
use App\Models\SensorData;

class MonitoringController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        // Pembacaan terakhir dipakai sebagai nilai awal halaman, supaya setelah
        // refresh kartu tidak menampilkan nol sambil menunggu broadcast.
        $latest = SensorData::latest('id')->first();

        // Riwayat titik GPS untuk peta jejak. Titik baru setelah ini
        // ditambahkan realtime lewat siaran SensorDataUpdated.
        $track = SensorData::recentTrack();

        // Riwayat posisi di peta lintasan (meter). Terpisah dari $track:
        // yang itu butuh GPS fix, yang ini butuh x/y - dan kapal bisa punya
        // posisi peta yang sah justru saat GPS-nya sedang tidak fix.
        $jejakLintasan = SensorData::recentLintasan($setting->active_track ?? null);

        return view('user.monitoring.index',
            compact('setting', 'latest', 'track', 'jejakLintasan'));
    }
}