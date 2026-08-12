<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AlarmController extends Controller
{
    public function index()
    {
        return view('admin.alarm.index');
    }
}