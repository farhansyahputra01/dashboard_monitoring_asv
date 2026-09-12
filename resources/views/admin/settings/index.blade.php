@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')
<div class="settings-page">
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3><i class="bi bi-gear-wide-connected"></i> Pengaturan Sistem</h3>
                <p>Kelola informasi akun, keamanan, dan konfigurasi dashboard telemetri.</p>
            </div>
            <div class="settings-header-icon">
                <i class="bi bi-sliders"></i>
            </div>
        </div>
        <div class="settings-menu-list">
            <a href="{{ route('admin.settings.account') }}" class="settings-menu-item">
                <div class="settings-menu-icon">
                    <i class="bi bi-person-gear"></i>
                </div>
                <div class="settings-menu-info">
                    <strong>Profil & Akun</strong>
                    <span>Kelola informasi profil, email, dan kata sandi administrator.</span>
                </div>
                <i class="bi bi-chevron-right settings-menu-arrow"></i>
            </a>
            <a href="{{ route('admin.monitoring.koordinat') }}" class="settings-menu-item">
                <div class="settings-menu-icon" style="background: var(--accent-blue-soft); color: var(--accent-blue);">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="settings-menu-info">
                    <strong>Koordinat Lintasan</strong>
                    <span>Konfigurasi titik koordinat waypoint lintasan arena perlombaan.</span>
                </div>
                <i class="bi bi-chevron-right settings-menu-arrow"></i>
            </a>
        </div>
    </div>
</div>
@endsection