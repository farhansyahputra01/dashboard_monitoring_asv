@extends('layouts.admin')
@section('title', 'Edit Profil Pengguna')
@section('content')
<div class="settings-page">
    <div class="settings-breadcrumb">
        <a href="{{ route('admin.settings') }}"><i class="bi bi-arrow-left"></i> Pengaturan</a>
        <span>/</span>
        <a href="{{ route('admin.settings.account') }}">Profil & Akun</a>
        <span>/</span>
        <span>Edit Informasi</span>
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
                <h3><i class="bi bi-person-gear"></i> Edit Informasi Akun</h3>
                <p>Ubah nama tampilan administrator dan alamat email yang terdaftar.</p>
            </div>
            <div class="settings-header-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
        </div>

        <form
            action="{{ route('admin.settings.account.update') }}"
            method="POST"
            class="account-form"
        >
            @csrf

            {{-- NAMA --}}
            <div class="form-group">
                <label for="name">Nama Lengkap Administrator</label>
                <div class="input-wrapper">
                    <i class="bi bi-person"></i>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        placeholder="Masukkan nama lengkap"
                        required
                    >
                </div>
                @error('name')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            {{-- EMAIL --}}
            <div class="form-group">
                <label for="email">Alamat Email Login</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        placeholder="nama@email.com"
                        required
                    >
                </div>
                @error('email')
                    <small class="form-error">{{ $message }}</small>
                @enderror
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
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection