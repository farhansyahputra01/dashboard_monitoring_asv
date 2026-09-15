# Deteksi Buoy: dari Ambang Warna ke Model YOLO (TFLite)

Dokumen ini menjelaskan perpindahan deteksi buoy dari ambang warna HSV ke model
YOLOv8 yang dijalankan lewat TFLite, apa yang **tidak** ikut pindah, dan
angka-angka mana yang harus dikalibrasi ulang di lapangan.

Sisi kapal ada di folder `asv`; dokumen ini berada di repo dashboard karena di
sinilah runbook deploy tinggal - lihat
[runbook-deploy-linux.md](runbook-deploy-linux.md) Tahap 1 dan 3.

> **Riwayat singkat**, supaya tidak ada yang berputar balik:
> HSV -> YOLOv8n/NCNN 2 kelas (16 Agt 2026) -> **YOLOv8/TFLite 3 kelas
> (24 Agt 2026, yang berlaku sekarang)**. Backend NCNN dan berkas
> `ncnn_model/` sudah dihapus; yang dipakai `best.tflite`.

---

## Kenapa pindah

Deteksi lama menilai **warna**, dan hanya warna. Penahannya cuma dua: luas
kontur dan circularity. Rumput, daun, terpal hijau, dan pantulan matahari di
air berada di rentang hijau yang sama dengan bola - begitu salah satunya
kebetulan cukup luas dan cukup membulat, ia lolos sebagai `ball_green`, dan
kapal membelok ke arahnya.

Gejalanya sudah terlihat di kode lama itu sendiri: ambang luas hijau pernah
dinaikkan ke 1500 px² khusus untuk menahan vegetasi, lalu diturunkan lagi ke
400 karena bola hijau jadi baru terbaca saat sudah sangat dekat. Tidak ada
angka yang benar - satu ambang dipaksa melayani dua tujuan yang berlawanan.

Model YOLO menilai **rupa bolanya**, bukan warnanya saja, dan dilatih memakai
foto lapangan. Itu menghapus pertukaran di atas: ambang tidak lagi harus
memilih antara membuang rumput dan melihat bola jauh.

---

## Yang pindah dan yang tidak

| Objek | Fase | Detektor sekarang |
|---|---|---|
| `ball_red`, `ball_green` | SLALOM | **model** |
| `ball_blue` (3 buoy) | DOCKING | **model** - berubah di versi ini |
| Kotak imaging biru/hijau | UNDERWATER_IMG, SURFACE_IMG | HSV, satu-satunya sisa |

Model 24 Agustus 2026 punya **tiga** kelas - `ball_blue`, `ball_green`,
`ball_red` - sementara model NCNN sebelumnya hanya dua. Karena kelas biru kini
ada, fase DOCKING ikut pindah: `detect_blue_buoys()` tidak lagi mencari rentang
biru di HSV. Itu menghilangkan sumber salah kenali yang paling sulit disetel -
pantulan langit di permukaan air selalu kebiruan.

Deteksi warna untuk **bola sudah dihapus** dari `buoy_detection.py`, bukan
sekadar dimatikan: tidak ada `--detector hsv`, tidak ada jalan mundur.
Menyimpan jalur lama berarti kapal bisa berjalan dengan detektor yang sudah
diputuskan tidak layak tanpa ada yang menyadarinya.

Yang masih HSV tinggal **kotak imaging** - itu sebabnya `LOWER_GREEN`/
`UPPER_GREEN` dan `LOWER_BLUE`/`UPPER_BLUE` masih ada: pemakainya sekarang
KOTAK, bukan bola. Kotak belum ada di dataset pelatihan mana pun. Begitu ia
masuk, sisa HSV di sistem ini hilang seluruhnya.

---

## Berkas

```
asv/
├── yolo_detector.py         <- pra-proses, inferensi TFLite, NMS, saringan bentuk
├── buoy_detection.py        <- semua bola = model; HSV tinggal untuk kotak imaging
├── telemetry_motor_controller_turn_speed.py   <- argumen model + HUD
└── best.tflite              <- ~12 MB; nama kelas ada DI DALAMNYA
```

