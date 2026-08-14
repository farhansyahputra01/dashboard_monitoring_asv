{{--
    Peta jejak kapal.

    $track - array titik awal dari server: [['lat'=>..,'lng'=>..,'hdg'=>..], ...]
             urut dari yang paling lama.

    $bolehReset - true hanya di halaman admin. Tombol RESET mengosongkan jejak
             di peta, jadi tidak diberikan ke halaman user: penonton tidak
             boleh bisa menghapus tampilan lintasan yang sedang berjalan.

    Titik baru ditambahkan realtime oleh trajectory-map.js lewat siaran
    SensorDataUpdated - tidak ada polling.
--}}
<div
    class="trajectory"
    data-trajectory
    data-track="{{ json_encode($track ?? [], JSON_UNESCAPED_SLASHES) }}"
    @if (!empty($bolehReset)) data-boleh-reset="1" @endif
>
    <canvas class="trajectory-canvas"></canvas>

    <div class="trajectory-empty">
        <i class="bi bi-geo-alt"></i>
        <span>Menunggu sinyal GPS</span>
    </div>

    <div class="trajectory-info">
        <span class="trajectory-scale"></span>
        <span class="trajectory-dist"></span>
    </div>
</div>
