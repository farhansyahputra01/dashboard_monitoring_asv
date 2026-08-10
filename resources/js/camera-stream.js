/**
 * Menjaga stream MJPEG dari Raspberry Pi tetap tersambung.
 *
 * Stream MJPEG adalah satu respons HTTP yang tidak pernah selesai. Kalau kapal
 * kehilangan koneksi atau Pi restart, <img> memicu event 'error' dan gambarnya
 * berhenti selamanya - browser tidak menyambung ulang dengan sendirinya.
 * Di sini kita coba lagi berkala dengan cache-buster supaya browser benar-benar
 * membuka koneksi baru, bukan memakai yang sudah mati dari cache.
 */
document.addEventListener('DOMContentLoaded', () => {
    const RETRY_MS = 3000;

    document.querySelectorAll('img[data-stream-url]').forEach((img) => {
        const baseUrl = img.dataset.streamUrl;
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
    });
});
