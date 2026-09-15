# Uji Lapangan 12 September 2026 — Checklist

> **Diperbarui 12 Sep sore, sesudah uji air kedua.** Bagian 0a = temuan uji
> pertama; bagian 0b = tiga perubahan 12 Sep pagi yang ternyata **memperburuk**
> dan sudah dikembalikan. Perintah jalan di bagian C sudah direvisi lagi.

---

## 0c. Uji air 15 Sep: belok kanan sesudah gerbang 7

> **Perubahan di bagian ini ada di folder TERPISAH: `G:\ASV\asv2` (di Jetson
> rencananya `~/asv2`; di git = branch `asv2` repo asv, dipasang sebagai worktree).** `G:\ASV\asv` / `~/asv` tetap versi 12 Sep yang sudah
> terbukti — tidak disentuh. Jalankan dari `~/asv2` untuk mencoba; kalau
> hasilnya lebih buruk, kembali ke `~/asv` tanpa perlu mengubah apa pun.
> `lintasan.json` di `asv2` = `public/data/lintasan.json` (bearing 90, titik
> GPS kosong); yang di `asv` masih versi lama berisi titik GPS.

Hasil: lintasan B mantap; lintasan A lolos 7 gerbang (belok kiri sesudah
gerbang 3 benar), tapi sesudah gerbang 7 kapal **belok kanan** padahal tabel
petunjuk berkata kiri → nyasar. Di B sesudah gerbang 7 kapal juga terlihat
"langsung belok kanan" begitu keluar gerbang (di B arahnya kebetulan benar).

| Gejala | Penyebab di kode | Sekarang |
|---|---|---|
| Langsung berbelok begitu keluar gerbang | *belok proaktif*: di tikungan (petunjuk kiri/kanan) jeda maju-pelan `TEMP_FORWARD` 1,5 s **dilewati**, pivot mulai saat haluan masih sejajar gerbang | jeda maju pelan **selalu** berlaku: `CARI_MAJU_AWAL` 2 s @ 63 (`--cari-maju-sec 2.0`, `--cari-maju-rasio 0.7`), baru tangga sapuan. `--cari-maju-sec 0` = perilaku lama |
| Belok ke sisi yang salah walau tabel bilang kiri | urutan prioritas lama: **bukti arah** (bola gerbang yang *baru dilewati* keluar bingkai lewat kanan → "kanan") → kunci haluan peta → **arah koordinat tanpa cek posisi dipercaya** (`arah_pencarian` menunjuk ke `pair_count+1` walau DR sudah hanyut / hitungan tertinggal) → tabel paling akhir | di tikungan (tabel kiri/kanan): **bukti arah diabaikan**, koordinat hanya kalau posisi dipercaya (`HALUAN_*`), selain itu **sapu ke arah tabel** (`CARI_KIRI`). `arah_pencarian` kini juga memakai `posisi_dipercaya()` + `gerbang_sasaran()` |
| Sapuan sisi seberang (`CARI_KANAN_BALIK`) tetap dicoba 2 s | disengaja (tabel bisa salah kalau hitungan gerbang meleset) | tidak berubah; kalau hitungan gerbang di dashboard tidak sampai 7, bacanya **D2 dulu** |

Urutan waktu sesudah bola hilang sekarang (detik sejak bola terakhir):
`KUNCI_GERBANG` 2,5 s (BASE 150) → `CARI_MAJU_AWAL` 0–2 s @ 63 → `CARI_KIRI`
(busur) 2–5 s → `CARI_MAJU` 5–6,5 s → `CARI_KIRI_2` (pivot) 6,5–9,5 s →
`CARI_KANAN_BALIK` 9,5–11,5 s → sapu terus. Kalau peta dipercaya, sesudah
`CARI_MAJU_AWAL` yang muncul `HALUAN_PIVOT_KIRI` / `HALUAN_LURUS`.

