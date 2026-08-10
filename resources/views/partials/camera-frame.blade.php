{{--
    Penampil stream kamera kapal.

    $url   - URL stream MJPEG dari Raspberry Pi (boleh null)
    $label - nama kamera, dipakai untuk alt text
--}}
@if (!empty($url))
    <img
        src="{{ $url }}"
        alt="{{ $label ?? 'Kamera kapal' }}"
        data-stream-url="{{ $url }}"
        style="width:100%;height:100%;object-fit:cover;display:block;"
    >
@else
    <div class="camera-placeholder">
        <i class="bi bi-camera-video-off"></i>
        <span>Stream belum dikonfigurasi</span>
    </div>
@endif
