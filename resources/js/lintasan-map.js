/**
 * Peta LINTASAN: posisi kapal dalam meter di arena lomba 30 x 30 m,
 * lengkap dengan penyunting geometri untuk admin.
 *
 * Menggantikan peta jejak GPS (trajectory-map.js) untuk pemantauan lomba.
 * Bedanya mendasar, bukan sekadar tampilan:
 *
 *   trajectory-map  : menggambar lat/lon apa adanya, skalanya ikut sebaran
 *                     titik, dan tidak tahu apa pun tentang arena
 *   lintasan-map    : menggambar arena yang SUDAH DIKETAHUI bentuknya, lalu
 *                     menaruh kapal di atasnya pada koordinat meter
 *
 * Kenapa pindah: modul GPS berdesir 2-5 m walau kapal terikat diam - sudah
 * terukur di proyek ini. Pada kotak 1 meter, desiran itu 2-5 kotak, jadi
 * lat/lon mentah tidak akan pernah bisa menjawab "kapal ada di gerbang
 * berapa". Posisi x/y di sini dihitung kapal dari kompas + kecepatan, dan
 * ditambat ulang tiap kali kapal melewati gerbang (lihat posisi_lintasan.py).
 *
 * MODE EDIT
 * ---------------------------------------------------------------
 * Geometri arena tidak mungkin ditebak benar dari gambar lomba, jadi admin
 * bisa menggesernya sendiri. Yang digeser BUKAN sekadar gambar: koordinat
 * gerbang inilah yang dipakai kapal untuk menambatkan posisi, sehingga
 * menggeser satu bola merah berarti mengubah ke mana kapal "dipindahkan" saat
 * melewati gerbang itu.
 *
 * Karena itu mode edit harus DINYALAKAN dulu dengan sengaja, dan perubahan
 * baru berlaku setelah ditekan Terapkan. Peta yang bisa tergeser karena salah
 * klik saat lomba berjalan adalah bahaya, bukan kemudahan.
 */

function getWarna() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    if (isLight) {
        return {
            latar: '#f0f9ff',
            arena: '#ffffff',
            gridHalus: 'rgba(2, 132, 199, 0.08)',
            gridTebal: 'rgba(2, 132, 199, 0.20)',
            tepi: 'rgba(71, 85, 105, 0.65)',
            label: 'rgba(71, 85, 105, 0.90)',
            kolam: 'rgba(2, 132, 199, 0.08)',
            kolamTepi: 'rgba(2, 132, 199, 0.45)',
            merah: '#dc2626',
            hijau: '#16a34a',
            biru: '#2563eb',
            jejak: 'rgba(217, 119, 6, 0.9)',
            kapal: '#d97706',
            start: 'rgba(15, 23, 42, 0.8)',
            pegangan: 'rgba(15, 23, 42, 0.85)',
            peganganAktif: '#0284c7',
        };
    }
    return {
        latar: '#09141f',
        arena: '#0d1a29',
        gridHalus: 'rgba(148, 163, 184, 0.12)',
        gridTebal: 'rgba(148, 163, 184, 0.28)',
        tepi: 'rgba(148, 163, 184, 0.55)',
        label: 'rgba(148, 163, 184, 0.75)',
        kolam: 'rgba(56, 189, 248, 0.10)',
        kolamTepi: 'rgba(56, 189, 248, 0.55)',
        merah: '#ef4444',
        hijau: '#22c55e',
        biru: '#3b82f6',
        jejak: 'rgba(250, 204, 21, 0.85)',
        kapal: '#facc15',
        start: 'rgba(226, 232, 240, 0.8)',
        pegangan: 'rgba(255, 255, 255, 0.85)',
        peganganAktif: '#38bdf8',
    };
}

const WARNA = new Proxy({}, {
    get: (_, prop) => getWarna()[prop]
});


// Tepi untuk angka sumbu (piksel). Tanpa ruang ini, label "0" dan "30"
// terpotong di pinggir kanvas.
const TEPI = 22;

// Jejak dipangkas supaya kanvas tidak melambat setelah lomba berjalan lama.
// 1 titik/detik x 20 menit = 1200; di atas itu bagian terlama tidak lagi
// menarik untuk dilihat.
const MAKS_JEJAK = 1200;

// Jarak (piksel) maksimum klik dianggap mengenai sebuah pegangan.
const RADIUS_PEGANG = 10;


