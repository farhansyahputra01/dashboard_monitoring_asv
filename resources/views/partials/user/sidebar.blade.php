<aside class="sidebar">
    <div class="logo">
        <h2>Laksamana 5</h2>
        <span>Politeknik Negeri Bengkalis</span>
    </div>
    <nav class="menu">
        <a href="{{ route('dashboard') }}"
            class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('monitoring') }}"
            class="{{ request()->routeIs('monitoring') ? 'active' : '' }}">
            <i class="bi bi-broadcast"></i>
            <span>Monitoring</span>
        </a>
        <a href="{{ route('camera') }}"
            class="{{ request()->routeIs('camera') ? 'active' : '' }}">
            <i class="bi bi-camera-video"></i>
            <span>Camera</span>
        </a>
    </nav>
</aside>