Cek dari log lapangan berikutnya: baris `[LOST]` pertama sesudah gerbang 7
harus `CARI_MAJU_AWAL`, lalu `CARI_KIRI`/`HALUAN_*KIRI` (A) atau
`CARI_KANAN`/`HALUAN_*KANAN` (B). Kalau `CARI_KANAN` muncul di A → tabel
tidak terbaca: pastikan `lintasan.json` di Jetson = `public/data/lintasan.json`
(`bearing_sumbu_deg: 90`, `titik: []`) dan dashboard memilih lintasan A.

### 0c-2. Foto kotak biru dari kamera bawah air (ketentuan lomba — ada di `asv` DAN `asv2`)

Keadaan sebelum 15 Sep: fase `UNDERWATER_IMG` / `SURFACE_IMG` mendeteksi
kotak biru/hijau dengan kamera **atas** dan menyimpan frame kamera atas itu
juga ke `mission_images/`. Kamera bawah hanya disiarkan ke `/stream/bawah`.

Sekarang (master `asv` commit `e418fb1`, dan `asv2`): deteksi dan kemudi tetap kamera atas; saat `area >=
--box-area`:

| Kotak | Fase | Foto dari |
|---|---|---|
| biru (bawah air) | `UNDERWATER_IMG` | **kamera bawah** — `blue_<ts>.jpg`, log `BLUE_IMAGE_CAPTURED (..., kamera bawah)` |
| hijau (permukaan) | `SURFACE_IMG` | kamera atas (tidak berubah) — `green_<ts>.jpg` |

Kalau `--source-bawah` tidak dipasang atau kamera bawah macet (> 2 s tanpa
frame) → kotak biru pun difoto kamera atas + baris `!! FOTO BLUE: kamera
bawah tidak punya frame ...`; fase tetap lanjut. Baris "Misi aktif: ... Foto
kotak biru: KAMERA BAWAH, kotak hijau: kamera atas" saat start memastikan
kamera bawah ikut dipakai.

Yang **belum** ada dan perlu diuji sebelum diandalkan:

- `--box-area 8000` belum pernah dikalibrasi (kotak ~90×90 px di 320×240 —
  sangat dekat). Uji di darat: tekan `n` sampai `UNDERWATER_IMG`, sodorkan
  kotak, baca `area=` di log `[MISSION]`, set `--box-area` dari situ.
- Kotak tidak terlihat → kapal hanya **maju lurus** `base_speed/2` sampai
  `--phase-timeout` 60 s lalu lompat fase. Petunjuk fase di `lintasan.json`
  dan koordinat `kotak_biru`/`kotak_hijau` belum dipakai `mission_controller`.
- Fase ini belum pernah terpicu di air: hitungan gerbang belum pernah
  mencapai `--total-pairs 10`.

### 0c-3. Deploy ke Jetson: `asv2` baru + update foto di `asv` lama

Repo `G:\ASV\asv` tidak punya remote, jadi semuanya lewat `scp` dari laptop.
Dua folder di Jetson, satu yang jalan pada satu waktu.

**1. `~/asv` (lama) — hanya dua berkas yang berubah (foto kotak biru dari
kamera bawah, commit `e418fb1`):**

```bash
# dari laptop, folder G:\ASV\asv
scp mission_controller.py telemetry_motor_controller_turn_speed.py \
    <user>@<ip-jetson>:~/asv/
```

**2. `~/asv2` (baru) — seluruh folder, pertama kali:**

```bash
# dari laptop, folder G:\ASV
scp -r asv2 <user>@<ip-jetson>:~/asv2
# folder .git ikut tersalin (kecil) - tidak apa-apa, tapi worktree-nya tidak
# berfungsi di Jetson; kalau mengganggu: rm -rf ~/asv2/.git
```

Update berikutnya `asv2` cukup berkas `.py` + `lintasan.json`:

```bash
scp asv2/*.py asv2/lintasan.json <user>@<ip-jetson>:~/asv2/
```

**3. Di Jetson — titik periksa sebelum masuk air:**

