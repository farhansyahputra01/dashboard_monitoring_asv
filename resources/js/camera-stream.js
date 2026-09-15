/**
 * Kamera kapal: sambung ulang otomatis + penampil layar penuh.
 *
 * 1. Stream MJPEG adalah satu respons HTTP yang tidak pernah selesai. Kalau
 *    kapal kehilangan koneksi atau Raspberry Pi restart, <img> memicu event
 *    'error' dan gambarnya berhenti selamanya - browser tidak menyambung ulang
 *    dengan sendirinya.
 *
 * 2. Klik dua kali pada kamera membuka layar penuh. Di dalamnya, geser ke
 *    kanan/kiri (sentuh atau seret tetikus) berpindah antar kamera, sama
 *    seperti panah kiri/kanan di papan ketik.
 *
 * 3. Dua cara menerima gambar, dipilih otomatis dari host halaman:
 *
 *    MJPEG (dorong)  - kapal mendorong frame secepat ia menghasilkannya.
 *                      Bagus di LAN. Lewat link yang lebih lambat dari laju
 *                      frame (ngrok), frame yang belum terkirim menumpuk di
 *                      buffer jaringan: tampilan tertinggal makin lama makin
 *                      jauh, lalu macet. Server tidak bisa menarik kembali
 *                      byte yang sudah masuk buffer.
 *    Foto (tarik)    - browser meminta SATU frame (/stream/foto/<kamera>),
 *                      dan baru meminta lagi setelah frame itu sampai.
 *                      Lajunya mengikuti kecepatan link dengan sendirinya;
 *                      tumpukan paling banyak satu frame, jadi tidak pernah
 *                      "makin tertinggal". Di ngrok inilah yang dipakai.
 *
 *    Paksa lewat ?stream=foto atau ?stream=mjpeg di URL halaman.
 */

const RETRY_MS = 3000;

// Jeda antar permintaan pada mode foto, dihitung SESUDAH frame sebelumnya
// sampai. Di LAN ini membatasi ke ~10 fps; lewat ngrok RTT-nya yang
// menentukan, bukan angka ini.
const FOTO_JEDA_MS = 80;

/* ------------------------------------------------------------------ */
/* Pilihan mode                                                        */
/* ------------------------------------------------------------------ */

function pilihMode() {
    const minta = new URLSearchParams(window.location.search).get('stream');
    if (minta === 'foto' || minta === 'mjpeg') {
        return minta;
    }
    return /ngrok/i.test(window.location.hostname) ? 'foto' : 'mjpeg';
}

const MODE = pilihMode();

/**
 * /stream/atas -> /stream/foto/atas. Null kalau URL-nya bukan bentuk itu
 * (misal stream dari alat lain): pemanggil lalu kembali ke MJPEG.
 */
