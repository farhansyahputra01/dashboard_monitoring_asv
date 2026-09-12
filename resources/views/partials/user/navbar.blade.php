<header class="navbar">
    <div class="navbar-left">
        <h2>@yield('title')</h2>
    </div>
    <div class="navbar-right">
        <button type="button" class="theme-toggle-btn" data-theme-toggle aria-label="Ganti Tema" title="Ganti Mode Terang / Gelap">
            <i class="bi bi-sun-fill"></i>
            <span class="theme-label">Terang</span>
        </button>
        <div class="ship-status">
            <span class="status-dot"></span>
            <span>Aktif</span>
        </div>
        <div class="datetime">
            <span id="current-date">
                {{ now()->translatedFormat('d F Y') }}
            </span>
            <span id="current-time"></span>
        </div>
    </div>
</header>
<script>

function updateClock(){
    const now = new Date();
    document.getElementById("current-time").innerHTML =
        now.toLocaleTimeString('id-ID');
}
updateClock();
setInterval(updateClock,1000);
</script>