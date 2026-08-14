{{--
    Isi galeri foto misi.

    $fotos       - array dari MissionImageMirror: nama, warna, waktu, url
    $tautanSiap  - apakah symlink public/storage sudah dibuat

    Atribut data-camera-label / data-camera-url sengaja dipakai ulang: dengan
    itu camera-stream.js langsung memberi klik-dua-kali untuk layar penuh dan
    geser kiri/kanan untuk berpindah foto, tanpa JavaScript tambahan.
--}}
<div class="gallery">
    <div class="gallery-head">
        <div>
            <h3>Galeri Misi</h3>
            <p>
                Foto diambil otomatis saat kapal mendekati kotak sasaran.
                Klik dua kali untuk layar penuh, geser untuk berpindah.
            </p>
        </div>
        <span class="gallery-count">{{ count($fotos) }} foto</span>
    </div>

    @unless ($tautanSiap)
        <div class="gallery-warn">
            <i class="bi bi-exclamation-triangle"></i>
            <span>
                <strong>public/storage belum ada.</strong>
                Foto tidak akan tampil sampai dijalankan:
                <code>php artisan storage:link</code>
            </span>
        </div>
    @endunless

    @if (count($fotos) === 0)
        <div class="gallery-empty">
            <i class="bi bi-images"></i>
            <strong>Belum ada foto misi</strong>
            <span>
                @if (empty(config('asv.mission_images_path')))
                    Folder sumber belum dikonfigurasi. Isi ASV_MISSION_IMAGES_PATH di .env
                @else
                    Foto akan muncul sendiri di sini begitu kapal menyelesaikan
                    fase imaging.
                @endif
            </span>
        </div>
    @else
        <div class="gallery-grid">
            @foreach ($fotos as $f)
                <figure class="gallery-item">
                    <img
                        src="{{ $f['url'] }}"
                        alt="Foto misi {{ $f['warna'] }} {{ $f['waktu']->format('d M Y H:i:s') }}"
                        loading="lazy"
                        title="Klik dua kali untuk layar penuh"
                        data-camera-label="Kotak {{ ucfirst($f['warna']) }} — {{ $f['waktu']->format('d M Y H:i:s') }}"
                        data-camera-url="{{ $f['url'] }}"
                    >
                    <figcaption>
                        <span class="gallery-tag gallery-tag-{{ $f['warna'] }}">
                            {{ ucfirst($f['warna']) }}
                        </span>
                        <time datetime="{{ $f['waktu']->toIso8601String() }}">
                            {{ $f['waktu']->format('d M Y H:i:s') }}
                        </time>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
</div>
