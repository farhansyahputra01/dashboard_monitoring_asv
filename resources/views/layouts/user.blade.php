<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Laksamana 5</title>
    @vite([
        'resources/css/app.css',
        'resources/css/global.css',
        'resources/css/user.css',
        'resources/css/user/dashboard.css',
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