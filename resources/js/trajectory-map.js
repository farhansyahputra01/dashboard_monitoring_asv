/**
 * Peta jejak kapal dari koordinat GPS.
 *
 * Berbeda dengan dashboard ROV yang menghitung posisi lewat integrasi gyro
 * (dead reckoning, galat menumpuk seiring waktu), ASV punya GPS - jadi setiap
 * titik adalah posisi absolut dan tidak ada galat yang beranak-pinak. Kompas
 * hanya dipakai untuk arah hadap kapal, bukan untuk menghitung posisi.
 *
 * Titik awal diambil dari server saat halaman dimuat, lalu ditambah realtime
 * dari siaran SensorDataUpdated.
 */

const METER_PER_DEG_LAT = 110540;
const METER_PER_DEG_LON = 111320;

// Jarak minimum yang dianggap perpindahan nyata. GPS diam tetap berdesir
// beberapa meter; tanpa ini jejaknya jadi gumpalan benang kusut di titik start.
//
// 0,4 m dulu terlalu kecil: modul GPS kelas hobi berdesir 2-5 m walau kapal
// terikat di dermaga, jadi hampir SETIAP desiran lolos dan terbaca sebagai
// gerakan - itulah kenapa angka "jejak ... m" terus bertambah padahal kapal
// berhenti. 3 m berada di atas desiran khas tapi masih di bawah satu panjang
// lambung, jadi manuver asli tetap tergambar.
const MIN_GERAK_M = 3.0;

// Lompatan lebih jauh dari ini dalam satu pembaruan dianggap galat GPS, bukan
// gerakan. Titiknya TIDAK langsung dibuang tapi ditahan: kalau pembacaan
// berikutnya jatuh di dekat titik tertahan itu, berarti kapal memang benar
// pindah (atau GPS baru dapat fix ulang) dan titiknya diterima. Jadi satu
// pencilan liar tidak menarik garis melintasi peta, tapi perpindahan nyata
// tetap tercatat - hanya tertunda satu pembacaan.
const MAX_LOMPAT_M = 15;

// Di bawah kecepatan ini (km/jam) kapal dianggap DIAM, dan posisinya tidak
// ditambahkan ke jejak sama sekali.
//
// Ini saringan terkuat yang ada, dan alasannya penting: kecepatan dari modul
// GPS dihitung dari pergeseran Doppler sinyal satelit, BUKAN dari selisih dua
// posisi berurutan. Artinya ia tetap mendekati nol saat kapal terikat di
// dermaga, walaupun lat/lng-nya sedang berdesir puluhan meter. Jadi ketika
// posisi dan kecepatan bertentangan - "pindah 40 m" tapi "0,2 km/jam" -
// yang benar hampir selalu kecepatannya.
//
// Angka 2,5 diukur, bukan ditebak. Dari 400 baris yang direkam saat kapal
// DIAM di dalam ruangan dengan fix 6-8 satelit:
//
//     DIAM_KMH   jejak palsu   lintasan uji 4 km/jam   merayap 2,5 km/jam
//        1,0       43 - 75 m           57 m                  59 m
//        2,0        0 -  4 m           57 m                  59 m
//        2,5            0 m            57 m                  59 m
//        3,0            0 m            57 m                   0 m
//
// Kecepatan lapor saat diam ternyata bisa menyentuh 2,94 km/jam - jauh di
// atas dugaan awal 1 km/jam - jadi ambang lama meloloskan banyak sekali
// desiran. Di 3,0 kapal yang merayap ikut hilang, jadi 2,5 adalah titik
// terbaik antara menahan desiran dan tetap menggambar gerakan pelan.
const DIAM_KMH = 2.5;

// Perpindahan tidak boleh melebihi (kecepatan Doppler x waktu) dikali angka
// ini, ditambah MARGIN_DOPPLER_M.
//
// Ini penyaring paling menentukan, dan dasarnya fisika, bukan tebakan: kalau
// modul melaporkan 2,3 km/jam, dalam 1 detik kapal mustahil pindah lebih dari
// 0,64 m. Ketika posisi mengaku pindah 3 m pada detik yang sama, yang salah
// pasti posisinya - dan justru langkah 3-6 m seperti inilah yang selama ini
// lolos, karena masih jauh di bawah MAX_LOMPAT_M sehingga dianggap wajar.
//
// Faktor 1,5 memberi ruang untuk kecepatan yang naik-turun di antara dua
// laporan; marginnya menyerap kesalahan pembulatan waktu.
const TOLERANSI_DOPPLER = 1.5;
const MARGIN_DOPPLER_M = 1.0;