**Tidak ada `ultralytics` di kapal, dan itu disengaja.** Paket itu menarik
torch + tensorflow: ratusan megabyte dan RAM yang tidak dimiliki RPi4, hanya
untuk memanggil satu berkas 12 MB. Yang dibutuhkan cuma interpreter TFLite
(`ai-edge-litert`, beberapa MB); letterbox, decode, dan NMS-nya ada di
`yolo_detector.py` - numpy + OpenCV yang memang sudah terpasang.

Skrip tim yang memakai ultralytics (`tflite_test.py`, `asv_yolo_inference.py`
di folder `best/ASV`) tetap berguna sebagai alat uji di laptop, tapi jangan
dijadikan program kapal.

Bentuk keluaran `detect_buoy()` **tidak berubah**:

```
(x, y, radius, skor, area, label)
```

`mission_controller.py` dan `docking.py` tidak perlu disentuh sama sekali -
keduanya hanya membaca `y`, `area`, dan `label`. Kolom ke-4 dulu berisi
circularity, sekarang berisi keyakinan model (0..1); kolom itu hanya dipakai
untuk tampilan.

Nama kelas **dibaca dari dalam `best.tflite`**, bukan ditanam di kode. Urutan
kelas ditentukan saat pelatihan; kalau model dilatih ulang dan urutannya
bergeser, kapal akan mengira bola merah adalah bola hijau - seluruh lintasan
slalom terbalik tanpa satu pun pesan galat.

Ultralytics menempelkan metadata sebagai **arsip ZIP di ujung berkas .tflite**
(berisi `metadata.json`). Jadi satu berkas itu flatbuffer dan zip sekaligus -
sebabnya nama kelas tidak terlihat kalau berkasnya sekadar di-`grep`: isinya
terkompresi. Untuk memeriksanya sendiri:

```bash
python3 -c "import zipfile,json; print(json.loads(zipfile.ZipFile('best.tflite').read('metadata.json'))['names'])"
```

Harus menjawab `{'0': 'ball_blue', '1': 'ball_green', '2': 'ball_red'}`.

---

## Menjalankan

Pasang interpreternya sekali:

```bash
pip install ai-edge-litert --break-system-packages
```

Uji deteksinya sendiri dulu, tanpa ESP32 dan tanpa kapal:

```bash
cd /home/pi/asv
python3 yolo_detector.py --source 0                  # ada layar
python3 yolo_detector.py --source 0 --no-display     # lewat SSH
```

Yang harus terlihat:

```
[YOLO] Model dimuat: /home/pi/asv/best.tflite
[YOLO] LiteRT | imgsz=320 | tata letak=NCHW | conf=0.45 | thread=3
[YOLO] Kelas: 0=ball_blue, 1=ball_green, 2=ball_red
ball_red x=118 y=161 r=24 skor=0.83 area=1810 | infer=180ms FPS=5.2
```

Kalau baris `Kelas:` cuma memuat dua kelas, modelnya bukan yang 24 Agustus
2026 - fase docking akan buta.

Program utama tidak perlu argumen tambahan - YOLO adalah bawaannya:

```bash
python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 --source 0 --no-display --stream \
    --api-url http://127.0.0.1/api/telemetry
```

Argumen baru yang tersedia:

| Argumen | Bawaan | Gunanya |
|---|---|---|
| `--model` | `./best.tflite` | Kalau model disimpan di tempat lain |
| `--conf` | `0.45` | Ambang keyakinan |
| `--roi-top` | `0` | Abaikan N bagian atas frame |
| `--yolo-threads` | `3` | Thread inferensi (RPi4 punya 4 inti) |

Di HUD kamera muncul baris `DET  YOLO 180ms`. Itu yang pertama dilihat kalau
kapal "tidak melihat bola": memastikan model benar-benar dipanggil, sekaligus
menunjukkan kalau inferensi melambat. `YOLO --` berarti model belum pernah
dipanggil sama sekali.

### Kenapa program berhenti kalau model tidak ada

