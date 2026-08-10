@extends('layouts.user')
@section('title', 'Monitoring')
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
    $batt = $latest?->battery_percent !== null ? round($latest->battery_percent) : 0;
@endphp
<div class="monitoring-page">

    {{-- =====================================================
         LINTASAN
    ====================================================== --}}
    <div class="monitor-card monitor-map-card">

        <div class="monitor-card-title">
            <i class="bi bi-signpost-2-fill"></i>
            <span>Lintasan</span>
        </div>

        {{-- AREA MONITORING LINTASAN --}}
        <div class="monitor-track-layout">

            {{-- =================================================
                 PANEL INFORMASI
            ================================================== --}}
            <div class="monitor-track-info">

                <div class="track-info-section">
                    <h4>Positioning</h4>
                    <ol>
                        <li>Start</li>
                        <li>Floating Ball Set 1–10</li>
                        <li>Mission Surface</li>
                        <li>Mission Underwater</li>
                        <li>Docking</li>
                        <li>Finish</li>
                    </ol>
                </div>

                <div class="track-info-section">
                    <h4>Altitude Information</h4>
                    <ol>
                        <li>TOG</li>
                        <li>COG</li>
                        <li>COG</li>
                    </ol>
                </div>

                <div class="track-info-section">
                    <h4>Indikator Lain</h4>
                    <ol>
                        <li>Battery Level</li>
                        <li>Visual Video</li>
                    </ol>
                </div>

                {{-- STATUS GPS --}}
                <div class="track-status">

                    <div>
                        <span>Latitude</span>
                        <strong id="dummyLatitude">
                            {{ $latest?->latitude !== null ? number_format($latest->latitude, 6, '.', '') : '-' }}
                        </strong>
                    </div>

                    <div>
                        <span>Longitude</span>
                        <strong id="dummyLongitude">
                            {{ $latest?->longitude !== null ? number_format($latest->longitude, 6, '.', '') : '-' }}
                        </strong>
                    </div>

                    <div>
                        <span>GPS</span>
                        <strong id="gpsStatusText" class="{{ $sat > 0 ? 'gps-active' : '' }}">
                            {{ $sat > 0 ? '● ACTIVE' : '● SEARCHING' }}
                        </strong>
                    </div>

                </div>

            </div>


            {{-- =================================================
                 AREA LINTASAN
            ================================================== --}}
            <div class="monitor-track-area">

                <div class="track-grid">

                    {{-- LABEL KOLOM --}}
                    <div class="track-column-labels">
                        <span>A</span>
                        <span>B</span>
                        <span>C</span>
                        <span>D</span>
                        <span>E</span>
                    </div>

                    {{-- LABEL BARIS --}}
                    <div class="track-row-labels">
                        <span>5</span>
                        <span>4</span>
                        <span>3</span>
                        <span>2</span>
                        <span>1</span>
                    </div>

                    {{-- LINTASAN --}}
                    <div class="track-field">

                        @if(optional($setting)->active_track == 'B')

                            @include('admin.monitoring.lintasan-b')

                        @else

                            @include('admin.monitoring.lintasan-a')

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         INFO
    ====================================================== --}}
    <div class="monitor-info-grid">

        {{-- KOORDINAT --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Koordinat</span>
            </div>

            <div class="monitor-info-value">

                <strong id="coordinateLatitude">
                    {{ $latest?->latitude !== null ? number_format($latest->latitude, 6, '.', '') : '-' }}
                </strong>

                <small id="coordinateLongitude">
                    {{ $latest?->longitude !== null ? number_format($latest->longitude, 6, '.', '') : '-' }}
                </small>

            </div>

        </div>


        {{-- KECEPATAN --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-speedometer2"></i>
                <span>Kecepatan</span>
            </div>

            <div class="monitor-info-value">

                <strong id="mon-speed-ms">
                    {{ number_format(($latest?->speed ?? 0) * 0.277778, 1) }} m/s
                </strong>

                <small id="mon-speed-kmh">
                    {{ number_format($latest?->speed ?? 0, 1) }} km/h
                </small>

            </div>

        </div>


        {{-- HALUAN --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-compass-fill"></i>
                <span>Haluan</span>
            </div>

            <div class="monitor-info-value">

                <strong id="mon-heading-deg">
                    {{ round($latest?->heading ?? 0) }}°
                </strong>

                <small id="mon-heading-dir">
                    {{ $arahHaluan($latest?->heading) }}
                </small>

            </div>

        </div>


        {{-- TOTAL JARAK --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Altitude / Satelites</span>
            </div>

            <div class="monitor-info-value">

                <strong id="mon-alt-meters">
                    {{ round($latest?->altitude ?? 0) }} m
                </strong>

                <small id="mon-satellites">
                    {{ $sat }} Sats
                </small>

            </div>

        </div>


        {{-- WAKTU TEMPUH --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-lightning-charge-fill"></i>
                <span>Tegangan & Arus</span>
            </div>

            <div class="monitor-info-value">

                <strong id="mon-voltage">
                    {{ number_format($latest?->voltage ?? 0, 1) }} V
                </strong>

                <small id="mon-current">
                    {{ number_format($latest?->current ?? 0, 1) }} A
                </small>

            </div>

        </div>


        {{-- LOKASI --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-water"></i>
                <span>Lokasi Perairan</span>
            </div>

            <div class="monitor-info-value">

                <strong>
                    Kolam Lomba KKI 2026
                </strong>

                <small>
                    Politeknik Negeri Bengkalis
                </small>

            </div>

        </div>


        {{-- SUHU --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-thermometer-half"></i>
                <span>Suhu</span>
            </div>

            <div class="monitor-info-value">

                <strong id="temperature">
                    {{ $latest?->temperature !== null ? number_format($latest->temperature, 1) . ' °C' : '- °C' }}
                </strong>

                <small>
                    Suhu Lingkungan
                </small>

            </div>

        </div>


        {{-- KELEMBAPAN --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-droplet-fill"></i>
                <span>Kelembapan</span>
            </div>

            <div class="monitor-info-value">

                <strong id="humidity">
                    {{ $latest?->humidity !== null ? number_format($latest->humidity, 1) . '%' : '-%' }}
                </strong>

                <small>
                    Kelembapan Lingkungan
                </small>

            </div>

        </div>

    </div>


    {{-- =====================================================
         MONITORING TAMBAHAN
    ====================================================== --}}
    <div class="monitor-bottom-grid">


        {{-- =================================================
             COMPASS
        ================================================== --}}
        <div class="monitor-card">

            <div class="monitor-card-title">

                <i class="bi bi-compass-fill"></i>

                <span>Compass</span>

            </div>


            <div class="monitor-compass-wrapper">

                <div class="monitor-compass-circle">

                    <div class="monitor-north">
                        N
                    </div>

                    <div class="monitor-east">
                        E
                    </div>

                    <div class="monitor-south">
                        S
                    </div>

                    <div class="monitor-west">
                        W
                    </div>

                    <div class="monitor-compass-center" id="compassArrow" style="transform: rotate({{ round($latest?->heading ?? 0) }}deg);">

                        <i class="bi bi-send-fill"></i>

                    </div>

                </div>


                <div class="monitor-heading-value">

                    <h2 id="mon-compass-heading">
                        {{ round($latest?->heading ?? 0) }}°
                    </h2>

                    <p id="mon-compass-dir">
                        {{ $arahHaluan($latest?->heading) }}
                    </p>

                </div>

            </div>

        </div>


        {{-- =================================================
             BATTERY
        ================================================== --}}
        <div class="monitor-card">

            <div class="monitor-card-title">

                <i class="bi bi-battery-half"></i>

                <span>Status Baterai</span>

            </div>


            <div class="monitor-battery">

                <i class="bi bi-battery-half monitor-battery-big"></i>

                <h1 id="mon-battery-percent">
                    {{ $batt }}%
                </h1>

                <p id="mon-battery-status">
                    {{ $batt < 20 ? 'Baterai Lemah' : 'Baterai Normal' }}
                </p>

                <div class="monitor-battery-bar">

                    <div
                        id="mon-battery-fill"
                        class="monitor-battery-fill"
                        style="width:{{ $batt }}%"
                    ></div>

                </div>

            </div>

        </div>


        {{-- =================================================
             DISTRIBUSI DAYA
        ================================================== --}}
        <div class="monitor-card">

            <div class="monitor-card-title">

                <i class="bi bi-lightning-charge-fill"></i>

                <span>
                    Distribusi Konsumsi Daya
                </span>

            </div>


            <div class="monitor-power-list">

                @foreach([
                    ['Motor Kiri',38],
                    ['Motor Kanan',34],
                    ['Mini PC',10],
                    ['Kamera',8],
                    ['Sensor',5],
                    ['Komunikasi',5]
                ] as $item)

                    <div class="monitor-power-item">

                        <div class="monitor-power-top">

                            <span>
                                {{ $item[0] }}
                            </span>

                            <strong>
                                {{ $item[1] }}%
                            </strong>

                        </div>


                        <div class="monitor-progress">

                            <div
                                class="monitor-progress-fill"
                                style="width:{{ $item[1] }}%"
                            ></div>

                        </div>

                    </div>

                @endforeach

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

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        if (window.Echo) {
            window.Echo.channel('sensors')
                .listen('SensorDataUpdated', (e) => {
                    const data = e.sensorData;
                    
                    // Lat/Lng
                    if (data.latitude !== null) {
                        const lat = parseFloat(data.latitude).toFixed(6);
                        document.getElementById('dummyLatitude').textContent = lat;
                        document.getElementById('coordinateLatitude').textContent = lat;
                    }
                    if (data.longitude !== null) {
                        const lng = parseFloat(data.longitude).toFixed(6);
                        document.getElementById('dummyLongitude').textContent = lng;
                        document.getElementById('coordinateLongitude').textContent = lng;
                    }
                    
                    // Satellites & GPS Status
                    if (data.satellites !== null) {
                        document.getElementById('mon-satellites').textContent = data.satellites + ' Sats';
                        const gpsText = document.getElementById('gpsStatusText');
                        if (data.satellites > 0) {
                            gpsText.textContent = '● ACTIVE';
                            gpsText.className = 'gps-active';
                        } else {
                            gpsText.textContent = '● SEARCHING';
                            gpsText.className = '';
                        }
                    }
                    
                    // Speed
                    if (data.speed !== null) {
                        const speedKmh = parseFloat(data.speed).toFixed(1);
                        const speedMs = (data.speed * 0.277778).toFixed(1);
                        document.getElementById('mon-speed-ms').textContent = speedMs + ' m/s';
                        document.getElementById('mon-speed-kmh').textContent = speedKmh + ' km/h';
                    }
                    
                    // Heading
                    if (data.heading !== null) {
                        const hDeg = Math.round(data.heading);
                        const hDir = getHeadingDirection(data.heading);
                        document.getElementById('mon-heading-deg').textContent = hDeg + '°';
                        document.getElementById('mon-heading-dir').textContent = hDir;
                        document.getElementById('mon-compass-heading').textContent = hDeg + '°';
                        document.getElementById('mon-compass-dir').textContent = hDir;
                        
                        const arrow = document.getElementById('compassArrow');
                        if (arrow) {
                            arrow.style.transform = `rotate(${hDeg}deg)`;
                        }
                    }
                    
                    // Altitude
                    if (data.altitude !== null) {
                        document.getElementById('mon-alt-meters').textContent = Math.round(data.altitude) + ' m';
                    }
                    
                    // Voltage & Current
                    if (data.voltage !== null) {
                        document.getElementById('mon-voltage').textContent = parseFloat(data.voltage).toFixed(1) + ' V';
                    }
                    if (data.current !== null) {
                        document.getElementById('mon-current').textContent = parseFloat(data.current).toFixed(1) + ' A';
                    }
                    
                    // Temp & Humidity
                    if (data.temperature !== null) {
                        document.getElementById('temperature').textContent = parseFloat(data.temperature).toFixed(1) + ' °C';
                    }
                    if (data.humidity !== null) {
                        document.getElementById('humidity').textContent = parseFloat(data.humidity).toFixed(1) + '%';
                    }
                    
                    // Battery
                    if (data.battery_percent !== null) {
                        const bPercent = Math.round(data.battery_percent);
                        document.getElementById('mon-battery-percent').textContent = bPercent + '%';
                        document.getElementById('mon-battery-fill').style.width = bPercent + '%';
                        document.getElementById('mon-battery-status').textContent = bPercent < 20 ? 'Baterai Lemah' : 'Baterai Normal';
                    }
                });
        }
    }, 1000);
});
</script>

@endsection