<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | Laksamana 5</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

    @include('partials.theme-head')

    {{-- Harus sebelum @vite: mendefinisikan saatEchoSiap() yang dipakai skrip
         inline halaman untuk menunggu window.Echo tanpa balapan. --}}
    @include('partials.echo-ready')

    @vite([
        'resources/css/app.css',
        'resources/css/global.css',
        'resources/css/user.css',
        'resources/css/user/dashboard.css',

        {{-- Gaya kartu data monitoring (.monitor-card, .monitor-info-grid,
             kompas, baterai) hanya ada di berkas ini meski namanya "admin" -
             halaman monitoring user memakai kelas yang sama persis. Tanpa ini
             blok datanya tampil polos tanpa kartu.
             HARUS sebelum user/monitoring.css, karena berkas itu menimpa
             beberapa aturan lintasan khusus tampilan user. --}}
        'resources/css/admin/monitoring.css',
        'resources/css/user/monitoring.css',

        'resources/css/user/camera.css',

        'resources/js/app.js'
    ])
</head>
<body>
    <div class="layout">
        {{-- SIDEBAR USER --}}
        @include('partials.user.sidebar')
        <div class="main-content">
            {{-- NAVBAR USER --}}
            @include('partials.user.navbar')
            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>