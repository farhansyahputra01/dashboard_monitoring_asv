@extends('layouts.user')

@section('title', 'Dashboard')

@section('content')

<div class="user-dashboard-grid">

    {{-- STATUS KAPAL --}}
    <div class="user-card user-status-card">
        <div class="user-status-content">

            <div class="user-status-icon">
                <i class="bi bi-shield-fill-check"></i>
            </div>

            <div>
                <small>Status Kapal</small>
                <h2>BEROPERASI</h2>
                <p>Sistem berfungsi normal</p>
            </div>

        </div>
    </div>

    {{-- KECEPATAN --}}
    <div class="user-card user-speed-card">

        <i class="bi bi-speedometer2"></i>

        <div>
            <small>Kecepatan</small>
            <h2>1.6 <span>m/s</span></h2>
        </div>

    </div>

    {{-- HALUAN --}}
    <div class="user-card user-heading-card">

        <i class="bi bi-compass"></i>

        <div>
            <small>Haluan</small>
            <h2>128 SE</h2>
            <p>Southeast</p>
        </div>

    </div>

    {{-- KAMERA --}}
    <div class="user-card user-camera-card">

        <h3>Kamera Permukaan</h3>

        <div class="user-camera-wrapper">

            <div class="user-camera-item">

                <div class="user-camera-box">
                    <img src="{{ asset('images/camera-water.jpg') }}" alt="">
                </div>

                <p>Kamera Atas Air</p>

            </div>

            <div class="user-camera-item">

                <div class="user-camera-box">
                    <img src="{{ asset('images/camera-sea.jpg') }}" alt="">
                </div>

                <p>Kamera Permukaan</p>

            </div>

        </div>

    </div>

    {{-- BATTERY --}}
    <div class="user-card user-battery-card">

        <h3>Performa Baterai</h3>

        <div class="user-battery-content">

            <div class="user-battery-icon">
                <i class="bi bi-battery-half"></i>
            </div>

            <div class="user-battery-info">

                <div class="user-battery-item">
                    <span>Status Baterai</span>
                    <strong>45%</strong>
                </div>

                <div class="user-battery-item">
                    <span>Tegangan</span>
                    <strong>51.2 V</strong>
                </div>

                <div class="user-battery-item">
                    <span>Sisa Operasi</span>
                    <strong>10 Jam 30 Menit</strong>
                </div>

            </div>

        </div>

    </div>

    {{-- PERFORMA --}}
    <div class="user-card user-performance-card">

        <h3>Performa Kapal</h3>

        <div class="user-performance-wrapper">

            <div class="user-performance-item">

                <div class="user-circle">
                    <span>79%</span>
                </div>

                <p>Performa Mesin</p>
                <small>Sempurna</small>

            </div>

            <div class="user-performance-item">

                <div class="user-circle user-circle-76">
                    <span>76%</span>
                </div>

                <p>Konsumsi Baterai</p>
                <small>Baik</small>

            </div>

        </div>

    </div>

    {{-- STATUS SISTEM --}}
    <div class="user-card user-system-card">

        <h3>Status Sistem</h3>

        <ul>

            <li>
                <i class="bi bi-wifi"></i>
                Koneksi Terhubung
            </li>

            <li>
                <i class="bi bi-broadcast"></i>
                Telekomunikasi Aktif
            </li>

            <li>
                <i class="bi bi-check-circle-fill"></i>
                Sistem Normal
            </li>

        </ul>

    </div>

    {{-- MAP --}}
    <div class="user-card user-map-card">

        <h3>Posisi Kapal</h3>

        <div class="user-map-placeholder">
            MAP LIVE
        </div>

    </div>

</div>

@endsection