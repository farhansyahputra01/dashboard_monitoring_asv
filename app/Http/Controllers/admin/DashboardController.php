<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;
use App\Models\SensorData;

class DashboardController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        // Pembacaan terakhir dipakai sebagai nilai awal halaman, supaya setelah
        // refresh kartu tidak kosong sambil menunggu broadcast berikutnya.
        $latest = SensorData::latest('id')->first();

        return view('admin.dashboard.index', compact('setting', 'latest'));
    }
}
