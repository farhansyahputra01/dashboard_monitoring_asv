@extends('layouts.admin')
@section('title','Alarm & Notifikasi')
@section('content')
<div class="alarm-grid">
    {{-- =========================
        SUMMARY
    ========================== --}}
    <div class="alarm-summary">
        <div class="alarm-card alarm-total-card">
            <i class="bi bi-bell-fill"></i>
            <div>
                <small>Total Alarm</small>
                <h2>12</h2>
                <p>Keseluruhan alarm</p>
            </div>
        </div>
        <div class="alarm-card alarm-critical-card">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <small>Critical</small>
                <h2>2</h2>
                <p>Perlu penanganan segera</p>
            </div>
        </div>
        <div class="alarm-card alarm-warning-card">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>
                <small>Warning</small>
                <h2>4</h2>
                <p>Perlu diperhatikan</p>
            </div>
        </div>
        <div class="alarm-card alarm-info-card">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <small>Information</small>
                <h2>6</h2>
                <p>Informasi umum</p>
            </div>
        </div>
    </div>
    {{-- Alarm Aktif --}}
    <div class="alarm-card alarm-active-card">
        <div class="alarm-header">
            <h3>Alarm Aktif</h3>
            <div class="alarm-filter">
                <button class="active">Semua</button>
                <button>Critical</button>
                <button>Warning</button>
                <button>Information</button>
            </div>
        </div>
        <table class="alarm-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Prioritas</th>
                    <th>Alarm</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>08:26</td>
                    <td><span class="badge critical">Critical</span></td>
                    <td>Baterai Low &lt;20%</td>
                    <td><span class="status-red">● Active</span></td>
                </tr>
                <tr>
                    <td>08:26</td>
                    <td><span class="badge warning">Warning</span></td>
                    <td>Telemetry Delay</td>
                    <td><span class="status-yellow">● Active</span></td>
                </tr>
                <tr>
                    <td>08:26</td>
                    <td><span class="badge info">Info</span></td>
                    <td>Mission Started</td>
                    <td><span class="status-blue">● Active</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    {{-- Status Sistem --}}
    <div class="alarm-card alarm-system-card">
        <h3>Status Sistem</h3>
        <ul>
            <li>GPS <span class="green">● Online</span></li>
            <li>Compass <span class="green">● Normal</span></li>
            <li>IMU <span class="green">● Normal</span></li>
            <li>Battery <span class="yellow">● Low</span></li>
            <li>Camera <span class="green">● Active</span></li>
            <li>LoRa <span class="green">● Connected</span></li>
            <li>Motor Left <span class="red">● Error</span></li>
            <li>Motor Right <span class="green">● Normal</span></li>
        </ul>
    </div>
    {{-- Riwayat Alarm --}}
    <div class="alarm-card alarm-history-card">
        <h3>Riwayat Alarm</h3>
        <ul>
            <li>08:26 - Battery Low</li>
            <li>08:26 - Telemetry Delay</li>
            <li>08:26 - Mission Started</li>
            <li>08:26 - Motor Left Error</li>
            <li>08:26 - GPS Weak</li>
        </ul>
        <button class="history-btn">
            Lihat Semua
        </button>
    </div>
    {{-- Distribusi Alarm --}}
    <div class="alarm-card alarm-chart-card">
        <h3>Distribusi Alarm</h3>
        <div class="alarm-chart-placeholder">
            Pie Chart
        </div>
    </div>
</div>
@endsection