function buatPeta(el) {
    const canvas = el.querySelector('.trajectory-canvas');
    const kosong = el.querySelector('.trajectory-empty');
    const infoKiri = el.querySelector('.trajectory-scale');
    const infoKanan = el.querySelector('.trajectory-dist');
    const ctx = canvas.getContext('2d');

    const bolehEdit = el.dataset.bolehEdit === '1';
    const urlSimpan = el.dataset.urlSimpan || '';

    let geometri = null;
    let kunci = (el.dataset.lintasan || 'A').toUpperCase();
    let jejak = [];
    let kapal = null;

    // Baris kemajuan gerbang - lihat partials/lintasan-map.blade.php.
    // Boleh tidak ada (halaman lain memakai partial peta tanpa baris ini).
    const wrap = el.closest('.lintasan-wrap');
    const progres = wrap ? wrap.querySelector('[data-lintasan-progres]') : null;
    const progresAngka = progres ? progres.querySelector('[data-progres-angka]') : null;
    const progresPip = progres ? progres.querySelector('[data-progres-pip]') : null;
    const progresFase = progres ? progres.querySelector('[data-progres-fase]') : null;

    let gerbangLewat = (kapal && kapal.gate !== null && kapal.gate !== undefined)
        ? Number(kapal.gate) : 0;
    let faseKapal = (kapal && kapal.fase) ? String(kapal.fase) : '';

    let arenaKapalLain = null;   // arena yang sedang dipakai kapal, kalau bukan ini
    let modeEdit = false;
    let berubah = false;
    let pegangan = [];        // dibangun ulang tiap gambar()
    let sedangSeret = null;
    let pesanEdit = '';

    try {
        jejak = JSON.parse(el.dataset.jejak || '[]');
    } catch (e) {
        jejak = [];
    }

    if (jejak.length) {
        kapal = jejak[jejak.length - 1];
    }

    // -----------------------------------------------------
    // GEOMETRI
    // -----------------------------------------------------

    async function muatGeometri() {
        const url = el.dataset.geometri || '/data/lintasan.json';
        try {
            const res = await fetch(url, { cache: 'no-cache' });
            if (!res.ok) throw new Error(res.status);
            geometri = await res.json();
            berubah = false;
        } catch (e) {
            // Peta tidak bisa digambar tanpa geometri, tapi ini BUKAN alasan
            // untuk menampilkan kanvas kosong tanpa penjelasan - operator akan
            // mengira telemetrinya yang mati.
            geometri = null;
            if (kosong) {
                kosong.querySelector('span').textContent =
                    'Geometri lintasan tidak terbaca (' + url + ')';
                kosong.style.display = '';
            }
        }
        gambar();
        gambarProgres();
    }

    function peta() {
        return geometri?.lintasan?.[kunci] || null;
    }

    function arena() {
        return geometri?.arena || { lebar: 30, tinggi: 30, grid: 1 };
    }

    // -----------------------------------------------------
    // SKALA
    // -----------------------------------------------------
    //
    // Arena persegi, kanvas biasanya tidak. Gambar dipusatkan dan skalanya
    // diambil dari sisi yang paling sempit, supaya 1 meter ke arah x selalu
    // sama panjang dengan 1 meter ke arah y. Peta yang sisinya diregangkan
    // membuat operator salah menilai jarak - tepat hal yang ingin dihindari.

    function ukuran() {
        const a = arena();
        const w = el.clientWidth || 1;
        const h = el.clientHeight || 1;

        const pakaiW = w - TEPI * 2;
        const pakaiH = h - TEPI * 2;
        const skala = Math.max(1, Math.min(pakaiW / a.lebar, pakaiH / a.tinggi));

        return {
            skala,
            offX: (w - a.lebar * skala) / 2,
            offY: (h - a.tinggi * skala) / 2,
            w,
            h,
            a,
        };
    }

    // meter -> piksel. Sumbu y dibalik: di peta y bertambah MENJAUH dari
    // paddock (ke atas layar), sedangkan kanvas menghitung dari atas.
    function px(u, x, y) {
        return [u.offX + x * u.skala, u.offY + (u.a.tinggi - y) * u.skala];
    }

    // piksel -> meter, kebalikan px()
    function meter(u, cx, cy) {
        return [
            (cx - u.offX) / u.skala,
            u.a.tinggi - (cy - u.offY) / u.skala,
        ];
    }

    // -----------------------------------------------------
    // GAMBAR
    // -----------------------------------------------------

    function gambar() {
        const dpr = window.devicePixelRatio || 1;

        // Diukur dari WADAH, bukan dari kanvas - dan ukuran CSS kanvas
        // ditetapkan sendiri di bawah.
        //
        // Kalau lebar diambil dari canvas.clientWidth sementara CSS tidak
        // pernah memberi kanvas ukuran (.trajectory-canvas hanya
        // display:block), maka canvas.width = clientWidth * dpr LANGSUNG
        // mengubah ukuran tampil kanvas itu sendiri. ResizeObserver melihat
        // perubahan itu, menggambar ulang, dan mengalikannya dengan dpr lagi.
        // Pada zoom browser 90% dpr bernilai 0,9 - jadi tiap putaran kanvas
        // menyusut 10%, dan dalam beberapa detik peta tinggal secuil di pojok.
        const w = el.clientWidth || 1;
        const h = el.clientHeight || 1;

        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        canvas.style.width = `${w}px`;
        canvas.style.height = `${h}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, w, h);

        ctx.fillStyle = WARNA.latar;
        ctx.fillRect(0, 0, w, h);

        if (!geometri) return;

        const u = ukuran();
        pegangan = [];

        gambarArena(u);
        gambarKolam(u);
        gambarPenanda(u);
        gambarGerbang(u);
        gambarJejak(u);
        gambarKapal(u);

        if (modeEdit) gambarPegangan(u);

        perbaruiInfo();
    }

    function gambarArena(u) {
        const a = u.a;
        const grid = a.grid || 1;

        const [x0, y0] = px(u, 0, a.tinggi);
        ctx.fillStyle = WARNA.arena;
        ctx.fillRect(x0, y0, a.lebar * u.skala, a.tinggi * u.skala);

        ctx.lineWidth = 1;
        ctx.font = '10px system-ui, sans-serif';
        ctx.fillStyle = WARNA.label;

        for (let m = 0; m <= a.lebar; m += grid) {
            const tebal = m % 5 === 0;
            const [x] = px(u, m, 0);
            ctx.strokeStyle = tebal ? WARNA.gridTebal : WARNA.gridHalus;
            ctx.beginPath();
            ctx.moveTo(x, y0);
            ctx.lineTo(x, y0 + a.tinggi * u.skala);
            ctx.stroke();

            if (tebal) {
                ctx.textAlign = 'center';
                ctx.fillText(String(m), x, y0 + a.tinggi * u.skala + 13);
            }
        }

        for (let m = 0; m <= a.tinggi; m += grid) {
            const tebal = m % 5 === 0;
            const [, y] = px(u, 0, m);
            ctx.strokeStyle = tebal ? WARNA.gridTebal : WARNA.gridHalus;
            ctx.beginPath();
            ctx.moveTo(x0, y);
            ctx.lineTo(x0 + a.lebar * u.skala, y);
            ctx.stroke();

            if (tebal) {
                ctx.textAlign = 'right';
                ctx.fillText(String(m), x0 - 5, y + 3);
            }
        }

        ctx.strokeStyle = WARNA.tepi;
        ctx.lineWidth = 1.5;
        ctx.strokeRect(x0, y0, a.lebar * u.skala, a.tinggi * u.skala);

        // Sisi paddock ditandai supaya operator tahu peta ini dilihat dari
        // mana - tanpa itu, kiri/kanan di layar bisa terbalik dari kenyataan.
        ctx.fillStyle = WARNA.label;
        ctx.textAlign = 'center';
        ctx.fillText('PADDOCK / PANITIA',
            x0 + (a.lebar * u.skala) / 2, y0 + a.tinggi * u.skala + 25);
    }

    function gambarKolam(u) {
        const p = peta();
        if (!p?.kolam?.length) return;

        ctx.beginPath();
        p.kolam.forEach(([x, y], i) => {
            const [cx, cy] = px(u, x, y);
            if (i === 0) ctx.moveTo(cx, cy);
            else ctx.lineTo(cx, cy);
        });
        ctx.closePath();

        ctx.fillStyle = WARNA.kolam;
        ctx.fill();
        ctx.strokeStyle = WARNA.kolamTepi;
        ctx.lineWidth = 1.5;
        ctx.stroke();

        if (modeEdit) {
            p.kolam.forEach(([x, y], i) =>
                daftarPegangan(u, x, y, { tipe: 'kolam', idx: i }));
        }
    }

    function titik(u, x, y, warna, r = 4) {
        const [cx, cy] = px(u, x, y);
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, Math.PI * 2);
        ctx.fillStyle = warna;
        ctx.fill();
    }

    function gambarGerbang(u) {
        const p = peta();
        if (!p?.gerbang?.length) return;

        ctx.font = '9px system-ui, sans-serif';
        ctx.textAlign = 'center';

        p.gerbang.forEach((g, i) => {
            if (!g.merah || !g.hijau) return;

            const [mx, my] = px(u, g.merah[0], g.merah[1]);
            const [hx, hy] = px(u, g.hijau[0], g.hijau[1]);

            ctx.strokeStyle = 'rgba(148, 163, 184, 0.35)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(mx, my);
            ctx.lineTo(hx, hy);
            ctx.stroke();

            titik(u, g.merah[0], g.merah[1], WARNA.merah, 3.5);
            titik(u, g.hijau[0], g.hijau[1], WARNA.hijau, 3.5);

            // Nomor gerbang: dipakai mencocokkan "pair 6" di panel dengan
            // tempatnya di peta.
            ctx.fillStyle = WARNA.label;
            ctx.fillText(String(g.no ?? i + 1), (mx + hx) / 2, (my + hy) / 2 - 5);

            if (modeEdit) {
                daftarPegangan(u, g.merah[0], g.merah[1],
                    { tipe: 'gerbang', idx: i, sub: 'merah' });
                daftarPegangan(u, g.hijau[0], g.hijau[1],
                    { tipe: 'gerbang', idx: i, sub: 'hijau' });
            }
        });
    }

    function gambarPenanda(u) {
        const p = peta();
        if (!p) return;

        (p.docking_biru || []).forEach(([x, y], i) => {
            titik(u, x, y, WARNA.biru, 4);
            if (modeEdit) daftarPegangan(u, x, y, { tipe: 'docking', idx: i });
        });

        const kotak = (nama, koord, warna, teks) => {
            if (!koord) return;
            const [cx, cy] = px(u, koord[0], koord[1]);
            const s = Math.max(6, u.skala * 0.8);
            ctx.fillStyle = warna;
            ctx.fillRect(cx - s / 2, cy - s / 2, s, s);
            ctx.fillStyle = WARNA.label;
            ctx.font = '9px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(teks, cx, cy - s);

            if (modeEdit) daftarPegangan(u, koord[0], koord[1], { tipe: nama });
        };

        kotak('kotak_biru', p.kotak_biru, WARNA.biru, 'kotak');
        kotak('kotak_hijau', p.kotak_hijau, WARNA.hijau, 'kotak');

        if (p.start) {
            const [sx, sy] = px(u, p.start.x, p.start.y);
            ctx.strokeStyle = WARNA.start;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(sx, sy, 7, 0, Math.PI * 2);
            ctx.stroke();
            ctx.fillStyle = WARNA.start;
            ctx.font = '9px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('START', sx, sy + 18);

            if (modeEdit) daftarPegangan(u, p.start.x, p.start.y, { tipe: 'start' });
        }
    }

    function gambarJejak(u) {
        if (jejak.length < 2) return;

        ctx.strokeStyle = WARNA.jejak;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.beginPath();

        jejak.forEach((t, i) => {
            const [cx, cy] = px(u, t.x, t.y);
            if (i === 0) ctx.moveTo(cx, cy);
            else ctx.lineTo(cx, cy);
        });
        ctx.stroke();

        // Titik tebal di tempat posisi ditambatkan gerbang. Ini penanda
        // kejujuran: di situ posisi diketahui pasti, di antaranya hasil
        // perhitungan.
        jejak.forEach((t) => {
            if (t.src && String(t.src).startsWith('GERBANG')) {
                titik(u, t.x, t.y, '#ffffff', 3);
            }
        });
    }

    function gambarKapal(u) {
        if (!kapal) {
            if (kosong) {
                kosong.style.display = modeEdit ? 'none' : '';

                // Teksnya ikut menyesuaikan: "menunggu posisi" pada peta arena
                // yang memang bukan tempat kapalnya berada terbaca seperti
                // telemetri yang mati, padahal kapalnya baik-baik saja.
                const teks = kosong.querySelector('span');
                if (teks) {
                    teks.textContent = arenaKapalLain
                        ? `Kapal berada di Lintasan ${arenaKapalLain}, bukan di arena ini`
                        : 'Menunggu posisi dari kapal';
                }
            }
            return;
        }
        if (kosong) kosong.style.display = 'none';

        const [cx, cy] = px(u, kapal.x, kapal.y);

        ctx.beginPath();
        ctx.arc(cx, cy, 6, 0, Math.PI * 2);
        ctx.fillStyle = WARNA.kapal;
        ctx.fill();

        if (kapal.hdg === null || kapal.hdg === undefined) return;

        const sudut = (kapal.hdg * Math.PI) / 180;
        const panjang = 16;
        ctx.strokeStyle = WARNA.kapal;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + Math.sin(sudut) * panjang, cy - Math.cos(sudut) * panjang);
        ctx.stroke();
    }

    // -----------------------------------------------------
    // PEGANGAN (hanya saat mode edit)
    // -----------------------------------------------------

    function daftarPegangan(u, x, y, info) {
        const [cx, cy] = px(u, x, y);
        pegangan.push({ ...info, x, y, cx, cy });
    }

    function gambarPegangan(u) {
        pegangan.forEach((h) => {
            const aktif = sedangSeret
                && sedangSeret.tipe === h.tipe
                && sedangSeret.idx === h.idx
                && sedangSeret.sub === h.sub;

            ctx.beginPath();
            ctx.arc(h.cx, h.cy, aktif ? 8 : 6, 0, Math.PI * 2);
            ctx.strokeStyle = aktif ? WARNA.peganganAktif : WARNA.pegangan;
            ctx.lineWidth = aktif ? 2.5 : 1.5;
            ctx.stroke();
        });
    }

    function cariPegangan(cx, cy) {
        let dekat = null;
        let jarakTerdekat = RADIUS_PEGANG;

        pegangan.forEach((h) => {
            const d = Math.hypot(h.cx - cx, h.cy - cy);
            if (d <= jarakTerdekat) {
                jarakTerdekat = d;
                dekat = h;
            }
        });

        return dekat;
    }

    function setKoordinat(h, x, y) {
        const p = peta();
        if (!p) return;

        const a = arena();
        x = Math.min(Math.max(x, 0), a.lebar);
        y = Math.min(Math.max(y, 0), a.tinggi);

        if (h.tipe === 'kolam') p.kolam[h.idx] = [x, y];
        else if (h.tipe === 'docking') p.docking_biru[h.idx] = [x, y];
        else if (h.tipe === 'gerbang') p.gerbang[h.idx][h.sub] = [x, y];
        else if (h.tipe === 'start') { p.start.x = x; p.start.y = y; }
        else if (h.tipe === 'kotak_biru') p.kotak_biru = [x, y];
        else if (h.tipe === 'kotak_hijau') p.kotak_hijau = [x, y];

        berubah = true;
    }

    // -----------------------------------------------------
    // INFO
    // -----------------------------------------------------

    function perbaruiInfo() {
        const p = peta();

        if (infoKiri) {
            infoKiri.textContent = (p?.nama || 'Lintasan ' + kunci) + '  ·  kotak 1 m';
        }

        if (!infoKanan) return;

        if (modeEdit) {
            infoKanan.textContent = pesanEdit
                || 'MODE EDIT - seret penanda, klik ganda = tambah titik kolam, klik kanan = hapus';
            return;
        }

        if (arenaKapalLain) {
            // Penanda hilang itu benar, tapi tanpa keterangan ia terbaca
            // sebagai telemetri yang mati. Sebutkan kapalnya ada di mana.
            infoKanan.textContent =
                `kapal sedang di Lintasan ${arenaKapalLain} - pindah tab lintasan untuk melihatnya`;
            return;
        }

        if (!kapal) {
            infoKanan.textContent = 'menunggu posisi';
            return;
        }

        const bagian = [`x ${kapal.x.toFixed(1)} m  y ${kapal.y.toFixed(1)} m`];
        if (kapal.src) bagian.push(kapal.src);
        if (kapal.jarak != null) bagian.push(`${kapal.jarak.toFixed(0)} m tempuh`);
        if (kapal.selisih != null) bagian.push(`GPS Δ${kapal.selisih.toFixed(1)} m`);
        infoKanan.textContent = bagian.join('  ·  ');
    }

    // -----------------------------------------------------
    // INTERAKSI EDIT
    // -----------------------------------------------------

    function posisiTetikus(ev) {
        const r = canvas.getBoundingClientRect();
        return [ev.clientX - r.left, ev.clientY - r.top];
    }

    if (bolehEdit) {
        canvas.addEventListener('pointerdown', (ev) => {
            if (!modeEdit || !geometri) return;

            const [cx, cy] = posisiTetikus(ev);
            const h = cariPegangan(cx, cy);
            if (!h) return;

            sedangSeret = h;
            canvas.setPointerCapture(ev.pointerId);
            ev.preventDefault();
            gambar();
        });

        canvas.addEventListener('pointermove', (ev) => {
            if (!modeEdit || !geometri) return;

            const [cx, cy] = posisiTetikus(ev);

            if (!sedangSeret) {
                canvas.style.cursor = cariPegangan(cx, cy) ? 'grab' : 'crosshair';
                return;
            }

            const u = ukuran();
            let [mx, my] = meter(u, cx, cy);

            // Kunci ke kotak 1 meter. Shift menahannya, untuk titik kolam yang
            // memang tidak jatuh pas di garis grid.
            if (!ev.shiftKey) {
                mx = Math.round(mx);
                my = Math.round(my);
            } else {
                mx = Math.round(mx * 10) / 10;
                my = Math.round(my * 10) / 10;
            }

            setKoordinat(sedangSeret, mx, my);
            pesanEdit = `${sedangSeret.tipe}${sedangSeret.sub ? ' ' + sedangSeret.sub : ''}`
                + `  ->  x ${mx.toFixed(1)}  y ${my.toFixed(1)}`
                + (ev.shiftKey ? '  (bebas)' : '  (kunci 1 m)');
            canvas.style.cursor = 'grabbing';
            gambar();
        });

        const lepas = (ev) => {
            if (!sedangSeret) return;
            sedangSeret = null;
            canvas.style.cursor = 'crosshair';
            if (ev && ev.pointerId !== undefined) {
                try { canvas.releasePointerCapture(ev.pointerId); } catch (e) { /* sudah lepas */ }
            }
            gambar();
            perbaruiTombol();
        };

        canvas.addEventListener('pointerup', lepas);
        canvas.addEventListener('pointercancel', lepas);

        // Klik ganda di air = sisipkan titik kolam pada ruas TERDEKAT.
        // Disisipkan, bukan ditambahkan di ujung: menambah di ujung akan
        // menarik garis melintasi kolam dan bentuknya jadi kacau.
        canvas.addEventListener('dblclick', (ev) => {
            if (!modeEdit || !geometri) return;

            const p = peta();
            if (!p?.kolam?.length) return;

            const u = ukuran();
            const [cx, cy] = posisiTetikus(ev);
            const [mx, my] = meter(u, cx, cy);

            let ruasTerbaik = 0;
            let jarakTerbaik = Infinity;

            for (let i = 0; i < p.kolam.length; i++) {
                const a = p.kolam[i];
                const b = p.kolam[(i + 1) % p.kolam.length];
                const d = jarakKeRuas(mx, my, a, b);
                if (d < jarakTerbaik) {
                    jarakTerbaik = d;
                    ruasTerbaik = i;
                }
            }

            p.kolam.splice(ruasTerbaik + 1, 0, [Math.round(mx), Math.round(my)]);
            berubah = true;
            pesanEdit = 'titik kolam ditambahkan';
            gambar();
            perbaruiTombol();
        });

        canvas.addEventListener('contextmenu', (ev) => {
            if (!modeEdit || !geometri) return;

            const [cx, cy] = posisiTetikus(ev);
            const h = cariPegangan(cx, cy);
            if (!h) return;

            ev.preventDefault();
            const p = peta();

            if (h.tipe === 'kolam') {
                if (p.kolam.length <= 3) {
                    pesanEdit = 'kolam butuh minimal 3 titik';
                } else {
                    p.kolam.splice(h.idx, 1);
                    berubah = true;
                    pesanEdit = 'titik kolam dihapus';
                }
            } else if (h.tipe === 'gerbang') {
                p.gerbang.splice(h.idx, 1);
                p.gerbang.forEach((g, i) => { g.no = i + 1; });
                berubah = true;
                pesanEdit = 'gerbang dihapus, nomor diurutkan ulang';
            } else if (h.tipe === 'docking') {
                p.docking_biru.splice(h.idx, 1);
                berubah = true;
                pesanEdit = 'buoy docking dihapus';
            } else {
                pesanEdit = h.tipe + ' tidak bisa dihapus';
            }

            gambar();
            perbaruiTombol();
        });
    }

    function jarakKeRuas(x, y, a, b) {
        const dx = b[0] - a[0];
        const dy = b[1] - a[1];
        const panjang2 = dx * dx + dy * dy;

        if (panjang2 === 0) return Math.hypot(x - a[0], y - a[1]);

        let t = ((x - a[0]) * dx + (y - a[1]) * dy) / panjang2;
        t = Math.min(Math.max(t, 0), 1);

        return Math.hypot(x - (a[0] + t * dx), y - (a[1] + t * dy));
    }

    // -----------------------------------------------------
    // TOMBOL
    // -----------------------------------------------------

    const tblEdit = el.parentElement?.querySelector('[data-lintasan-edit]');
    const tblSimpan = el.parentElement?.querySelector('[data-lintasan-simpan]');
    const tblBatal = el.parentElement?.querySelector('[data-lintasan-batal]');
    const tblGerbang = el.parentElement?.querySelector('[data-lintasan-tambah-gerbang]');

    function perbaruiTombol() {
        if (tblEdit) {
            tblEdit.textContent = modeEdit ? 'Selesai Edit' : 'Edit Peta';
            tblEdit.classList.toggle('aktif', modeEdit);
        }
        [tblSimpan, tblBatal, tblGerbang].forEach((b) => {
            if (b) b.style.display = modeEdit ? '' : 'none';
        });
        if (tblSimpan) tblSimpan.disabled = !berubah;
    }

    if (tblEdit) {
        tblEdit.addEventListener('click', () => {
            if (modeEdit && berubah
                && !confirm('Ada perubahan yang belum diterapkan. Tutup mode edit?')) {
                return;
            }
            modeEdit = !modeEdit;
            sedangSeret = null;
            pesanEdit = '';
            canvas.style.cursor = modeEdit ? 'crosshair' : '';
            if (!modeEdit && berubah) muatGeometri();
            else gambar();
            perbaruiTombol();
        });
    }

    if (tblBatal) {
        tblBatal.addEventListener('click', () => {
            pesanEdit = 'perubahan dibatalkan';
            muatGeometri();
            perbaruiTombol();
        });
    }

    if (tblGerbang) {
        tblGerbang.addEventListener('click', () => {
            const p = peta();
            if (!p) return;
            const a = arena();

            p.gerbang = p.gerbang || [];
            p.gerbang.push({
                no: p.gerbang.length + 1,
                merah: [Math.round(a.lebar / 2) - 2, Math.round(a.tinggi / 2)],
                hijau: [Math.round(a.lebar / 2) + 2, Math.round(a.tinggi / 2)],
            });

            berubah = true;
            pesanEdit = 'gerbang baru ditaruh di tengah - seret ke tempatnya';
            gambar();
            perbaruiTombol();
        });
    }

    if (tblSimpan) {
        tblSimpan.addEventListener('click', async () => {
            const p = peta();
            if (!p || !urlSimpan) return;

            tblSimpan.disabled = true;
            pesanEdit = 'menyimpan...';
            gambar();

            try {
                const res = await fetch(urlSimpan, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        lintasan: kunci,
                        data: {
                            start: p.start,
                            kolam: p.kolam,
                            gerbang: p.gerbang,
                            docking_biru: p.docking_biru,
                            kotak_biru: p.kotak_biru,
                            kotak_hijau: p.kotak_hijau,
                        },
                    }),
                });

                const hasil = await res.json().catch(() => ({}));

                if (res.ok && hasil.ok) {
                    berubah = false;
                    pesanEdit = 'tersimpan - salin lintasan.json ke kapal';
                } else {
                    pesanEdit = 'GAGAL: ' + (hasil.message || res.status);
                }
            } catch (e) {
                pesanEdit = 'GAGAL: ' + e.message;
            }

            gambar();
            perbaruiTombol();
        });
    }

    // -----------------------------------------------------
    // API
    // -----------------------------------------------------

    function tambah(t) {
        // Titik dari ARENA LAIN ditolak, bukan digambar.
        //
        // Koordinat x/y hanya bermakna di dalam arenanya sendiri: 3,5 di arena
        // B adalah tempat yang sama sekali berbeda dari 3,5 di arena A.
        // Membersihkan jejak saat pemilih lintasan diganti saja TIDAK cukup -
        // siaran berikutnya (satu detik kemudian) langsung menggambarnya
        // kembali, dan penanda kuning muncul lagi di peta yang salah.
        if (t.lintasan && t.lintasan !== kunci) {
            arenaKapalLain = t.lintasan;
            if (!sedangSeret) gambar();
            return;
        }

        arenaKapalLain = null;

        // Saat mode edit, posisi kapal tetap dicatat tapi peta TIDAK digambar
        // ulang tiap detik - gambar ulang di tengah seretan membuat penanda
        // yang sedang dipegang berkedip dan meleset.
        kapal = t;
        jejak.push(t);
        if (jejak.length > MAKS_JEJAK) jejak.splice(0, jejak.length - MAKS_JEJAK);
        if (!sedangSeret) gambar();
    }

    function setLintasan(baru) {
        const k = String(baru || '').toUpperCase();
        if (!k || k === kunci) return;
        kunci = k;

        // Jejak TIDAK dibawa pindah. Koordinat x/y bermakna hanya di dalam
        // arenanya sendiri; menempelkan jejak arena A ke gambar arena B akan
        // menampilkan lintasan yang tidak pernah terjadi.
        jejak = [];
        kapal = null;
        arenaKapalLain = null;
        gambar();
        gambarProgres();
    }

    function kosongkan() {
        jejak = [];
        kapal = null;
        gerbangLewat = 0;
        faseKapal = '';
        gambar();
        gambarProgres();
    }

    // -----------------------------------------------------
    // KEMAJUAN GERBANG
    // -----------------------------------------------------

    function gambarProgres() {
        if (!progres) return;

        const p = peta();
        const total = (p && Array.isArray(p.gerbang)) ? p.gerbang.length : 0;

        // Tanpa geometri, jumlah gerbangnya tidak diketahui - dan "3/0" lebih
        // membingungkan daripada tidak ada apa-apa.
        progres.hidden = (total === 0);
        if (total === 0) return;

        const lewat = Math.max(0, Math.min(total, gerbangLewat));

        if (progresAngka) progresAngka.textContent = lewat + '/' + total;

        if (progresPip) {
            if (progresPip.childElementCount !== total) {
                progresPip.textContent = '';
                for (let i = 0; i < total; i++) {
                    progresPip.appendChild(document.createElement('span'));
                }
            }

            Array.from(progresPip.children).forEach((s, i) => {
                s.className = i < lewat ? 'lewat' : (i === lewat ? 'tujuan' : '');
                s.title = 'Gerbang ' + (i + 1);
            });
        }

        if (progresFase) {
            progresFase.textContent = lewat >= total
                ? (faseKapal ? 'Slalom selesai - fase ' + faseKapal : 'Slalom selesai')
                : (faseKapal === 'SLALOM' || !faseKapal
                    ? 'Menuju gerbang ' + (lewat + 1)
                    : 'Fase ' + faseKapal);
        }
    }

    function setelProgres(gate, fase) {
        // Hitungan gerbang TIDAK boleh mundur. Satu baris telemetri lama yang
        // datang terlambat - antrean unggah kapal memang menyimpan yang gagal
        // terkirim - jangan sampai membuat kemajuan lomba terlihat berkurang.
        if (gate !== null && gate !== undefined) {
            gerbangLewat = Math.max(gerbangLewat, Number(gate) || 0);
        }

        if (fase) faseKapal = String(fase);

        gambarProgres();
    }

    muatGeometri();
    perbaruiTombol();
    gambarProgres();

    return { gambar, tambah, setLintasan, kosongkan, setelProgres };
}


const wadah = document.querySelectorAll('[data-lintasan-map]');

if (wadah.length) {
    const peta = Array.from(wadah).map(buatPeta);

    const amati = new ResizeObserver(() => peta.forEach((p) => p.gambar()));
    wadah.forEach((el) => amati.observe(el));

    const amatiTema = new MutationObserver(() => peta.forEach((p) => p.gambar()));
    amatiTema.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    window.addEventListener('theme-changed', () => peta.forEach((p) => p.gambar()));

    // Sinkronisasi lintasan aktif realtime (dari event lokal atau polling track-sync)
    window.addEventListener('active-track-changed', (e) => {
        if (e.detail && (e.detail.activeTrack === 'A' || e.detail.activeTrack === 'B')) {
            peta.forEach((p) => p.setLintasan(e.detail.activeTrack));
            const pilih = document.getElementById('trackSelect');
            if (pilih) {
                pilih.value = e.detail.activeTrack;
                const penanda = document.querySelector('[data-track-belum-simpan]');
                if (penanda) penanda.hidden = true;
            }
        }
    });


    // Pemilih Lintasan A/B yang sudah ada di halaman admin.
    const pilih = document.getElementById('trackSelect');
    if (pilih) {
        // Nilai yang BENAR-BENAR berlaku di sistem adalah yang terpasang saat
        // halaman dimuat - itulah yang tersimpan di server. Apa pun yang
        // dipilih sesudahnya baru sekadar pratinjau sampai Simpan ditekan.
        const tersimpan = pilih.value;
        const penanda = document.querySelector('[data-track-belum-simpan]');

        pilih.addEventListener('change', function () {
            peta.forEach((p) => p.setLintasan(this.value));
            if (penanda) penanda.hidden = (this.value === tersimpan);
        });
    }

    const tombolReset = document.querySelector('[data-lintasan-reset]');
    if (tombolReset) {
        tombolReset.addEventListener('click', () =>
            peta.forEach((p) => p.kosongkan()));
    }

    // saatEchoSiap: lihat partials/echo-ready.blade.php. Jangan diganti
    // setTimeout - di Raspberry Pi bundelnya sering selesai lebih lambat dari
    // tenggat mana pun yang ditebak, dan listener-nya tidak pernah terpasang.
    const pasang = () => {
        window.Echo.channel('sensors').listen('SensorDataUpdated', (e) => {
            const d = e.sensorData;

            // Kemajuan gerbang diperbarui LEBIH DULU, sebelum posisi diperiksa.
            // Kapal yang berjalan dengan --tanpa-posisi tidak mengirim x/y sama
            // sekali, tapi tetap menghitung gerbang - dan itu justru saat
            // angka ini paling dibutuhkan, karena petanya kosong.
            peta.forEach((p) => p.setelProgres(d.pair_count, d.phase));

            if (d.x_m === null || d.x_m === undefined
                || d.y_m === null || d.y_m === undefined) {
                return;      // kapal belum mengirim posisi peta
            }

            peta.forEach((p) => p.tambah({
                x: parseFloat(d.x_m),
                y: parseFloat(d.y_m),
                hdg: d.heading !== null && d.heading !== undefined
                    ? parseFloat(d.heading) : null,
                src: d.pos_sumber || null,
                gate: d.pair_count ?? null,
                fase: d.phase ?? null,
                jarak: d.jarak_m !== null && d.jarak_m !== undefined
                    ? parseFloat(d.jarak_m) : null,
                selisih: d.selisih_gps_m !== null && d.selisih_gps_m !== undefined
                    ? parseFloat(d.selisih_gps_m) : null,
                // Penentu apakah titik ini milik arena yang sedang digambar.
                lintasan: d.lintasan || null,
            }));
        });

        window.Echo.channel('sensors').listen('ActiveTrackUpdated', (e) => {
            peta.forEach((p) => p.setLintasan(e.activeTrack));
            
            // Perbarui nilai dropdown pemilih lintasan jika ada (di halaman admin)
            const pilih = document.getElementById('trackSelect');
            if (pilih) {
                pilih.value = e.activeTrack;
                const penanda = document.querySelector('[data-track-belum-simpan]');
                if (penanda) penanda.hidden = true;
            }
        });
    };

    if (window.saatEchoSiap) {
        window.saatEchoSiap(pasang);
    } else {
        window.addEventListener('echo:siap', pasang, { once: true });
    }
}