```bash
# asv lama: harus ada fungsi foto kamera bawah, TIDAK ada mode pencarian baru
cd ~/asv  && grep -c "foto_kamera_bawah" telemetry_motor_controller_turn_speed.py   # >= 1
            grep -c "CARI_MAJU_AWAL"     telemetry_motor_controller_turn_speed.py   # 0

# asv2 baru: dua-duanya ada, dan lintasan.json versi repo
cd ~/asv2 && grep -c "foto_kamera_bawah" telemetry_motor_controller_turn_speed.py   # >= 1
            grep -c "CARI_MAJU_AWAL"     telemetry_motor_controller_turn_speed.py   # >= 1
            python3 -c "import json;d=json.load(open('lintasan.json'));print(d['lintasan']['A']['acuan_gps']['bearing_sumbu_deg'], d['lintasan']['A']['acuan_gps']['titik'])"   # 90 []
            python3 -c "import peta_jalur, posisi_lintasan, pelacak_gerbang, mission_controller; print('import OK')"
            python3 telemetry_motor_controller_turn_speed.py --help | grep -c cari-maju   # >= 2
```

**4. Perintah jalan** — sama persis dengan bagian C, hanya `cd`-nya yang
beda (`cd ~/asv` atau `cd ~/asv2`). `--image-dir /var/lib/asv/mission_images`
dipakai bersama oleh keduanya, jadi galeri dashboard tidak perlu diubah.
`--source-bawah` WAJIB ada — tanpa itu foto kotak biru jatuh ke kamera atas.

Baris awal yang membedakan keduanya:

```
asv : Algoritma gerbang: kunci gerbang 2.5s, ..., margin kolam 2.0 m
      Misi aktif: 10 pasang bola -> ... Foto kotak biru: KAMERA BAWAH, kotak hijau: kamera atas
asv2: Algoritma gerbang: kunci gerbang 2.5s, ..., margin kolam 2.0 m, cari: maju pelan 2.0s @63 dulu
      Misi aktif: 10 pasang bola -> ... Foto kotak biru: KAMERA BAWAH, kotak hijau: kamera atas
```

Kalau tertulis `Foto kotak biru: kamera atas (--source-bawah tidak dipasang!)`
→ berhenti, tambahkan `--source-bawah`.

### 0c-4. Kamera di dashboard lewat ngrok patah-patah (15 Sep)

Bukan FPS kendali (jendela OpenCV di kapal normal). Sejak `/stream/foto`
(commit `988ba70`), halaman yang dibuka lewat host ngrok memakai mode
**foto-tarik**: satu request HTTP per frame, baru minta lagi sesudah frame
sampai. Lajunya = 1 / (RTT ngrok + 80 ms) ≈ 2–4 fps. Ini sengaja: MJPEG lewat
ngrok halus tapi tertinggal makin jauh lalu macet (gejala 12 Sep).

Perbaikan 15 Sep (`resources/js/camera-stream.js`, `FOTO_PARALEL = 2`): dua
request berjalan bersamaan → laju ~2× (simulasi RTT 300 ms: 2,6 → 5,3 fps),
tumpukan tetap maksimum 2 frame jadi tidak bisa tertinggal jauh. Frame yang
kembalinya menyalip dibuang supaya gambar tidak mundur.

`public/build` di-ignore git → sesudah `npm run build` **salin manual**:

```bash
scp -r public/build <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/public/
```

Kalau masih kurang: coba `?stream=mjpeg` di URL ngrok + `--stream-fps 5` di
kapal; pakai kalau 3–5 menit tidak makin tertinggal. Cek juga ngrok inspector
(`http://127.0.0.1:4040` di Jetson) — respons `429` berarti ngrok gratis
membatasi laju request; turunkan `FOTO_PARALEL` ke 1 atau naikkan
`FOTO_JEDA_MS`.

---

## 0b. Uji air kedua (12 Sep): tiga perubahan yang ditarik kembali

