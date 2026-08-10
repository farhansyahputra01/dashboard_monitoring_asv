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

        {{-- Kamera Atas Air --}}
        <div class="user-camera-item">

            <div class="user-camera-label">
                Kamera Atas Air
            </div>

            <div class="user-camera-frame">

                @include('partials.camera-frame', [
                        'url' => config('camera.streams.atas'),
                        'label' => 'Kamera Atas Air',
                    ])

            </div>

            <div class="user-camera-info">
                Live Camera
            </div>

        </div>

        {{-- Kamera Bawah Air --}}
        <div class="user-camera-item">

            <div class="user-camera-label">
                Kamera Bawah Air
            </div>

            <div class="user-camera-frame">

                @include('partials.camera-frame', [
                        'url' => config('camera.streams.bawah'),
                        'label' => 'Kamera Bawah Air',
                    ])

            </div>

            <div class="user-camera-info">
                Live Camera
            </div>

        </div>

    </div>

</div>

@endsection