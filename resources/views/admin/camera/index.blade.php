@extends('layouts.admin')
@section('title','Kamera')
@section('content')
<div class="admin-camera-page">
    <div class="admin-camera-card">
        <div class="admin-camera-header">
            <h2>Kamera</h2>
            <div class="admin-camera-live">
                <span class="admin-live-dot"></span>
                Live
            </div>
        </div>
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area bawah laut
            </div>
            <div class="admin-camera-frame">
                <img src="{{ asset('images/camera-bawah.jpg') }}">
            </div>
            <div class="admin-camera-info">
                1080P • 30 FPS
            </div>
        </div>
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area atas laut
            </div>
            <div class="admin-camera-frame">
                <img src="{{ asset('images/camera-atas.jpg') }}">
            </div>
            <div class="admin-camera-info">
                1080P • 30 FPS
            </div>
        </div>
    </div>
</div>
@endsection