/**
 * Sinkronisasi Lintasan Aktif Realtime Dashboard ASV
 * Memastikan peta lintasan di sisi User dan Admin selalu sinkron otomatis saat diubah.
 */
document.addEventListener('DOMContentLoaded', () => {
    let currentTrack = null;

    // Inisialisasi dari trackSelect jika ada
    const select = document.getElementById('trackSelect');
    if (select) {
        currentTrack = select.value;
    }

    function terapkanLintasan(track) {
        if (!track || (track !== 'A' && track !== 'B')) return;

        if (currentTrack !== track) {
            currentTrack = track;
            
            // Siarkan event ke kanvas lintasan-map dan komponen lainnya
            window.dispatchEvent(new CustomEvent('active-track-changed', {
                detail: { activeTrack: track }
            }));
        }

        // Kompatibilitas elemen gambar legacy jika ada
        ['dashboardAdminLintasan', 'dashboardUserLintasan', 'lintasan'].forEach(prefix => {
            const elA = document.getElementById(prefix + 'A');
            const elB = document.getElementById(prefix + 'B');
            if (elA) elA.style.display = track === 'A' ? 'block' : 'none';
            if (elB) elB.style.display = track === 'B' ? 'block' : 'none';
        });
    }

    async function cekLintasan() {
        try {
            const response = await fetch('/monitoring/active-track', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store'
            });

            if (!response.ok) return;

            const data = await response.json();
            if (data.active_track) {
                terapkanLintasan(data.active_track);
            }
        } catch (e) {
            // silent catch
        }
    }

    // Periksa saat pertama kali dimuat
    cekLintasan();

    // Polling setiap 1.5 detik sebagai fallback instan jika WebSocket offline
    setInterval(cekLintasan, 1500);
});