import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Host & port WebSocket sengaja diambil dari alamat yang sedang dibuka
// browser, BUKAN dari VITE_REVERB_HOST. Nilai VITE_* ditanam permanen saat
// `npm run build`, jadi kalau dipatok ke dashboard_monitoring_asv.test maka
// komputer lain di jaringan (yang membuka lewat IP laptop) akan mencoba konek
// ke domain yang tidak mereka kenal: halaman & kamera tetap tampil, tapi data
// sensor beku tanpa pesan error. Dengan cara ini dashboard ikut host apapun -
// .test di laptop, IP di komputer lain - tanpa perlu build ulang saat IP
// laptop berganti.
const scheme = window.location.protocol === 'https:' ? 'https' : 'http';
const port = window.location.port
    ? Number(window.location.port)
    : (scheme === 'https' ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Berkas ini dimuat sebagai modul, jadi eksekusinya DITUNDA sampai HTML selesai
// diurai - skrip inline di halaman selalu berjalan lebih dulu. Peristiwa ini
// yang memberi tahu mereka bahwa window.Echo sudah siap dipakai.
//
// Jangan kembali ke pola lama "setTimeout 1 detik lalu cek window.Echo sekali":
// di Raspberry Pi bundelnya kerap selesai lebih dari satu detik, cek itu kalah
// balapan, listener tidak pernah terpasang, dan dashboard beku permanen tanpa
// satu pun pesan galat. Lihat helper saatEchoSiap() di partials/echo-ready.
window.dispatchEvent(new Event('echo:siap'));

// Teks status dulu hanya menebak dari ada/tidaknya window.Echo, sehingga
// "Terputus" sebenarnya berarti "bundel JS belum selesai dimuat" - menyesatkan
// justru waktu paling dibutuhkan. Sekarang dibaca dari keadaan sambungan
// Pusher yang sesungguhnya.
const TEKS_STATUS = {
    connected: 'WebSockets Terhubung',
    connecting: 'Koneksi Menghubungkan...',
    unavailable: 'WebSockets Tidak Tersedia',
    failed: 'WebSockets Gagal',
    disconnected: 'WebSockets Terputus',
};

function tampilkanStatus(state) {
    document.querySelectorAll('#system-ws-status').forEach((el) => {
        el.textContent = TEKS_STATUS[state] ?? `WebSockets: ${state}`;
    });
}

const sambungan = window.Echo.connector.pusher.connection;

sambungan.bind('state_change', ({ current }) => tampilkanStatus(current));
tampilkanStatus(sambungan.state);
