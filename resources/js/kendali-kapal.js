/**
 * Tombol kendali kapal: BERHENTI DARURAT dan MULAI.
 *
 * Satu tombol, dua keadaan - bukan dua tombol terpisah. Dua kontrol yang
 * sama-sama berarti "jalan" adalah cara termudah membuat operator menekan
 * yang salah saat panik, dan saat panik itulah tombol ini paling dibutuhkan.
 *
 * Laravel hanya meneruskan perintah ke program Python di kapal - dialah
 * pemilik port serial ESP32. Kegagalan TIDAK BOLEH senyap: kalau perintah
 * tidak sampai, operator harus tahu kapal kemungkinan masih berjalan.
 *
 * Dipakai di halaman Dashboard dan Monitoring lewat
 * partials/kendali-kapal.blade.php.
 */

// Keadaan kapal diperiksa ulang berkala supaya tombol tidak berbohong kalau
// keadaannya berubah dari tempat lain (halaman kedua, tombol fisik, atau
// program kapal yang baru dinyalakan ulang).
const JEDA_PERIKSA_MS = 5000;

// Selagi arena kapal belum sama dengan pilihan dashboard, kapal sedang dalam
// perjalanan menyesuaikan diri - dan itu berlangsung hitungan detik. Menunggu
// 5 detik penuh untuk memeriksanya membuat keadaan sementara itu terasa
// seperti jalan buntu. Diperiksa lebih rapat sampai selesai.
const JEDA_MENYESUAIKAN_MS = 1500;


