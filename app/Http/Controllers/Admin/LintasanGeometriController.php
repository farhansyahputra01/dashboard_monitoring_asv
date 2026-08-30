<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penyimpan geometri lintasan hasil sunting admin.
 *
 * Peta lintasan digambar dari public/data/lintasan.json, dan berkas itu juga
 * disalin ke kapal - dipakai menambatkan posisi tiap kali kapal melewati
 * gerbang. Jadi yang disunting di layar bukan sekadar gambar: koordinat yang
 * disimpan di sini menentukan ke mana kapal "dipindahkan" saat penambatan.
 * Itu sebabnya berkasnya divalidasi ketat dan dicadangkan sebelum ditimpa.
 *
 * Berkasnya dilayani nginx sebagai berkas statis (bukan lewat PHP), sehingga
 * pembacaan oleh browser dan oleh kapal sama-sama murah.
 */
class LintasanGeometriController extends Controller
{
    /**
     * Batas kewarasan koordinat. Arena lomba 30 x 30 m; menerima angka di luar
     * itu berarti menyimpan penambat yang akan melempar kapal keluar peta.
     */
    private const MAKS_METER = 200.0;

    public function simpan(Request $request): JsonResponse
    {
        $path = public_path('data/lintasan.json');

        if (!is_file($path)) {
            return response()->json([
                'ok' => false,
                'message' => 'Berkas geometri tidak ditemukan: ' . $path,
            ], 500);
        }

        $asli = json_decode((string)file_get_contents($path), true);

        if (!is_array($asli)) {
            return response()->json([
                'ok' => false,
                'message' => 'Berkas geometri yang ada tidak bisa dibaca sebagai JSON.',
            ], 500);
        }

        $kunci = strtoupper((string)$request->input('lintasan'));

        if (!in_array($kunci, ['A', 'B'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Lintasan harus A atau B.',
            ], 422);
        }

        try {
            $baru = $this->bersihkan($request->input('data', []));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Bagian yang TIDAK disunting di layar (nama, catatan, kunci lain yang
        // mungkin ditambahkan belakangan) dipertahankan apa adanya. Menulis
        // ulang seluruh objek dari masukan browser akan menghapus diam-diam
        // apa pun yang belum dikenal editor.
        $lama = $asli['lintasan'][$kunci] ?? [];

        // Catatan cara pakai di dalam acuan_gps ikut dipertahankan: ia
        // penjelasan untuk manusia yang membuka berkasnya langsung, dan
        // editor tidak pernah mengirimkannya kembali.
        if (isset($baru['acuan_gps'], $lama['acuan_gps']['_cara_pakai'])) {
            $baru['acuan_gps'] = array_merge(
                ['_cara_pakai' => $lama['acuan_gps']['_cara_pakai']],
                $baru['acuan_gps']
            );
        }

        $asli['lintasan'][$kunci] = array_merge($lama, $baru);

        // Cadangan satu tingkat. Sunting peta itu pekerjaan menit-menit
        // terakhir sebelum lomba, dan salah geser satu penanda tidak boleh
        // berarti kehilangan seluruh koordinat yang sudah diukur susah payah.
        @copy($path, public_path('data/lintasan.bak.json'));

        $json = json_encode(
            $asli,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal menulis berkas geometri. Periksa izin tulis pada '
                    . 'public/data/ (di Raspberry Pi: chown www-data).',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Geometri lintasan ' . $kunci . ' disimpan. Salin ulang '
                . 'public/data/lintasan.json ke kapal supaya keduanya sama.',
        ]);
    }

    /**
     * Terima hanya bentuk yang dikenal, dan bangun ulang strukturnya dari nol.
     *
     * Bukan sekadar validasi: nilai yang ditulis ke berkas SELALU hasil
     * bentukan ulang di sini, tidak pernah potongan masukan mentah. Berkas ini
     * dilayani ke semua penonton dashboard dan dibaca kapal - menyalin isi
     * request apa adanya ke dalamnya adalah cara termudah menaruh sesuatu yang
     * tidak diinginkan di sana.
     */
    private function bersihkan($data): array
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Data geometri kosong.');
        }

        $hasil = [];

        if (isset($data['start'])) {
            [$x, $y] = $this->titik($data['start'], 'start');
            $hasil['start'] = [
                'x' => $x,
                'y' => $y,
                'heading_deg' => $this->angka($data['start']['heading_deg'] ?? 0, 0, 360, 'heading start'),
            ];
        }

        if (isset($data['kolam'])) {
            $hasil['kolam'] = $this->daftarTitik($data['kolam'], 'kolam', 3, 200);
        }

        if (isset($data['docking_biru'])) {
            $hasil['docking_biru'] = $this->daftarTitik($data['docking_biru'], 'docking_biru', 0, 10);
        }

