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
