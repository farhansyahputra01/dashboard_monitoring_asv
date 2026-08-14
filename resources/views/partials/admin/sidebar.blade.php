<aside class="sidebar">
    <div class="logo">
        <h2>Laksamana 5</h2>
        <span>Politeknik Negeri Bengkalis</span>
    </div>
    <nav class="menu">
        <a href="{{ route('admin.dashboard') }}"
            class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('admin.monitoring') }}"
            class="{{ request()->routeIs('admin.monitoring') ? 'active' : '' }}">
            <i class="bi bi-broadcast"></i>
            <span>Monitoring</span>
        </a>
        <a href="{{ route('admin.camera') }}"
            class="{{ request()->routeIs('admin.camera') ? 'active' : '' }}">
            <i class="bi bi-camera-video"></i>
            <span>Camera</span>
        </a>
        <a href="{{ route('admin.galeri') }}"
            class="{{ request()->routeIs('admin.galeri') ? 'active' : '' }}">
            <i class="bi bi-images"></i>
            <span>Galeri Misi</span>
        </a>
        <a href="{{ route('admin.alarm') }}"
            class="{{ request()->routeIs('admin.alarm') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            <span>Alarm</span>
        </a>
        <a href="{{ route('admin.settings') }}"
            class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <form action="{{ route('logout') }}" method="POST">
            @csrf

            <button type="submit">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>