@extends('layouts.admin')
@section('title','Monitoring')
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
    @if(session('success'))
        <div class="alert alert-success" style="padding: 12px 18px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--accent-emerald); font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 10px;">
            <i class="bi bi-check-circle-fill" style="font-size: 16px;"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="padding: 12px 18px; border-radius: 12px; background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--accent-rose); font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 10px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size: 16px;"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- =====================================================
         LINTASAN
    ====================================================== --}}
    <div class="monitor-card monitor-map-card">
        <div class="monitor-card-header-row">
            <div class="monitor-card-title">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Lintasan</span>
            </div>
            {{-- PILIHAN LINTASAN --}}
            <form
                action="{{ route('admin.monitoring.track') }}"
                method="POST"
                class="monitor-track-selector"
            >
                @csrf
                <label>Pilih Lintasan</label>
                <select
                    name="active_track"
                    id="trackSelect"
                >
                    <option
                        value="A"
                        {{ optional($setting)->active_track=='A' ? 'selected' : '' }}
                    >
                        Lintasan A
                    </option>
                    <option
                        value="B"
                        {{ optional($setting)->active_track=='B' ? 'selected' : '' }}
                    >
                        Lintasan B
                    </option>
                </select>
                <button type="submit">
                    Simpan
                </button>

                {{--
                    Pilihan di kotak ini BELUM berlaku sampai Simpan ditekan.

                    Tanpa penanda ini, peta ikut berpindah begitu pilihan diganti
                    sementara sistem masih memakai yang lama - dua pengertian
                    "lintasan terpilih" yang berbeda di satu layar, dan operator
                    tidak punya cara tahu yang mana yang sedang berlaku.
                --}}
                <span class="track-belum-simpan" data-track-belum-simpan hidden>
                    belum disimpan
                </span>
            </form>
            {{--
                Kendali kapal: di baris paling atas kartu, sejajar pemilih lintasan.

                TIDAK boleh diletakkan di dalam .monitor-track-layout - itu grid
                dua kolom (panel info | peta), jadi apa pun yang disisipkan di sana
                merebut kolom peta dan mendorong petanya turun ke baris berikutnya.
            --}}
            @include('partials.kendali-kapal', ['ringkas' => true])
        </div>

        {{-- AREA MONITORING LINTASAN --}}
        <div class="monitor-track-layout">
            {{-- =================================================
                 PANEL INFORMASI
            ================================================== --}}
            <div class="monitor-track-info">
                {{--
                    KENDALI KAPAL (LANGSUNG). Dulu angka-angka ini ditulis
                    program Python di atas gambar kamera. Gambarnya sekarang
                    bersih (hanya lingkaran bola yang dipakai kemudi), dan
                    angkanya dibaca dari GET /stream/status - JSON yang ditulis
                    kapal tiap frame, lewat proxy /stream/ nginx. Bukan dari
                    telemetri database (1 Hz). Lihat resources/js/panel-kendali.js.
                --}}
                <div class="track-info-section panel-kendali" data-panel-kendali data-status-url="/stream/status">
                    <h4>Kendali Kapal <small class="panel-kendali-tanda is-diam" data-k-tanda>memeriksa...</small></h4>
                    <div class="panel-kendali-mode" data-k="mode">-</div>
                    <dl class="panel-kendali-grid">
                        <dt>Motor L / R</dt><dd data-k="motor">-</dd>
                        <dt>Terkirim</dt><dd data-k="kirim">-</dd>
                        <dt>Error</dt><dd data-k="error">-</dd>
                        <dt>Gerbang</dt><dd data-k="gerbang">-</dd>
                        <dt>Fase</dt><dd data-k="phase">-</dd>
                        <dt>Bola</dt><dd data-k="bola">-</dd>
                        <dt>Pelacak</dt><dd data-k="lacak">-</dd>
                        <dt>Kunci gerbang</dt><dd data-k="kunci">-</dd>
                        <dt>Sisi</dt><dd data-k="sisi">-</dd>
                        <dt>Peta</dt><dd data-k="peta">-</dd>
                        <dt>Posisi</dt><dd data-k="pos">-</dd>
                        <dt>Dipercaya</dt><dd data-k="percaya">-</dd>
                        <dt>FPS</dt><dd data-k="fps">-</dd>
                        <dt>Detektor</dt><dd data-k="det">-</dd>
                        <dt>Tenaga</dt><dd data-k="tenaga">-</dd>
                    </dl>
                    <p class="panel-kendali-ket" data-k="ket">-</p>
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
                @include('partials.lintasan-map', [
                    'jejak' => $jejakLintasan,
                    'lintasan' => $setting->active_track ?? 'A',
                    'bolehReset' => true,
                    'bolehEdit' => true,
                ])
            </div>
        </div>
    </div>
    {{-- =====================================================
         INFO
    ====================================================== --}}
    <div class="monitor-info-grid">
        {{-- Koordinat --}}
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
        {{-- Kecepatan --}}
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
        {{-- Haluan --}}
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
        {{-- Altitude / Satelit --}}
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Altitude / Satelit</span>
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
        {{-- Tegangan & Arus --}}
        <div class="monitor-card">
            <div class="monitor-info-header">
                <i class="bi bi-lightning-charge-fill"></i>
                <span>Tegangan &amp; Arus</span>
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
        {{-- Lokasi --}}
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
        {{-- =================================================
             SUHU
        ================================================== --}}
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
        {{-- =================================================
             KELEMBAPAN
        ================================================== --}}
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
        {{-- Compass --}}
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
                    <div
                        class="monitor-compass-center"
                        id="compassArrow"
                        style="transform: rotate({{ round($latest?->heading ?? 0) }}deg);"
                    >
                        <svg class="compass-needle" viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="12,2 19,21 12,17 5,21"/>
                        </svg>
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
        {{-- Battery --}}
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
                        class="monitor-battery-fill"
                        id="mon-battery-fill"
                        style="width: {{ $batt }}%;"
                    ></div>
                </div>
            </div>
        </div>
        {{-- Distribusi --}}
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
                    ['Komunikasi',5],
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
{{-- =========================================================
     PEMILIH LINTASAN + DATA SENSOR REALTIME
========================================================= --}}
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Dulu di sini ada dua <div id="lintasanA/B"> berisi gambar PNG lintasan
    // yang disembunyikan bergantian. Keduanya sudah tidak ada - petanya
    // sekarang digambar kanvas dari koordinat sungguhan - tapi kodenya
    // tertinggal dan memanggil .style pada null. TypeError itu MENGHENTIKAN
    // seluruh skrip inline ini, termasuk pembaruan angka sensor di bawahnya.
    //
    // Pergantian arena kini ditangani lintasan-map.js lewat elemen select yang
    // sama, jadi di sini tidak ada lagi yang perlu dikerjakan.
    const select = document.getElementById("trackSelect");
    const lintasanA = document.getElementById("lintasanA");
    const lintasanB = document.getElementById("lintasanB");

    if (select && lintasanA && lintasanB) {
        const tampilkan = (track) => {
            lintasanA.style.display = track === "A" ? "block" : "none";
            lintasanB.style.display = track === "B" ? "block" : "none";
        };
        tampilkan(select.value);
        select.addEventListener("change", function () {
            tampilkan(this.value);
        });
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

document.addEventListener('DOMContentLoaded', () => {
    saatEchoSiap(() => {
        window.Echo.channel('sensors')
            .listen('SensorDataUpdated', (e) => {
                const data = e.sensorData;

                // Posisi. latitude/longitude bernilai null selama GPS belum
                // fix, jadi nilai lama sengaja dibiarkan apa adanya.
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

                if (data.speed !== null) {
                    // Firmware mengirim km/h (gps.speed.kmph())
                    angkaHalus('mon-speed-ms', data.speed * 0.277778, { desimal: 1, satuan: ' m/s' });
                    angkaHalus('mon-speed-kmh', data.speed, { desimal: 1, satuan: ' km/h' });
                }

                if (data.heading !== null) {
                    // putar:true -> 350° lalu 10° dibaca sebagai perputaran 20°
                    // ke kanan, bukan 340° ke kiri. Lihat angka-halus.js.
                    //
                    // Teks arah ikut diperbarui dari nilai yang SEDANG tampil,
                    // bukan dari nilai akhir - kalau tidak, angkanya masih
                    // bergerak menuju 95° sementara tulisannya sudah "East".
                    angkaHalus('mon-heading-deg', data.heading, {
                        satuan: '°',
                        putar: true,
                        saat: (v) => {
                            const arah = getHeadingDirection(v);
                            document.getElementById('mon-heading-dir').textContent = arah;
                            document.getElementById('mon-compass-dir').textContent = arah;
                        },
                    });
                    angkaHalus('mon-compass-heading', data.heading, { satuan: '°', putar: true });
                    sudutHalus('compassArrow', data.heading);
                }

                if (data.altitude !== null) {
                    angkaHalus('mon-alt-meters', data.altitude, { satuan: ' m' });
                }

                if (data.voltage !== null) {
                    angkaHalus('mon-voltage', data.voltage, { desimal: 1, satuan: ' V' });
                }
                if (data.current !== null) {
                    angkaHalus('mon-current', data.current, { desimal: 1, satuan: ' A' });
                }

                if (data.temperature !== null) {
                    angkaHalus('temperature', data.temperature, { desimal: 1, satuan: ' °C' });
                }
                if (data.humidity !== null) {
                    angkaHalus('humidity', data.humidity, { desimal: 1, satuan: '%' });
                }

                if (data.battery_percent !== null) {
                    angkaHalus('mon-battery-percent', data.battery_percent, {
                        satuan: '%',
                        saat: (v) => {
                            // Batang baterai ikut nilai yang sedang tampil
                            // supaya angka dan batangnya tidak pernah beda.
                            document.getElementById('mon-battery-fill').style.width = v + '%';
                        },
                    });
                    document.getElementById('mon-battery-status').textContent =
                        data.battery_percent < 20 ? 'Baterai Lemah' : 'Baterai Normal';
                }
            });
    });
});
</script>
@endsection