Karena tidak ada lagi yang bisa menggantikannya - dan itu memang disengaja.
Jalur mundur diam-diam adalah kegagalan paling berbahaya yang bisa dirancang
di sini: kapal tetap berjalan, layar tetap tampak normal, dan tidak ada yang
tahu bahwa yang dipakai justru detektor yang sudah diputuskan tidak layak,
sampai kapal membelok ke rumput di tengah lomba.

Kegagalan model selalu terjadi saat program baru dinyalakan, sebelum motor
bergerak. Berhenti di situ tidak merugikan siapa pun; yang merugikan adalah
berlayar dengan mata yang salah.

---

## Kalibrasi lapangan

### 1. `--pass-area` WAJIB diukur ulang

Ini pekerjaan kalibrasi yang paling penting sesudah pindah model.

`area` sekarang dihitung dari kotak deteksi (π·r², dengan r = rata-rata
setengah sisi kotak), bukan dari luas kontur mask. Keduanya sengaja dibuat
sebanding - luas kontur bola memang mendekati π·r² - tapi **tidak identik**:
pada pengujian, kotak YOLO cenderung sedikit lebih besar daripada kontur HSV
untuk bola yang sama.

Akibatnya `--pass-area 4000` bisa tercapai lebih awal dari sebelumnya, dan
kapal menghitung pasangan slalom terlalu cepat. Cara mengukurnya:

```bash
python3 yolo_detector.py --source 0 --no-display
```

Taruh kapal (atau kamera) pada jarak yang menurut kalian sudah "terlewati",
lalu baca angka `area=` di layar. Angka itulah yang dipakai `--pass-area`.
**`--dock-area` sekarang WAJIB diukur ulang juga**, karena buoy biru sudah
ikut pindah ke model di versi ini - angka 6000 bawaannya masih berasal dari
luas kontur mask HSV. Cara mengukurnya sama: arahkan kamera ke buoy biru
tengah pada jarak yang seharusnya memicu fase ALIGN, lalu baca `area=`.

### 2. `--conf`

Bawaan 0.45 - di antara 0.35 yang dipakai model NCNN lama dan 0.55 di skrip uji
tim.

- Bola jauh sering hilang -> **turunkan** (0.25)
- Ada benda lain ikut terbaca -> **naikkan** (0.45)

Naikkan hanya kalau `--roi-top` sudah dicoba dan tidak cukup: menaikkan `conf`
ikut membuang bola jauh yang sah.

### 3. `--roi-top`

Buoy mengapung **di air**, dan air selalu berada di bagian bawah pandangan
kamera depan. Bagian atas frame berisi garis pantai, pepohonan, dan langit -
sumber salah kenali yang paling keras kepala.

`--roi-top 0.25` membuang seperempat atas frame. Ini tombol **pertama** yang
harus dicoba kalau masih ada salah kenali, sebelum menyentuh `--conf`, karena
ia hanya membuang wilayah yang memang bukan air.

### 4. Kecepatan inferensi

Terukur di laptop pengembangan (LiteRT, 4 thread): **±40-55 ms per frame**. Di
RPi4 perkirakan **150-400 ms**, dan lebih lambat lagi kalau CPU panas - **angka
ini wajib diukur sendiri di Pi**, jangan dipercaya dari dokumen. Di Jetson Orin
Nano (CPU, LiteRT) terukur 12-15 FPS; untuk memakai GPU-nya lihat bagian 5.

Yang berubah karena itu: laju loop kendali turun dari ~27 FPS ke sekitar 4-8
FPS. Kemudi tetap bekerja (PID memakai selisih waktu sungguhan, bukan asumsi
frame), tapi:

- `--yolo-threads 4` boleh dicoba kalau telemetry masih lancar
- `--stream-every` boleh dinaikkan supaya encode JPEG tidak ikut merebut CPU
- **failsafe motor di firmware ESP32 sudah dinaikkan dari 1000 ke 1500 ms**
  untuk mengikuti perlambatan ini (lihat bagian berikut)

Ukuran masukan model **terkunci di 320x320** - itu tertanam di berkas
`.tflite` hasil ekspor. Mengecilkan resolusi kamera tidak mempercepat
inferensi.

