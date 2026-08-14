<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | Laksamana 5</title>

    {{-- Harus sebelum @vite: mendefinisikan saatEchoSiap() yang dipakai skrip
         inline halaman untuk menunggu window.Echo tanpa balapan. --}}
    @include('partials.echo-ready')

    @vite([
        'resources/css/app.css',
        'resources/css/global.css',
        'resources/css/admin.css',
        'resources/css/admin/dashboard.css',
        'resources/css/admin/monitoring.css',
        'resources/css/admin/camera.css',
        'resources/css/admin/alarm.css',
        'resources/css/admin/settings.css',
        'resources/css/auth/account.css',

        'resources/js/app.js'
    ])
</head>
<body>
<div class="layout">
    @include('partials.admin.sidebar')
    <div class="main-content">
        @include('partials.admin.navbar')
        <div class="content">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>