// Berapa pembacaan berturut-turut yang harus sepakat sebelum sebuah lompatan
// diterima sebagai perpindahan nyata. Dinaikkan dari 2 ke 3: dua desiran yang
// kebetulan jatuh berdekatan masih cukup sering terjadi, tiga jarang.
const KONFIRMASI_MIN = 3;

// Jumlah satelit minimum sebelum sebuah posisi boleh digambar.
//
// Diuji terhadap DUA rekaman kapal diam yang berbeda watak, karena ambang
// yang bersih di satu rekaman ternyata bocor di rekaman lain:
//
//     rekaman A : 3-7 satelit, kecepatan palsu sampai 6,43 km/jam
//     rekaman B : 6-8 satelit, kecepatan palsu 2,94 km/jam, desiran 12,5 m
//
//     MIN_SAT   palsu-A   palsu-B      (DIAM_KMH 2,5)
//        5        139 m       0 m
//        6         89 m       0 m
//        7          0 m       0 m
//
// DIAM_KMH sendirian sudah membersihkan rekaman B pada ambang satelit berapa
// pun, tapi TIDAK cukup untuk rekaman A - di sana kecepatan lapornya ikut
// ngawur sampai 6,43 km/jam, sehingga hanya jumlah satelit yang bisa
// membedakannya. Keduanya diperlukan; tidak ada satu penyaring yang menang
// sendirian.
//
// Konsekuensinya jujur: saat fix lemah, peta akan kosong dan menulis
// "Sinyal GPS lemah". Data tanpa satellites (null) sengaja TIDAK diblokir,
// supaya pengiriman lama yang belum mengirim field itu tetap tergambar.
const MIN_SAT = 7;

// Ambang longgar untuk MODE UJI. Dipakai saat menguji dashboard di dalam
// ruangan, di mana 7 satelit hampir tidak pernah tercapai sehingga peta
// selalu kosong dan tidak ada yang bisa diperiksa.
//
// Mode ini menggambar jejak yang SUDAH DIKETAHUI tidak dapat dipercaya, jadi
// peta diberi tanda merah mencolok selama aktif. Pilihannya disimpan di
// browser masing-masing, bukan di server: kalau juri atau penonton membuka
// dashboard yang sama, mereka tetap melihat data yang jujur.
const MIN_SAT_UJI = 4;
const KUNCI_MODE_UJI = 'asv-peta-mode-uji';

// Batas waktu hasil tombol RESET: titik yang lebih tua dari ini tidak
// digambar lagi. Disimpan supaya reset tetap berlaku setelah halaman
// di-refresh - kalau tidak, riwayat dari server akan langsung memunculkan
// kembali jejak yang baru saja dihapus operator.
//
// Ini TIDAK menghapus apa pun dari database. Datanya utuh; yang berubah cuma
// bagian mana yang ditampilkan di browser ini.
const KUNCI_RESET = 'asv-peta-reset-ts';

// Jeda (milidetik) untuk klik kedua sebagai pembenaran RESET. Sekali klik
// saja terlalu berbahaya: jejak satu babak lomba bisa lenyap karena tangan
// tergelincir, dan tidak ada cara mengembalikannya.
const JEDA_PEMBENARAN_MS = 3000;

// Bentang minimum peta. Tanpa ini, kapal yang baru bergerak 20 cm akan
// membuat peta ter-zoom sampai desiran GPS terlihat seperti manuver.
//
// 10 m dulu terlalu sempit: skala peta menyesuaikan isi, jadi jejak sependek
// 20 m pun langsung memenuhi seluruh kanvas dan terlihat seperti pelayaran
// panjang. 50 m kira-kira selebar arena lomba, sehingga panjang jejak yang
// terlihat di layar sebanding dengan jarak yang benar-benar ditempuh - dan
// peta berhenti "melompat" skalanya tiap kali kapal bergeser sedikit.
const MIN_BENTANG_M = 50;

const LANGKAH_GRID = [1, 2, 5, 10, 20, 50, 100, 200, 500];

// Grid halus 1 meter, digambar di bawah grid utama.
const LANGKAH_HALUS_M = 1;