| Gejala | Penyebab (perubahan 12 Sep pagi) | Sekarang |
|---|---|---|
| Garis gerbang / garis arah kapal hilang di kamera | overlay bersih jadi bawaan | **overlay penuh kembali bawaan**; `--kamera-bersih` untuk mode bersih |
| Kapal mundur sebentar saat mulai | `--pivot-mundur 1.4`: sisi mundur diperkuat, sisi maju jatuh di zona mati → dorongan bersih mundur | bawaan `1.0`, **jangan dipakai** sampai zona mati diukur |
| Pasangan terlihat, tiba-tiba pilih satu bola dan belok tajam | `--satu-pivot-px 70`: pasangan gagal dipasangkan *sesaat* (rasio ukuran 0,30 terlalu ketat saat mendekat menyerong) → satu bola → taksiran tengah di luar bingkai → **pivot 50%** | satu bola kembali **koreksi lembut** seperti 11 Sep (`--satu-pivot-px 0`); rasio pasangan dilonggarkan 0,30 → 0,22 |
| "Menyerah" saat tidak ada bola; RTH diam | `SEARCH_SPEED` 90 × 0,2 = **18** → di bawah zona mati ESC → thruster diam. Plus RTH menolak jalan saat posisi "tidak dipercaya" | pencarian & pulang memakai **tenaga manuver** (`--tenaga-putar`, bawaan ≥ 40%); `--motor-min` bawaan 25; RTH tetap jalan walau posisi ragu (mode `PULANG_..._RAGU`) |

Yang **dipertahankan** dari 12 Sep pagi karena terbukti perlu: gerbang dihitung
dari jarak (`--pass-jarak`), GPS mati di peta, sasaran = gerbang yang belum
terlewati, kunci haluan hanya saat posisi dipercaya, `motor_on` untuk peta
jejak, panel Kendali Kapal di Monitoring.

---

## 0a. Temuan uji air 11 Sep dan perbaikannya

| Gejala di kolam | Akar | Perbaikan |
|---|---|---|
| Lewat gerbang 2, misi masih gerbang 1 | `--pass-area 4000` **mustahil tercapai**: dua bola berjarak 2 m hanya muat bersama di bingkai pada jarak ≥ 1,66 m, dan di sana luas bola cuma ~800 px². Kamera tidak pernah "melihat" gerbang lewat | Hitung dari **jarak** (`--pass-jarak 2.2` m), bukan luas piksel |
| Lintasan B: lewat gerbang 1, lihat merah gerbang 2, malah **berputar kanan**, lalu mengikuti bola gerbang 4 | (1) `pair_count` macet 0 → sasaran peta = gerbang 1 yang sudah di belakang → kunci haluan memutar kapal balik. (2) Kerangka peta B **terputar ~70°**: tiga titik acuan GPS B saling bertentangan dengan peta (GPS: start→g1 2,3 m, peta: 7 m), dan dengan ≥ 2 titik program mengabaikan 90° | (a) Sasaran peta = gerbang pertama yang **belum terlewati menurut posisi**, bukan `pair_count+1` mentah. (b) Kunci haluan **hanya kalau posisi dipercaya** (< 12 m sejak penambat terakhir); kalau tidak, tangga sapuan lama. (c) Semua titik acuan GPS **dimatikan** di `lintasan.json`; sumbu 90° tetap |
| Trajectory kacau, RTH tak berfungsi | Titik acuan GPS = tebakan; tiap fix menarik posisi ke tempat salah | Sama dengan (c). GPS kini **tidak dipakai sama sekali**; posisi = kompas + kecepatan, ditambat tiap gerbang. RTH menolak jalan kalau posisi tidak dipercaya (`PULANG_TANPA_PETA` + alasan) |
| Belokan lebar, kapal "pivot" sambil maju, zig-zag terlewat | Lambung mono panjang; pivot ±30 (20%) terlalu lemah; dorongan mundur baling-baling ~60% dari maju → pivot "seimbang" sebenarnya mendorong maju; satu bola dikemudikan maju-belok | `--tenaga-putar 0.5` (tenaga pivot terpisah dari jelajah), `--pivot-mundur 1.4` (sisi mundur diperkuat), `--satu-pivot-px 70` (satu bola: pivot dulu kalau error besar) |
| Window OpenCV kecil | — | `--fullscreen` (bantalan ke 16:9, bola tidak lonjong) |

