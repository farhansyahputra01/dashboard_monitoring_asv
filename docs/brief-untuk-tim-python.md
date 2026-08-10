# Brief: Integrasi Program Python ASV dengan Dashboard

> Berkas ini berdiri sendiri. Bisa langsung diberikan ke AI/asisten koding
> sebagai konteks, tanpa perlu berkas lain.

---

## Konteks sistem

Kapal ASV (Laksamana 5, Politeknik Negeri Bengkalis). Semua perangkat lunak
berjalan di **satu Raspberry Pi 4** yang diletakkan di kapal, **tanpa monitor
dan tanpa keyboard**.

Komponennya:

| Komponen | Bahasa | Peran |
|---|---|---|
| ESP32 | C++ (Arduino) | Baca sensor, gerakkan ESC thruster. Tersambung USB ke Pi. **Selesai, jangan diubah.** |
| Program Python | Python + OpenCV | Deteksi buoy, kemudi PID otomatis, pemilik serial & kamera |
| Dashboard | Laravel 12 + MySQL + nginx | Menyimpan dan menampilkan data, ditonton operator dari darat lewat ngrok |

**Aturan yang menentukan seluruh rancangan:** di Linux, satu perangkat
(`/dev/ttyUSB0`, `/dev/video0`) hanya bisa dibuka **satu proses**. Karena
program Python sudah memegang keduanya, maka program Python-lah yang harus
melayani semua pihak lain yang butuh perangkat itu. Laravel tidak pernah
menyentuh serial maupun kamera.

## Berkas yang dikerjakan

`telemetry_motor_controller_full_buoy_pid.py` (+ `buoy_detection_full.py`)

Program ini **sudah berjalan dan sudah benar** dalam hal:

- membuka serial ESP32 dua arah (`serial.Serial`, dilindungi `serial_lock`)
- membaca telemetri di thread terpisah
- mengirim perintah motor `L:<int>,R:<int>\n`
- deteksi buoy merah/hijau dan kemudi PID
- opsi `--no-display` untuk jalan tanpa monitor
- backup CSV lokal

### JANGAN diubah

- Logika `detect_buoy()` dan `calc_steering_error()`
- Perhitungan PID, zona STRAIGHT/PIVOT/SEARCH, nilai `KP/KI/KD`
- Format perintah motor `L:<int>,R:<int>\n` (firmware ESP32 sudah cocok)
- Kepemilikan tunggal serial dan kamera
- Format telemetri dari ESP32

Semua perubahan di bawah bersifat **integrasi**, bukan perbaikan algoritma.

---

## Tugas 1 — Header token pada pengiriman telemetri

**Masalah:** endpoint `/api/telemetry` di Laravel sekarang **wajib bertoken**.
Tanpa header, semua POST dibalas **401** dan tidak ada telemetri yang tersimpan.

Endpoint terbuka ke internet lewat ngrok, jadi tanpa token siapa pun yang tahu
URL-nya bisa menyuntikkan data sensor palsu.

**Yang perlu dilakukan:** tambahkan header pada `requests.post` di
`telemetry_reader_thread`.

```python
INGEST_TOKEN = os.environ.get('ASV_INGEST_TOKEN', '')

resp = requests.post(
    api_url,
    json=data,
    headers={'X-ASV-Token': INGEST_TOKEN},
    timeout=HTTP_TIMEOUT_SEC,
)
```

Token dikirim terpisah oleh tim Laravel (jangan ditulis di dalam kode atau
di-commit ke git). Baca dari environment variable.

### Kontrak endpoint

```
POST http://127.0.0.1/api/telemetry
Header: X-ASV-Token: <token>
        Content-Type: application/json
```

Body JSON — nama field yang dipakai program sekarang **sudah diterima apa
adanya**, tidak perlu diubah:

```json
{
  "temperature": 32, "humidity": 51,
  "latitude": 1.459146, "longitude": 102.149653,
  "speed_kmph": 5.76, "altitude": -17.9,
  "satellites": 5, "heading": 337.3,
  "current": 2.4, "battery_voltage": 12.9, "battery_percent": 50,
  "timestamp": 1786000000.123
}
```

Balasan:

| Kode | Arti |
|---|---|
| `201` | Tersimpan. Body: `{"id":123,"gps_fix":true}` |
| `401` | Token salah/kosong |
| `422` | Ada field yang tidak lolos validasi |

Laravel sudah menangani sendiri penanda "tidak valid" dari firmware
(`-999` untuk DHT11, `0` untuk GPS belum fix), jadi **kirim apa adanya** — jangan
disaring di sisi Python.

