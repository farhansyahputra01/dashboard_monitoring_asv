/**
 * Panel "Kendali Kapal (langsung)" di halaman Monitoring admin.
 *
 * Angka-angka ini dulu ditulis di atas gambar kamera oleh program Python
 * (MODE, L/R, ERROR, FPS, ...). Di 320x240 teks itu menutup seperlima
 * gambar, jadi gambarnya dibuat bersih dan angkanya dipindah ke sini.
 *
 * Sumbernya BUKAN telemetri Laravel (1 Hz, lewat database), melainkan
 * GET /stream/status - JSON kecil yang ditulis program kapal tiap frame dan
 * dilewatkan nginx lewat proxy /stream/ yang sudah ada. Di-poll 2x/detik:
 * cukup cepat untuk mata, cukup ringan untuk Jetson.
 *
 * Kalau endpoint tidak terjangkau (program kapal mati, atau halaman dibuka
 * di laptop pengembangan tanpa nginx), panel menulis "-" dan menandai
 * dirinya tidak tersambung - bukan membeku dengan angka lama.
 */

const JEDA_MS = 500;
const BASI_S = 3;         // status lebih tua dari ini = program kapal berhenti mengirim

function buatPanel(el) {
    const url = el.dataset.statusUrl || '/stream/status';
    const sel = (nama) => el.querySelector(`[data-k="${nama}"]`);
    const tanda = el.querySelector('[data-k-tanda]');

    function tulis(nama, nilai) {
        const target = sel(nama);
        if (!target) return;
        target.textContent = (nilai === null || nilai === undefined || nilai === '') ? '-' : String(nilai);
    }

    function warnaMode(mode) {
        const m = String(mode || '');
        if (m.startsWith('PULANG')) return 'is-pulang';
        if (m.includes('STOP') || m.includes('CUTOFF')) return 'is-stop';
        if (m.startsWith('KUNCI') || m === 'STRAIGHT' || m.includes('PASANGAN')) return 'is-ok';
        if (m.startsWith('CARI') || m.startsWith('HALUAN') || m.startsWith('TEMP')) return 'is-cari';
        return '';
    }

    function terapkan(d) {
        tulis('mode', d.mode);
        tulis('motor', `${d.motor_l ?? '-'} / ${d.motor_r ?? '-'}`);
        tulis('kirim', Array.isArray(d.motor_kirim) ? `${d.motor_kirim[0]} / ${d.motor_kirim[1]}` : '-');
        tulis('error', d.error);
        tulis('fps', d.fps);
        tulis('det', d.det);
        tulis('bola', `${d.bola_dipakai ?? '-'} dipakai / ${d.bola_terlihat ?? '-'} terlihat`);
        tulis('lacak', d.lacak_mode ? `${d.lacak_mode}${d.lacak_jarak_m != null ? ` · ${d.lacak_jarak_m} m` : ''}` : '-');
        tulis('ket', d.lacak_ket);
        tulis('gerbang', `${d.pair_count ?? '-'} lewat · sasaran ${d.gerbang_sasaran ?? '-'}`);
        tulis('phase', d.phase);
        tulis('sisi', d.sisi);
        tulis('peta', d.peta);
        tulis('pos', (d.pos_x != null && d.pos_y != null)
            ? `${d.pos_x} , ${d.pos_y} m (${d.pos_sumber ?? '-'})`
            : '-');
        tulis('percaya', d.pos_dipercaya === true ? 'YA' : (d.pos_dipercaya === false ? 'TIDAK - hanyut' : '-'));
        tulis('kunci', d.kunci_gerbang ? 'AKTIF' : '-');
        tulis('tenaga', d.tenaga != null ? `${Math.round(d.tenaga * 100)}%` : '-');

        const modeEl = sel('mode');
        if (modeEl) {
            modeEl.className = 'panel-kendali-mode ' + warnaMode(d.mode);
        }
    }

    function setTanda(teks, kelas) {
        if (!tanda) return;
        tanda.textContent = teks;
        tanda.className = 'panel-kendali-tanda ' + kelas;
    }

    async function periksa() {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) throw new Error(String(res.status));
            const d = await res.json();

            if (!d || d.ts === undefined) {
                setTanda('menunggu program kapal', 'is-diam');
                return;
            }

            terapkan(d);

            if (d.umur_s !== null && d.umur_s !== undefined && d.umur_s > BASI_S) {
                setTanda(`basi ${Math.round(d.umur_s)} s - loop kendali berhenti?`, 'is-basi');
            } else if (d.stop) {
                setTanda('BERHENTI DARURAT', 'is-stop');
            } else if (d.batt_cutoff) {
                setTanda('BATERAI KRITIS - motor mati', 'is-stop');
            } else if (d.pulang) {
                setTanda('sedang PULANG', 'is-pulang');
            } else {
                setTanda('langsung', 'is-ok');
            }
        } catch (e) {
            setTanda('tidak terjangkau', 'is-diam');
            el.querySelectorAll('[data-k]').forEach((t) => { t.textContent = '-'; });
        }
    }

    periksa();
    setInterval(periksa, JEDA_MS);
}

document.querySelectorAll('[data-panel-kendali]').forEach(buatPanel);