function buatKendali(el) {
    const tombol = el.querySelector('[data-kendali-tombol]');
<<<<<<< HEAD
    const tombolRth = el.querySelector('[data-kendali-rth]');
=======
    const tombolPulang = el.querySelector('[data-kendali-pulang]');
>>>>>>> fc27ed5b06fe9959b2cb750ee245fd9ff196f6e8
    const pesan = el.querySelector('[data-kendali-pesan]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!tombol) return;

    let berhenti = null;      // null = belum diketahui
    let pulang = false;       // kapal sedang kembali ke start
    let terjangkau = null;
    let arenaCocok = true;    // arena kapal vs arena yang dipilih operator
    let arenaKapal = null;
    let arenaDipilih = null;

    function gambarPulang() {
        if (!tombolPulang) return;

        // Tampil hanya kalau kendali terjangkau dan kapal SEDANG BERJALAN.
        // Kapal yang berhenti tidak akan bergerak pulang (berhenti menang),
        // jadi tombolnya hanya membingungkan di keadaan itu.
        const tampil = terjangkau === true && berhenti === false;
        tombolPulang.hidden = !tampil;
        tombolPulang.disabled = !tampil;
        tombolPulang.classList.toggle('is-pulang', pulang === true);
        tombolPulang.textContent = pulang ? 'Sedang PULANG...' : 'PULANG ke Start';
    }

    function gambar() {
        gambarPulang();

        if (terjangkau === false) {
            tombol.textContent = 'Kendali tidak terjangkau';
            tombol.disabled = true;
            tombol.classList.remove('is-stopped');
            tombol.classList.add('is-offline');
            if (tombolRth) tombolRth.style.display = 'none';
            return;
        }

        tombol.disabled = false;
        tombol.classList.remove('is-offline');
        if (tombolRth) {
            tombolRth.style.display = 'block';
            // Nonaktifkan RTH jika belum siap (misal kapal masih menyesuaikan lintasan)
            tombolRth.disabled = (berhenti && !arenaCocok);
        }

        if (berhenti === null) {
            tombol.textContent = 'Memeriksa keadaan kapal...';
            return;
        }

        // Teks tombol menyatakan APA YANG AKAN TERJADI kalau ditekan, bukan
        // keadaan sekarang. Tombol berlabel "BERHENTI" yang ternyata
        // menjalankan kapal adalah kesalahan yang tidak bisa dimaafkan.
        tombol.textContent = berhenti ? 'MULAI - Jalankan Kemudi' : 'BERHENTI DARURAT';
        tombol.classList.toggle('is-stopped', berhenti === true);

        // Arena belum cocok -> MULAI dikunci. BERHENTI tidak pernah dikunci:
        // apa pun keadaannya, menghentikan kapal harus selalu bisa.
        //
        // Teksnya TIDAK boleh menyuruh "tentukan lintasan dulu": operator baru
        // saja menekan Simpan, dan kapal memang sedang berpindah. Menyuruh
        // mengulangi hal yang baru dilakukan membuat keadaan sementara ini
        // terbaca sebagai jalan buntu.
        if (berhenti && !arenaCocok) {
            tombol.disabled = true;
            tombol.classList.add('is-offline');
            tombol.textContent = 'Menyesuaikan lintasan...';
        }
    }

    function tulis(teks, galat) {
        if (!pesan) return;
        pesan.textContent = teks || '';
        pesan.classList.toggle('is-error', !!galat);
    }

    async function kirim(url) {
        tombol.disabled = true;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                tulis(data.message || 'Perintah gagal.', true);
                return;
            }

            berhenti = data.stopped;
            pulang = data.pulang === true;
            terjangkau = true;
            tulis(data.message, false);
        } catch (e) {
            tulis('PERINTAH TIDAK SAMPAI. Kapal kemungkinan masih berjalan.', true);
        } finally {
            gambar();
        }
    }

    async function periksa() {
        try {
            const res = await fetch(el.dataset.statusUrl, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            terjangkau = !!data.reachable;
            berhenti = data.reachable ? data.stopped : null;
            pulang = data.reachable && data.pulang === true;

            arenaKapal = data.lintasan_kapal ?? null;
            arenaDipilih = data.lintasan_dipilih ?? null;
            arenaCocok = data.lintasan_cocok !== false;

            if (!data.reachable) {
                tulis('Kendali kapal tidak terjangkau - program di kapal belum jalan?', true);
            } else if (!arenaCocok && berhenti) {
                // Kapal berhenti -> ia akan mengikuti sendiri. Ini keterangan
                // proses, BUKAN galat, jadi tidak diwarnai merah.
                tulis(
                    `Kapal sedang berpindah ke Lintasan ${arenaDipilih} `
                    + `(sekarang masih di ${arenaKapal}). Tombol MULAI terbuka `
                    + 'sendiri begitu selesai - beberapa detik saja.',
                    false
                );
            } else if (!arenaCocok) {
                // Kapal BERJALAN -> ia sengaja tidak ikut berpindah, karena
                // mengganti kerangka koordinat di tengah lomba akan membuat
                // posisinya melompat. Di sini operator memang harus bertindak.
                tulis(
                    `Kapal berjalan di Lintasan ${arenaKapal}, dashboard menunjuk `
                    + `Lintasan ${arenaDipilih}. Tekan BERHENTI DARURAT dulu - `
                    + 'kapal baru mengikuti pilihan baru setelah motornya berhenti.',
                    true
                );
            } else if (berhenti) {
                tulis('Kapal dalam keadaan BERHENTI. Tekan MULAI kalau sudah siap.', false);
            } else if (pulang) {
                tulis('Kapal sedang PULANG ke titik start lewat jejaknya. '
                    + 'BERHENTI DARURAT tetap berlaku; MULAI membatalkan pulang.', false);
            } else {
                tulis('Kemudi otomatis sedang berjalan.', false);
            }
        } catch (e) {
            terjangkau = false;
            tulis('Kendali kapal tidak terjangkau.', true);
        }

        gambar();
    }

    tombol.addEventListener('click', () => {
        // Menjalankan kapal butuh satu langkah sadar lagi. Menghentikan tidak -
        // tombol berhenti harus selalu bisa ditekan tanpa hambatan apa pun.
        if (berhenti && !confirm('Jalankan kemudi otomatis? Pastikan kapal sudah di air dan area aman.')) {
            return;
        }

        kirim(berhenti ? el.dataset.resumeUrl : el.dataset.stopUrl);
    });

<<<<<<< HEAD
    if (tombolRth) {
        tombolRth.addEventListener('click', () => {
            if (!confirm('Perintahkan kapal untuk kembali ke titik awal (Return to Home)?')) {
                return;
            }
            kirim(el.dataset.rthUrl);
=======
    if (tombolPulang) {
        tombolPulang.addEventListener('click', () => {
            if (pulang) return;
            if (!confirm('Kapal akan meninggalkan misi dan kembali ke titik start lewat jejaknya. Lanjutkan?')) {
                return;
            }
            kirim(el.dataset.pulangUrl);
>>>>>>> fc27ed5b06fe9959b2cb750ee245fd9ff196f6e8
        });
    }

    // Rantai setTimeout, bukan setInterval: jedanya ikut berubah mengikuti
    // keadaan. Saat sedang menyesuaikan lintasan, pemeriksaan dirapatkan
    // supaya tombol terbuka begitu kapal selesai berpindah.
    function jadwalkan() {
        const jeda = arenaCocok ? JEDA_PERIKSA_MS : JEDA_MENYESUAIKAN_MS;
        setTimeout(() => periksa().finally(jadwalkan), jeda);
    }

    gambar();
    periksa().finally(jadwalkan);
}


document.querySelectorAll('[data-kendali-kapal]').forEach(buatKendali);
