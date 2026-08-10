@extends('layouts.user')
@section('title', 'Dashboard')
@section('content')
@php
    // Nilai awal dari pembacaan sensor terakhir; setelah itu diperbarui
    // realtime lewat broadcast SensorDataUpdated di bawah.
    $arahHaluan = function ($heading) {
        if ($heading === null) return 'N/A';
        return match (true) {
            $heading >= 337.5 || $heading < 22.5 => 'North',
            $heading < 67.5                      => 'North East',
            $heading < 112.5                     => 'East',
            $heading < 157.5                     => 'South East',
            $heading < 202.5                     => 'South',
            $heading < 247.5                     => 'South West',
            $heading < 292.5                     => 'West',
            default                              => 'North West',
        };
    };
    $sat = $latest?->satellites ?? 0;
@endphp
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
            <h2><span id="dash-speed">{{ $latest?->speed !== null ? number_format($latest->speed * 0.277778, 1) : '0.0' }}</span> <span>m/s</span></h2>
        </div>
    </div>
    {{-- HALUAN --}}
    <div class="user-card user-heading-card">
        <i class="bi bi-compass"></i>
        <div>
            <small>Haluan</small>
            <h2><span id="dash-heading">{{ round($latest?->heading ?? 0) }}</span>°</h2>
            <p id="dash-heading-text">{{ $arahHaluan($latest?->heading) }}</p>
        </div>
    </div>
    {{-- KAMERA --}}
    <div class="user-card user-camera-card">
        <h3>Kamera Permukaan</h3>
        <div class="user-camera-wrapper">
            {{-- Kamera Atas Air --}}
            <div class="user-camera-item">
                <div class="user-camera-box">
                    @include('partials.camera-frame', [
                        'url' => config('camera.streams.atas'),
                        'label' => 'Kamera Atas Air',
                    ])
                </div>
                <p>Kamera Atas Air</p>
            </div>
            {{-- Kamera Bawah Air --}}
            <div class="user-camera-item">
                <div class="user-camera-box">
                    @include('partials.camera-frame', [
                        'url' => config('camera.streams.bawah'),
                        'label' => 'Kamera Bawah Air',
                    ])
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
                    <strong id="dash-battery-percent">{{ $latest?->battery_percent !== null ? round($latest->battery_percent) : 0 }}%</strong>
                </div>
                <div class="user-battery-item">
                    <span>Tegangan</span>
                    <strong id="dash-voltage">{{ number_format($latest?->voltage ?? 0, 1) }} V</strong>
                </div>
                <div class="user-battery-item">
                    <span>Arus</span>
                    <strong id="dash-current">{{ number_format($latest?->current ?? 0, 1) }} A</strong>
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
                    <span id="dash-satellites">{{ $sat }}</span>
                </div>
                <p>Satelit GPS</p>
                <small id="dash-gps-status">{{ $sat > 0 ? 'Sinyal Aktif' : 'Mencari...' }}</small>
            </div>
            <div class="user-performance-item">
                <div class="user-circle user-circle-76">
                    <span id="dash-altitude">{{ round($latest?->altitude ?? 0) }}m</span>
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