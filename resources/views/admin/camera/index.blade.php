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
        {{-- Kamera Atas Laut --}}
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area atas laut
            </div>

            <div class="admin-camera-frame">
                @include('partials.camera-frame', [
                        'url' => config('camera.streams.atas'),
                        'label' => 'Kamera Atas Air',
                    ])
            </div>
            <div class="admin-camera-info">
                Live C922 Camera
            </div>
        </div>
        {{-- Kamera Bawah Laut --}}
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area bawah laut
            </div>
            <div class="admin-camera-frame">
                @include('partials.camera-frame', [
                        'url' => config('camera.streams.bawah'),
                        'label' => 'Kamera Bawah Air',
                    ])
            </div>
            <div class="admin-camera-info">
                Live USB Camera
            </div>
        </div>
</div>

@endsection