**Yang paling penting dari semuanya:** dengan gerbang yang sekarang terhitung,
penambat posisi bekerja, sehingga kunci haluan, clue arah, dan RTH baru punya
posisi yang benar untuk dipijak. Kemarin ketiganya berjalan di atas posisi
yang salah — dan berperilaku persis seperti yang dirancang, ke arah yang salah.

Yang diuji hari ini, semuanya **baru dan belum pernah menyentuh air**:

| # | Fitur | Flag | Matikan dengan |
|---|---|---|---|
| 1 | Tenaga thruster 20% + zona mati ESC | `--tenaga 0.2 --motor-min N` | `--tenaga 1.0` |
| 2 | Kamera bawah: MJPG sebelum frame pertama | otomatis | — (fallback otomatis) |
| 3 | Sumbu arena = 90° (timur) + cek kompas start | `lintasan.json` | — |
| 4 | Komitmen gerbang (jangan lompat ke bola jauh) | `--kunci-sec 2.5` | `--kunci-sec 0` |
| 5 | Pilih bola berbobot arah peta (dua hijau) | `--bobot-arah 0.5` | `--bobot-arah 0` |
| 6 | Kunci haluan ke peta saat bola hilang (belok proaktif dicabut 15 Sep, lihat 0c) | otomatis | `--tanpa-kunci-haluan` |
| 7 | Jalur menghindari tepi kolam | `--margin-kolam 2.0` | `--margin-kolam 0` |
| 8 | PULANG: tombol dashboard + baterai | `--batt-pulang 25` | jangan tulis flag-nya |
| 9 | Gerbang dihitung dari jarak | `--pass-jarak 2.2` | `--pass-jarak 0` (kembali ke luas — tidak disarankan) |
| 10 | Tenaga manuver (pivot, pencarian, pulang) terpisah dari jelajah | `--tenaga-putar 0.5` | hapus flag (bawaan ≥ 40%) |
| 11 | ~~Satu bola pivot~~ — DITARIK 12 Sep, bawaan mati | `--satu-pivot-px 0` | — |
| 12 | GPS diabaikan di peta | otomatis (titik acuan kosong) / `--tanpa-gps` | isi kembali `titik` di JSON |
| 13 | Monitor layar penuh | `--fullscreen` | hapus flag |
| 14 | Peta jejak GPS: titik valid hanya saat thruster hidup (`motor_on` dari kapal) | otomatis | — (program lama → saringan Doppler) |
| 15 | Kamera bersih (bawaan): 2 lingkaran bola pilihan + garis tengah + garis sasaran, tanpa teks; angka di panel "Kendali Kapal" Monitoring (`/stream/status`) | otomatis | `--hud` (teks + semua bola) |
| 16 | **Sisi aman**: hijau selalu di luar, merah di dalam — penjepit sasaran kemudi untuk semua mode. Log: `sisi-aman:geser` / `konflik` | otomatis | `--tanpa-sisi-aman`, atau kode: `git checkout sebelum-sisi-aman -- .` di `~/asv` |

**Prinsip uji: satu fitur baru per percobaan pertama.** Kalau semuanya
dinyalakan sekaligus lalu kapal berperilaku aneh, kamu tidak tahu yang mana.
Urutan yang disarankan ada di bagian D.

---

## A. Salin ke Jetson (dari laptop)