        foreach (['kotak_biru', 'kotak_hijau'] as $nama) {
            if (isset($data[$nama])) {
                $hasil[$nama] = $this->titik($data[$nama], $nama);
            }
        }

        if (isset($data['acuan_gps'])) {
            $hasil['acuan_gps'] = $this->acuan($data['acuan_gps']);
        }

        if (isset($data['gerbang'])) {
            if (!is_array($data['gerbang'])) {
                throw new \InvalidArgumentException('Gerbang harus berupa daftar.');
            }

            if (count($data['gerbang']) > 30) {
                throw new \InvalidArgumentException('Gerbang terlalu banyak (maksimal 30).');
            }

            $gerbang = [];
            $no = 1;

            foreach ($data['gerbang'] as $g) {
                if (!is_array($g) || !isset($g['merah'], $g['hijau'])) {
                    throw new \InvalidArgumentException('Tiap gerbang butuh titik merah dan hijau.');
                }

                $gerbang[] = [
                    // Nomor ditulis ulang berurutan, tidak diambil dari browser.
                    // Kapal mencocokkan pair_count dengan nomor ini; nomor yang
                    // bolong atau kembar akan membuat penambatan meleset ke
                    // gerbang yang salah.
                    'no' => $no++,
                    'merah' => $this->titik($g['merah'], 'gerbang merah'),
                    'hijau' => $this->titik($g['hijau'], 'gerbang hijau'),
                ];
            }

            $hasil['gerbang'] = $gerbang;
        }

        if (!$hasil) {
            throw new \InvalidArgumentException('Tidak ada bagian geometri yang dikenali.');
        }

