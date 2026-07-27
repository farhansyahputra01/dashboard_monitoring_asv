@extends('layouts.user')
@section('title','Kamera')
@section('content')
<div class="user-camera-page">
    <div class="user-camera-card">
        <div class="user-camera-header">
            <h2>Kamera</h2>
            <div class="user-camera-live">
                <span class="user-live-dot"></span>
                Live
            </div>
        </div>
        <div class="user-camera-item">
            <div class="user-camera-label">
                Kamera Atas Air
            </div>
            <div class="user-camera-frame">
                <img src="{{ asset('images/camera-atas.jpg') }}">
            </div>
            <div class="user-camera-info">
                1080P • 30 FPS
            </div>
        </div>
        <div class="user-camera-item">
            <div class="user-camera-label">
                Kamera Bawah Air
            </div>
            <div class="user-camera-frame">
                <img src="{{ asset('images/camera-bawah.jpg') }}">
            </div>
            <div class="user-camera-info">
                1080P • 30 FPS
            </div>
        </div>
    </div>
</div>
@endsection