function urlFoto(url) {
    const hasil = url.replace(/\/stream\/([^/?#]+)(?=[?#]|$)/, '/stream/foto/$1');
    return hasil === url ? null : hasil;
}

function tambahCacheBuster(url, kunci) {
    const pemisah = url.includes('?') ? '&' : '?';
    return `${url}${pemisah}${kunci}=${Date.now()}`;
}

/* ------------------------------------------------------------------ */
/* Mode foto: minta satu frame, tunggu sampai, minta lagi              */
/* ------------------------------------------------------------------ */

function pasangTarik(img, url) {
    let timer = null;

    const minta = () => {
        timer = null;
        // <img> yang sudah dilepas dari halaman (penampil layar penuh
        // ditutup) berhenti sendiri - tidak ada yang perlu membatalkan.
        if (!img.isConnected) {
            return;
        }
        img.src = tambahCacheBuster(url, '_t');
    };

    img.addEventListener('load', () => {
        img.classList.remove('camera-offline');
        if (!timer) {
            timer = setTimeout(minta, FOTO_JEDA_MS);
        }
    });

    img.addEventListener('error', () => {
        img.classList.add('camera-offline');
        if (!timer) {
            timer = setTimeout(minta, RETRY_MS);
        }
    });

    minta();
}

/* ------------------------------------------------------------------ */
/* Sambung ulang stream yang putus                                     */
/* ------------------------------------------------------------------ */

function pasangSambungUlang(img) {
    const baseUrl = img.dataset.streamUrl;

    if (MODE === 'foto') {
        const foto = urlFoto(baseUrl);
        if (foto) {
            pasangTarik(img, foto);
            return;
        }
    }

    let pending = null;

    const reconnect = () => {
        if (pending) {
            return;
        }

        img.classList.add('camera-offline');

        pending = setTimeout(() => {
            pending = null;
            const separator = baseUrl.includes('?') ? '&' : '?';
            img.src = `${baseUrl}${separator}_ts=${Date.now()}`;
        }, RETRY_MS);
    };

    img.addEventListener('error', reconnect);
    img.addEventListener('load', () => img.classList.remove('camera-offline'));
}

/* ------------------------------------------------------------------ */
/* Penampil layar penuh                                                */
/* ------------------------------------------------------------------ */

function buatPenampil(daftarKamera) {
    let indeks = 0;
    let terbuka = false;

    const overlay = document.createElement('div');
    overlay.className = 'camera-viewer';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.hidden = true;

    overlay.innerHTML = `
        <div class="camera-viewer-stage"></div>
        <div class="camera-viewer-bar">
            <button class="camera-viewer-nav" data-arah="-1" aria-label="Kamera sebelumnya">&#8249;</button>
            <div class="camera-viewer-meta">
                <span class="camera-viewer-label"></span>
                <span class="camera-viewer-dots"></span>
            </div>
            <button class="camera-viewer-nav" data-arah="1" aria-label="Kamera berikutnya">&#8250;</button>
        </div>
        <button class="camera-viewer-close" aria-label="Tutup layar penuh">&times;</button>
    `;

    document.body.appendChild(overlay);

    const stage = overlay.querySelector('.camera-viewer-stage');
    const label = overlay.querySelector('.camera-viewer-label');
    const dots = overlay.querySelector('.camera-viewer-dots');

    function tampilkan() {
        const kamera = daftarKamera[indeks];

        // Kosongkan dulu. Pada MJPEG ini penting: mengganti src tanpa melepas
        // <img> lama meninggalkan koneksi HTTP yang menggantung ke Flask.
        stage.replaceChildren();

        if (kamera.url) {
            const img = document.createElement('img');
            img.className = 'camera-viewer-img';
            img.alt = kamera.label;
            stage.appendChild(img);

            // Foto galeri (bukan stream) selalu dimuat biasa; hanya stream
            // kapal yang ikut mode foto/MJPEG.
            const foto = kamera.stream && MODE === 'foto'
                ? urlFoto(kamera.url)
                : null;

            if (foto) {
                pasangTarik(img, foto);
            } else {
                img.src = tambahCacheBuster(kamera.url, '_fs');
            }
        } else {
            const kosong = document.createElement('div');
            kosong.className = 'camera-placeholder';
            kosong.innerHTML =
                '<i class="bi bi-camera-video-off"></i><span>Stream belum dikonfigurasi</span>';
            stage.appendChild(kosong);
        }

        label.textContent = kamera.label;
        dots.textContent = daftarKamera.length > 1
            ? `${indeks + 1} / ${daftarKamera.length}`
            : '';

        overlay.querySelectorAll('.camera-viewer-nav').forEach((b) => {
            b.hidden = daftarKamera.length < 2;
        });
    }

    function pindah(arah) {
        if (daftarKamera.length < 2) {
            return;
        }
        indeks = (indeks + arah + daftarKamera.length) % daftarKamera.length;
        tampilkan();
    }

    function buka(dariIndeks) {
        indeks = dariIndeks;
        terbuka = true;
        overlay.hidden = false;
        tampilkan();

        // Layar penuh sungguhan kalau diizinkan; kalau ditolak, overlay tetap
        // menutupi layar lewat CSS position:fixed.
        if (overlay.requestFullscreen) {
            overlay.requestFullscreen().catch(() => {});
        }
    }

    function tutup() {
        terbuka = false;
        overlay.hidden = true;

        // Lepaskan <img> supaya koneksi MJPEG tambahan benar-benar ditutup.
        stage.replaceChildren();

        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
    }

    /* --- kendali --- */

    overlay.querySelector('.camera-viewer-close').addEventListener('click', tutup);

    overlay.querySelectorAll('.camera-viewer-nav').forEach((tombol) => {
        tombol.addEventListener('click', (e) => {
            e.stopPropagation();
            pindah(Number(tombol.dataset.arah));
        });
    });

    overlay.addEventListener('dblclick', (e) => {
        if (e.target.closest('.camera-viewer-nav, .camera-viewer-close')) {
            return;
        }
        tutup();
    });

    document.addEventListener('keydown', (e) => {
        if (!terbuka) {
            return;
        }
        if (e.key === 'ArrowRight') pindah(1);
        else if (e.key === 'ArrowLeft') pindah(-1);
        else if (e.key === 'Escape') tutup();
    });

    // Keluar dari layar penuh lewat Esc bawaan browser -> tutup overlay juga,
    // supaya tidak tertinggal overlay yang menutupi halaman.
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && terbuka) {
            tutup();
        }
    });

    /* --- geser: sentuh dan seret tetikus --- */

    const AMBANG_GESER = 60;   // px; di bawah ini dianggap sentuhan biasa
    let mulaiX = null;
    let mulaiY = null;

    function mulai(x, y) {
        mulaiX = x;
        mulaiY = y;
    }

    function selesai(x, y) {
        if (mulaiX === null) {
            return;
        }

        const dx = x - mulaiX;
        const dy = y - mulaiY;
        mulaiX = null;

        // Abaikan kalau gerakannya lebih vertikal - itu gulir, bukan geser.
        if (Math.abs(dx) < AMBANG_GESER || Math.abs(dx) < Math.abs(dy)) {
            return;
        }

        // Geser ke KIRI (dx negatif) = maju ke kamera berikutnya, mengikuti
        // kebiasaan galeri foto. Geser ke KANAN = kembali.
        pindah(dx < 0 ? 1 : -1);
    }

    overlay.addEventListener('touchstart', (e) => {
        mulai(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
    }, { passive: true });

    overlay.addEventListener('touchend', (e) => {
        selesai(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
    }, { passive: true });

    overlay.addEventListener('mousedown', (e) => mulai(e.clientX, e.clientY));
    overlay.addEventListener('mouseup', (e) => selesai(e.clientX, e.clientY));

    return { buka };
}

/* ------------------------------------------------------------------ */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('img[data-stream-url]').forEach(pasangSambungUlang);

    const elemen = Array.from(document.querySelectorAll('[data-camera-label]'));

    if (elemen.length === 0) {
        return;
    }

    const daftarKamera = elemen.map((el) => ({
        label: el.dataset.cameraLabel,
        url: el.dataset.cameraUrl || null,
        stream: Boolean(el.dataset.streamUrl),
    }));

    const penampil = buatPenampil(daftarKamera);

    elemen.forEach((el, i) => {
        el.addEventListener('dblclick', () => penampil.buka(i));
    });
});