Satu hal lagi yang menghemat banyak: `detect_buoy()` memanggil model **sekali**
untuk ketiga kelas, tidak sekali untuk bola lalu sekali lagi untuk biru.
Memecahnya jadi dua panggilan membelah FPS kendali tepat dua tanpa menambah
satu pun informasi.

### 5. Jetson: `best.onnx` di GPU (sejak 15 September 2026)

Di Jetson Orin Nano, `best.tflite` lewat LiteRT mentok **12-15 FPS** - dan
itu batas wajarnya, karena LiteRT hanya memakai CPU ARM; GPU Orin tidak pernah
tersentuh. Tim mengekspor bobot yang sama ke `best.onnx` (14 September 2026,
6 kelas yang sama, masukan 320x320) supaya bisa dijalankan lewat
**onnxruntime-gpu** di TensorRT/CUDA.

Yang perlu dipahami sebelum menguji: **berkas `.onnx` sendiri tidak
mempercepat apa pun**. Terukur di laptop dengan onnxruntime CPU: ~34 ms/frame,
TFLite ~37 ms - sama saja. Kenaikan FPS hanya datang kalau providernya
TensorRT atau CUDA, dan itu hanya ada di build onnxruntime-gpu khusus Jetson.
Wheel `onnxruntime-gpu` dari PyPI biasa adalah x86_64 dan tidak akan terpasang
di aarch64.

`yolo_detector.py` memilih backend dari ekstensi berkas: `.tflite` -> LiteRT,
`.onnx` -> onnxruntime dengan urutan provider TensorRT -> CUDA -> CPU. Baris
kedua log `[YOLO]` menyebut mana yang benar-benar aktif -
`onnxruntime/TensorRT`, `onnxruntime/CUDA`, atau `onnxruntime/CPU`. **Kalau
tertulis CPU, GPU tidak dipakai** dan angka FPS tidak akan berubah.

Pemasangan di Jetson (`lsb_release -a` menentukan barisnya):

```bash
# JetPack 6 (Ubuntu 22.04, CUDA 12.x) - indeks pip resmi Jetson AI Lab:
pip3 install onnxruntime-gpu --index-url https://pypi.jetson-ai-lab.io/jp6/cu126
#   (kalau JetPack 6.0/CUDA 12.2: ganti cu126 -> cu122; indeks lama beralamat
#    pypi.jetson-ai-lab.dev - coba yang .io dulu)

# JetPack 5 (Ubuntu 20.04, CUDA 11.4) - unduh wheel dari Jetson Zoo:
#   https://elinux.org/Jetson_Zoo#ONNX_Runtime  -> pilih yang JP5 + python 3.8
pip3 install ./onnxruntime_gpu-*-cp38-cp38-linux_aarch64.whl

# pastikan providernya ada - harus menyebut Tensorrt dan CUDA:
python3 -c "import onnxruntime as ort; print(ort.get_available_providers())"

# Jetson di mode daya penuh, kalau tidak angkanya tidak bisa dibandingkan:
sudo nvpmodel -m 0 && sudo jetson_clocks
```

Uji yang adil - dua perintah yang sama persis, hanya modelnya beda, kamera
yang sama, 300 frame:

```bash
cd ~/asv
python3 yolo_detector.py --source 0 --no-display --frames 300
python3 yolo_detector.py --source 0 --no-display --frames 300 --model best.onnx
```

Ringkasan di akhir mencetak dua angka yang sengaja dipisah: **`infer`** hanya
model, **`FPS loop`** seluruh putaran termasuk kamera. Kalau `infer` sudah
turun ke satu digit milidetik tapi `FPS loop` berhenti di ~30, yang membatasi
sekarang kamera (30 fps), bukan model - dan itu memang hasil yang diharapkan.

Hal-hal yang akan ditemui:

- **Start pertama dengan TensorRT lambat, 1-3 menit** - engine dibangun untuk
  GPU ini. Program menunggu di `[YOLO] Pemanasan ... s` sebelum kamera
  dibuka. Engine disimpan di `~/asv/trt_cache/`, start berikutnya beberapa
  detik. Folder itu terikat versi TensorRT + GPU: jangan disalin antar mesin,
  hapus saja kalau ada yang aneh setelah update JetPack.
