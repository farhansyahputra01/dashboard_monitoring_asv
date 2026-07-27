@extends('layouts.admin')
@section('title', 'Akun')
@section('content')
<div class="settings-page">
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3>Informasi Akun</h3>
                <p>Informasi administrator yang sedang masuk ke sistem.</p>
            </div>
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="account-info">
            <div class="account-info-item">
                <span>Nama</span>
                <strong>{{ $user->name }}</strong>
            </div>
            <div class="account-info-item">
                <span>Email</span>
                <strong>{{ $user->email }}</strong>
            </div>
            <div class="account-info-item">
                <span>Role</span>
                <strong>{{ ucfirst($user->role) }}</strong>
            </div>
        </div>
    </div>
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3>Edit Pengguna</h3>
                <p>Ubah nama dan email akun administrator.</p>
            </div>
            <i class="bi bi-person-gear"></i>
        </div>
        <a
            href="{{ route('admin.settings.account.edit') }}"
            class="settings-action-btn"
        >
            <i class="bi bi-pencil-square"></i>
            Edit Pengguna
        </a>
    </div>
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3>Ubah Kata Sandi</h3>
                <p>Perbarui kata sandi akun administrator.</p>
            </div>
            <i class="bi bi-shield-lock"></i>
        </div>
        <a
            href="{{ route('admin.settings.account.password') }}"
            class="settings-action-btn"
        >
            <i class="bi bi-key"></i>
            Ubah Kata Sandi
        </a>
    </div>
</div>
@endsection