```bash
# Python - dari G:\ASV\asv
scp peta_jalur.py posisi_lintasan.py pelacak_gerbang.py stream_server.py \
    mission_controller.py telemetry_motor_controller_turn_speed.py uji_thruster.py \
    <user>@<ip-jetson>:~/asv/

# Peta - dari repo. HARUS identik dengan yang di kapal.
scp public/data/lintasan.json <user>@<ip-jetson>:~/asv/lintasan.json

# Web - Laravel
scp -r public/build <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/public/
scp routes/web.php <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/routes/
scp app/Http/Controllers/Admin/ControlController.php \
    <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/app/Http/Controllers/Admin/
scp resources/views/partials/kendali-kapal.blade.php \
    <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/resources/views/partials/
```

Lebih rapi: commit + push dari laptop, `git pull` di Jetson, lalu salin
`public/build` saja. Sesudah itu di Jetson:

```bash
cd /var/www/dashboard_monitoring_asv
php artisan migrate --force          # kolom motor_on (12 Sep) - WAJIB, kalau tidak telemetri ditolak 500
php artisan route:clear && php artisan route:cache && php artisan view:clear
```

**Titik periksa** di Jetson:

```bash
cd ~/asv && python3 -c "import peta_jalur, posisi_lintasan, pelacak_gerbang; print('import OK')"
python3 peta_jalur.py --lintasan A        # semua baris 'lurus=YA' kecuali dua kasus sudut
python3 uji_thruster.py --tabel           # tabel nilai -> us, tanpa serial
```

---

## B. Di darat, sebelum kapal masuk air

### B1. Zona mati ESC (5 menit) — baling-baling di luar air

```bash
python3 uji_thruster.py --port /dev/ttyUSB0 --tangga 5 --sampai 60
python3 uji_thruster.py --port /dev/ttyUSB0 --tangga 5 --sampai 60 --mundur
```

Catat nilai pertama yang memutar baling-baling → `N`. Ambil yang lebih besar
dari maju/mundur.

- `N < 25` → `--tenaga 0.2 --motor-min N`
- `N ≥ 30` → `--tenaga 0.3 --motor-min N` (20% terlalu sempit: BASE 30 sudah menempel zona mati)

### B2. Kompas di start

Letakkan kapal di titik start **menghadap timur** (sumbu arena). Jalankan
program (bagian C) dan baca baris pertama yang muncul:

```
[KOMPAS] start OK: selisih +4 derajat dari peta.          ← lanjut
!! KOMPAS DI START MELESET +22 derajat dari peta.          ← JANGAN MULAI
```

Kalau meleset: kapal miring, atau `--kompas-offset` salah. Semua heading
segmen dan arah pencarian bertumpu pada ini.

### B3. Kamera bawah

Di log harus ada:

```
[KAMERA] Kamera atas terbuka: index/path 0 lewat bawaan, MJPG 320x240 -> 320x240
[KAMERA] Kamera bawah terbuka: index/path 2 lewat bawaan, MJPG 320x240 -> 320x240
```

Kalau `KAMERA BAWAH GAGAL DIBUKA` → runbook-deploy-jetson, Lapisan 1–4.
Ingat: kamera kedua biasanya `/dev/video2`, bukan 1 (`v4l2-ctl --list-devices`).

---

## C. Perintah jalan

```bash
cd ~/asv
export ASV_INGEST_TOKEN='<token>'

python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 --source 0 --source-bawah 2 --no-display \
    --stream --stream-port 8000 \
    --image-dir /var/lib/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry \
    --tenaga 0.2 --motor-min <N> \
    --tenaga-putar 0.5 \
    --pass-jarak 2.2 --tanpa-gps \
    --batt-pulang 25 --batt-cutoff 15
```

`<N>` = zona mati ESC dari B1; kalau belum diukur, hapus flag-nya (bawaan 25).
**Jangan** menambah `--pivot-mundur` atau `--satu-pivot-px` — keduanya penyebab
gejala 12 Sep. Tambahkan `--fullscreen` (dan hapus `--no-display`) kalau ada
monitor tercolok.

Baris yang harus muncul di awal:

