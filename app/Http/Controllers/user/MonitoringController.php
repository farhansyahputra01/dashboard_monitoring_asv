<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;

class MonitoringController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        return view('user.monitoring.index', compact('setting'));
    }
}