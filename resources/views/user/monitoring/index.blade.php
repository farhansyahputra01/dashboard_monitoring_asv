@extends('layouts.user')
@section('title','Monitoring')
@section('content')
<div class="monitoring-page">
    {{-- ================= MAP ================= --}}
    <div class="monitor-card monitor-map-card">
        <div class="monitor-card-title">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Posisi Kapal</span>
        </div>
        <div class="monitor-map-placeholder">
            <div class="monitor-map-center">
                <i class="bi bi-map"></i>
                <h2>MAP LIVE</h2>
                <p>Lokasi kapal ditampilkan secara real-time</p>
            </div>
        </div>
    </div>
    {{-- ================= INFO ================= --}}
    <div class="monitor-info-grid">
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Koordinat</span>
            </div>
            <div class="monitor-info-value">
                <strong>07°12.345' S</strong>
                <small>112°45.678' E</small>
            </div>
        </div>
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-speedometer2"></i>
                <span>Kecepatan</span>
            </div>
            <div class="monitor-info-value">
                <strong>1.6 m/s</strong>
                <small>5.8 km/h</small>
            </div>
        </div>
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-compass-fill"></i>
                <span>Haluan</span>
            </div>
            <div class="monitor-info-value">
                <strong>128°</strong>
                <small>South East</small>
            </div>
        </div>
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Total Jarak</span>
            </div>
            <div class="monitor-info-value">
                <strong>12.45 km</strong>
                <small>Total Perjalanan</small>
            </div>
        </div>
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-stopwatch-fill"></i>
                <span>Waktu Tempuh</span>
            </div>
            <div class="monitor-info-value">
                <strong>02:19:32</strong>
                <small>Durasi Operasi</small>
            </div>
        </div>
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-water"></i>
                <span>Lokasi Perairan</span>
            </div>
            <div class="monitor-info-value">
                <strong>Kolam Lomba KKI 2026</strong>
                <small>Politeknik Negeri Bengkalis</small>
            </div>
        </div>
    </div>

    {{-- ================= BOTTOM ================= --}}
    <div class="monitor-bottom-grid">
        {{-- Compass --}}
        <div class="monitor-card">
            <div class="monitor-card-title">
                <i class="bi bi-compass-fill"></i>
                <span>Compass</span>
            </div>
            <div class="monitor-compass-wrapper">
                <div class="monitor-compass-circle">
                    <div class="monitor-north">N</div>
                    <div class="monitor-east">E</div>
                    <div class="monitor-south">S</div>
                    <div class="monitor-west">W</div>
                    <div class="monitor-compass-center">
                        <i class="bi bi-send-fill"></i>
                    </div>
                </div>
                <div class="monitor-heading-value">
                    <h2>128°</h2>
                    <p>South East</p>
                </div>
            </div>
        </div>
        {{-- Battery --}}
        <div class="monitor-card">
            <div class="monitor-card-title">
                <i class="bi bi-battery-half"></i>
                <span>Status Baterai</span>
            </div>
            <div class="monitor-battery">
                <i class="bi bi-battery-half monitor-battery-big"></i>
                <h1>45%</h1>
                <p>Baterai Normal</p>
                <div class="monitor-battery-bar">
                    <div class="monitor-battery-fill"></div>
                </div>
            </div>
        </div>
        {{-- Distribusi Daya --}}
        <div class="monitor-card">
            <div class="monitor-card-title">
                <i class="bi bi-lightning-charge-fill"></i>
                <span>Distribusi Konsumsi Daya</span>
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
                        <span>{{ $item[0] }}</span>
                        <strong>{{ $item[1] }}%</strong>
                    </div>
                    <div class="monitor-progress">
                        <div class="monitor-progress-fill"
                            style="width:{{ $item[1] }}%">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection