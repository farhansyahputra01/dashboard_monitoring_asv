@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="dashboard-grid">
    {{-- Status Kapal --}}
    <div class="card dashboard-status-card">
        <div class="dashboard-status-content">
            <div class="dashboard-status-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div>
                <small>Status Kapal</small>
                <h2>BEROPERASI</h2>
                <p>Sistem berfungsi normal</p>
            </div>
        </div>
    </div>
    {{-- Kecepatan --}}
    <div class="card dashboard-speed-card">
        <i class="bi bi-speedometer2"></i>
        <div>
            <small>Kecepatan</small>
            <h2>1.6 <span>m/s</span></h2>
        </div>
    </div>
    {{-- Haluan --}}
    <div class="card dashboard-heading-card">
        <i class="bi bi-compass"></i>
        <div>
            <small>Haluan</small>
            <h2>128 SE</h2>
            <p>Southeast</p>
        </div>
    </div>
    {{-- Kamera --}}
    <div class="card dashboard-camera-card">
        <h3>Kamera Permukaan</h3>
        <div class="dashboard-camera-wrapper">
            <div class="dashboard-camera-item">
                <div class="dashboard-camera-box">
                    <video
                        id="dashboard-camera-atas"
                        autoplay
                        playsinline
                        muted
                        style="width:100%;height:100%;object-fit:cover;">
                    </video>
                </div>
                <p>Kamera Atas Air</p>
            </div>
            <div class="dashboard-camera-item">
                <div class="dashboard-camera-box">
                    <video
                        id="dashboard-camera-bawah"
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
    {{-- Performa Baterai --}}
    <div class="card dashboard-battery-card">
        <h3>Performa Baterai</h3>
        <div class="dashboard-battery-content">
            <div class="dashboard-battery-icon">
                <i class="bi bi-battery-half"></i>
            </div>
            <div class="dashboard-battery-info">
                <div class="dashboard-battery-item">
                    <span>Status Baterai</span>
                    <strong>45%</strong>
                </div>
                <div class="dashboard-battery-item">
                    <span>Tegangan</span>
                    <strong>51.2 V</strong>
                </div>
                <div class="dashboard-battery-item">
                    <span>Sisa Operasi</span>
                    <strong>10 Jam 30 Menit</strong>
                </div>
            </div>
        </div>
    </div>
    {{-- Performa Kapal --}}
    <div class="card dashboard-performance-card">
        <h3>Performa Kapal</h3>
        <div class="dashboard-performance-wrapper">
            <div class="dashboard-performance-item">
                <div class="dashboard-circle">
                    <span>79%</span>
                </div>
                <p>Performa Mesin</p>
                <small>Sempurna</small>
            </div>
            <div class="dashboard-performance-item">
                <div class="dashboard-circle">
                    <span>76%</span>
                </div>
                <p>Konsumsi Baterai</p>
                <small>Baik</small>
            </div>
        </div>
    </div>
    {{-- Status Sistem --}}
    <div class="card dashboard-system-card">
        <h3>Status Sistem</h3>
        <ul>
            <li>
                <i class="bi bi-check-lg"></i>
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
        <button class="dashboard-emergency-btn">
            Emergency Button
        </button>
    </div>
    {{-- Posisi Kapal --}}
    <div class="card dashboard-map-card">
        <h3>Posisi Kapal</h3>
        <div class="dashboard-map-placeholder">
            MAP LIVE
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", async () => {
    try {
        await navigator.mediaDevices.getUserMedia({
            video:true,
            audio:false
        });
        const DEVICE_BAWAH =
        "ed8b5b0d75e7e3db3f4839c11399217ffe8d9d7ae4ed5bc217ba038c230472ff";
        const DEVICE_ATAS =
        "02fe8959a97e174d48a667ec0451e9d4cf13d79b9feccc39757896afd19ab4ba";
        // Kamera Atas
        const streamAtas = await navigator.mediaDevices.getUserMedia({
            video:{
                deviceId:{
                    exact:DEVICE_ATAS
                }
            },
            audio:false
        });
        document
            .getElementById("dashboard-camera-atas")
            .srcObject = streamAtas;
        // Kamera Bawah
        const streamBawah = await navigator.mediaDevices.getUserMedia({
            video:{
                deviceId:{
                    exact:DEVICE_BAWAH
                }
            },
            audio:false
        });
        document
            .getElementById("dashboard-camera-bawah")
            .srcObject = streamBawah;
    }
    catch(error){
        console.error(error);
    }
});
</script>
@endsection