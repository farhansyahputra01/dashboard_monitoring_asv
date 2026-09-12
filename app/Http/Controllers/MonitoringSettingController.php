<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MonitoringSetting;

class MonitoringSettingController extends Controller
{
    /**
     * Lintasan aktif dalam bentuk JSON, dipolling oleh track-sync.js
     */
    public function activeTrack()
    {
        return response()->json([
            'active_track' => optional(MonitoringSetting::first())->active_track ?? 'A',
        ]);
    }

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

        try {
            event(new \App\Events\ActiveTrackUpdated($setting->active_track));
        } catch (\Throwable $e) {
            \Log::warning('ActiveTrackUpdated broadcast failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Lintasan berhasil diperbarui.');
    }
}