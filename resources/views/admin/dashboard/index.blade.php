@extends('layouts.admin')
@section('title','Dashboard')
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
@endphp
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
            <h2><span id="dash-speed">{{ $latest?->speed !== null ? number_format($latest->speed * 0.277778, 1) : '0.0' }}</span> <span>m/s</span></h2>
        </div>
    </div>
    {{-- Haluan --}}
    <div class="card dashboard-heading-card">
        <i class="bi bi-compass"></i>
        <div>
            <small>Haluan</small>
            <h2><span id="dash-heading">{{ $latest?->heading !== null ? round($latest->heading) : 0 }}</span>°</h2>
            <p id="dash-heading-text">{{ $arahHaluan($latest?->heading) }}</p>
        </div>
    </div>
    {{-- Kamera --}}
    <div class="card dashboard-camera-card">
        <h3>Kamera Permukaan</h3>
        <div class="dashboard-camera-wrapper">
            <div class="dashboard-camera-item">
                <div class="dashboard-camera-box">
                    @include('partials.camera-frame', [
                        'url' => config('camera.streams.atas'),
                        'label' => 'Kamera Atas Air',
                    ])
                </div>
                <p>Kamera Atas Air</p>
            </div>
            <div class="dashboard-camera-item">
                <div class="dashboard-camera-box">
                    @include('partials.camera-frame', [
                        'url' => config('camera.streams.bawah'),
                        'label' => 'Kamera Bawah Air',
                    ])
                </div>
                <p>Kamera Bawah Air</p>
            </div>
        </div>
    </div>
    {{-- Performa Baterai --}}
    @php
        $batt = $latest?->battery_percent !== null ? round($latest->battery_percent) : 0;
        $volt = $latest?->voltage ?? 0;
        $curr = $latest?->current ?? 0;
        $power = number_format($volt * $curr, 1);
        $sat = $latest?->satellites ?? 0;
    @endphp
    <div class="card dashboard-battery-card">
        <div class="dash-card-header">
            <h3><i class="bi bi-battery-charging"></i> Performa Baterai</h3>
            <span class="dash-status-pill {{ $batt < 20 ? 'pill-danger' : 'pill-normal' }}" id="dash-battery-status-pill">{{ $batt < 20 ? 'Lemah' : 'Normal' }}</span>
        </div>
        <div class="dash-battery-hero">
            <div class="dash-battery-hero-icon">
                <i class="bi bi-battery-half"></i>
            </div>
            <div class="dash-battery-hero-main">
                <span class="dash-battery-big-val" id="dash-battery-percent">{{ $batt }}%</span>
                <span class="dash-battery-sub">Kapasitas Tersisa</span>
            </div>
        </div>
        <div class="dash-battery-bar-container">
            <div class="dash-battery-bar-track">
                <div class="dash-battery-bar-fill" id="dash-battery-bar" style="width: {{ $batt }}%;"></div>
            </div>
        </div>
        <div class="dash-battery-metrics-grid">
            <div class="dash-battery-metric-item">
                <span class="metric-label"><i class="bi bi-lightning-charge"></i> Tegangan</span>
                <strong id="dash-voltage">{{ number_format($volt, 1) }} V</strong>
            </div>
            <div class="dash-battery-metric-item">
                <span class="metric-label"><i class="bi bi-activity"></i> Arus</span>
                <strong id="dash-current">{{ number_format($curr, 1) }} A</strong>
            </div>
            <div class="dash-battery-metric-item">
                <span class="metric-label"><i class="bi bi-plug"></i> Daya</span>
                <strong id="dash-power">{{ $power }} W</strong>
            </div>
        </div>
    </div>
    {{-- Performa Kapal --}}
    <div class="card dashboard-performance-card">
        <div class="dash-card-header">
            <h3><i class="bi bi-cpu"></i> Performa Kapal</h3>
            <span class="dash-status-pill {{ $sat > 0 ? 'pill-normal' : 'pill-warning' }}" id="dash-gps-status">{{ $sat > 0 ? 'GPS Terkunci' : 'Mencari...' }}</span>
        </div>
        <div class="dashboard-performance-wrapper">
            <div class="dashboard-performance-item">
                <div class="dashboard-circle">
                    <span id="dash-satellites">{{ $sat }}</span>
                </div>
                <p>Satelit GPS</p>
                <small id="dash-gps-sub">{{ $sat >= 4 ? 'Sinyal Kuat' : ($sat > 0 ? 'Sinyal Cukup' : 'Mencari...') }}</small>
            </div>
            <div class="dashboard-performance-item">
                <div class="dashboard-circle">
                    <span id="dash-altitude">{{ round($latest?->altitude ?? 0) }}m</span>
                </div>
                <p>Ketinggian</p>
                <small>Permukaan Laut</small>
            </div>
        </div>
        <div class="dash-perf-footer-grid">
            <div class="dash-perf-footer-item">
                <i class="bi bi-broadcast-pin"></i>
                <div>
                    <small>Akurasi Navigasi</small>
                    <strong id="dash-gps-quality">{{ $sat >= 6 ? '3D Fix (Akurat)' : ($sat > 0 ? '2D Fix' : 'Mencari Sinyal') }}</strong>
                </div>
            </div>
            <div class="dash-perf-footer-item">
                <i class="bi bi-water"></i>
                <div>
                    <small>Stabilitas Sikap</small>
                    <strong id="dash-stability">Stabil & Siap</strong>
                </div>
            </div>
        </div>
    </div>
    {{-- Status Sistem --}}
    <div class="card dashboard-system-card">
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
        @include('partials.kendali-kapal')
    </div>
    {{-- Posisi Kapal --}}
    <div class="card dashboard-map-card">
        <h3>Posisi Kapal</h3>

        @include('partials.trajectory-map', ['track' => $track, 'bolehReset' => true])
    </div>
</div>

<script>
/* -------------------------------------------------------------------------
   Tombol berhenti darurat / mulai sekarang ada di resources/js/kendali-kapal.js
   dan dipasang lewat partials/kendali-kapal.blade.php, supaya halaman
   Monitoring memakai kontrol yang SAMA - bukan salinan kedua yang harus
   dijaga sejalan.
------------------------------------------------------------------------- */

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

// Teks #system-ws-status diurus resources/js/echo.js dari keadaan sambungan
// yang sebenarnya - jangan tulis manual di sini.
saatEchoSiap(() => {
        window.Echo.channel('sensors')
            .listen('SensorDataUpdated', (e) => {
                const data = e.sensorData;
                if (data.speed !== null) {
                    // Kecepatan GPS dalam km/h -> m/s
                    angkaHalus('dash-speed', data.speed * 0.277778, { desimal: 1 });
                }
                if (data.heading !== null) {
                    angkaHalus('dash-heading', data.heading, {
                        putar: true,
                        saat: (v) => {
                            document.getElementById('dash-heading-text').textContent =
                                getHeadingDirection(v);
                        },
                    });
                }
                if (data.battery_percent !== null) {
                    const bPercent = Math.round(data.battery_percent);
                    document.getElementById('dash-battery-percent').textContent = bPercent + '%';
                    const bar = document.getElementById('dash-battery-bar');
                    if (bar) bar.style.width = bPercent + '%';
                    const pill = document.getElementById('dash-battery-status-pill');
                    if (pill) {
                        pill.textContent = bPercent < 20 ? 'Lemah' : 'Normal';
                        pill.className = 'dash-status-pill ' + (bPercent < 20 ? 'pill-danger' : 'pill-normal');
                    }
                }
                let currentVolt = null;
                let currentCurr = null;
                if (data.voltage !== null) {
                    currentVolt = parseFloat(data.voltage);
                    document.getElementById('dash-voltage').textContent = currentVolt.toFixed(1) + ' V';
                }
                if (data.current !== null) {
                    currentCurr = parseFloat(data.current);
                    document.getElementById('dash-current').textContent = currentCurr.toFixed(1) + ' A';
                }
                const powerEl = document.getElementById('dash-power');
                if (powerEl && (data.voltage !== null || data.current !== null)) {
                    const v = currentVolt !== null ? currentVolt : parseFloat(document.getElementById('dash-voltage')?.textContent || 0);
                    const i = currentCurr !== null ? currentCurr : parseFloat(document.getElementById('dash-current')?.textContent || 0);
                    if (!isNaN(v) && !isNaN(i)) {
                        powerEl.textContent = (v * i).toFixed(1) + ' W';
                    }
                }
                if (data.satellites !== null) {
                    const s = data.satellites;
                    document.getElementById('dash-satellites').textContent = s;
                    const gpsStatus = document.getElementById('dash-gps-status');
                    if (gpsStatus) {
                        gpsStatus.textContent = s > 0 ? 'GPS Terkunci' : 'Mencari...';
                        gpsStatus.className = 'dash-status-pill ' + (s > 0 ? 'pill-normal' : 'pill-warning');
                    }
                    const gpsSub = document.getElementById('dash-gps-sub');
                    if (gpsSub) {
                        gpsSub.textContent = s >= 4 ? 'Sinyal Kuat' : (s > 0 ? 'Sinyal Cukup' : 'Mencari...');
                    }
                    const gpsQual = document.getElementById('dash-gps-quality');
                    if (gpsQual) {
                        gpsQual.textContent = s >= 6 ? '3D Fix (Akurat)' : (s > 0 ? '2D Fix' : 'Mencari Sinyal');
                    }
                }
                if (data.altitude !== null) {
                    document.getElementById('dash-altitude').textContent = Math.round(data.altitude) + 'm';
                }
            });
});
</script>
@endsection