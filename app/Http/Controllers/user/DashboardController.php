<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;

class DashboardController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        return view('user.dashboard.index', compact('setting'));
    }
}