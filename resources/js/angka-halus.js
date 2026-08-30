/**
 * Angka sensor yang bergerak halus, bukan melompat.
 *
 * Telemetri datang SATU KALI PER DETIK. Jadi saat kapal berputar 60 derajat
 * per detik, tiap pembaruan menggeser angka haluan sejauh 60 derajat sekaligus -
 * di layar itu terbaca sebagai lompatan, padahal kapalnya berputar mulus.
 * Yang diperbaiki di sini adalah CARA MENAMPILKAN, bukan datanya.
 *
 * DUA HAL YANG SENGAJA DIJAGA
 *
 * 1. Angka yang ditampilkan SELALU menuju nilai yang benar-benar dikirim
 *    kapal, dan sampai di sana sebelum pembacaan berikutnya tiba. Ia tidak
 *    pernah mengarang nilai yang tidak ada - hanya mengisi jeda antar
 *    pembacaan. Kalau telemetri berhenti, angkanya berhenti juga.
 *
 * 2. Lamanya animasi lebih pendek dari jarak antar pembacaan (800 ms < 1 s).
 *    Animasi yang lebih panjang akan membuat tampilan tertinggal terus-menerus
 *    dari keadaan kapal - halus tapi berbohong.
 *
 * SUDUT TIDAK BISA DIPERLAKUKAN SEPERTI ANGKA BIASA
 *
 * Haluan 350 derajat lalu 10 derajat adalah perputaran 20 derajat ke kanan,
 * bukan 340 derajat ke kiri. Diinterpolasi begitu saja, jarum kompas akan
 * berputar hampir satu lingkaran penuh ke arah yang salah setiap kali melewati
 * utara. Karena itu ada mode 'putar' yang selalu mengambil busur terpendek.
 */

// Sedikit lebih pendek dari jarak antar telemetri (1 detik), supaya angkanya
// sudah tenang tepat sebelum pembacaan berikutnya datang.
const DURASI_MS = 800;


function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}


/**
 * Selisih sudut terpendek dari a ke b, hasilnya -180..180.
 */
function selisihSudut(a, b) {
    return ((b - a + 540) % 360) - 180;
}


function jalankan(el, target, opsi) {
    const {
        desimal = 0,
        satuan = '',
        durasi = DURASI_MS,
        putar = false,
        saat = null,
    } = opsi || {};

    if (!el) return;

    const keadaan = el.__halus || (el.__halus = { nilai: target, raf: 0 });

    // Tab yang tidak terlihat tidak menjalankan requestAnimationFrame sama
    // sekali. Tanpa jalan pintas ini, angkanya membeku di nilai lama sampai
    // tab dibuka lagi - persis kebalikan dari yang diinginkan.
    if (document.hidden) {
        keadaan.nilai = target;
        el.textContent = target.toFixed(desimal) + satuan;
        if (saat) saat(target);
        return;
    }

    const dari = keadaan.nilai;
    const jarak = putar ? selisihSudut(dari, target) : target - dari;

    if (keadaan.raf) cancelAnimationFrame(keadaan.raf);

    // Perubahan yang tak terlihat tidak perlu dianimasikan.
    if (Math.abs(jarak) < Math.pow(10, -desimal) / 2) {
        keadaan.nilai = target;
        el.textContent = target.toFixed(desimal) + satuan;
        if (saat) saat(target);
        return;
    }

    const mulai = performance.now();

    function langkah(sekarang) {
        const t = Math.min(1, (sekarang - mulai) / durasi);
        let nilai = dari + jarak * easeOutCubic(t);

        if (putar) nilai = ((nilai % 360) + 360) % 360;

        keadaan.nilai = nilai;
        el.textContent = nilai.toFixed(desimal) + satuan;
        if (saat) saat(nilai);

        if (t < 1) {
            keadaan.raf = requestAnimationFrame(langkah);
        } else {
            keadaan.raf = 0;
            // Pastikan berhenti TEPAT di nilai yang dikirim kapal, bukan di
            // hasil pembulatan animasi.
            keadaan.nilai = putar ? ((target % 360) + 360) % 360 : target;
            el.textContent = keadaan.nilai.toFixed(desimal) + satuan;
            if (saat) saat(keadaan.nilai);
        }
    }

    keadaan.raf = requestAnimationFrame(langkah);
}


/**
 * Tampilkan angka pada elemen, bergerak halus menuju nilai baru.
 *
 * angkaHalus('mon-heading-deg', 137, { satuan: '°', putar: true })
 */
window.angkaHalus = function (elemen, nilai, opsi) {
    const el = typeof elemen === 'string' ? document.getElementById(elemen) : elemen;

    if (!el || nilai === null || nilai === undefined || Number.isNaN(Number(nilai))) {
        return;
    }

    jalankan(el, Number(nilai), opsi);
};


/**
 * Putar sebuah elemen (jarum kompas) mengikuti haluan, lewat busur terpendek.
 *
 * Sudutnya DIAKUMULASI - tidak pernah dikembalikan ke 0..360 - supaya CSS
 * memutar jarum searah gerakan kapal. Menuliskan rotate(10deg) sesudah
 * rotate(350deg) membuat jarum berputar balik 340 derajat, dan di layar itu
 * terlihat seperti kompas yang rusak.
 */
window.sudutHalus = function (elemen, sudut, durasi) {
    const el = typeof elemen === 'string' ? document.getElementById(elemen) : elemen;

    if (!el || sudut === null || sudut === undefined) return;

    const keadaan = el.__putar || (el.__putar = { terkumpul: Number(sudut) });

    keadaan.terkumpul += selisihSudut(
        ((keadaan.terkumpul % 360) + 360) % 360,
        ((Number(sudut) % 360) + 360) % 360
    );

    el.style.transition = `transform ${durasi || DURASI_MS}ms cubic-bezier(.22,.61,.36,1)`;
    el.style.transform = `rotate(${keadaan.terkumpul}deg)`;
};
