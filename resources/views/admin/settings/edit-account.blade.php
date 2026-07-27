@extends('layouts.admin')
@section('title', 'Edit Pengguna')
@section('content')
<div class="settings-page">
    <div class="settings-card">
        <div class="settings-header">
            <div>
                <h3>Edit Pengguna</h3>
                <p>Ubah informasi akun administrator.</p>
            </div>
            <i class="bi bi-person-gear"></i>
        </div>
        <form
            action="{{ route('admin.settings.account.update') }}"
            method="POST"
            class="account-form"
        >
            @csrf
            {{-- NAMA --}}
            <div class="form-group">
                <label for="name">
                    Nama
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-person"></i>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        required
                    >
                </div>
                @error('name')
                    <small class="form-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>
            {{-- EMAIL --}}
            <div class="form-group">
                <label for="email">
                    Email
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        required
                    >
                </div>
                @error('email')
                    <small class="form-error">
                        {{ $message }}
                    </small>
                @enderror
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
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection