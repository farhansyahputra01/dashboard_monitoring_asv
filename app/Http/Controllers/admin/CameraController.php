<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class CameraController extends Controller
{
    public function index()
    {
        return view('admin.camera.index');
    }
}