@extends('layouts.admin')
@section('title', 'Ubah Kata Sandi')
@section('content')
<div class="settings-page">
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3>Ubah Kata Sandi</h3>
                <p>Perbarui kata sandi akun administrator.</p>
            </div>
            <i class="bi bi-shield-lock"></i>
        </div>
        <form
            action="{{ route('admin.settings.account.password.update') }}"
            method="POST"
            class="account-form"
        >
            @csrf
            {{-- PASSWORD SAAT INI --}}
            <div class="form-group">
                <label for="current_password">
                    Kata Sandi Saat Ini
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-lock"></i>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Masukkan kata sandi saat ini"
                        required
                    >
                </div>
                @error('current_password')
                    <small class="form-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>
            {{-- PASSWORD BARU --}}
            <div class="form-group">
                <label for="password">
                    Kata Sandi Baru
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-key"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Masukkan kata sandi baru"
                        required
                    >
                </div>
                @error('password')
                    <small class="form-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>
            {{-- KONFIRMASI PASSWORD --}}
            <div class="form-group">
                <label for="password_confirmation">
                    Konfirmasi Kata Sandi Baru
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-key-fill"></i>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Konfirmasi kata sandi baru"
                        required
                    >
                </div>
            </div>
            {{-- ACTION --}}
            <div class="settings-form-actions">
                <a
                    href="{{ route('admin.settings.account') }}"
                    class="settings-cancel-btn"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
                <button
                    type="submit"
                    class="settings-save-btn"
                >
                    <i class="bi bi-check-lg"></i>
                    Ubah Kata Sandi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection