# Menyiarkan Kamera Kapal ke Dashboard

Dokumen ini spesifik untuk `telemetry_motor_controller_full_buoy_pid.py`.

## Kondisi sekarang

Program itu **membuka kamera tapi tidak menyiarkannya ke mana pun**. Frame hanya
berakhir di `cv2.imshow()` - jendela di layar Raspberry Pi.

Dan ada satu hal yang membuatnya lebih parah di kapal: seluruh kode overlay
(lingkaran deteksi, garis tengah, teks FPS/MODE/ERROR/PID, panel telemetri)
berada **di dalam** `if show_display:`. Artinya begitu dijalankan dengan
`--no-display` - yaitu justru mode yang dipakai di kapal karena tidak ada
monitor - frame beranotasi itu **tidak pernah dibuat sama sekali**.

Jadi bukan sekadar "streamnya belum disambungkan". Di mode headless, gambar yang
ingin ditonton operator memang belum ada.

## Kenapa penyiarnya harus program ini juga

Di Linux satu perangkat kamera hanya bisa dibuka **satu proses**. Program ini
sudah memegang `cv2.VideoCapture(source)`, jadi menjalankan `mjpg-streamer`
terpisah untuk perangkat yang sama pasti gagal - sama persis dengan kasus port
serial ESP32 yang sudah dipegang program ini lewat `serial.Serial`.

Untungnya justru menguntungkan: yang disiarkan bisa frame **yang sudah ada
kotak deteksi buoy dan panel telemetrinya**. Operator melihat apa yang "dilihat"
kapal, bukan video mentah.

## Patch: tiga langkah

### 1. Tambahkan penyiar

Simpan sebagai `stream_server.py` di folder yang sama:

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
            # tidur sampai ada frame baru, JANGAN berputar bebas -
            # loop kosong akan memakan CPU yang dibutuhkan deteksi buoy
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
    """Jalankan server di thread daemon supaya tidak memblokir loop kontrol."""
    threading.Thread(
        target=lambda: app.run(host='0.0.0.0', port=port,
                               threaded=True, use_reloader=False),
        daemon=True,
    ).start()
```

`threaded=True` wajib. Stream MJPEG adalah koneksi HTTP yang tidak pernah
selesai, jadi tanpa itu satu penonton (dashboard admin) akan memblokir penonton
lain (dashboard user).

### 2. Keluarkan overlay dari `if show_display:`

Di `motor_control_loop()`, gambar overlay **selalu**, lalu siarkan. Hanya
`cv2.imshow` dan `cv2.waitKey` yang tetap di dalam `if show_display:`.

```python
# SEBELUM:
#   if show_display:
#       overlay = frame.copy()
#       ... semua cv2.circle / cv2.putText ...
#       combined = np.hstack([overlay, panel])
#       cv2.imshow(...)

# SESUDAH:
overlay = frame.copy()
... semua cv2.circle / cv2.putText tetap sama ...

panel = make_telemetry_panel(panel_width, overlay.shape[0])
combined = np.hstack([overlay, panel])

stream_server.publish('atas', combined)   # <-- selalu, termasuk saat headless

if show_display:
    cv2.imshow('ASV Monitor - Full Buoy + PID + Telemetry', combined)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break
```

Kirim `combined` (kamera + panel telemetri) atau `overlay` saja (kamera saja),
sesuai selera. `combined` membuat operator melihat angka sensor menempel di
videonya.

### 3. Nyalakan servernya di `main()`

```python
import stream_server

def main(port, source, api_url, baudrate=115200, show_display=True):
    global ser
    ...
    ser = serial.Serial(port, baudrate, timeout=1)
    time.sleep(2)

    stream_server.start(8000)          # <-- tambahkan

    t_telemetry = threading.Thread(...)
```

## Sambungkan ke dashboard

Isi di `.env` Laravel:

```env
CAMERA_ATAS_URL=http://<ip-raspberry-pi>:8000/stream/atas
CAMERA_BAWAH_URL=
```

Dikosongkan -> dashboard menampilkan placeholder, bukan kotak hitam.

Lebih baik lagi lewat nginx supaya seorigin dengan dashboard (tidak perlu CORS,
cukup satu terowongan ngrok):

```nginx
location /stream/ {
    proxy_pass http://127.0.0.1:8000/stream/;
    proxy_buffering off;          # WAJIB: MJPEG tidak pernah selesai
    proxy_read_timeout 3600s;
}
```

`proxy_buffering off` bukan opsional. Kalau nginx menahan stream di buffer,
gambar tidak akan pernah muncul di browser.

Dengan proxy itu, isian `.env` cukup jalur relatif:

```env
CAMERA_ATAS_URL=/stream/atas
```

## Soal fps dan bandwidth

Yang membatasi fps **bukan** encoding JPEG. Frame program ini hanya 320x240
(`CAP_PROP_FRAME_WIDTH/HEIGHT`), jadi `cv2.imencode` hanya butuh sekitar 1-2 ms.

Yang menentukan:

1. **Kecepatan loop deteksi.** fps stream = fps loop kontrol, karena yang
   dikirim adalah frame hasil anotasi. Ada `time.sleep(0.02)` di akhir loop,
   jadi batas atasnya sekitar 50 fps dikurangi waktu `detect_buoy()`.
2. **Bandwidth.** Pada 320x240 kualitas 75, satu frame kira-kira 10-20 KB. Di
   20 fps berarti sekitar 1,6-3,2 Mbps - ringan untuk WiFi lokal, tapi terasa
   lewat ngrok kalau internetnya lambat. Turunkan `JPEG_QUALITY` atau siarkan
   hanya setiap frame ke-2 kalau perlu.

## Catatan: dashboard punya dua kotak kamera, program baru satu

Program ini hanya membuka satu `--source`. Dashboard menyediakan "Kamera Atas
Air" dan "Kamera Bawah Air". Selama baru ada satu kamera, biarkan
`CAMERA_BAWAH_URL` kosong - kotak keduanya akan menampilkan placeholder yang
rapi. Untuk kamera kedua, buka `VideoCapture` kedua dan panggil
`stream_server.publish('bawah', frame_kedua)`.
