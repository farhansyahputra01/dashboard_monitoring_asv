@extends('layouts.admin')
@section('title', 'Profil & Akun')
@section('content')
<div class="settings-page">
    <div class="settings-breadcrumb">
        <a href="{{ route('admin.settings') }}"><i class="bi bi-arrow-left"></i> Pengaturan</a>
        <span>/</span>
        <span>Profil & Akun</span>
    </div>

    @if(session('success'))
        <div class="settings-alert settings-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="settings-alert settings-alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- KARTU PROFIL UTAMA --}}
    <div class="settings-card">
        <div class="profile-hero">
            <div class="profile-avatar">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="profile-details">
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
                <div class="profile-badge">
                    <i class="bi bi-shield-check"></i>
                    <span>{{ ucfirst($user->role ?? 'admin') }}</span>
                </div>
            </div>
        </div>

        <div class="account-info-grid">
            <div class="account-info-item">
                <span>Nama Pengguna</span>
                <strong>{{ $user->name }}</strong>
            </div>
            <div class="account-info-item">
                <span>Alamat Email</span>
                <strong>{{ $user->email }}</strong>
            </div>
            <div class="account-info-item">
                <span>Hak Akses</span>
                <strong>{{ ucfirst($user->role ?? 'Administrator') }}</strong>
            </div>
        </div>
    </div>

    {{-- KARTU AKSI PENGATURAN --}}
    <div class="account-actions-grid">
        <div class="settings-card">
            <div class="settings-header">
                <div>
                    <h3>Edit Data Akun</h3>
                    <p>Perbarui nama tampilan dan alamat email login.</p>
                </div>
                <div class="settings-header-icon">
                    <i class="bi bi-person-gear"></i>
                </div>
            </div>
            <a href="{{ route('admin.settings.account.edit') }}" class="settings-action-btn">
                <i class="bi bi-pencil-square"></i>
                Edit Informasi
            </a>
        </div>

        <div class="settings-card">
            <div class="settings-header">
                <div>
                    <h3>Keamanan & Sandi</h3>
                    <p>Ganti kata sandi akun secara berkala untuk keamanan.</p>
                </div>
                <div class="settings-header-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
            </div>
            <a href="{{ route('admin.settings.account.password') }}" class="settings-action-btn btn-security">
                <i class="bi bi-key-fill"></i>
                Ubah Kata Sandi
            </a>
        </div>
    </div>
</div>
@endsection