- **Membandingkan provider**: `ASV_ONNX_PROVIDER=cuda` atau `=cpu` memaksa
  satu provider, misalnya untuk memisahkan "TensorRT bermasalah" dari
  "modelnya lambat". `ASV_ONNX_PROVIDER=cuda` juga jalan keluar kalau
  pembangunan engine TensorRT gagal di lapangan - lebih lambat dari TensorRT,
  masih jauh lebih cepat dari CPU.
- **Koordinat keluaran ONNX dalam piksel**, TFLite ternormalisasi 0..1.
  `yolo_detector.py` sudah menangani keduanya; terverifikasi kotak deteksi
  kedua backend selisih <=2 px pada gambar yang sama. Kalau suatu saat ada
  ekspor baru dan semua kotak menumpuk di pojok kiri atas atau melesat ke
  luar frame, satuan inilah yang tertukar - lihat `_koordinat_normal`.
- **Model 14 September adalah hasil latih yang berbeda** dari `best.tflite`
  9 September (Ultralytics 8.4.152 vs 8.4.145), bukan sekadar ekspor ulang.
  Pada gambar sintetis uji, ia memberi `box_blue` palsu berskor 0,44-0,47 di
  tepi frame hitam yang tidak dikeluarkan model lama - di bawah `--conf 0.45`
  bawaan itu lolos-tidaknya tipis. Periksa lagi salah kenali di air
  sesungguhnya sebelum menjadikannya bawaan.

**Efek samping FPS naik: stream dashboard lewat ngrok tertinggal lalu macet.**
Terukur 15 Sep 2026 setelah loop naik ke ~30 FPS: tampilan di LAN lancar,
lewat ngrok delay kecil yang makin lama makin besar sampai freeze. Sebabnya
`--stream-every 3` menghitung *frame*, jadi laju MJPEG ikut naik 5 → 10 fps
dan melampaui uplink ngrok; frame yang belum terkirim menumpuk di buffer
jaringan dan server tidak bisa menariknya kembali. Dua perbaikan yang sudah
masuk:

- `stream_server.py` membatasi MJPEG per penonton berbasis **waktu**
  (`--stream-fps`, bawaan 10; turunkan ke 5 kalau masih tertinggal).
- Endpoint `/stream/foto/<kamera>` (satu JPEG terbaru) + `camera-stream.js`
  yang otomatis memakai mode **tarik** kalau host halaman mengandung `ngrok`:
  browser meminta frame berikutnya baru setelah yang sebelumnya sampai, jadi
  tumpukannya paling banyak satu frame. Paksa dengan `?stream=foto` /
  `?stream=mjpeg` di URL dashboard. Perlu `npm run build` + salin
  `public/build` ke Jetson.

`best.tflite` **tetap bawaan** sampai `best.onnx` terbukti di air; untuk
memakainya di kapal tambahkan `--model best.onnx` pada perintah
`telemetry_motor_controller_turn_speed.py` (atau `ExecStart` di systemd).
Setelah FPS naik, `MOTOR_TIMEOUT` 1500 ms di firmware **tidak perlu**
diturunkan - lebih longgar tidak merugikan apa pun.

---

## Perubahan di firmware ESP32

Satu baris, di `src/main.cpp` (dan disamakan di `esp32_sensor_and_motor.ino`):

```cpp
const unsigned long MOTOR_TIMEOUT = 1500;   // dulu 1000
```

Angka itu adalah "berapa lama RPi boleh diam sebelum motor dimatikan". Jeda
terpanjang yang wajar antara dua perintah motor = heartbeat sisi RPi (500 ms)
+ satu putaran loop. Selama loop masih ~35 ms, batas 1000 ms punya margin
lebar. Dengan inferensi YOLO, satu putaran bisa 250-600 ms, sehingga jeda
menyentuh ~1000 ms - motor mati sesaat lalu hidup lagi berulang-ulang, dan di
air gejalanya terlihat seperti ESC rusak atau baterai drop.

