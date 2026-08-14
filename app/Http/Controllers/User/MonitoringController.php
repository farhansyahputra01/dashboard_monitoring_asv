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

        return view('user.monitoring.index', compact('setting', 'latest', 'track'));
    }
}