<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | Laksamana 5</title>
    @vite([
        'resources/css/auth/login.css',
        'resources/js/app.js'
    ])
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            {{-- LOGO --}}
            <div class="login-logo">
                <div class="logo-icon">
                    <i class="bi bi-water"></i>
                </div>
                <h1>LAKSAMANA 5</h1>
                <p>ADMINISTRATOR</p>
            </div>
            {{-- HEADER --}}
            <div class="login-header">
                <h2>Login Admin</h2>
                <p>Silakan masuk untuk mengakses sistem monitoring</p>
            </div>
            {{-- ERROR MESSAGE --}}
            @if(session('error'))
                <div class="login-alert error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="login-alert error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- LOGIN FORM --}}
            <form
                action="{{ route('login.process') }}"
                method="POST"
                class="login-form"
            >
                @csrf

                {{-- EMAIL --}}
                <div class="form-group">
                    <label for="email">
                        Email Admin
                    </label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Masukkan email admin"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                {{-- PASSWORD --}}
                <div class="form-group">
                    <label for="password">
                        Password
                    </label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                            aria-label="Tampilkan password"
                        >
                            <i class="bi bi-eye" id="passwordIcon"></i>
                        </button>
                    </div>
                </div>

                {{-- REMEMBER --}}
                <div class="login-options">
                    <label class="remember">
                        <input
                            type="checkbox"
                            name="remember"
                        >
                        <span>Ingat saya</span>
                    </label>
                </div>

                {{-- BUTTON --}}
                <button
                    type="submit"
                    class="login-button"
                >
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Login Admin</span>
                </button>
            </form>

            {{-- FOOTER --}}
            <div class="login-footer">
                <span>
                    <i class="bi bi-shield-check"></i>
                    Sistem Monitoring ASV
                </span>
                <small>
                    Laksamana 5
                </small>
            </div>
        </div>
    </div>

    {{-- PASSWORD TOGGLE --}}
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>