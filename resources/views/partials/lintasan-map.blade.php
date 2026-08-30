{{--
    Peta LINTASAN: posisi kapal dalam meter di arena lomba 30 x 30 m.

    $jejak       - array posisi awal dari server: [['x'=>..,'y'=>..,'hdg'=>..,'src'=>..], ...]
                   urut dari yang paling lama. Lihat SensorData::recentLintasan().
    $lintasan    - 'A' atau 'B', arena yang sedang aktif.
    $bolehReset  - true hanya di halaman admin (tombol kosongkan jejak).
    $bolehEdit   - true hanya di halaman admin: memunculkan penyunting geometri.

    Menggantikan partials/trajectory-map.blade.php untuk pemantauan lomba.
    Kelas CSS petanya sengaja dipakai ulang (.trajectory*) supaya tata letak
    kartunya persis sama.

    Titik baru masuk realtime lewat siaran SensorDataUpdated - tidak ada
    polling. Geometri arenanya diambil sekali dari /data/lintasan.json, berkas
    yang sama yang disalin ke kapal.
--}}
<div class="lintasan-wrap">

    @if (!empty($bolehEdit))
        {{--
            Mode edit harus dinyalakan dengan sengaja.

            Yang digeser di peta bukan sekadar gambar: koordinat gerbang inilah
            yang dipakai kapal untuk menambatkan posisinya. Peta yang bisa
            tergeser karena salah klik saat lomba berjalan adalah bahaya, jadi
            tombol-tombol penyunting baru muncul setelah Edit Peta ditekan.
        --}}
        <div class="lintasan-tools">
            <button type="button" class="lintasan-btn" data-lintasan-edit>Edit Peta</button>
            <button type="button" class="lintasan-btn" data-lintasan-tambah-gerbang style="display:none">+ Gerbang</button>
            <button type="button" class="lintasan-btn utama" data-lintasan-simpan style="display:none" disabled>Terapkan</button>
            <button type="button" class="lintasan-btn" data-lintasan-batal style="display:none">Batal</button>

        {{--
            Titik acuan GPS punya halamannya sendiri.

            Daftarnya panjang - satu baris untuk tiap gerbang, tiap buoy, tiap
            kotak - dan memaksakannya ke dalam kartu peta membuat keduanya
            sama-sama sempit. Di sini cukup tautannya.
        --}}
        <a class="lintasan-btn" href="{{ route('admin.monitoring.koordinat') }}"
           style="text-decoration:none">Koordinat GPS</a>
        </div>
    @endif

    <div
        class="trajectory"
        data-lintasan-map
        data-lintasan="{{ $lintasan ?? 'A' }}"
        data-geometri="{{ asset('data/lintasan.json') }}"
        data-jejak="{{ json_encode($jejak ?? [], JSON_UNESCAPED_SLASHES) }}"
        @if (!empty($bolehReset)) data-boleh-reset="1" @endif
        @if (!empty($bolehEdit))
            data-boleh-edit="1"
            data-url-simpan="{{ route('admin.monitoring.geometri') }}"
        @endif
    >
        <canvas class="trajectory-canvas"></canvas>

        <div class="trajectory-empty">
            <i class="bi bi-geo-alt"></i>
            <span>Menunggu posisi dari kapal</span>
        </div>

        <div class="trajectory-info">
            <span class="trajectory-scale"></span>
            <span class="trajectory-dist"></span>
        </div>
    </div>

    {{--
        Kemajuan gerbang.

        Angka ini sudah lama ikut terkirim bersama telemetri (kolom
        pair_count) dan dipakai menandai jejak di peta, tapi belum pernah
        ditampilkan sebagai angka. Padahal inilah satu-satunya ukuran
        kemajuan lomba yang terlihat dari darat: peta menunjukkan kapal ada
        di mana, bukan sudah sampai gerbang ke berapa.

        Disembunyikan selama geometri belum terbaca - "0/0" hanya akan
        membuat operator mengira kapalnya belum jalan.
    --}}
    <div class="lintasan-progres" data-lintasan-progres hidden>
        <div class="lintasan-progres-atas">
            <span class="lintasan-progres-judul">Gerbang terlewati</span>
            <strong data-progres-angka>0/0</strong>
        </div>

        <div class="lintasan-progres-pip" data-progres-pip></div>

        <div class="lintasan-progres-fase" data-progres-fase></div>
    </div>
</div>