Nilainya **tidak berubah lagi** saat pindah dari NCNN ke TFLite: kedua backend
berada di rentang kecepatan yang sama, jadi 1500 ms tetap memberi margin ~1,5x.

1500 ms mengembalikan margin ~1,5x seperti rancangan semula, dan tetap
menghentikan kapal dalam waktu di bawah dua detik kalau RPi benar-benar mati
atau kabel USB terlepas. Jangan dinaikkan lagi tanpa alasan sekuat ini: setiap
tambahan di sini adalah tambahan waktu kapal berjalan tanpa kendali.

Selebihnya firmware **tidak berubah**. Tidak ada deteksi apa pun di ESP32 - ia
hanya membaca sensor dan menggerakkan ESC; seluruh penglihatan ada di RPi.

---

## Yang tidak berubah di dashboard

Tidak ada kode Laravel yang perlu disentuh. Dashboard menerima telemetri lewat
`POST /api/telemetry` dan menonton kamera lewat MJPEG dari `stream_server.py` -
keduanya tidak tahu-menahu bagaimana bola dideteksi. Frame yang disiarkan tetap
frame yang sudah beranotasi, jadi lingkaran deteksi di dashboard sekarang
otomatis berasal dari model.

---

## Kalau interpreter TFLite tidak mau terpasang

```bash
pip install ai-edge-litert --break-system-packages
python3 -c "import ai_edge_litert; print('LiteRT OK')"
```

Kalau gagal karena tidak ada wheel untuk arsitektur Pi:

1. Pastikan Raspberry Pi OS **64-bit** (`uname -m` harus `aarch64`, bukan
   `armv7l`). Wheel 32-bit tidak selalu tersedia.
2. `pip install --upgrade pip` dulu - pip lama tidak mengenali tag wheel baru.
3. Coba paket lamanya: `pip install tflite-runtime`. `yolo_detector.py`
   menerimanya tanpa perubahan kode apa pun.
4. Kalau `tensorflow` sudah terlanjur terpasang di Pi, itu pun dipakai otomatis
   (berat, tapi jalan).

Tidak ada jalan pintas kelima: deteksi warna untuk bola sudah dihapus, jadi
tanpa interpreter TFLite kapal memang tidak bisa berlayar otomatis. Selesaikan
pemasangannya sebelum hari lomba, jangan di pinggir kolam.

## Kalau masih ada salah kenali

Urutan yang dicoba, dari yang paling tidak merugikan:

1. `--roi-top 0.25` - buang wilayah yang memang bukan air
2. `--conf 0.45` - hanya kalau langkah 1 belum cukup
3. Latih ulang model dengan foto lokasi lomba yang sesungguhnya, termasuk foto
   rumput/semak yang menipu itu sebagai contoh negatif

Saringan bentuk sudah bekerja otomatis: kotak deteksi dengan rasio sisi di
atas 2,0 dibuang, karena bola itu bulat dan kotaknya nyaris bujur sangkar.
Terukur pada model ini, bola sungguhan menghasilkan rasio 1,09-1,18 sementara
hamparan hijau melebar sampai 2,2-2,7. Saringan itu **bukan pengganti model** -
salah kenali yang kebetulan membulat tetap harus diselesaikan dengan tiga
langkah di atas.

## Kalau kapal tidak melihat bola sama sekali

1. Lihat HUD: `DET` harus berbunyi `YOLO <angka>ms`. Kalau `YOLO --`, model
   belum pernah dipanggil sama sekali - masalahnya di alur program, bukan di
   ambang deteksi.
2. `python3 yolo_detector.py --source 0 --no-display` - apakah modelnya sendiri
   melihat sesuatu?
3. Turunkan `--conf 0.2` sementara. Kalau tiba-tiba banyak deteksi muncul,
   masalahnya ambang, bukan model.
4. Kalau tetap kosong padahal bola jelas terlihat di kamera: kemungkinan besar
   modelnya dilatih pada kondisi cahaya/latar yang jauh berbeda. Itu pekerjaan
   melatih ulang, bukan menyetel ambang.
