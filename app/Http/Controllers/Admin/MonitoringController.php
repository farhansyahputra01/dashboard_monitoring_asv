<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        return view('admin.monitoring.index', compact('setting', 'latest'));
    }

    public function updateTrack(Request $request)
    {
        $request->validate([
            'active_track' => 'required|in:A,B'
        ]);

        $setting = MonitoringSetting::first();

        if (!$setting) {
            $setting = new MonitoringSetting();
        }

        $setting->active_track = $request->active_track;
        $setting->save();

        return redirect()
            ->route('admin.monitoring')
            ->with('success', 'Lintasan berhasil diperbarui.');
    }
}