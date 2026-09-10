# Runbook Deploy Sisi Web di Jetson Orin Nano

Keadaan saat catatan ini ditulis: **folder Python sudah tersalin ke Jetson dan
kode deteksi objek sudah diuji jalan.** Yang belum pernah diuji di Jetson:
serial ESP32, pengiriman telemetri, dan seluruh sisi web (PHP, nginx, MySQL,
Laravel, Reverb, aset).

Jadi dari enam mata rantai **ESP32 → serial → Python → POST → MySQL → Reverb →
browser**, yang terbukti baru satu — dan itu bahkan bukan mata rantai telemetri,
melainkan deteksi objeknya. Anggap sisanya belum ada.

Alur logikanya sama persis dengan
[runbook-deploy-linux.md](runbook-deploy-linux.md) — yang berubah hanya hal-hal
yang memang berbeda antara Raspberry Pi OS dan Ubuntu-nya JetPack. Kalau ada
yang tidak dibahas di sini, jawabannya ada di runbook Pi.

**Aturan main tetap sama: jangan lanjut sebelum titik periksa tahap sekarang
terlewati.**

---

## Urutan yang disarankan dari keadaan sekarang

Godaannya adalah mencolok ESP32, menjalankan program lengkap, lalu menebak-nebak
kenapa dashboard kosong. Jangan. Bangun rantainya dari dua ujung yang terpisah,
baru disambung di tengah — kalau semuanya dinyalakan sekaligus, satu kegagalan
terlihat sama dengan lima kegagalan lain.

| Urutan | Yang dibuktikan | Tahap |
|---|---|---|
| 1 | ESP32 terbaca stabil sebagai `/dev/ttyUSB0` | Tahap 2 |
| 2 | Sisi web hidup sendirian: `curl` POST dapat `201` | Tahap 1, 3–10 + Lampiran nomor 2 |
| 3 | Python bisa POST **tanpa ESP32** (`--no-serial`) | Tahap 11 |
| 4 | Rantai penuh dengan ESP32 sungguhan | Tahap 11 |
| 5 | Semuanya hidup sendiri setelah reboot | Tahap 12–13 |

Langkah 3 itu yang sering dilewat dan paling menghemat waktu: `--no-serial`
membuat Python mengirim telemetri palsu, sehingga jalur **Python → nginx →
Laravel → MySQL → Reverb → browser** terbukti benar **sebelum** ESP32 ikut
dituduh. Kalau dashboard sudah bergerak dengan `--no-serial`, sisa masalah pasti
ada di serial — bukan di web.

**Tahap 2 adalah blokir berikutnya buatmu**, bukan Tahap 1: di Ubuntu, ESP32
kerap tidak pernah stabil terbaca sampai `brltty` dibuang.

---

## Yang berbeda dari Raspberry Pi — baca ini dulu

| Hal | Raspberry Pi OS | Jetson (JetPack / Ubuntu) |
|---|---|---|
| PHP bawaan | 8.2 (bookworm) — cukup | 20.04 → 7.4, 22.04 → 8.1 — **dua-duanya terlalu tua** |
| Node bawaan apt | 18/20 | 22.04 → 12.22 — **terlalu tua untuk Vite 7** |
| User | `pi` | user buatanmu sendiri (`nvidia`, `jetson`, dsb.) |
| `/dev/ttyUSB0` | langsung stabil | direbut `brltty` + `ModemManager` — **port hilang sendiri** |
| MySQL root | password biasa | `auth_socket` — PHP tidak bisa login sebagai root |
| Jam saat boot | tanpa RTC | tanpa RTC juga; dev kit Orin Nano tidak dikirim dengan baterai RTC |

Empat baris pertama itulah yang akan memakan waktu kalau perintah dari runbook
Pi disalin mentah-mentah.

---

## TAHAP 0 — Catat lima nilai ini

Jalankan di Jetson, catat hasilnya. Semua `<...>` di bawah mengacu ke sini.

```bash
hostname -I | awk '{print $1}'   # <ip-jetson>
whoami                           # <user>  — BUKAN 'pi'
ls -d /home/*/asv                # <dir-python>, misal /home/nvidia/asv
lsb_release -a                   # 20.04 (JetPack 5) atau 22.04 (JetPack 6)
php -v 2>/dev/null || echo "php belum ada"
```