        return $hasil;
    }

    /**
     * Titik acuan yang mengikat koordinat peta ke koordinat bumi.
     *
     * lat/lon boleh kosong - itu keadaan wajar sebelum diukur. Yang TIDAK
     * boleh adalah angka yang ada tapi ngawur, karena kapal memakainya untuk
     * membentuk seluruh kerangka peta: satu digit lat yang salah salin
     * memindahkan arena puluhan kilometer.
     */
    private function acuan($nilai): array
    {
        if (!is_array($nilai)) {
            throw new \InvalidArgumentException('Titik acuan tidak terbaca.');
        }

        $daftar = is_array($nilai['titik'] ?? null) ? $nilai['titik'] : [];

        if (count($daftar) > 8) {
            throw new \InvalidArgumentException('Titik acuan terlalu banyak (maksimal 8).');
        }

        $titik = [];

        foreach ($daftar as $t) {
            if (!is_array($t) || !isset($t['x'], $t['y'])) {
                continue;
            }

            [$x, $y] = $this->titik([$t['x'], $t['y']], 'titik acuan');

            $lat = $t['lat'] ?? null;
            $lon = $t['lon'] ?? null;

            // Keduanya harus ada, atau keduanya kosong. Satu saja tidak
            // menentukan letak apa pun, dan menyimpannya setengah hanya
            // menunda kebingungan.
            $adaLat = $lat !== null && $lat !== '';
            $adaLon = $lon !== null && $lon !== '';

            if ($adaLat !== $adaLon) {
                throw new \InvalidArgumentException(
                    'Titik acuan harus punya latitude DAN longitude, atau kosong keduanya.'
                );
            }

            $titik[] = [
                'nama' => mb_substr(trim((string)($t['nama'] ?? 'acuan')), 0, 40),
                'x' => $x,
                'y' => $y,
                'lat' => $adaLat ? $this->angka($lat, -90, 90, 'latitude acuan', 7) : null,
                'lon' => $adaLon ? $this->angka($lon, -180, 180, 'longitude acuan', 7) : null,
            ];
        }

        $bearing = $nilai['bearing_sumbu_deg'] ?? null;

        return [
            'bearing_sumbu_deg' => ($bearing === null || $bearing === '')
                ? null
                : $this->angka($bearing, 0, 360, 'bearing sumbu'),
            'titik' => $titik,
        ];
    }

    private function daftarTitik($nilai, string $nama, int $minimal, int $maksimal): array
    {
        if (!is_array($nilai)) {
            throw new \InvalidArgumentException("$nama harus berupa daftar titik.");
        }

        if (count($nilai) < $minimal || count($nilai) > $maksimal) {
            throw new \InvalidArgumentException(
                "$nama harus berisi $minimal sampai $maksimal titik."
            );
        }

        return array_map(fn ($t) => $this->titik($t, $nama), array_values($nilai));
    }

    private function titik($nilai, string $nama): array
    {
        if (isset($nilai['x'], $nilai['y'])) {
            $nilai = [$nilai['x'], $nilai['y']];
        }

        if (!is_array($nilai) || count($nilai) < 2) {
            throw new \InvalidArgumentException("Titik $nama tidak lengkap.");
        }

        return [
            $this->angka($nilai[0], -self::MAKS_METER, self::MAKS_METER, "$nama x"),
            $this->angka($nilai[1], -self::MAKS_METER, self::MAKS_METER, "$nama y"),
        ];
    }

    private function angka($nilai, float $min, float $maks, string $nama, int $desimal = 2): float
    {
        if (!is_numeric($nilai)) {
            throw new \InvalidArgumentException("$nama bukan angka.");
        }

        $n = (float)$nilai;

        if ($n < $min || $n > $maks) {
            throw new \InvalidArgumentException("$nama di luar batas wajar ($min..$maks).");
        }

        // Koordinat METER dibulatkan ke 2 desimal: editor mengunci ke kotak
        // 1 m, jadi angka di belakang koma yang panjang hanya membuat
        // berkasnya sulit dibaca manusia.
        //
        // LATITUDE/LONGITUDE WAJIB 7 DESIMAL, dan itu bukan kemewahan:
        // 2 desimal pada lintang berarti 0,01 derajat = 1,1 KILOMETER.
        // Menyimpan 1.459439 sebagai 1.46 memindahkan seluruh arena lebih
        // jauh daripada panjang lomba itu sendiri. 7 desimal = ~1 cm.
        return round($n, $desimal);
    }

    /**
     * Halaman khusus pengisian koordinat GPS tiap objek peta.
     *
     * Dipisah dari kartu peta karena daftarnya panjang: satu baris untuk
     * start, dua untuk tiap gerbang, satu untuk tiap buoy docking, dan dua
     * untuk kotak imaging - lebih dari dua puluh baris pada arena penuh.
     * Memaksakannya ke sisi kanvas membuat keduanya sama-sama sempit.
     */
    public function koordinat()
    {
        $geometri = $this->bacaGeometri();
        $setting = \App\Models\MonitoringSetting::first();

        return view('admin.monitoring.koordinat', [
            'geometri' => $geometri,
            'aktif' => request('lintasan', $setting->active_track ?? 'A'),
            'daftar' => [
                'A' => $this->daftarObjek($geometri, 'A'),
                'B' => $this->daftarObjek($geometri, 'B'),
            ],
        ]);
    }

    /**
     * Simpan lat/lon dari halaman koordinat.
     *
     * Yang disimpan hanya REF + lat/lon - koordinat x/y sengaja TIDAK ikut.
     * Ia dibaca dari objek petanya sendiri saat dipakai, sehingga menggeser
     * gerbang 6 di editor peta tidak pernah membuat acuan gerbang 6 menunjuk
     * tempat yang sudah bukan gerbang 6.
     */
    public function simpanKoordinat(Request $request)
    {
        $kunci = strtoupper((string)$request->input('lintasan'));

        if (!in_array($kunci, ['A', 'B'], true)) {
            return back()->withErrors(['lintasan' => 'Lintasan harus A atau B.']);
        }

        $asli = $this->bacaGeometri();

        if (!isset($asli['lintasan'][$kunci])) {
            return back()->withErrors(['lintasan' => 'Lintasan tidak ada di berkas geometri.']);
        }

        $titik = [];
        $terisi = 0;

        foreach ((array)$request->input('titik', []) as $ref => $isi) {
            $lat = trim((string)($isi['lat'] ?? ''));
            $lon = trim((string)($isi['lon'] ?? ''));

            if ($lat === '' && $lon === '') {
                continue;
            }

            // Satu tanpa yang lain tidak menunjuk tempat mana pun. Ditolak di
            // sini supaya tidak tersimpan setengah dan membingungkan nanti.
            if ($lat === '' || $lon === '') {
                return back()->withErrors([
                    'titik' => "Titik '$ref' hanya terisi salah satu dari latitude/longitude.",
                ])->withInput();
            }

            try {
                $titik[] = [
                    'ref' => $this->ref($ref),
                    'nama' => mb_substr(trim((string)($isi['nama'] ?? $ref)), 0, 40),
                    // 7 desimal, BUKAN 2. Pada lintang, 0,01 derajat = 1,1 km -
                    // pembulatan yang wajar untuk meter memindahkan seluruh
                    // arena lebih jauh daripada panjang lombanya sendiri.
                    'lat' => $this->angka($lat, -90, 90, "latitude $ref", 7),
                    'lon' => $this->angka($lon, -180, 180, "longitude $ref", 7),
                ];
                $terisi++;
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['titik' => $e->getMessage()])->withInput();
            }
        }

        $bearing = trim((string)$request->input('bearing_sumbu_deg', ''));
        $lama = $asli['lintasan'][$kunci]['acuan_gps'] ?? [];

        $acuan = [
            'bearing_sumbu_deg' => $bearing === ''
                ? null
                : $this->angka($bearing, 0, 360, 'arah sumbu', 2),
            'titik' => $titik,
        ];

        if (isset($lama['_cara_pakai'])) {
            $acuan = array_merge(['_cara_pakai' => $lama['_cara_pakai']], $acuan);
        }

        $asli['lintasan'][$kunci]['acuan_gps'] = $acuan;

        if (!$this->tulisGeometri($asli)) {
            return back()->withErrors([
                'titik' => 'Gagal menulis berkas geometri. Periksa izin tulis public/data/.',
            ]);
        }

        return redirect()
            ->route('admin.monitoring.koordinat', ['lintasan' => $kunci])
            ->with('success', "$terisi titik koordinat lintasan $kunci disimpan. "
                . 'Salin ulang public/data/lintasan.json ke kapal.');
    }

    /**
     * Daftar objek peta yang bisa diberi koordinat, urut seperti urutan lomba.
     *
     * Tiap baris membawa REF (penunjuk objek), nama yang terbaca manusia, dan
     * koordinat petanya - yang terakhir untuk ditampilkan saja, tidak disimpan
     * bersama koordinat GPS.
     */
    private function daftarObjek(array $geometri, string $kunci): array
    {
        $p = $geometri['lintasan'][$kunci] ?? [];
        $sudah = [];

        foreach (($p['acuan_gps']['titik'] ?? []) as $t) {
            if (!empty($t['ref'])) {
                $sudah[$t['ref']] = $t;
            }
        }

        $baris = [];

        $tambah = function ($ref, $nama, $koord, $jenis) use (&$baris, $sudah) {
            $baris[] = [
                'ref' => $ref,
                'nama' => $nama,
                'jenis' => $jenis,
                'x' => $koord[0] ?? null,
                'y' => $koord[1] ?? null,
                'lat' => $sudah[$ref]['lat'] ?? null,
                'lon' => $sudah[$ref]['lon'] ?? null,
            ];
        };

        if (isset($p['start'])) {
            $tambah('start', 'Start', [$p['start']['x'], $p['start']['y']], 'start');
        }

        // SATU baris per gerbang, di titik TENGAH antara bola merah dan hijau.
        //
        // Bukan dua baris terpisah, karena koordinat lomba diukur di tengah
        // gerbang - bukan di masing-masing bola. Titik tengah juga persis
        // tempat kapal berada saat melewati gerbang itu, sehingga acuan GPS-nya
        // sejalan dengan penambat posisi yang dipakai kapal.
        foreach (($p['gerbang'] ?? []) as $g) {
            $no = $g['no'] ?? 0;

            if (!isset($g['merah'], $g['hijau'])) {
                continue;
            }

            $tambah(
                "gerbang.$no",
                "Gerbang $no - titik tengah",
                [
                    round(($g['merah'][0] + $g['hijau'][0]) / 2, 2),
                    round(($g['merah'][1] + $g['hijau'][1]) / 2, 2),
                ],
                'gerbang'
            );
        }

        foreach (($p['docking_biru'] ?? []) as $i => $d) {
            $n = $i + 1;
            $tambah("docking.$n", "Buoy docking $n", $d, 'biru');
        }

        if (isset($p['kotak_biru'])) {
            $tambah('kotak_biru', 'Kotak imaging bawah air', $p['kotak_biru'], 'biru');
        }

        if (isset($p['kotak_hijau'])) {
            $tambah('kotak_hijau', 'Kotak imaging permukaan', $p['kotak_hijau'], 'hijau');
        }

        return $baris;
    }

    /**
     * Terima hanya bentuk ref yang dikenal.
     *
     * Ref datang dari nama field HTML, jadi ia masukan pengguna - dan ia ikut
     * tertulis ke berkas yang dilayani ke semua penonton dashboard.
     */
    private function ref(string $ref): string
    {
        // gerbang.6      -> titik tengah (yang dipakai halaman koordinat)
        // gerbang.6.merah -> satu bola saja (bentuk lama, tetap diterima)
        $pola = '/^(start|kotak_biru|kotak_hijau|docking\.\d{1,2}'
            . '|gerbang\.\d{1,2}(\.(merah|hijau))?)$/';

        if (!preg_match($pola, $ref)) {
            throw new \InvalidArgumentException("Penunjuk objek tidak dikenal: $ref");
        }

        return $ref;
    }

    private function bacaGeometri(): array
    {
        $isi = json_decode((string)@file_get_contents(public_path('data/lintasan.json')), true);

        return is_array($isi) ? $isi : [];
    }

    private function tulisGeometri(array $data): bool
    {
        $path = public_path('data/lintasan.json');
        @copy($path, public_path('data/lintasan.bak.json'));

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $json !== false && file_put_contents($path, $json . "\n") !== false;
    }
}
