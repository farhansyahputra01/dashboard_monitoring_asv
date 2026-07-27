<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class MonitoringController extends Controller
{
    public function index()
    {
        return view('user.monitoring.index');
    }
}