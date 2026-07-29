<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MonitoringSetting;

class MonitoringSettingController extends Controller
{
    /**
     * Menyimpan lintasan aktif
     */
    public function update(Request $request)
    {
        $request->validate([
            'active_track' => 'required|in:A,B',
        ]);

        $setting = MonitoringSetting::first();

        if (!$setting) {
            $setting = new MonitoringSetting();
        }

        $setting->active_track = $request->active_track;
        $setting->save();

        return back()->with('success', 'Lintasan berhasil diperbarui.');
    }
}