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
            {{-- Kamera Atas Air --}}
            <div class="user-camera-item">
                <div class="user-camera-box">
                    <video
                        id="user-camera-atas"
                        autoplay
                        playsinline
                        muted
                        style="width:100%;height:100%;object-fit:cover;">
                    </video>
                </div>
                <p>Kamera Atas Air</p>
            </div>
            {{-- Kamera Bawah Air --}}
            <div class="user-camera-item">
                <div class="user-camera-box">
                    <video
                        id="user-camera-bawah"
                        autoplay
                        playsinline
                        muted
                        style="width:100%;height:100%;object-fit:cover;">
                    </video>
                </div>
                <p>Kamera Bawah Air</p>
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
    {{-- LINTASAN --}}
    <div class="user-card user-map-card">
        <h3>Posisi Kapal</h3>
        <div class="dashboard-track-wrapper">
            <div
                id="dashboardUserLintasanA"
                class="dashboard-track-item"
                style="{{ optional($setting)->active_track == 'B' ? 'display:none;' : '' }}"
            >
                @include('admin.monitoring.lintasan-a')
            </div>
            <div
                id="dashboardUserLintasanB"
                class="dashboard-track-item"
                style="{{ optional($setting)->active_track == 'B' ? '' : 'display:none;' }}"
            >
                @include('admin.monitoring.lintasan-b')
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", async () => {
    try {
        // Minta izin kamera
        await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        });
        // Device ID Kamera
        const DEVICE_BAWAH = "ed8b5b0d75e7e3db3f4839c11399217ffe8d9d7ae4ed5bc217ba038c230472ff";
        const DEVICE_ATAS = "02fe8959a97e174d48a667ec0451e9d4cf13d79b9feccc39757896afd19ab4ba";
        // Kamera Atas
        const streamAtas = await navigator.mediaDevices.getUserMedia({
            video: {
                deviceId: {
                    exact: DEVICE_ATAS
                },
                width: 1280,
                height: 720
            },
            audio: false
        });
        document.getElementById("user-camera-atas").srcObject = streamAtas;
        // Kamera Bawah
        const streamBawah = await navigator.mediaDevices.getUserMedia({
            video: {
                deviceId: {
                    exact: DEVICE_BAWAH
                },
                width: 1280,
                height: 720
            },
            audio: false
        });
        document.getElementById("user-camera-bawah").srcObject = streamBawah;
    } catch (error) {
        console.error(error);
    }
});
</script>
@endsection