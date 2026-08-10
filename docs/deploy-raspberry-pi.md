# Menjalankan Semuanya di Raspberry Pi 4

Pi ada di kapal, tanpa monitor dan tanpa keyboard. Semua harus menyala sendiri
saat listrik masuk, dan harus bangkit sendiri kalau ada yang mati.

## Penghubung antar program

Tidak ada program yang memanggil program lain secara langsung. Semuanya lewat
tiga jenis sambungan:

| Dari | Ke | Lewat |
|---|---|---|
| Python | ESP32 | Serial USB `/dev/ttyUSB0` 115200, dua arah |
| Python | Laravel | `POST /api/telemetry` + header `X-ASV-Token` |
| Python | Browser | MJPEG `GET /stream/atas` (Flask :8000, di-proxy nginx) |
| Laravel | Browser | WebSocket Reverb `:8081` (di-proxy nginx) |
| Laravel | Python | `POST` ke Flask untuk berhenti darurat — **belum ada di kedua sisi** |

Konsekuensi penting: **video tidak pernah melewati PHP.** Laravel hanya mencetak
URL-nya ke HTML; browser menyambung langsung ke Flask.

## Satu hal yang harus diubah dari cara pakai sekarang

Contoh pemakaian di program Python memakai URL ngrok untuk `--api-url`:

```
--api-url https://xxxx.ngrok-free.app/api/telemetry
```

Di kapal ini keliru. Python dan Laravel berada di **mesin yang sama**, jadi
mengirim telemetri keluar ke internet lalu kembali lagi ke Pi berarti: boros
kuota, tambah latensi, dan telemetri ikut mati begitu internet putus - padahal
databasenya ada di meja sebelah.

Pakai alamat lokal:

```
--api-url http://127.0.0.1/api/telemetry
```

ngrok tetap dipakai, tapi hanya untuk **operator dari darat** membuka dashboard,
bukan untuk lalu lintas di dalam Pi sendiri.

## nginx: satu pintu depan

Satu berkas ini menyelesaikan tiga hal sekaligus: cukup satu terowongan ngrok,
tidak ada masalah CORS karena stream kamera seorigin dengan dashboard, dan
WebSocket Reverb ikut lewat tanpa port terpisah.

Simpan di `/etc/nginx/sites-available/asv`, lalu symlink ke `sites-enabled`.

```nginx
server {
    listen 80;
    server_name _;

    root /var/www/dashboard_monitoring_asv/public;
    index index.php;

    client_max_body_size 8m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # WebSocket Reverb. Tanpa header Upgrade, koneksi ditolak sebagai HTTP biasa.
    location /app {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
    }

    # Stream MJPEG dari program Python.
    location /stream/ {
        proxy_pass http://127.0.0.1:8000/stream/;
        proxy_http_version 1.1;
        proxy_buffering off;          # WAJIB: MJPEG tidak pernah selesai
        proxy_read_timeout 3600s;
        proxy_set_header Host $host;
    }

    # /control/* SENGAJA TIDAK di-proxy. Endpoint itu bisa menjalankan kembali
    # kapal; hanya Laravel di mesin yang sama yang boleh memanggilnya.

    location ~ /\.(?!well-known).* { deny all; }
}
```

Setelah itu di `.env` Laravel, jalur kamera cukup relatif karena sudah seorigin:

```env
CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=
```

Dan supaya browser menyambung ke Reverb lewat ngrok, bukan langsung ke port 8081:

```env
REVERB_HOST=xxxx.ngrok-free.app
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8081
```

Aset harus dibangun ulang setiap kali variabel `VITE_*` berubah, karena nilainya
ditanam saat build - bukan dibaca saat berjalan.

## Izin perangkat

Pengguna yang menjalankan program Python harus punya akses serial dan kamera:

```bash
sudo usermod -aG dialout,video pi
```

Perlu logout/reboot supaya berlaku. Tanpa ini `serial.Serial()` gagal dengan
"Permission denied" - dan itu terjadi diam-diam saat boot, jauh dari mata kamu.

## Berkas systemd

Semua di `/etc/systemd/system/`.

### asv-reverb.service

```ini
[Unit]
Description=ASV Reverb WebSocket server
After=network-online.target mysql.service
Wants=network-online.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/dashboard_monitoring_asv
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8081
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### asv-queue.service

```ini
[Unit]
Description=ASV queue worker (pengirim siaran telemetri)
After=network-online.target mysql.service
Wants=network-online.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/dashboard_monitoring_asv
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=30 --sleep=1
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Tanpa unit ini, telemetri tetap masuk database tetapi **tidak pernah sampai ke
browser** - siarannya menumpuk di tabel `jobs`.

### asv-vision.service

```ini
[Unit]
Description=ASV computer vision, kemudi otomatis, dan telemetri
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=simple
User=pi
WorkingDirectory=/home/pi/asv
Environment="ASV_INGEST_TOKEN=<token dari .env Laravel>"
ExecStart=/usr/bin/python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 \
    --source 0 \
    --no-display \
    --api-url http://127.0.0.1/api/telemetry
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

`--no-display` wajib: tanpa monitor, `cv2.imshow` akan gagal dan program mati.

Kalau Laravel belum siap saat Python mulai, POST telemetri pertama akan gagal.
Itu tidak apa-apa selama program Python mencoba lagi dan tidak berhenti - dan
kode sekarang memang sudah menangkap `RequestException` lalu lanjut.

### asv-tunnel.service

```ini
[Unit]
Description=Terowongan ngrok ke dashboard
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=simple
User=pi
ExecStart=/usr/local/bin/ngrok http 80 --log=stdout
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Paling akhir, karena tidak ada gunanya membuka terowongan ke layanan yang belum
siap.

## Menyalakan

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now asv-reverb asv-queue asv-vision asv-tunnel
sudo systemctl enable --now nginx php8.3-fpm mysql
```

Memeriksa dari SSH:

```bash
systemctl status asv-vision
journalctl -u asv-vision -f          # log langsung, pengganti layar monitor
```

`journalctl` inilah pengganti jendela `cv2.imshow` yang hilang. Semua `print()`
di program Python muncul di sana.

## Urutan menyala

1. `mysql`, `php-fpm`, `nginx` — paket distro, sudah otomatis
2. `asv-reverb` — harus hidup sebelum ada yang menyiarkan
3. `asv-queue` — pengantar siaran ke browser
4. `asv-vision` — pemilik ESP32 dan kamera
5. `asv-tunnel` — pintu untuk operator dari darat

## Memeriksa tanpa membuka dashboard

```bash
# telemetri masuk?
mysql -u root dashboard_monitoring_asv -e \
  "SELECT id, latitude, satellites, battery_percent, created_at
   FROM sensor_data ORDER BY id DESC LIMIT 5;"

# stream hidup? (harus mengalir terus, hentikan dengan Ctrl+C)
curl -s http://127.0.0.1/stream/atas | head -c 200 | xxd | head

# siaran menumpuk? (harus 0 atau mendekati 0)
mysql -u root dashboard_monitoring_asv -e "SELECT COUNT(*) FROM jobs;"
```

Kalau `jobs` terus bertambah, `asv-queue` mati.

## Yang masih kosong

- **Berhenti darurat.** Loop kontrol Python selalu mengirim perintah motor di
  setiap iterasi - tidak ada satu pun kondisi yang membuatnya diam. Selama belum
  ada flag `armed`/`manual_stop`, tombol berhenti di dashboard tidak akan
  berpengaruh: perintah PID berikutnya menyusul 20 ms kemudian.
- **Pemangkasan data.** 1 baris/detik = 86.400 baris/hari. Di kartu SD ini soal
  umur perangkat, bukan cuma ukuran.
- **URL ngrok berubah setiap restart** pada paket gratis. Untuk lomba, pakai
  domain statis.
