document.addEventListener('DOMContentLoaded', () => {

    const lintasanA = [
        document.getElementById('dashboardAdminLintasanA'),
        document.getElementById('dashboardUserLintasanA'),
        document.getElementById('lintasanA')
    ];

    const lintasanB = [
        document.getElementById('dashboardAdminLintasanB'),
        document.getElementById('dashboardUserLintasanB'),
        document.getElementById('lintasanB')
    ];

    function tampilkanLintasan(track) {

        lintasanA.forEach(element => {
            if (element) {
                element.style.display = track === 'A' ? 'block' : 'none';
            }
        });

        lintasanB.forEach(element => {
            if (element) {
                element.style.display = track === 'B' ? 'block' : 'none';
            }
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

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.active_track === 'A' || data.active_track === 'B') {
                tampilkanLintasan(data.active_track);
            }

        } catch (error) {
            console.error('Gagal mengambil status lintasan:', error);
        }
    }

    // Cek pertama kali
    cekLintasan();

    // Cek setiap 2 detik
    setInterval(cekLintasan, 2000);

});