```
Tenaga motor: 20% (BASE 150 -> 30, MAX 255 -> 51), zona mati ESC: ..., ambang diam peta: ...
Putaran: tenaga pivot 50%, sisi mundur x1.00, satu bola selalu maju-belok
Algoritma gerbang: kunci gerbang 2.5s, bobot arah 0.50, kunci haluan peta AKTIF, margin kolam 2.0 m, pulang otomatis <25%
[POSISI] Kerangka dari sumbu manual: start di (3.0, 5.0), sumbu +y menghadap 90.0 derajat. GPS TIDAK dipakai ...
Gerbang dihitung LEWAT saat kedua bola rata-rata <= 2.2 m (r >= 12 px, luas ~452 px^2). --pass-area 4000 tidak dipakai.
[KOMPAS] start OK: ...
```

**Baris `Gerbang dihitung LEWAT ...` wajib ada.** Kalau yang muncul "dari luas
piksel >= 4000", gerbang tidak akan pernah terhitung lagi seperti kemarin.

Kapal **mulai dalam keadaan berhenti**. Tekan MULAI di dashboard.

---

## D. Urutan percobaan di air

### D1. Hanya tenaga (fitur lama, pelan)

Tambahkan `--kunci-sec 0 --bobot-arah 0 --tanpa-kunci-haluan --margin-kolam 0`.
Tujuan: memastikan 20% cukup untuk melawan angin/arus dan kapal masih bisa
belok. Kalau kapal tidak sanggup memegang haluan → naik `--tenaga 0.3`.

### D2. Hitungan gerbang + komitmen gerbang

Hapus `--kunci-sec 0`. Lewati gerbang 1–3 lurus. **Yang pertama diperiksa
hari ini: hitungan gerbang naik.** Tiap gerbang harus memunculkan baris
`[GERBANG]` (dihitung kamera/koordinat) dan `pair_count` di HUD/dashboard
bertambah. Kalau tetap 0 sesudah gerbang 1, baca `[TRACK] ... jarak=...m`:
kalau jarak pasangan tidak pernah turun di bawah 2,2 m sebelum bola keluar
bingkai, naikkan `--pass-jarak 2.6`. Kalau muncul `kamera bilang gerbang ke-1
lewat, tapi peta menaruhnya X m di depan - ditahan`, itu peta membantah —
sesudah 3x kamera dimenangkan, normal.

Lalu perhatikan komitmen saat melewati tiap gerbang:

```
[TRACK] ... PASANGAN ... (pair dx=120 KUNCI)          ← gerbang < 2 m, kunci pasang
[KUNCI] KUNCI_GERBANG -> L=30 R=30 (kunci gerbang 1.3m, 1 bola jauh diabaikan)
```

**Yang diharapkan:** kapal lurus menembus gerbang, tidak menikung ke bola
gerbang berikutnya. Kalau kapal justru lurus terlalu lama dan melewatkan
belokan → turunkan `--kunci-sec 1.5`.

### D3. Kunci haluan (tikungan sesudah gerbang 3)

Hapus `--tanpa-kunci-haluan`. Sesudah gerbang 3, bola hilang. Log versi
`asv2` (belok proaktif dicabut — lihat 0c; di `asv` lama baris pertama
langsung `HALUAN_PIVOT_KIRI` tanpa `CARI_MAJU_AWAL`):

```
[LOST] CARI_MAJU_AWAL 0.3s -> L=63 R=63           ← maju pelan dulu 2 s
[LOST] HALUAN_PIVOT_KIRI 2.2s -> L=-90 R=90       ← baru pivot ke gerbang 4
[LOST] HALUAN_LURUS 4.1s -> L=90 R=90
[TRACK] ...                                        ← gerbang 4 masuk bingkai
```

Kalau yang muncul `CARI_KIRI` / `CARI_MAJU` → salah satu dari tiga: peta
tidak terkalibrasi (`[POSISI]` di awal tidak ada), flag masih terpasang,
atau **posisi tidak dipercaya** — kapal sudah > 12 m sejak penambat terakhir
tanpa satu gerbang pun terhitung. Yang terakhir ini disengaja: peta yang
hanyut tidak boleh mengemudi. Betulkan hitungan gerbang (D2) dulu.

