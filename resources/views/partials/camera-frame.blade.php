{{--
    Penampil stream kamera kapal.

    $url   - URL stream MJPEG dari Raspberry Pi (boleh null)
    $label - nama kamera, dipakai untuk alt text dan judul saat layar penuh

    Atribut data-camera-label dipakai camera-stream.js untuk mengumpulkan
    seluruh kamera di halaman, supaya klik dua kali membuka layar penuh dan
    geser ke samping berpindah kamera.
--}}
@if (!empty($url))
    <img
        src="{{ $url }}"
        alt="{{ $label ?? 'Kamera kapal' }}"
        title="Klik dua kali untuk layar penuh"
        data-stream-url="{{ $url }}"
        data-camera-label="{{ $label ?? 'Kamera kapal' }}"
        data-camera-url="{{ $url }}"
        style="width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in;"
    >
@else
    <div
        class="camera-placeholder"
        data-camera-label="{{ $label ?? 'Kamera kapal' }}"
    >
        <i class="bi bi-camera-video-off"></i>
        <span>Stream belum dikonfigurasi</span>
    </div>
@endif
