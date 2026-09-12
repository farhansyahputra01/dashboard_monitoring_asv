@extends('layouts.user')
@section('title', 'Kamera')
@section('content')
<div class="camera-page">
    <div class="camera-card">
        <div class="camera-header">
            <h2>Kamera</h2>
            <div class="camera-live">
                <span class="live-dot"></span>
                Live
            </div>
        </div>
        {{-- Kamera Atas Air --}}
        <div class="camera-item">
            <div class="camera-label">
                Kamera Atas Air
            </div>
            <div class="camera-frame">
                @include('partials.camera-frame', [
                    'url' => config('camera.streams.atas'),
                    'label' => 'Kamera Atas Air',
                ])
            </div>
            <div class="camera-info">
                Live C922 Camera
            </div>
        </div>
        {{-- Kamera Bawah Air --}}
        <div class="camera-item">
            <div class="camera-label">
                Kamera Bawah Air
            </div>
            <div class="camera-frame">
                @include('partials.camera-frame', [
                    'url' => config('camera.streams.bawah'),
                    'label' => 'Kamera Bawah Air',
                ])
            </div>
            <div class="camera-info">
                Live USB Camera
            </div>
        </div>
    </div>
</div>
@endsection