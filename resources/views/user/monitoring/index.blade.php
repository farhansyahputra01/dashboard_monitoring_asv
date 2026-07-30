@extends('layouts.user')

@section('title','Monitoring')

@section('content')

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
                            1.123400
                        </strong>
                    </div>

                    <div>
                        <span>Longitude</span>
                        <strong id="dummyLongitude">
                            102.123400
                        </strong>
                    </div>

                    <div>
                        <span>GPS</span>
                        <strong class="gps-active">
                            ● ACTIVE
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
                    07°12.345' S
                </strong>

                <small id="coordinateLongitude">
                    112°45.678' E
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

                <strong>
                    1.6 m/s
                </strong>

                <small>
                    5.8 km/h
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

                <strong>
                    128°
                </strong>

                <small>
                    South East
                </small>

            </div>

        </div>


        {{-- TOTAL JARAK --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Total Jarak</span>
            </div>

            <div class="monitor-info-value">

                <strong>
                    12.45 km
                </strong>

                <small>
                    Total Perjalanan
                </small>

            </div>

        </div>


        {{-- WAKTU TEMPUH --}}
        <div class="monitor-card">

            <div class="monitor-info-header">
                <i class="bi bi-stopwatch-fill"></i>
                <span>Waktu Tempuh</span>
            </div>

            <div class="monitor-info-value">

                <strong>
                    02:19:32
                </strong>

                <small>
                    Durasi Operasi
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
                    31 °C
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
                    78%
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

                    <div class="monitor-compass-center">

                        <i class="bi bi-send-fill"></i>

                    </div>

                </div>


                <div class="monitor-heading-value">

                    <h2>
                        128°
                    </h2>

                    <p>
                        South East
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

                <h1>
                    45%
                </h1>

                <p>
                    Baterai Normal
                </p>

                <div class="monitor-battery-bar">

                    <div
                        class="monitor-battery-fill"
                        style="width:45%"
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

@endsection