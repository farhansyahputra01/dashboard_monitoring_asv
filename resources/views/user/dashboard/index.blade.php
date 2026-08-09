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
            <h2><span id="dash-speed">0.0</span> <span>m/s</span></h2>
        </div>
    </div>
    {{-- HALUAN --}}
    <div class="user-card user-heading-card">
        <i class="bi bi-compass"></i>
        <div>
            <small>Haluan</small>
            <h2><span id="dash-heading">0</span>°</h2>
            <p id="dash-heading-text">N/A</p>
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
                    <strong id="dash-battery-percent">0%</strong>
                </div>
                <div class="user-battery-item">
                    <span>Tegangan</span>
                    <strong id="dash-voltage">0.0 V</strong>
                </div>
                <div class="user-battery-item">
                    <span>Arus</span>
                    <strong id="dash-current">0.0 A</strong>
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
                    <span id="dash-satellites">0</span>
                </div>
                <p>Satelit GPS</p>
                <small id="dash-gps-status">Mencari...</small>
            </div>
            <div class="user-performance-item">
                <div class="user-circle user-circle-76">
                    <span id="dash-altitude">0m</span>
                </div>
                <p>Ketinggian</p>
                <small>Ketinggian Laut</small>
            </div>
        </div>
    </div>
    {{-- STATUS SISTEM --}}
    <div class="user-card user-system-card">
        <h3>Status Sistem</h3>
        <ul>
            <li>
                <i class="bi bi-wifi"></i>
                <span id="system-ws-status">Koneksi Menghubungkan...</span>
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

function getHeadingDirection(heading) {
    if (heading >= 337.5 || heading < 22.5) return 'North';
    if (heading >= 22.5 && heading < 67.5) return 'North East';
    if (heading >= 67.5 && heading < 112.5) return 'East';
    if (heading >= 112.5 && heading < 157.5) return 'South East';
    if (heading >= 157.5 && heading < 202.5) return 'South';
    if (heading >= 202.5 && heading < 247.5) return 'South West';
    if (heading >= 247.5 && heading < 292.5) return 'West';
    if (heading >= 292.5 && heading < 337.5) return 'North West';
    return 'N/A';
}

setTimeout(() => {
    if (window.Echo) {
        document.getElementById('system-ws-status').textContent = 'WebSockets Terhubung';
        window.Echo.channel('sensors')
            .listen('SensorDataUpdated', (e) => {
                const data = e.sensorData;
                if (data.speed !== null) {
                    // Convert km/h to m/s if speed is in km/h (1 km/h = 0.277778 m/s)
                    const speedMS = (data.speed * 0.277778).toFixed(1);
                    document.getElementById('dash-speed').textContent = speedMS;
                }
                if (data.heading !== null) {
                    document.getElementById('dash-heading').textContent = Math.round(data.heading);
                    document.getElementById('dash-heading-text').textContent = getHeadingDirection(data.heading);
                }
                if (data.battery_percent !== null) {
                    document.getElementById('dash-battery-percent').textContent = Math.round(data.battery_percent) + '%';
                }
                if (data.voltage !== null) {
                    document.getElementById('dash-voltage').textContent = parseFloat(data.voltage).toFixed(1) + ' V';
                }
                if (data.current !== null) {
                    document.getElementById('dash-current').textContent = parseFloat(data.current).toFixed(1) + ' A';
                }
                if (data.satellites !== null) {
                    document.getElementById('dash-satellites').textContent = data.satellites;
                    document.getElementById('dash-gps-status').textContent = data.satellites > 0 ? 'Sinyal Aktif' : 'Mencari...';
                }
                if (data.altitude !== null) {
                    document.getElementById('dash-altitude').textContent = Math.round(data.altitude) + 'm';
                }
            });
    } else {
        document.getElementById('system-ws-status').textContent = 'WebSockets Terputus';
    }
}, 1000);
</script>
@endsection