// Di bawah jarak ini (piksel) garis 1 m tidak digambar. Bukan demi kecepatan,
// tapi demi keterbacaan: garis yang berhimpitan lebih rapat dari ini berubah
// jadi bidang abu-abu rata yang justru menyembunyikan jejak kapal, dan pada
// layar ber-devicePixelRatio tinggi ia memunculkan pola moire. Peta tetap
// punya grid utama, jadi tidak ada informasi yang hilang - hanya tingkat
// paling halus yang menyingkir saat sudah terlalu jauh untuk berguna.
const MIN_PIKSEL_GRID_HALUS = 6;

const WARNA = {
    gridHalus: 'rgba(255,255,255,.035)',
    grid: 'rgba(255,255,255,.10)',
    sumbu: 'rgba(255,255,255,.22)',
    jejak: '#00E5FF',
    home: '#4ade80',
    kapal: '#FFD166',
    teks: 'rgba(255,255,255,.55)',
};

function buatPeta(wadah) {
    const canvas = wadah.querySelector('.trajectory-canvas');
    const kosong = wadah.querySelector('.trajectory-empty');
    const labelSkala = wadah.querySelector('.trajectory-scale');
    const labelJarak = wadah.querySelector('.trajectory-dist');
    const teksKosong = kosong ? kosong.querySelector('span') : null;
    const ctx = canvas.getContext('2d');

    let titik = [];
    let asal = null;          // {lat, lng} - titik fix pertama, jadi acuan 0,0
    let arahKapal = null;     // derajat dari KOMPAS; null = belum pernah masuk
    let arahGerak = null;     // derajat dari perpindahan GPS (course over ground)
    let tertunda = null;      // kandidat lompatan jauh yang menunggu konfirmasi
    let satTerakhir = null;   // jumlah satelit terakhir yang terlihat

    let tsTerbaru = 0;        // stempel waktu terbaru yang pernah terlihat

    let modeUji = false;
    let resetTs = 0;
    try {
        modeUji = localStorage.getItem(KUNCI_MODE_UJI) === '1';
        resetTs = Number(localStorage.getItem(KUNCI_RESET)) || 0;
    } catch (e) {
        /* localStorage diblokir: cukup jalan di mode normal */
    }

    const ambangSat = () => (modeUji ? MIN_SAT_UJI : MIN_SAT);

    try {
        const awal = JSON.parse(wadah.dataset.track || '[]');
        awal.forEach(tambahTitik);
    } catch (e) {
        /* data rusak: mulai dari kosong saja */
    }

    /** lat/lng -> meter relatif terhadap titik pertama */
    function keMeter(lat, lng) {
        const skalaLon = METER_PER_DEG_LON * Math.cos((asal.lat * Math.PI) / 180);
        return {
            x: (lng - asal.lng) * skalaLon,
            y: (lat - asal.lat) * METER_PER_DEG_LAT,
        };
    }

    function tambahTitik(p) {
        if (p.lat === null || p.lng === null) {
            return false;
        }

        // Riwayat dari server mengirim waktu sebagai 'ts'; siaran realtime
        // sudah mengirim 't' matang. Disatukan di sini supaya sisa fungsi
        // tidak perlu tahu asal titiknya dari mana.
        if (p.t === undefined && Number.isFinite(p.ts)) {
            p = { ...p, t: p.ts };
        }

        if (Number.isFinite(p.t)) {
            tsTerbaru = Math.max(tsTerbaru, p.t);

            // Titik dari sebelum operator menekan RESET. Dibandingkan dengan
            // stempel waktu KAPAL, bukan jam browser, supaya selisih jam
            // antara laptop dan server tidak ikut membuang titik yang baru.
            if (resetTs && p.t <= resetTs) {
                return false;
            }
        }

        // Haluan ikut diambil dari titik riwayat supaya panah sudah benar
        // sejak halaman dimuat, tidak menunggu siaran pertama.
        if (p.hdg !== null && p.hdg !== undefined && !Number.isNaN(p.hdg)) {
            arahKapal = p.hdg;
        }

        if (p.sat !== null && p.sat !== undefined) {
            satTerakhir = p.sat;
        }

        // Tanpa fix yang layak, lat/lng bukan posisi - cuma tebakan. Dibuang
        // di sini, sebelum sempat menjadi titik acuan 0,0 yang salah.
        if (p.sat !== null && p.sat !== undefined && p.sat < ambangSat()) {
            return false;
        }

        // Kapal diam menurut Doppler -> apa pun yang dikatakan lat/lng, itu
        // desiran. Dikembalikan lebih awal supaya tidak sempat masuk ke
        // penyaring jarak, yang bisa tertipu oleh dua desiran berurutan yang
        // kebetulan jatuh berdekatan di tempat yang salah.
        //
        // KECUALI saat peta belum punya titik sama sekali (baru dimuat, atau
        // baru saja di-RESET). Gerbang ini ada untuk mencegah titik JANGKAR
        // mengembara; kalau jangkarnya belum ada, satu titik tidak bisa
        // menumpuk jadi apa pun. Tanpa pengecualian ini peta berdiam diri
        // menulis "menunggu sinyal" sampai kapal benar-benar melaju - lama
        // sekali, dan operator tidak tahu kapalnya sudah terbaca atau belum.
        if (titik.length > 0
            && p.spd !== null && p.spd !== undefined && p.spd < DIAM_KMH) {
            tertunda = null;
            return false;
        }

        if (!asal) {
            asal = { lat: p.lat, lng: p.lng };
        }

        const m = keMeter(p.lat, p.lng);
        const t = Number.isFinite(p.t) ? p.t : Date.now();
        const akhir = titik[titik.length - 1];

        if (akhir) {
            const d = Math.hypot(m.x - akhir.x, m.y - akhir.y);

            if (d < MIN_GERAK_M) {
                // Kapal diam. Desiran GPS tidak boleh menambah titik, tapi
                // kandidat lompatan yang menunggu konfirmasi harus dilupakan:
                // posisi sudah kembali normal, jadi tadi itu memang pencilan.
                tertunda = null;
                return false;
            }

            const dt = (t - akhir.t) / 1000;      // detik sejak titik diterima

            // Dua ambang, dan yang Doppler jauh lebih tajam. MAX_LOMPAT_M
            // cuma menangkap teleportasi kasar; batas Doppler menangkap
            // desiran beberapa meter yang menyamar sebagai gerakan wajar -
            // yaitu justru yang selama ini lolos dan menumpuk jadi puluhan
            // meter jejak palsu.
            let mustahil = d > MAX_LOMPAT_M;

            // dt minimal 0,2 detik. Dua pembacaan yang tiba nyaris bersamaan
            // (jam sama, atau created_at hilang sehingga dua-duanya memakai
            // jam browser) membuat batas Doppler runtuh jadi sebesar margin
            // saja, lalu SEMUA gerakan wajar ikut dicurigai. Lebih baik
            // melewatkan pemeriksaan daripada memeriksanya dengan pembagi
            // yang tidak bermakna - MAX_LOMPAT_M masih berjaga di situ.
            if (!mustahil && p.spd !== null && p.spd !== undefined && dt >= 0.2) {
                const maksMeter = (p.spd / 3.6) * dt * TOLERANSI_DOPPLER
                    + MARGIN_DOPPLER_M;
                mustahil = d > maksMeter;
            }

            if (mustahil) {
                // Jangan langsung dibuang: GPS yang baru dapat fix ulang
                // memang melompat sekali, dan itu perpindahan sungguhan.
                // Diterima hanya kalau beberapa pembacaan berturut-turut
                // sepakat menunjuk tempat yang kira-kira sama.
                const sepakat = tertunda
                    && Math.hypot(m.x - tertunda.x, m.y - tertunda.y) < MIN_GERAK_M * 2;

                if (!sepakat) {
                    tertunda = { x: m.x, y: m.y, n: 1 };
                    return false;
                }

                tertunda.x = m.x;
                tertunda.y = m.y;
                tertunda.n += 1;

                if (tertunda.n < KONFIRMASI_MIN) {
                    return false;
                }
            }
        }

        if (akhir) {
            // Arah gerak sebenarnya, dihitung dari dua posisi yang sudah lolos
            // saringan. Dipakai untuk panah kapal karena kompas magnetik di
            // kapal ini terbukti tidak dapat dipercaya: pada pengukuran di
            // luar ruangan, GPS mencatat gerak 262 derajat (barat) sementara
            // kompas melapor 86 derajat (timur) - dan selisihnya tidak tetap,
            // kadang 14 derajat kadang 176, ciri magnetometer yang belum
            // dikalibrasi. Panah yang melawan garis jejaknya sendiri lebih
            // menyesatkan daripada tidak ada panah sama sekali.
            //
            // atan2(dx, dy): dx ke timur, dy ke utara -> 0 derajat = utara,
            // 90 = timur, searah jarum jam. Sama dengan acuan kompas.
            const busur = (Math.atan2(m.x - akhir.x, m.y - akhir.y) * 180) / Math.PI;
            arahGerak = (busur + 360) % 360;
        }

        tertunda = null;
        titik.push({ x: m.x, y: m.y, t });
        return true;
    }

    /**
     * Haluan diperbarui TERPISAH dari posisi.
     *
     * Sebelumnya keduanya menempel, sehingga saat GPS belum fix panah kapal
     * membeku di nilai lamanya (awalnya 0 derajat = menghadap utara)
     * sementara kartu kompas terus berubah. Itulah sebabnya kompas bisa
     * menunjuk selatan sementara panah di peta tetap menghadap ke atas.
     */
    function perbaruiArah(hdg) {
        if (hdg === null || hdg === undefined || Number.isNaN(hdg)) {
            return;
        }
        if (hdg === arahKapal) {
            return;
        }
        arahKapal = hdg;
        gambar();
    }

    function panjangJejak() {
        let total = 0;
        for (let i = 1; i < titik.length; i++) {
            total += Math.hypot(titik[i].x - titik[i - 1].x, titik[i].y - titik[i - 1].y);
        }
        return total;
    }

    function gambar() {
        const rasio = window.devicePixelRatio || 1;
        const w = wadah.clientWidth;
        const h = wadah.clientHeight;

        if (w === 0 || h === 0) {
            return;
        }

        canvas.width = w * rasio;
        canvas.height = h * rasio;
        canvas.style.width = `${w}px`;
        canvas.style.height = `${h}px`;
        ctx.setTransform(rasio, 0, 0, rasio, 0, 0);
        ctx.clearRect(0, 0, w, h);

        const adaJejak = titik.length > 0;
        kosong.hidden = adaJejak;

        if (!adaJejak) {
            // Pesan yang menyebut angka. "Menunggu sinyal GPS" saja membuat
            // orang mengira dashboardnya rusak, padahal modulnya memang belum
            // cukup satelit - dan tanpa angka pembandingnya tidak ada yang
            // tahu masih kurang berapa.
            if (teksKosong) {
                teksKosong.textContent = satTerakhir !== null && satTerakhir < ambangSat()
                    ? `Sinyal GPS lemah: ${satTerakhir} satelit, butuh ${ambangSat()}`
                    : 'Menunggu sinyal GPS';
            }
            labelSkala.textContent = '';
            labelJarak.textContent = '';
            return;
        }

        // --- muat seluruh jejak ke dalam kotak, skala SAMA di kedua sumbu ---
        // Kalau meter-per-piksel berbeda antar sumbu, lingkaran jadi lonjong
        // dan belokan siku-siku tidak terlihat siku-siku. Itu justru informasi
        // utama yang dicari orang dari peta lintasan.
        const xs = [0, ...titik.map((p) => p.x)];
        const ys = [0, ...titik.map((p) => p.y)];
        const cx = (Math.min(...xs) + Math.max(...xs)) / 2;
        const cy = (Math.min(...ys) + Math.max(...ys)) / 2;
        const bentang = Math.max(
            Math.max(...xs) - Math.min(...xs),
            Math.max(...ys) - Math.min(...ys),
            MIN_BENTANG_M,
        ) * 1.2;

        const tepi = 10;
        const skala = (Math.min(w, h) - tepi * 2) / bentang;   // piksel per meter
        const ox = w / 2;
        const oy = h / 2;

        // y dibalik: meter ke utara bertambah ke ATAS, piksel bertambah ke bawah
        const px = (x) => ox + (x - cx) * skala;
        const py = (y) => oy - (y - cy) * skala;

        // --- grid meter, dua tingkat ---
        // Halus 1 m untuk membaca jarak pendek, tebal (10 m, 20 m, ...) supaya
        // mata tetap punya pegangan saat lintasannya panjang. Tanpa yang tebal,
        // grid 1 m di peta 50 m tidak bisa dihitung dengan mata.
        const langkah = LANGKAH_GRID.find((s) => bentang / s <= 8) ?? LANGKAH_GRID.at(-1);

        /** gambar satu set garis grid dengan jarak `jarak` meter */
        function garisGrid(jarak) {
            ctx.beginPath();
            for (let gx = Math.floor((cx - bentang / 2) / jarak) * jarak; gx <= cx + bentang / 2; gx += jarak) {
                ctx.moveTo(px(gx), 0);
                ctx.lineTo(px(gx), h);
            }
            for (let gy = Math.floor((cy - bentang / 2) / jarak) * jarak; gy <= cy + bentang / 2; gy += jarak) {
                ctx.moveTo(0, py(gy));
                ctx.lineTo(w, py(gy));
            }
            ctx.stroke();
        }

        ctx.lineWidth = 1;

        // `skala` adalah piksel per meter, jadi ia sekaligus jarak layar
        // antar garis 1 m.
        const halusTerbaca = LANGKAH_HALUS_M * skala >= MIN_PIKSEL_GRID_HALUS
            && langkah > LANGKAH_HALUS_M;

        if (halusTerbaca) {
            ctx.strokeStyle = WARNA.gridHalus;
            garisGrid(LANGKAH_HALUS_M);
        }

        ctx.strokeStyle = WARNA.grid;
        garisGrid(langkah);

        // --- sumbu melalui titik start ---
        ctx.strokeStyle = WARNA.sumbu;
        ctx.beginPath();
        ctx.moveTo(px(0), 0);
        ctx.lineTo(px(0), h);
        ctx.moveTo(0, py(0));
        ctx.lineTo(w, py(0));
        ctx.stroke();

        // --- penanda mata angin ---
        // Peta ini SELALU utara-di-atas dan tidak pernah berputar mengikuti
        // haluan kapal (py() membalik sumbu y, itu satu-satunya rotasi yang
        // ada). Labelnya digambar supaya orientasi itu bisa dipastikan sendiri
        // oleh operator, bukan sekadar dipercaya - dan supaya panah kapal yang
        // menyimpang dari kompas langsung ketahuan.
        ctx.fillStyle = WARNA.teks;
        ctx.font = '11px system-ui, -apple-system, sans-serif';

        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillText('N', w / 2, 4);

        ctx.textBaseline = 'bottom';
        ctx.fillText('S', w / 2, h - 4);

        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText('W', 4, h / 2);

        ctx.textAlign = 'right';
        ctx.fillText('E', w - 4, h / 2);

        // --- jejak ---
        if (titik.length > 1) {
            ctx.strokeStyle = WARNA.jejak;
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            ctx.beginPath();
            titik.forEach((p, i) => {
                const X = px(p.x);
                const Y = py(p.y);
                if (i === 0) ctx.moveTo(X, Y);
                else ctx.lineTo(X, Y);
            });
            ctx.stroke();
        }

        // --- titik start ---
        ctx.strokeStyle = WARNA.home;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(px(0), py(0), 5, 0, Math.PI * 2);
        ctx.moveTo(px(0) - 9, py(0));
        ctx.lineTo(px(0) + 9, py(0));
        ctx.moveTo(px(0), py(0) - 9);
        ctx.lineTo(px(0), py(0) + 9);
        ctx.stroke();

        // --- kapal: segitiga, bukan titik ---
        // Arah hadap adalah separuh informasi yang dicari operator; titik bulat
        // akan membuatnya menebak kapal menghadap ke mana.
        const akhir = titik[titik.length - 1];
        const X = px(akhir.x);
        const Y = py(akhir.y);

        ctx.fillStyle = WARNA.kapal;

        // Arah gerak GPS didahulukan; kompas hanya cadangan saat kapal belum
        // pernah berpindah cukup jauh untuk menghitungnya.
        const sudutKapal = arahGerak !== null ? arahGerak : arahKapal;

        if (sudutKapal === null) {
            // Haluan belum diketahui -> gambar bulat, JANGAN segitiga.
            // Segitiga pada 0 derajat terbaca "kapal menghadap utara", padahal
            // yang sebenarnya terjadi adalah kompasnya belum masuk. Lebih baik
            // mengaku tidak tahu daripada menunjuk arah yang salah.
            ctx.beginPath();
            ctx.arc(X, Y, 5, 0, Math.PI * 2);
            ctx.fill();
        } else {
            ctx.save();
            ctx.translate(X, Y);
            ctx.rotate((sudutKapal * Math.PI) / 180);   // 0 deg = utara = ke atas
            ctx.beginPath();
            ctx.moveTo(0, -9);
            ctx.lineTo(6, 7);
            ctx.lineTo(0, 3);
            ctx.lineTo(-6, 7);
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }

        // Peringatan dicetak di atas kanvas, bukan cuma di tombol: tangkapan
        // layar peta sering beredar lepas dari halamannya, dan jejak mode uji
        // tidak boleh sampai disangka lintasan sungguhan.
        if (modeUji) {
            ctx.fillStyle = '#f87171';
            ctx.font = '600 11px system-ui, -apple-system, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            ctx.fillText(
                `MODE UJI - ambang ${MIN_SAT_UJI} satelit, jejak tidak dapat dipercaya`,
                8, 8
            );
        }

        // Sebut kedua tingkatnya, kalau tidak orang akan mengira kotak halus
        // itu bernilai `langkah` dan salah membaca jarak sepuluh kali lipat.
        labelSkala.textContent = halusTerbaca
            ? `grid ${LANGKAH_HALUS_M} m / ${langkah} m`
            : `grid ${langkah} m`;
        labelJarak.textContent = `jejak ${panjangJejak().toFixed(0)} m`;
    }

    function tambah(p) {
        if (tambahTitik(p)) {
            gambar();
        }
    }

    /**
     * Tombol MODE UJI + tanda peringatannya.
     *
     * Dibuat dari JavaScript, bukan ditambahkan ke partial Blade, supaya
     * halaman yang memasang peta tidak perlu diubah satu per satu - dan
     * supaya tombol ini ikut hilang dengan sendirinya kalau berkas ini
     * dicabut nanti.
     */
    function pasangAlatPeta() {
        const bar = document.createElement('div');
        bar.className = 'trajectory-alat';
        bar.style.cssText = [
            'position:absolute', 'top:8px', 'right:8px', 'z-index:2',
            'display:flex', 'gap:6px',
        ].join(';');

        const gayaDasar = [
            'font:600 10px/1 system-ui,sans-serif', 'letter-spacing:.04em',
            'padding:5px 8px', 'border-radius:5px', 'cursor:pointer',
            'border:1px solid rgba(255,255,255,.18)',
            'background:rgba(255,255,255,.06)', 'color:rgba(255,255,255,.55)',
        ].join(';');

        // ---------------- RESET (khusus halaman admin) ----------------
        // Penandanya datang dari Blade lewat data-boleh-reset, bukan ditebak
        // dari alamat URL: tata letak rute bisa berubah, sementara halaman
        // yang memasang peta tahu persis siapa penggunanya.
        const bolehReset = wadah.dataset.bolehReset === '1';

        const tombolReset = document.createElement('button');
        tombolReset.type = 'button';
        tombolReset.className = 'trajectory-reset';
        tombolReset.style.cssText = gayaDasar;
        tombolReset.textContent = 'RESET';
        tombolReset.title = 'Kosongkan jejak di peta. Data di database tidak dihapus.';

        let menungguPembenaran = null;

        function batalkanPembenaran() {
            clearTimeout(menungguPembenaran);
            menungguPembenaran = null;
            tombolReset.textContent = 'RESET';
            tombolReset.style.cssText = gayaDasar;
        }

        tombolReset.addEventListener('click', () => {
            if (!menungguPembenaran) {
                // Klik pertama cuma bertanya. Jejak satu babak lomba tidak
                // boleh lenyap karena tangan tergelincir.
                tombolReset.textContent = 'KLIK LAGI?';
                tombolReset.style.cssText = gayaDasar
                    + ';border-color:#fbbf24;background:rgba(251,191,36,.15);color:#fcd34d';
                menungguPembenaran = setTimeout(batalkanPembenaran, JEDA_PEMBENARAN_MS);
                return;
            }

            batalkanPembenaran();

            // Dipatok ke stempel waktu terbaru yang PERNAH TERLIHAT, bukan
            // Date.now(): kalau jam laptop meleset dari jam server, memakai
            // jam browser bisa ikut membuang titik-titik yang baru datang.
            resetTs = tsTerbaru || Date.now();
            try {
                localStorage.setItem(KUNCI_RESET, String(resetTs));
            } catch (e) {
                /* localStorage diblokir: reset berlaku sampai halaman ditutup */
            }

            titik = [];
            asal = null;
            tertunda = null;
            arahGerak = null;   // dihitung dari jejak, jadi ikut hangus
            gambar();
        });

        // ---------------- MODE UJI ----------------
        const tombol = document.createElement('button');
        tombol.type = 'button';
        tombol.className = 'trajectory-uji';
        tombol.style.cssText = gayaDasar;

        function segarkanTampilanTombol() {
            tombol.textContent = modeUji ? '● MODE UJI' : 'MODE UJI';
            tombol.title = modeUji
                ? `Ambang diturunkan ke ${MIN_SAT_UJI} satelit. Jejak TIDAK dapat dipercaya - matikan saat lomba.`
                : `Turunkan ambang ke ${MIN_SAT_UJI} satelit untuk menguji di dalam ruangan.`;
            tombol.style.borderColor = modeUji ? '#f87171' : 'rgba(255,255,255,.18)';
            tombol.style.background = modeUji ? 'rgba(248,113,113,.15)' : 'rgba(255,255,255,.06)';
            tombol.style.color = modeUji ? '#fca5a5' : 'rgba(255,255,255,.55)';
        }

        tombol.addEventListener('click', () => {
            modeUji = !modeUji;
            try {
                localStorage.setItem(KUNCI_MODE_UJI, modeUji ? '1' : '0');
            } catch (e) {
                /* localStorage diblokir: pilihan berlaku sampai halaman ditutup */
            }

            // Titik yang sudah diterima disaring dengan ambang LAMA, jadi
            // jejaknya harus dibangun ulang dari nol - kalau tidak, hasilnya
            // campuran dua aturan yang tidak berarti apa-apa.
            titik = [];
            asal = null;
            tertunda = null;
            arahGerak = null;   // dihitung dari jejak, jadi ikut hangus
            try {
                JSON.parse(wadah.dataset.track || '[]').forEach(tambahTitik);
            } catch (e) {
                /* data rusak: mulai dari kosong saja */
            }

            segarkanTampilanTombol();
            gambar();
        });

        segarkanTampilanTombol();

        if (getComputedStyle(wadah).position === 'static') {
            wadah.style.position = 'relative';
        }

        if (bolehReset) {
            bar.appendChild(tombolReset);
        }
        bar.appendChild(tombol);
        wadah.appendChild(bar);
    }

    if (typeof document !== 'undefined' && wadah.appendChild) {
        pasangAlatPeta();
    }

    return { gambar, tambah, perbaruiArah };
}

