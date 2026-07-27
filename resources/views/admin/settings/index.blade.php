@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')
<div class="settings-page">
    <div class="settings-card settings-menu-card">
        <div class="settings-header">
            <div>
                <h3>Pengaturan</h3>
                <p>Kelola pengaturan sistem dashboard.</p>
            </div>
            <i class="bi bi-gear"></i>
        </div>
        <a href="{{ route('admin.settings.account') }}" class="settings-menu-item">
            <div class="settings-menu-icon">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="settings-menu-info">
                <strong>Akun</strong>
                <span>
                    Kelola informasi dan keamanan akun administrator.
                </span>
            </div>
            <i class="bi bi-chevron-right settings-menu-arrow"></i>
        </a>
    </div>
</div>
@endsection