Sekalian pastikan Jetson tidak sedang di mode hemat daya — inferensi YOLO dan
PHP-FPM berebut CPU yang sama:

```bash
sudo nvpmodel -q                 # harus MAXN / mode 0
sudo nvpmodel -m 0
sudo jetson_clocks
```

---

## TAHAP 1 — PHP 8.2+ (ini blokir pertama)

`composer.json` proyek ini mensyaratkan `"php": "^8.2"` (Laravel 12). PHP
bawaan Ubuntu 22.04 adalah 8.1 dan 20.04 adalah 7.4 — `composer install` akan
**menolak jalan**, bukan sekadar berjalan lambat.

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
                    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
                    nginx mysql-server git unzip
```

Composer **jangan dari apt** — paket apt Ubuntu menarik php8.1 sebagai
dependensi dan bisa menggeser versi default kembali ke 8.1:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Titik periksa:**

```bash
php -v                            # 8.2 atau 8.3
ls /run/php/php*-fpm.sock         # catat namanya -> <php-sock>
systemctl is-active php8.3-fpm nginx mysql
composer --version
```

> Kalau `add-apt-repository ppa:ondrej/php` tidak menyediakan paket arm64 untuk
> rilis Ubuntu-mu, jangan memaksa dengan PHP 8.1. Dua jalan keluar yang masih
> waras: (a) naik ke JetPack yang berbasis 22.04, atau (b) biarkan sisi web di
> laptop dan arahkan `--api-url` Python ke sana — lihat "Web tidak harus di
> kapal" di bagian akhir.

---

## TAHAP 2 — Serial ESP32 di Ubuntu (`brltty` dan `ModemManager`)

**Ini gotcha khas Ubuntu yang tidak pernah muncul di Raspberry Pi OS.** Ubuntu
mengirim `brltty` (pembaca braille) yang mengklaim chip CH340/CP210x sebagai
perangkat braille, dan `ModemManager` yang membuka tiap port serial baru untuk
menyapanya dengan perintah AT. Gejalanya persis seperti kabel rusak:
`/dev/ttyUSB0` muncul sebentar lalu **hilang sendiri**, atau `serial.Serial()`
kena `Permission denied` / `Device busy` yang acak.

```bash
sudo apt remove -y brltty
sudo systemctl disable --now ModemManager
sudo usermod -aG dialout,video $USER
sudo reboot
```

**Reboot wajib** — keanggotaan grup baru dibaca saat login, dan unit systemd
Python di Tahap 12 akan gagal diam-diam tanpa itu.

**Titik periksa** setelah hidup lagi:

```bash
groups | grep -o 'dialout\|video'      # keduanya muncul
ls -l /dev/ttyUSB*                     # ada, grupnya dialout
dmesg | tail -20                       # tidak ada baris brltty merebut device
```

Cabut-colok ESP32 lalu tunggu 30 detik. Kalau `/dev/ttyUSB0` masih ada,
`brltty` benar-benar sudah pergi.

**Lalu buktikan ESP32-nya benar-benar bicara** — ini belum pernah diuji di
Jetson, dan jauh lebih murah dibuktikan sekarang daripada nanti di tengah rantai
penuh:

```bash
python3 - <<'PY'
import serial, time
s = serial.Serial('/dev/ttyUSB0', 115200, timeout=2)
time.sleep(2)          # membuka port me-reset ESP32; beri waktu boot
for _ in range(10):
    print(s.readline().decode(errors='replace').strip())
PY
```

Harus keluar baris telemetri dari firmware. Kalau kosong melulu, periksa baud
rate; kalau `Device busy`, ada proses lain yang memegang port (`sudo lsof
/dev/ttyUSB0`). **Jangan lanjut ke sisi web sambil berharap ini beres sendiri** —
nanti kegagalan serial akan terlihat persis seperti kegagalan web.

---

## TAHAP 3 — MySQL: jangan pakai root

Di Ubuntu, `root@localhost` memakai plugin `auth_socket`: `sudo mysql` jalan,
tapi PHP-FPM (yang berjalan sebagai `www-data`) tidak akan pernah bisa login
dengan password. Buat user khusus:

```bash
sudo mysql
```

```sql
CREATE DATABASE IF NOT EXISTS dashboard_monitoring_asv
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'asv'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_INI';
GRANT ALL PRIVILEGES ON dashboard_monitoring_asv.* TO 'asv'@'localhost';
FLUSH PRIVILEGES;
```

Sekalian samakan zona waktunya dengan `APP_TIMEZONE`. Beda zona waktu MySQL
membuat `created_at` telemetri meleset 7 jam dan `asv:doctor` melaporkan baris
"dari MASA DEPAN" — padahal telemetrinya baik-baik saja:

```bash
sudo timedatectl set-timezone Asia/Jakarta
timedatectl                       # cek 'System clock synchronized: yes'
```

**Titik periksa:**

```bash
mysql -u asv -p -e "SELECT NOW();" dashboard_monitoring_asv
```

---

## TAHAP 4 — Kode Laravel

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <url-repo> dashboard_monitoring_asv
sudo chown -R $USER:www-data /var/www/dashboard_monitoring_asv
cd dashboard_monitoring_asv
composer install --no-dev --optimize-autoloader
```

**Titik periksa:** `ls app/Services/MissionImageMirror.php` dan
`ls app/Console/Commands/DoctorCommand.php` — dua-duanya ada.

---

## TAHAP 5 — Berkas `.env`

`.env` ada di `.gitignore`, jadi tidak ikut `git clone`. Salin dari contoh lalu
isi:

```bash
cp .env.example .env
php artisan key:generate      # BOLEH di sini: .env ini benar-benar baru
nano .env
```

Nilai yang wajib benar di Jetson:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<ip-jetson>
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_DATABASE=dashboard_monitoring_asv
DB_USERNAME=asv
DB_PASSWORD=<password dari Tahap 3>

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<SALIN dari .env laptop>
REVERB_APP_KEY=<SALIN dari .env laptop>
REVERB_APP_SECRET=<SALIN dari .env laptop>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8081
REVERB_HOST=127.0.0.1          # alamat Laravel->Reverb, BUKAN alamat browser
REVERB_PORT=8081
REVERB_SCHEME=http

# Satu-satunya VITE_* yang benar-benar ditanam saat `npm run build`.
# Hilang -> SELURUH bundel JS gagal dievaluasi: angka beku, kamera mati, dan
# peta lintasan mati sekaligus, tanpa satu pun pesan galat yang mengarah ke sini.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"

CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=              # isi hanya kalau kamera kedua benar-benar ada

ASV_INGEST_TOKEN=<token yang SAMA PERSIS dengan yang dipakai Python>
ASV_CONTROL_URL=http://127.0.0.1:8000
ASV_MISSION_IMAGES_PATH=/var/lib/asv/mission_images
```

Tiga hal yang paling sering salah, sama seperti di Pi:

- **`REVERB_APP_KEY` disalin dari `.env` laptop**, bukan dibuat baru — nilai itu
  ikut tertanam ke berkas JS saat build. Kalau asetnya dibangun di laptop
  sementara key di Jetson berbeda, Reverb menolak sambungan browser tanpa pesan
  apa pun di layar.
- **Jangan pernah menulis `dashboard_monitoring_asv.test`** di sini. Domain itu
  buatan Laragon dan tidak ada di Jetson; Laravel akan gagal menyetor siaran.
- **`ASV_INGEST_TOKEN` bukan `APP_KEY`.** Ini token `X-ASV-Token` milik Python.
  Kalau Python di Jetson sudah pernah berhasil POST, pakai token yang sama —
  jangan diganti.

---

## TAHAP 6 — Migrasi, izin, symlink

```bash
php artisan migrate --force        # --force wajib karena APP_ENV=production
php artisan db:seed                # HANYA kalau belum ada akun admin

sudo chown -R $USER:www-data /var/www/dashboard_monitoring_asv
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

**Titik periksa:** `ls -l public/storage` menunjuk `../storage/app/public`, dan
`php artisan migrate:status | tail -5` semuanya `Ran`.

---

## TAHAP 7 — Aset frontend

Vite 7 butuh Node 20+. `apt install nodejs` di Ubuntu 22.04 memberi 12.22 dan
`npm run build` gagal dengan galat sintaks yang menyesatkan.

**Cara yang disarankan — bangun di laptop, salin hasilnya.** Aset di proyek ini
tidak terikat host (`resources/js/echo.js` mengambil host WebSocket dari
`window.location.hostname` saat halaman dibuka), jadi build laptop jalan apa
adanya di Jetson — asalkan `REVERB_APP_KEY`-nya sama.

```bash
# di laptop
npm run build
scp -r public/build <user>@<ip-jetson>:/var/www/dashboard_monitoring_asv/public/
```

**Kalau memang mau build di Jetson:**

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v                            # v20.x
cd /var/www/dashboard_monitoring_asv && npm ci && npm run build
```

**Titik periksa — jangan lanjut kalau yang kedua kosong:**

```bash
ls public/build/manifest.json
grep -rl "$(grep '^REVERB_APP_KEY=' .env | cut -d= -f2)" public/build/assets | head -1
```

Jangan mencari IP Jetson di dalam aset; memang tidak akan pernah ada di sana,
karena host diambil saat halaman dibuka. Grep kosong di situ bukan tanda gagal.

---

## TAHAP 8 — nginx

Konfigurasinya identik dengan runbook Pi. Yang berubah hanya nama soket php-fpm
(`php8.3-fpm.sock`, bukan `php8.2-fpm.sock`) — dan itu diisi otomatis oleh `sed`
di bawah.

```bash
sudo tee /etc/nginx/sites-available/asv > /dev/null <<'NGINX'
server {
    listen 80 default_server;
    server_name _;

    root __APPDIR__/public;
    index index.php;
    client_max_body_size 20M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:__PHPSOCK__;
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
NGINX

sudo sed -i "s|__PHPSOCK__|$(ls /run/php/php*-fpm.sock | head -1)|" /etc/nginx/sites-available/asv
sudo sed -i "s|__APPDIR__|/var/www/dashboard_monitoring_asv|"        /etc/nginx/sites-available/asv

sudo rm -f /etc/nginx/sites-enabled/*
sudo ln -sf /etc/nginx/sites-available/asv /etc/nginx/sites-enabled/asv
sudo nginx -t && sudo systemctl reload nginx
```

**Titik periksa:**

```bash
grep -E "root|fastcgi_pass" /etc/nginx/sites-available/asv   # tidak ada __...__ tersisa
curl -I http://localhost/login                               # HTTP/1.1 200
```

- **404 di semua URL** → `root` menunjuk folder yang salah, atau situs lama
  masih memegang `default_server` (`ls -l /etc/nginx/sites-enabled/`).
- **502** → nama soket php-fpm salah. Di Jetson ini sering terjadi karena nginx
  menunjuk `php8.1-fpm.sock` yang tidak dipakai.
- **500 `Target class ... does not exist`** → `composer dump-autoload -o`.

---

## TAHAP 9 — Folder foto misi

Pakai folder netral, di luar `/home`. Home Ubuntu ber-mode `0750` sehingga
`www-data` tidak bisa masuk sama sekali, dan `copy()` di `MissionImageMirror`
dibungkus `@` — galerinya sekadar kosong selamanya tanpa pesan apa pun.

```bash
sudo mkdir -p /var/lib/asv/mission_images
sudo chown $USER:www-data /var/lib/asv/mission_images
sudo chmod 2775 /var/lib/asv/mission_images     # setgid: berkas baru ikut grup
```

Nilai ini harus sama di tiga tempat: `ASV_MISSION_IMAGES_PATH` di `.env`,
`--image-dir` di Tahap 11, dan `ExecStart` di Tahap 12.

---

## TAHAP 10 — Segarkan cache

Wajib diulang **setiap kali `.env` berubah**. Selama cache lama ada, nilai baru
tidak dibaca sama sekali.

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## TAHAP 11 — Uji manual dulu, jangan langsung systemd

**Terminal 1 — Reverb:**

```bash
cd /var/www/dashboard_monitoring_asv && php artisan reverb:start
# harus muncul: Starting server on 127.0.0.1:8081
```

**Terminal 2 — Python.** Jalankan **dua kali**: sekali tanpa ESP32, baru sekali
dengan ESP32. Urutan ini yang memisahkan kegagalan web dari kegagalan serial.

**11a — tanpa ESP32 dulu (`--no-serial`):**

```bash
cd /home/<user>/asv
export ASV_INGEST_TOKEN='<token yang sama persis dengan .env Laravel>'

python3 telemetry_motor_controller_turn_speed.py \
    --no-serial --source 0 --no-display \
    --stream --stream-port 8000 \
    --image-dir /var/lib/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry
```

Buka `http://<ip-jetson>/` dari laptop. **Kalau angka sudah bergerak di sini,
seluruh sisi web terbukti benar** — nginx, token, MySQL, Reverb, dan bundel JS.
Apa pun yang gagal setelah ini pasti soal serial, bukan web. Kalau angka diam di
tahap ini, jangan colok ESP32 dulu; selesaikan lewat Lampiran nomor 5.

**11b — baru dengan ESP32:**

```bash
python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 --source 0 --no-display \
    --stream --stream-port 8000 \
    --image-dir /var/lib/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry
```

Yang harus terlihat berulang:
`[TELEMETRY] batt=50% heading=337.3 sat=5 -> POST 201`

Sebelum baris itu, harus terlihat dulu tiga baris YOLO — ini bagian yang sudah
kamu uji, jadi kalau tiba-tiba hilang berarti `WorkingDirectory`/`best.tflite`
yang bergeser, bukan telemetrinya:

```
[YOLO] Model dimuat: /home/<user>/asv/best.tflite
[YOLO] LiteRT | imgsz=320 | tata letak=NCHW | conf=0.45 | thread=3
[YOLO] Kelas: 0=ball_blue, 1=ball_green, 2=ball_red
```

**`--api-url` sekarang `http://127.0.0.1/api/telemetry`** — bukan ngrok, bukan
IP laptop. Python dan Laravel ada di mesin yang sama; keluar ke internet lalu
kembali lagi berarti telemetri ikut mati saat WiFi putus.

**Terminal 3 — periksa:**

```bash
cd /var/www/dashboard_monitoring_asv
php artisan asv:doctor
curl -s http://localhost/stream/atas | head -c 200 | xxd | head -3
```

Stream harus mengalir terus (hentikan dengan Ctrl+C). Kalau langsung berhenti,
`proxy_buffering off` belum ada di nginx.

Terakhir, buka `http://<ip-jetson>/` dari laptop. Angka harus berubah tiap detik
dan kotak kamera menampilkan video. Kalau tidak → Lampiran di bawah.

---

## TAHAP 12 — systemd

Perhatikan `User=` dan path — **bukan `pi`** lagi.

```bash
sudo tee /etc/systemd/system/asv-reverb.service > /dev/null <<'UNIT'
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
UNIT

sudo tee /etc/systemd/system/asv-vision.service > /dev/null <<'UNIT'
[Unit]
Description=ASV computer vision, kemudi otomatis, dan telemetri
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=simple
User=GANTI_USER
WorkingDirectory=/home/GANTI_USER/asv
Environment="ASV_INGEST_TOKEN=GANTI_DENGAN_TOKEN_DI_ENV_LARAVEL"
ExecStart=/usr/bin/python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 --source 0 --no-display \
    --stream --stream-port 8000 \
    --image-dir /var/lib/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry
Restart=always
RestartSec=5
UMask=0002

[Install]
WantedBy=multi-user.target
UNIT

sudo sed -i "s/GANTI_USER/$(whoami)/g" /etc/systemd/system/asv-vision.service
sudo systemctl daemon-reload
sudo systemctl enable --now nginx mysql cron asv-reverb asv-vision
```

Catatan:

- **Tidak ada `asv-queue`.** `SensorDataUpdated` sudah `ShouldBroadcastNow` —
  siaran dikirim di dalam request POST itu juga, bukan lewat antrean. Angka yang
  diam **bukan** lagi gejala queue worker mati.
- **`Environment="ASV_INGEST_TOKEN=..."` harus diisi.** `export` di terminal
  Tahap 11 tidak ikut terbawa ke unit systemd. Ini penyebab nomor satu telemetri
  mendadak berhenti tepat setelah pindah dari uji manual ke systemd.
- **Hanya satu unit untuk Python.** `mission_controller` dan `stream_server`
  adalah modul yang diimpor, bukan program terpisah.
- Penjadwal Laravel (`asv:sync-mission-images` tiap menit, `asv:prune-sensor-data`
  pukul 03:00) dipasang sebagai `www-data`:
  `* * * * * cd /var/www/dashboard_monitoring_asv && php artisan schedule:run >> /dev/null 2>&1`

---

## TAHAP 13 — Uji sesungguhnya

```bash
sudo reboot
```

Tunggu semenit, lalu **tanpa menjalankan apa pun secara manual**:

```bash
systemctl is-active asv-reverb asv-vision nginx mysql cron
cd /var/www/dashboard_monitoring_asv && php artisan asv:doctor
sudo -u www-data php artisan asv:sync-mission-images
```

---

# Lampiran — Jembatan ESP32 → web: urutan triase

Rantainya: **ESP32 → serial → Python → POST /api/telemetry → MySQL → Reverb →
browser.** Enam mata rantai, dan "layar diam" terlihat sama persis di keenamnya.
Periksa berurutan, jangan melompat — ini yang dulu memakan waktu paling lama.

Satu perintah yang menunjuk langsung mata rantai yang putus:

```bash
php artisan asv:doctor
```

## 1. ESP32 → serial (paling sering di Jetson)

```bash
ls -l /dev/ttyUSB*
sudo lsof /dev/ttyUSB0        # HARUS cuma satu proses
```

- Port hilang sendiri setelah beberapa detik → `brltty` / `ModemManager`
  (Tahap 2). Ini baru, tidak pernah terjadi di Pi.
- `Permission denied` → belum masuk grup `dialout`, atau reboot dilewat.
- **Dua proses memegang port** → hanya satu yang boleh.
  `php artisan asv:read-serial` **jangan pernah dijalankan di kapal**: perintah
  itu cuma untuk pengembangan di laptop, dan di Jetson ia akan merebut port dari
  Python.
- Membuka port me-reset ESP32; beri jeda ~2 detik sebelum menyalahkan datanya.

## 2. Python → POST

Baca lognya, jangan menebak:

```bash
journalctl -u asv-vision -f
```

| Yang terbaca | Artinya |
|---|---|
| `-> POST 201` | rantai sampai database. Lanjut ke nomor 4. |
| `-> POST 401` | token beda. `ASV_INGEST_TOKEN` di `.env` ≠ `Environment=` di unit systemd. |
| `-> POST 422` | ada field yang tidak lolos validasi — lihat `storage/logs/laravel.log`. |
| `ConnectionError` | nginx/php-fpm mati, atau `--api-url` salah host. |
| tidak ada baris `[TELEMETRY]` sama sekali | putusnya di nomor 1, bukan di web. |

Uji endpointnya sendiri, terpisah dari Python. Ini memisahkan "Python-nya salah"
dari "webnya salah" dalam satu perintah:

```bash
curl -i -X POST http://127.0.0.1/api/telemetry \
  -H "X-ASV-Token: $(grep '^ASV_INGEST_TOKEN=' /var/www/dashboard_monitoring_asv/.env | cut -d= -f2)" \
  -H "Content-Type: application/json" \
  -d '{"temperature":30,"humidity":50,"heading":180,"battery_percent":77}'
```

`201` → sisi web sehat, masalahnya di Python. `401` → token. `500` → baca
`storage/logs/laravel.log`.

## 3. Sudah membetulkan `.env` tapi tetap 401

`php artisan config:cache` belum diulang. Selama cache lama ada, `.env` baru
tidak dibaca sama sekali. Jebakan ini berulang kali memakan waktu — kalau
menyentuh `.env`, selalu ulangi Tahap 10.

## 4. Database — apakah datanya memang masuk?

Jalankan ini SEBELUM membongkar nginx, Reverb, atau bundel JS:

```bash
php artisan tinker --execute="echo \App\Models\SensorData::latest('id')->first()->created_at->diffForHumans();"
```

- `1 second ago` → data masuk; masalahnya murni di sisi tampilan → nomor 5.
- `7 hours ago` **padahal Python barusan mencetak POST 201** → zona waktu MySQL
  ≠ `APP_TIMEZONE`. Bukan telemetrinya yang mati (Tahap 3).
- Waktu "di MASA DEPAN" → jam Jetson salah. Dev kit Orin Nano tidak dikirim
  dengan baterai RTC, jadi setelah mati total jamnya mundur sampai NTP masuk.
  Tanpa internet di lapangan, set manual sebelum lomba:
  `sudo timedatectl set-time "2026-09-08 09:00:00"`.
- Kosong sama sekali → belum pernah ada satu pun POST yang lolos → nomor 2.

## 5. Database → browser (angka diam padahal `sensor_data` bertambah)

Periksa dari **browser**, bukan dari server — gejalanya selalu menyesatkan ke
arah nginx atau Reverb padahal masalahnya di bundel JS. Tempel di Console (F12):

```js
console.log({
  echo:   typeof window.Echo,            // harus "object"
  helper: typeof window.saatEchoSiap,    // harus "function"
  kamera: document.querySelectorAll('[data-camera-label]').length,
  skrip:  [...document.querySelectorAll('script[type=module]')].map(s => s.src),
});
```

- `echo: "undefined"` sementara `helper`/`kamera` normal → berkas JS sampai ke
  browser tapi gagal dijalankan. Tarik pesan galatnya:
  `import('/build/assets/app-XXXX.js').then(()=>console.log('OK')).catch(e=>console.log('GAGAL:',e.message))`.
  `You must pass your app key when you instantiate Pusher` berarti
  `VITE_REVERB_APP_KEY` hilang saat build (Tahap 5 + 7). Penanda khasnya:
  **data beku DAN dblclick kamera mati sekaligus** — padahal tidak ada
  hubungannya dengan WebSocket.
- Tab Network → filter `WS`: harus ada `/app` berstatus **101 Switching
  Protocols**. 200 atau 404 → blok `location /app` di nginx belum benar.
- WS tersambung lalu langsung tertutup → `REVERB_APP_KEY` di `.env` berbeda dari
  yang tertanam di aset. Betulkan `.env` **lalu** build ulang; tidak bisa
  dibalik.
- `asv:doctor` bilang telemetri masuk tapi tidak ada siaran sama sekali →
  periksa `REVERB_HOST`/`REVERB_PORT`. Itu alamat yang dipakai **Laravel** untuk
  menyetor ke Reverb (`127.0.0.1:8081`), bukan alamat browser.

## 6. Nilai sensor tampil tapi ada yang kosong — ini bukan kerusakan

Sudah ditangani di sisi server (`TelemetryController::applySensorRules`), jadi
**jangan disaring lagi di Python** — kirim apa adanya:

- `-999` dari DHT11 dan kompas → disimpan `null`, bukan ditolak. Dulu satu
  kompas mati membuat **seluruh baris** telemetri hilang karena aturan
  `between:0,360`; sekarang tidak lagi.
- GPS belum fix mengirim `0` → `latitude`, `longitude`, `speed`, `altitude`
  jadi `null`, supaya 0,0 tidak terlihat seperti koordinat sungguhan.
- Balasan `201` menyertakan `{"gps_fix":true|false}` — pakai itu untuk menilai
  fix GPS, bukan dengan membaca angka lat/lng.

---

# Web tidak harus ada di kapal

Kalau PHP 8.2 di Jetson bermasalah, atau Jetson perlu seluruh CPU-nya untuk
inferensi, sisi web boleh tetap di laptop. Yang berubah hanya satu argumen:

```
--api-url http://<ip-laptop>/api/telemetry
```

Konsekuensinya: telemetri ikut mati saat WiFi kapal↔darat putus, dan
`ASV_CONTROL_URL` tidak lagi `127.0.0.1` sehingga tombol berhenti darurat harus
menjangkau Jetson lewat jaringan — padahal `/control/*` sengaja tidak di-proxy
justru karena endpoint itu bisa menjalankan kembali kapal. Untuk lomba, web di
kapal tetap yang lebih aman.
