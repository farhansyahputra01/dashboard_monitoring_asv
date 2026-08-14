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
    <div class="card dashboard-battery-card">
        <h3>Performa Baterai</h3>
        <div class="dashboard-battery-content">
            <div class="dashboard-battery-icon">
                <i class="bi bi-battery-half"></i>
            </div>
            <div class="dashboard-battery-info">
                <div class="dashboard-battery-item">
                    <span>Status Baterai</span>
                    <strong id="dash-battery-percent">{{ $latest?->battery_percent !== null ? round($latest->battery_percent).'%' : '0%' }}</strong>
                </div>
                <div class="dashboard-battery-item">
                    <span>Tegangan</span>
                    <strong id="dash-voltage">{{ number_format($latest?->voltage ?? 0, 1) }} V</strong>
                </div>
                <div class="dashboard-battery-item">
                    <span>Arus</span>
                    <strong id="dash-current">{{ number_format($latest?->current ?? 0, 1) }} A</strong>
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
                    <span id="dash-satellites">{{ $latest?->satellites ?? 0 }}</span>
                </div>
                <p>Satelit GPS</p>
                <small id="dash-gps-status">{{ ($latest?->satellites ?? 0) > 0 ? 'Sinyal Aktif' : 'Mencari...' }}</small>
            </div>
            <div class="dashboard-performance-item">
                <div class="dashboard-circle">
                    <span id="dash-altitude">{{ round($latest?->altitude ?? 0) }}m</span>
                </div>
                <p>Ketinggian</p>
                <small>Ketinggian Laut</small>
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
        <button
            class="dashboard-emergency-btn"
            id="emergencyBtn"
            type="button"
            data-stop-url="{{ route('admin.control.stop') }}"
            data-resume-url="{{ route('admin.control.resume') }}"
            data-status-url="{{ route('admin.control.status') }}"
        >
            Emergency Button
        </button>
        <p id="emergencyMsg" class="dashboard-emergency-msg"></p>
    </div>
    {{-- Posisi Kapal --}}
    <div class="card dashboard-map-card">
        <h3>Posisi Kapal</h3>

        @include('partials.trajectory-map', ['track' => $track, 'bolehReset' => true])
    </div>
</div>

<script>
/* -------------------------------------------------------------------------
   Berhenti darurat.
   Laravel meneruskannya ke program Python di kapal — dialah pemilik port
   serial ESP32. Kegagalan TIDAK BOLEH senyap: kalau perintah tidak sampai,
   operator harus tahu kapal kemungkinan masih berjalan.
------------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('emergencyBtn');
    const msg = document.getElementById('emergencyMsg');
    if (!btn) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let stopped = null;

    function render() {
        btn.textContent = stopped ? 'Jalankan Kembali' : 'Emergency Button';
        btn.classList.toggle('is-stopped', stopped === true);
    }

    function show(text, isError) {
        msg.textContent = text || '';
        msg.classList.toggle('is-error', !!isError);
    }

    async function send(url) {
        btn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                show(data.message || 'Perintah gagal.', true);
                return;
            }
            stopped = data.stopped;
            render();
            show(data.message, false);
        } catch (e) {
            show('PERINTAH TIDAK SAMPAI. Kapal kemungkinan masih berjalan.', true);
        } finally {
            btn.disabled = false;
        }
    }

    btn.addEventListener('click', () => {
        send(stopped ? btn.dataset.resumeUrl : btn.dataset.stopUrl);
    });

    // Selaraskan tampilan tombol dengan keadaan kapal yang sebenarnya,
    // supaya refresh halaman tidak menampilkan keadaan yang keliru.
    fetch(btn.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
        .then((r) => r.json())
        .then((d) => {
            if (d.reachable) {
                stopped = d.stopped;
                render();
            } else {
                show('Kendali kapal tidak terjangkau.', true);
            }
        })
        .catch(() => show('Kendali kapal tidak terjangkau.', true));
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

// Teks #system-ws-status diurus resources/js/echo.js dari keadaan sambungan
// yang sebenarnya - jangan tulis manual di sini.
saatEchoSiap(() => {
        window.Echo.channel('sensors')
            .listen('SensorDataUpdated', (e) => {
                const data = e.sensorData;
                if (data.speed !== null) {
                    // Kecepatan GPS dalam km/h -> m/s
                    document.getElementById('dash-speed').textContent = (data.speed * 0.277778).toFixed(1);
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
});
</script>
@endsection