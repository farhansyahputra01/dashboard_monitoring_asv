@extends('layouts.admin')
@section('title', 'Ubah Kata Sandi')
@section('content')
<div class="settings-page">
    <div class="settings-breadcrumb">
        <a href="{{ route('admin.settings') }}"><i class="bi bi-arrow-left"></i> Pengaturan</a>
        <span>/</span>
        <a href="{{ route('admin.settings.account') }}">Profil & Akun</a>
        <span>/</span>
        <span>Ubah Kata Sandi</span>
    </div>

    @if(session('success'))
        <div class="settings-alert settings-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3><i class="bi bi-shield-lock"></i> Ubah Kata Sandi Akun</h3>
                <p>Pastikan kata sandi baru Anda kuat dan sulit ditebak demi keamanan sistem.</p>
            </div>
            <div class="settings-header-icon">
                <i class="bi bi-key"></i>
            </div>
        </div>

        <form
            action="{{ route('admin.settings.account.password.update') }}"
            method="POST"
            class="account-form"
        >
            @csrf

            {{-- PASSWORD SAAT INI --}}
            <div class="form-group">
                <label for="current_password">Kata Sandi Saat Ini</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock"></i>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Masukkan kata sandi lama Anda"
                        required
                    >
                </div>
                @error('current_password')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            {{-- PASSWORD BARU --}}
            <div class="form-group">
                <label for="password">Kata Sandi Baru</label>
                <div class="input-wrapper">
                    <i class="bi bi-key"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimal 8 karakter kombinasi huruf & angka"
                        required
                    >
                </div>
                @error('password')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            {{-- KONFIRMASI PASSWORD --}}
            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                <div class="input-wrapper">
                    <i class="bi bi-key-fill"></i>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Ulangi kata sandi baru"
                        required
                    >
                </div>
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="settings-form-actions">
                <a
                    href="{{ route('admin.settings.account') }}"
                    class="settings-cancel-btn"
                >
                    <i class="bi bi-arrow-left"></i>
                    Batal
                </a>
                <button
                    type="submit"
                    class="settings-save-btn"
                >
                    <i class="bi bi-check-lg"></i>
                    Perbarui Kata Sandi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection