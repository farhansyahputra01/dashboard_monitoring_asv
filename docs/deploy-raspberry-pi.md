# Menjalankan Semuanya di Raspberry Pi 4

Dari Pi kosong sampai dashboard tampil di browser dan menyala sendiri saat
listrik masuk.

**Kerjakan berurutan, dan jalankan manual dulu sebelum dipasang ke systemd.**
Kalau langsung systemd lalu ada yang gagal, kamu tidak akan tahu bagian mana
yang salah - semuanya diam di belakang layar.

---

# BAGIAN A - Pemasangan sekali saja

## A1. Paket

```bash
sudo apt update
sudo apt install -y nginx mysql-server git \
                    php-fpm php-mysql php-mbstring php-xml php-curl php-zip \
                    python3-opencv python3-pip

php -v          # catat versinya, dipakai di A6 (butuh 8.2 atau lebih baru)
pip install pyserial requests flask --break-system-packages
```

Pakai `python3-opencv` dari apt, jangan `pip install opencv-python` - versi pip
sering harus dikompilasi di Pi dan makan waktu sangat lama.

## A2. Izin perangkat

```bash
sudo usermod -aG dialout,video pi
sudo reboot
```

Wajib reboot. Tanpa ini `serial.Serial()` gagal "Permission denied" saat boot,
diam-diam.

## A3. Berkas Python

Tiga berkas, **satu folder**:

```
/home/pi/asv/
├── telemetry_motor_controller_turn_speed.py
├── buoy_detection_full.py      <- kalau namanya masih buoy_detection.py, ganti
└── stream_server.py
```

```bash
cd /home/pi/asv
mv buoy_detection.py buoy_detection_full.py    # kalau perlu
python3 -c "import buoy_detection_full, stream_server; print('import OK')"
```

Nama `buoy_detection_full.py` tidak boleh salah - itu yang ditulis di baris
`import` kedua controller.

## A4. Laravel

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <url-repo> dashboard_monitoring_asv
cd dashboard_monitoring_asv
sudo chown -R $USER:www-data .

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## A5. Isi `.env`

Bagian yang wajib diubah:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<ip-raspberry-pi>

DB_CONNECTION=mysql
DB_DATABASE=dashboard_monitoring_asv
DB_USERNAME=root
DB_PASSWORD=<password mysql>

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=832326
REVERB_APP_KEY=<isi>
REVERB_APP_SECRET=<isi>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8081
REVERB_HOST=<ip-raspberry-pi>
REVERB_PORT=80
REVERB_SCHEME=http

CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=

ASV_INGEST_TOKEN=<token; buat dengan: php -r "echo bin2hex(random_bytes(24));">
ASV_CONTROL_URL=http://127.0.0.1:8000
```

Perhatikan dua pasangan yang berbeda peran:

| | Untuk siapa | Nilai |
|---|---|---|
| `REVERB_SERVER_HOST/PORT` | server Reverb mengikat diri | `127.0.0.1:8081` |
| `REVERB_HOST/PORT` | alamat yang dituju **browser** | IP Pi, port 80 (lewat nginx) |

## A6. Database dan aset

```bash
sudo mysql -e "CREATE DATABASE dashboard_monitoring_asv
               CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed        # kalau perlu akun admin
```

Bangun aset. **Urutannya penting**: nilai `VITE_*` ditanam ke dalam berkas JS
saat build, bukan dibaca saat berjalan. Kalau `.env` diubah setelah ini, harus
build ulang.

Cara termudah: bangun di laptop lalu salin, karena Node di Pi lambat.

```bash
# di laptop
npm run build
scp -r public/build pi@<ip-pi>:/var/www/dashboard_monitoring_asv/public/
```

Izin folder tulis:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## A7. nginx

Ganti `php8.2-fpm.sock` sesuai hasil `php -v` di A1.

```bash
sudo tee /etc/nginx/sites-available/asv > /dev/null <<'EOF'
server {
    listen 80;
    server_name _;

    root /var/www/dashboard_monitoring_asv/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
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
        proxy_read_timeout 3600s;
    }

    # Stream MJPEG dari program Python.
    location /stream/ {
        proxy_pass http://127.0.0.1:8000/stream/;
        proxy_http_version 1.1;
        proxy_buffering off;          # WAJIB: MJPEG tidak pernah selesai
        proxy_read_timeout 3600s;
    }

    # /control/* SENGAJA TIDAK di-proxy: endpoint itu bisa menjalankan kembali
    # kapal, jadi hanya boleh dipanggil Laravel dari mesin yang sama.

    location ~ /\.(?!well-known).* { deny all; }
}
EOF

