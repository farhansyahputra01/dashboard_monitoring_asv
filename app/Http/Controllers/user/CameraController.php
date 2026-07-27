<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class CameraController extends Controller
{
    public function index()
    {
        return view('user.camera.index');
    }
}