### Penting: pakai alamat lokal, bukan ngrok

Contoh pemakaian sekarang memakai `--api-url https://xxxx.ngrok-free.app/...`.
Di kapal itu keliru: Python dan Laravel berada di **mesin yang sama**. Mengirim
telemetri keluar ke internet lalu kembali lagi ke Pi berarti boros kuota,
menambah latensi, dan **telemetri ikut mati saat internet putus** — padahal
databasenya ada di mesin yang sama.

```
--api-url http://127.0.0.1/api/telemetry
```

ngrok tetap dipakai, tapi hanya supaya operator di darat bisa membuka dashboard.

---

## Tugas 2 — Siarkan video ke dashboard (MJPEG)

**Masalah:** program membuka kamera tetapi **tidak menyiarkannya ke mana pun**.
Frame hanya berakhir di `cv2.imshow()` — jendela di layar Pi, yang di kapal tidak
ada.

Dashboard tidak bisa mengakses kamera sendiri (kameranya dipegang program ini),
jadi program ini harus menyiarkannya.

Keuntungannya: yang disiarkan adalah frame yang **sudah ada lingkaran deteksi
buoy dan panel telemetrinya** — operator melihat apa yang "dilihat" kapal.

### Buat `stream_server.py`

```python
import threading
import cv2
from flask import Flask, Response

app = Flask(__name__)

_frames = {}                      # 'atas' / 'bawah' -> bytes JPEG terakhir
_ready = threading.Condition()

JPEG_QUALITY = 75


def publish(cam, frame):
    """Dipanggil dari loop kontrol, setelah overlay digambar."""
    ok, jpg = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, JPEG_QUALITY])
    if not ok:
        return
    with _ready:
        _frames[cam] = jpg.tobytes()
        _ready.notify_all()


def _generate(cam):
    while True:
        with _ready:
            # tidur sampai ada frame baru. JANGAN pakai `while True` polos tanpa
            # penahan: loop kosong akan memakan CPU yang dibutuhkan detect_buoy()
            _ready.wait(timeout=2.0)
            buf = _frames.get(cam)
        if buf:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + buf + b'\r\n')


@app.route('/stream/<cam>')
def stream(cam):
    if cam not in ('atas', 'bawah'):
        return 'kamera tidak dikenal', 404
    return Response(_generate(cam),
                    mimetype='multipart/x-mixed-replace; boundary=frame')


def start(port=8000):
    """Jalankan di thread daemon supaya tidak memblokir loop kontrol."""
    threading.Thread(
        target=lambda: app.run(host='0.0.0.0', port=port,
                               threaded=True, use_reloader=False),
        daemon=True,
    ).start()
```

`threaded=True` **wajib**. Stream MJPEG adalah koneksi HTTP yang tidak pernah
selesai, jadi tanpa itu satu penonton akan memblokir penonton lain (dashboard
admin dan dashboard user dibuka bersamaan).

### Nyalakan di `main()`

```python
import stream_server

ser = serial.Serial(port, baudrate, timeout=1)
time.sleep(2)

stream_server.start(8000)          # <-- tambahkan di sini
```

---

## Tugas 3 — Bangun overlay walau headless

**Masalah — ini yang paling mudah terlewat.** Seluruh kode overlay (lingkaran
deteksi, garis tengah, teks FPS/MODE/ERROR/PID, `make_telemetry_panel`) berada
**di dalam** `if show_display:` pada `motor_control_loop()`.

Artinya begitu dijalankan dengan `--no-display` — yaitu justru mode yang dipakai
di kapal — frame beranotasi itu **tidak pernah dibuat sama sekali**. Tidak ada
yang bisa disiarkan.

**Yang perlu dilakukan:** gambar overlay **selalu**, siarkan **selalu**, dan
hanya `cv2.imshow` + `cv2.waitKey` yang tetap bersyarat.

```python
# SEBELUM
#   if show_display:
#       overlay = frame.copy()
#       ... semua cv2.circle / cv2.putText ...
#       combined = np.hstack([overlay, panel])
#       cv2.imshow(...)
#       if cv2.waitKey(1) & 0xFF == ord('q'): break

# SESUDAH
overlay = frame.copy()
... semua cv2.circle / cv2.putText tetap sama persis ...

panel = make_telemetry_panel(panel_width, overlay.shape[0])
combined = np.hstack([overlay, panel])

stream_server.publish('atas', combined)     # selalu, termasuk saat headless

if show_display:
    cv2.imshow('ASV Monitor - Full Buoy + PID + Telemetry', combined)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break
```