sudo ln -sf /etc/nginx/sites-available/asv /etc/nginx/sites-enabled/asv
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

# BAGIAN B - Jalankan manual, periksa tiap langkah

Buka **empat** terminal SSH. Jangan lanjut ke langkah berikutnya sebelum
langkah sebelumnya terbukti jalan.

## B1. Website muncul

```bash
curl -I http://localhost/login
```

Harus `HTTP/1.1 200`.

- Dapat **500** dengan pesan `Target class ... does not exist`
  -> `composer dump-autoload -o && php artisan config:clear`
- Dapat **502** -> php-fpm mati atau nama soket di nginx salah
- Halaman polos tanpa gaya -> `public/build` belum tersalin (A6)

Buka `http://<ip-pi>/` di browser. **Sampai di sini kamu belum akan melihat
angka sensor** - itu wajar, belum ada yang mengirim.

## B2. Reverb — terminal 1

```bash
cd /var/www/dashboard_monitoring_asv
php artisan reverb:start
```

Harus muncul `Starting server on 127.0.0.1:8081`.

## B3. Queue — terminal 2

```bash
cd /var/www/dashboard_monitoring_asv
php artisan queue:work
```

Tanpa ini, telemetri **masuk database tapi layar tidak pernah berubah**. Ini
penyebab tersering "dashboard diam padahal Python jalan".

## B4. Python — terminal 3

```bash
cd /home/pi/asv
export ASV_INGEST_TOKEN='<token yang sama persis dengan .env Laravel>'

python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 \
    --source 0 \
    --no-display \
    --api-url http://127.0.0.1/api/telemetry
```

Yang harus terlihat:

```
[TELEMETRY] batt=50% heading=337.3 sat=5 -> POST 201
```

- `POST 401` -> token tidak sama dengan `.env` Laravel
- `POST GAGAL (ConnectionError)` -> nginx/php-fpm mati, ulangi B1
- `Permission denied` pada serial -> A2 belum dijalankan atau belum reboot
- `ModuleNotFoundError: buoy_detection_full` -> nama berkas, lihat A3

## B5. Periksa hasilnya

Terminal 4:

```bash
cd /var/www/dashboard_monitoring_asv
php artisan asv:doctor
```

Perintah ini memeriksa seluruh rantai sekaligus dan menunjuk bagian yang putus
beserta cara memperbaikinya.

Cek stream kamera:

```bash
curl -s http://localhost/stream/atas | head -c 100 | xxd | head -3
```

Harus mengalir terus (hentikan dengan Ctrl+C). Kalau langsung berhenti,
`proxy_buffering off` belum ada di nginx.

## B6. Buka dashboard

`http://<ip-pi>/` — angka harus berubah tiap detik, dan kotak kamera menampilkan
video dengan lingkaran deteksi buoy.

Kalau angka masih diam padahal `asv:doctor` bilang telemetri mengalir:

1. Buka Developer Tools browser -> tab Console. Cari error WebSocket.
2. Tab Network -> filter `WS`. Harus ada koneksi ke `/app` berstatus `101
   Switching Protocols`. Kalau `200` atau `404`, blok `location /app` di nginx
   belum benar.
3. Kalau browser menyambung ke alamat yang salah, berarti aset dibangun sebelum
   `REVERB_HOST` diisi -> `npm run build` ulang lalu salin lagi.

---

# BAGIAN C - Otomatis saat menyala

Baru kerjakan ini **setelah Bagian B berhasil seluruhnya**.

Hentikan semua yang berjalan manual (Ctrl+C di tiap terminal), lalu:

```bash
sudo tee /etc/systemd/system/asv-reverb.service > /dev/null <<'EOF'
[Unit]
Description=ASV Reverb WebSocket server
After=network-online.target mysql.service
Wants=network-online.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/dashboard_monitoring_asv
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo tee /etc/systemd/system/asv-queue.service > /dev/null <<'EOF'
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
EOF

sudo tee /etc/systemd/system/asv-vision.service > /dev/null <<'EOF'
[Unit]
Description=ASV computer vision, kemudi otomatis, dan telemetri
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=simple
User=pi
WorkingDirectory=/home/pi/asv
Environment="ASV_INGEST_TOKEN=GANTI_DENGAN_TOKEN_DI_ENV_LARAVEL"
ExecStart=/usr/bin/python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 \
    --source 0 \
    --no-display \
    --api-url http://127.0.0.1/api/telemetry
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now nginx mysql asv-reverb asv-queue asv-vision
```

`WorkingDirectory` pada `asv-vision` wajib menunjuk folder berisi ketiga berkas
Python - importnya relatif.

## ngrok (kalau operator memantau dari luar jaringan)

```bash
sudo tee /etc/systemd/system/asv-tunnel.service > /dev/null <<'EOF'
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
EOF

sudo systemctl enable --now asv-tunnel
```

Kalau memakai ngrok, `REVERB_HOST` di `.env` harus diganti ke domain ngrok,
`REVERB_PORT=443`, `REVERB_SCHEME=https` — lalu **`npm run build` ulang**.

---

# BAGIAN D - Uji sesungguhnya

```bash
sudo reboot
```

Tunggu satu menit, lalu tanpa menjalankan apa pun secara manual:

```bash
systemctl status asv-reverb asv-queue asv-vision --no-pager | grep -E "●|Active"
cd /var/www/dashboard_monitoring_asv && php artisan asv:doctor
```

Buka dashboard dari laptop. Kalau angka berubah dan video tampil tanpa kamu
menyentuh terminal, sistemnya siap.

---

# Kalau macet

```bash
journalctl -u asv-vision -f      # pengganti jendela cv2.imshow yang hilang
journalctl -u asv-queue -f
journalctl -u asv-reverb -f
tail -f storage/logs/laravel.log
```

Semua `print()` di program Python muncul di `journalctl -u asv-vision`.

| Gejala | Penyebab tersering |
|---|---|
| Dashboard 500 `Target class ... does not exist` | huruf besar-kecil folder; `composer dump-autoload -o` |
| Angka diam, `sensor_data` bertambah | `asv-queue` mati |
| Angka diam, `sensor_data` kosong | token salah (401) atau Python mati |
| Kamera kotak kosong | `CAMERA_ATAS_URL` kosong, atau `proxy_buffering off` belum ada |
| Tombol berhenti tidak berpengaruh | `stream_server.start()` belum dipanggil / Python mati |
| Halaman polos tanpa gaya | `public/build` belum disalin |

---

# Yang masih kosong

- **URL ngrok gratis berubah setiap restart.** Untuk lomba, pakai domain statis
  - kalau tidak, `REVERB_HOST` harus diperbarui dan aset dibangun ulang setiap
  kali Pi dinyalakan.
- **`stream_server.py` mengikat ke `0.0.0.0`.** Karena nginx mem-proxy dari
  `127.0.0.1`, sebaiknya diubah ke `127.0.0.1` supaya `/control/resume` tidak
  bisa dipanggil siapa pun di WiFi yang sama.
- **Pemangkasan data** sudah terjadwal harian lewat `asv:prune-sensor-data`,
  tetapi penjadwal Laravel perlu satu baris cron:
  `* * * * * cd /var/www/dashboard_monitoring_asv && php artisan schedule:run >> /dev/null 2>&1`
