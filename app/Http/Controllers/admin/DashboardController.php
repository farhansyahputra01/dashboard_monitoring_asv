<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;

class DashboardController extends Controller
{
    public function index()
    {
        $setting = MonitoringSetting::first();

        return view('admin.dashboard.index', compact('setting'));
    }
}