Kirim `combined` (kamera + panel telemetri) atau `overlay` saja, sesuai selera.

---

## Tugas 4 — Flag berhenti darurat

**Masalah:** loop kontrol **selalu** mengirim perintah motor di setiap iterasi —
dua buoy kirim PID, satu buoy kirim koreksi, tidak ada buoy kirim SEARCH. Tidak
ada satu pun kondisi yang membuatnya diam.

Akibatnya tombol berhenti di dashboard tidak akan berpengaruh: perintah PID
berikutnya menyusul ~20 ms kemudian dan kapal jalan lagi.

### Yang perlu dilakukan

Tambahkan flag, misalnya `threading.Event`:

```python
manual_stop = threading.Event()
```

Di awal setiap iterasi `motor_control_loop`, sebelum deteksi dipakai untuk
menggerakkan motor:

```python
if manual_stop.is_set():
    send_motor_command(0, 0)     # tegas, tidak menunggu failsafe
    reset_pid()                  # cegah integral menumpuk selama berhenti
    # overlay + publish tetap jalan supaya operator tetap melihat kamera
    ...
    time.sleep(0.02)
    continue
```

Penting: **tetap kirim `L:0,R:0`**, jangan sekadar berhenti mengirim. Berhenti
mengirim memang akan memicu failsafe ESP32, tapi baru setelah 1 detik. Mengirim
nol secara tegas menghentikan kapal dalam ~20 ms. Failsafe tetap ada sebagai
lapis kedua kalau program Python sendiri yang mati.

Juga: overlay dan `publish()` harus tetap berjalan saat berhenti, supaya operator
tetap bisa melihat kamera untuk menilai keadaan.

### Endpoint kontrol (tambahkan di `stream_server.py` atau berkas terpisah)

Dashboard Laravel akan memanggil ini dari **mesin yang sama** (127.0.0.1), jadi
jangan diekspos lewat nginx dan tidak perlu token.

```
POST http://127.0.0.1:8000/control/stop      -> {"stopped": true}
POST http://127.0.0.1:8000/control/resume    -> {"stopped": false}
GET  http://127.0.0.1:8000/control/status    -> {"stopped": false}
```

`/control/status` dipakai dashboard untuk menampilkan keadaan tombol dengan
benar setelah halaman di-refresh.

---

## Cara menjalankan di kapal

Dijalankan otomatis oleh systemd saat Pi menyala:

```
python3 telemetry_motor_controller_full_buoy_pid.py \
    --port /dev/ttyUSB0 \
    --source 0 \
    --no-display \
    --api-url http://127.0.0.1/api/telemetry
```

Pengguna yang menjalankannya harus punya izin perangkat:

```bash
sudo usermod -aG dialout,video pi
```

Tanpa ini `serial.Serial()` gagal "Permission denied" saat boot, diam-diam.

Pengganti jendela `cv2.imshow` yang hilang:

```bash
journalctl -u asv-vision -f
```

Semua `print()` muncul di sana.

---

## Catatan fps

Yang membatasi fps **bukan** encoding JPEG. Frame program ini hanya 320x240,
jadi `cv2.imencode` hanya sekitar 1-2 ms. Yang menentukan:

1. **Kecepatan loop deteksi** — fps stream = fps loop kontrol, karena yang
   dikirim adalah frame hasil anotasi.
2. **Bandwidth** — pada 320x240 kualitas 75, satu frame kira-kira 10-20 KB. Di
   20 fps sekitar 1,6-3,2 Mbps. Ringan untuk WiFi lokal.

Kalau perlu dihemat: turunkan `JPEG_QUALITY`, atau `publish()` hanya setiap
frame ke-2.

---

## Ringkasan

| # | Tugas | Kenapa |
|---|---|---|
| 1 | Header `X-ASV-Token` | Tanpa ini semua telemetri dibalas 401 |
| 2 | `stream_server.py` + `publish()` | Video belum disiarkan ke mana pun |
| 3 | Overlay keluar dari `if show_display:` | Saat headless frame beranotasi tidak dibuat |
| 4 | Flag `manual_stop` + endpoint kontrol | Tombol berhenti dashboard tidak berfungsi tanpa ini |

Ubahan 1-3 murni integrasi. Ubahan 4 menyentuh loop kontrol, jadi perlu diuji di
darat dulu dengan thruster terangkat dari air.