Kalau kapal pivot ke arah yang **salah** → kompas. Cek B2 lagi; kalau start
OK tapi di air salah → interferensi motor ke kompas, ukur selisih heading
diam vs `L=R=51`.

### D4. Dua hijau

Buat skenario: merah ditutup/dipindah, dua hijau terlihat. Log:

```
[ONE BUOY] ONE_GREEN_SISI x=250 r=18 ... (sisi peta | 2 kandidat, skor 1.23 arah49)
```

`arahNN` = selisih arah bola vs arah gerbang menurut peta. Bola yang dipilih
harus yang dekat, atau yang searah peta kalau ukurannya mirip.

### D5. PULANG

Saat kapal di sekitar gerbang 3–4, tekan **PULANG ke Start** di dashboard
(tombol biru, muncul hanya saat kapal berjalan). Log:

```
[PULANG] PULANG_PIVOT_KANAN home=13.0m jejak[0] lurus relatif=+178 -> L=90 R=-90
[PULANG] PULANG_LURUS home=9.2m jejak[0] lurus relatif=+3 -> L=92 R=88
[PULANG] PULANG_TIBA home=2.7m ...                    ← motor 0, menunggu diambil
```

- `PULANG_TANPA_PETA` → `--tanpa-posisi` terpasang atau peta tidak terkalibrasi.
- Kapal berhenti > 3 m dari dermaga → galat posisi; wajar. Naikkan
  `--pulang-toleransi 4` kalau terlalu sering berhenti terlalu jauh, atau
  turunkan kalau berhenti terlalu dekat tepi.
- **BERHENTI DARURAT selalu menang.** MULAI membatalkan pulang.

Pulang otomatis (`--batt-pulang 25`) tidak perlu diuji khusus — mekanismenya
sama, pemicunya saja beda.

---

## E. Kalau harus mundur ke perilaku lama

Satu baris, tanpa mengganti kode:

```
--kunci-sec 0 --bobot-arah 0 --tanpa-kunci-haluan --margin-kolam 0 --tenaga 1.0 --satu-pivot-px 0
```

Jangan mengembalikan `--pass-jarak 0`: itu kembali ke penghitung gerbang
yang terbukti mustahil bekerja di 320x240.

Itu persis kapal kemarin (kecuali kamera MJPG dan sumbu 90°, yang tidak
punya sisi buruk).

---

## F. Yang TIDAK ada hari ini — jangan diharapkan

- **Pagar arena → berhenti.** Sengaja tidak dibuat: posisi peta dibatasi ke
  0–30 m dan galatnya 2–4 m; pemicu pagar akan salah tembak dan menghentikan
  kapal di tengah lomba.
- **Pulang sesudah DONE.** Sesudah docking kapal harus tetap di dermaga
  untuk dinilai. Pulang hanya lewat tombol atau baterai.
- **Deliberasi berjendela waktu.** Yang ada: skor per frame + histeresis
  pilihan sebelumnya. Kalau di lapangan pilihan bola masih berkedip antar
  frame, itu tanda jendela waktu perlu ditambahkan.
- **Bola sebagai halangan jalur.** Jalur pulang bisa menabrak bola gerbang.
  Bola lunak.

---

## G. Yang harus dibawa pulang dari kolam

Rekam `journalctl -u asv-vision` (atau keluaran terminal) untuk **setiap**
percobaan — inilah bahan penyetelan. Tiga angka yang paling saya butuhkan:

1. Zona mati ESC (`N`) dan `--tenaga` yang akhirnya dipakai
2. Selisih kompas di start (`[KOMPAS] start OK: selisih ...`) dan, kalau
   diukur, selisih heading diam vs motor jalan
3. Untuk tiap kali kapal nyasar: 10 baris log sebelum dan sesudahnya