document.addEventListener('DOMContentLoaded', () => {
    const wadah = Array.from(document.querySelectorAll('[data-trajectory]'));
    if (wadah.length === 0) {
        return;
    }

    const peta = wadah.map(buatPeta);
    peta.forEach((p) => p.gambar());

    // Ukuran kanvas ikut ukuran kartu, jadi harus digambar ulang saat berubah.
    const amati = new ResizeObserver(() => peta.forEach((p) => p.gambar()));
    wadah.forEach((el) => amati.observe(el));

    setTimeout(() => {
        if (!window.Echo) {
            return;
        }
        window.Echo.channel('sensors').listen('SensorDataUpdated', (e) => {
            const d = e.sensorData;

            // Haluan diperbarui LEBIH DULU dan tanpa syarat GPS. Kompas dan
            // GPS adalah dua sensor terpisah: kompas tetap benar walau GPS
            // belum dapat fix, jadi panah kapal tidak boleh ikut membeku
            // hanya karena posisinya belum diketahui.
            if (d.heading !== null && d.heading !== undefined) {
                peta.forEach((p) => p.perbaruiArah(parseFloat(d.heading)));
            }

            if (d.latitude === null || d.longitude === null) {
                return;      // GPS belum fix - jangan tarik garis ke 0,0
            }

            peta.forEach((p) => p.tambah({
                lat: parseFloat(d.latitude),
                lng: parseFloat(d.longitude),
                sat: d.satellites ?? null,
                spd: d.speed !== null && d.speed !== undefined
                    ? parseFloat(d.speed)      // km/jam, sama dengan kolom database
                    : null,
                // Waktu kapal, bukan waktu browser: jeda unggah yang tersendat
                // akan membuat batas Doppler salah hitung kalau dipakai
                // Date.now(). Kalau created_at tak terbaca, tambahTitik()
                // sendiri yang jatuh ke jam browser.
                t: d.created_at ? Date.parse(d.created_at) : undefined,
            }));
        });
    }, 1000);
});
