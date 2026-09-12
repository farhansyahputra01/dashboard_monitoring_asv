# Uji Lapangan 12 September 2026 — Checklist

> **Diperbarui 11 Sep malam, sesudah uji air pertama.** Bagian 0 di bawah
> merangkum apa yang ditemukan dan apa yang berubah. Perintah jalan di
> bagian C sudah direvisi — pakai yang itu.

---

## 0. Temuan uji air 11 Sep dan perbaikannya

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
| 6 | Kunci haluan ke peta saat bola hilang + belok proaktif | otomatis | `--tanpa-kunci-haluan` |
| 7 | Jalur menghindari tepi kolam | `--margin-kolam 2.0` | `--margin-kolam 0` |
| 8 | PULANG: tombol dashboard + baterai | `--batt-pulang 25` | jangan tulis flag-nya |
| 9 | Gerbang dihitung dari jarak | `--pass-jarak 2.2` | `--pass-jarak 0` (kembali ke luas — tidak disarankan) |
| 10 | Pivot bertenaga + sisi mundur diperkuat | `--tenaga-putar 0.5 --pivot-mundur 1.4` | hapus flag-nya |
| 11 | Satu bola: pivot dulu kalau error besar | `--satu-pivot-px 70` | `--satu-pivot-px 0` |
| 12 | GPS diabaikan di peta | otomatis (titik acuan kosong) / `--tanpa-gps` | isi kembali `titik` di JSON |
| 13 | Monitor layar penuh | `--fullscreen` | hapus flag |
| 14 | Peta jejak GPS: titik valid hanya saat thruster hidup (`motor_on` dari kapal) | otomatis | — (program lama → saringan Doppler) |

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
    --tenaga-putar 0.5 --pivot-mundur 1.4 \
    --pass-jarak 2.2 --tanpa-gps \
    --batt-pulang 25 --batt-cutoff 15
```

Tambahkan `--fullscreen` (dan hapus `--no-display`) kalau ada monitor tercolok.

Baris yang harus muncul di awal:

```
Tenaga motor: 20% (BASE 150 -> 30, MAX 255 -> 51), zona mati ESC: ..., ambang diam peta: ...
Putaran: tenaga pivot 50%, sisi mundur x1.40, satu bola pivot bila error > 70 px
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

### D3. Kunci haluan + belok proaktif (tikungan sesudah gerbang 3)

Hapus `--tanpa-kunci-haluan`. Sesudah gerbang 3, bola hilang. Log:

```
[LOST] HALUAN_PIVOT_KIRI 0.3s -> L=-90 R=90       ← langsung pivot, tanpa TEMP_FORWARD
[LOST] HALUAN_LURUS 2.1s -> L=90 R=90
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
