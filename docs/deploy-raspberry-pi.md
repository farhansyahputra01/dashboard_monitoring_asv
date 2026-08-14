# Menjalankan Semuanya di Raspberry Pi 4

Dari Pi kosong sampai dashboard tampil di browser dan menyala sendiri saat
listrik masuk.

**Kerjakan berurutan, dan jalankan manual dulu sebelum dipasang ke systemd.**
Kalau langsung systemd lalu ada yang gagal, kamu tidak akan tahu bagian mana
yang salah - semuanya diam di belakang layar.

> Berkas ini menjelaskan **kenapa** tiap langkah begitu. Kalau yang kamu cari
> cuma urutan perintah siap salin-tempel, pakai
> [runbook-deploy-linux.md](runbook-deploy-linux.md) - Tahap 0 sampai 13,
> lengkap dengan titik periksa di tiap tahap.

---

# BAGIAN 0 - Kalau di Pi SUDAH ada web lama (tinggal `git pull`)

Lewati Bagian A kalau php, nginx, dan mysql sudah terpasang dan repo ini sudah
pernah di-clone di Pi. **Tidak ada Laragon di Linux** - yang di Windows
disatukan Laragon, di Pi terpisah jadi tiga servis systemd:

| Di laptop (Laragon) | Di Raspberry Pi |
|---|---|
| Laragon bikin vhost `.test` otomatis | satu berkas `/etc/nginx/sites-available/asv` (A7), ditulis sekali |
| PHP bawaan Laragon | `php-fpm`, dihubungi nginx lewat soket unix |
| MySQL bawaan Laragon | servis `mysql`/`mariadb` |
| Tombol Start/Stop di jendela Laragon | `sudo systemctl start|stop|status <servis>` |
| `http://dashboard_monitoring_asv.test` | `http://<ip-raspberry-pi>` |

Konsekuensinya: **tidak ada domain `.test` di Pi.** Semua nilai `.env` yang
menyebut `dashboard_monitoring_asv.test` harus jadi IP Pi.

## 0a. Pastikan reponya memang repo ini

```bash
cd /var/www/dashboard_monitoring_asv     # sesuaikan kalau letaknya lain
git remote -v                            # harus .../dashboard_monitoring_asv.git
git status                               # harus bersih; commit/stash kalau ada yang tercecer
```

## 0b. Tarik perubahan

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

`--force` wajib karena `APP_ENV=production` - tanpa itu artisan menunggu
jawaban "yes" yang tidak akan pernah datang di skrip.

## 0c. Samakan `.env` - ini yang paling sering terlewat

`.env` ada di `.gitignore`, jadi **`git pull` tidak pernah menyentuhnya.** Kunci
baru yang lahir di laptop tidak akan muncul sendiri di Pi. Bandingkan dulu:

```bash
diff <(grep -oP '^[A-Z_]+(?==)' .env.example | sort) \
     <(grep -oP '^[A-Z_]+(?==)' .env | sort)
```

Baris yang cuma ada di sebelah kiri berarti belum ada di `.env` Pi - tambahkan
manual. Yang sekarang wajib ada dan nilainya khas Pi:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<ip-raspberry-pi>
APP_TIMEZONE=Asia/Jakarta

DB_PASSWORD=<password mysql di Pi>

BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8081
REVERB_HOST=127.0.0.1                # alamat Laravel->Reverb, bukan browser
REVERB_PORT=8081
REVERB_SCHEME=http

CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=              # isi /stream/bawah HANYA kalau Python dijalankan
                               # dengan --source-bawah; kalau tidak, kotaknya
                               # menggantung menunggu frame yang tak pernah ada

ASV_INGEST_TOKEN=<token; harus SAMA PERSIS dengan yang dipakai program Python>
ASV_CONTROL_URL=http://127.0.0.1:8000

# BARU - folder tempat mission_controller.py menyimpan foto misi.
# Kosong -> halaman galeri tampil kosong, tanpa error.
ASV_MISSION_IMAGES_PATH=/home/pi/asv/mission_images
```

## 0d. Bangun ulang aset - tidak ikut `git pull`

`/public/build` ada di `.gitignore`, jadi hasil build **tidak pernah ikut
ter-push**. Setiap kali CSS/JS berubah, asetnya harus dibangun ulang di sisi
Pi (atau dibangun di laptop lalu disalin).

**Asetnya tidak terikat host.** `resources/js/echo.js` sengaja mengambil host
WebSocket dari `window.location.hostname` saat halaman dibuka, bukan dari
`VITE_REVERB_HOST` - komentar di berkas itu menjelaskan alasannya: aset yang
dipatok ke satu domain membuat komputer lain di jaringan gagal menyambung tanpa
pesan apa pun. Jadi aset yang dibangun di laptop **boleh langsung dipakai di
Pi**, dan tidak perlu dibangun ulang tiap kali IP berganti.

Yang tetap ikut tertanam saat build hanyalah `VITE_REVERB_APP_KEY`. Itu satu-
satunya nilai `.env` yang, kalau diubah, mewajibkan build ulang.

Dua cara, pilih salah satu:

```bash
# Cara 1 - bangun di Pi (lambat; butuh nodejs+npm)
npm ci
npm run build
```

```bash
# Cara 2 - bangun di laptop lalu salin (lebih cepat)
npm run build
scp -r public/build pi@<ip-pi>:/var/www/dashboard_monitoring_asv/public/
```

Cek hasilnya sebelum lanjut:

```bash
ls public/build/manifest.json                                    # harus ada
grep -rl "$(grep '^REVERB_APP_KEY=' .env | cut -d= -f2)" \
     public/build/assets | head -1                               # harus ketemu
```

Jangan mencari IP Pi di dalam aset - memang tidak akan ketemu, dan itu bukan
tanda kegagalan.

## 0e. Galeri foto misi - langkah yang BELUM pernah ada sebelumnya

Fitur galeri baru, jadi tiga hal berikut belum pernah dikerjakan di Pi. Tanpa
ketiganya halaman `/galeri` tampil kosong.

**1. Symlink storage.** Foto dicermin ke `storage/app/public/mission_images`
lalu dilayani nginx sebagai berkas statis. Jembatannya symlink:

```bash
php artisan storage:link      # membuat public/storage -> storage/app/public
```

Sekali saja, tapi wajib - `asv:sync-mission-images` menolak jalan tanpa ini.

**2. Izin baca folder Python.** Ini jebakan yang paling merepotkan: foto ditulis
program Python sebagai user `pi`, sedangkan yang menyalinnya adalah php-fpm
sebagai user `www-data`. Di Raspberry Pi OS baru, `/home/pi` ber-mode `0750`,
sehingga `www-data` **tidak bisa masuk ke sana sama sekali** - dan `copy()` di
dalam pencerminan sengaja dibungkus `@`, jadi gagalnya diam-diam: tidak ada
error, galeri sekadar kosong selamanya.

```bash
sudo usermod -aG pi www-data
sudo chmod o+x /home/pi /home/pi/asv          # cukup telusur, bukan baca
sudo chmod -R g+rX /home/pi/asv/mission_images
sudo systemctl restart php8.2-fpm             # sesuaikan versi php
```

Restart php-fpm wajib: keanggotaan grup hanya dibaca saat proses lahir.

Cek benar-benar tembus, jangan hanya percaya perintah di atas berhasil:

```bash
sudo -u www-data ls /home/pi/asv/mission_images | head
```

Kalau ini `Permission denied`, galeri tidak akan pernah terisi.

**3. Cron penjadwal - sekarang WAJIB.** Dulu penjadwal hanya untuk pemangkasan
data harian dan boleh dilewati. Sekarang `asv:sync-mission-images` berjalan tiap
menit, supaya foto tetap tersalin walau tidak ada yang membuka dashboard -
persis situasi saat lomba berlangsung.

```bash
sudo tee /etc/cron.d/asv > /dev/null <<'EOF'
* * * * * www-data cd /var/www/dashboard_monitoring_asv && php artisan schedule:run >> /dev/null 2>&1
EOF
sudo chmod 644 /etc/cron.d/asv
```

Kolom keenam (`www-data`) menentukan siapa penjalannya - itu format
`/etc/cron.d`, berbeda dari crontab per-user. Harus `www-data`, **bukan** `pi`
atau `root`: kalau cron berjalan sebagai user lain, berkas hasil salinan jadi
milik user itu dan php-fpm tidak bisa menimpanya belakangan - galeri berhenti
bertambah tanpa pesan apa pun.

Uji sekarang juga, jangan menunggu semenit:

```bash
sudo -u www-data php artisan asv:sync-mission-images
```

Harus menjawab `N foto baru disalin` atau `Tidak ada foto baru`. Kalau
`public/storage belum ada`, ulangi langkah 1.

## 0f. Segarkan cache dan servis

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

sudo systemctl restart php8.2-fpm asv-reverb        # sesuaikan versi php
php artisan asv:doctor
```

`config:cache` wajib diulang tiap kali `.env` berubah - selama cache lama masih
ada, nilai `.env` yang baru **tidak dibaca sama sekali**.

Kalau ini pertama kalinya versi baru masuk ke Pi, lanjutkan ke A7 (berkas
nginx) dan Bagian C (systemd) karena keduanya belum pernah dibuat di sana.

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

Perintah di atas aman diulang kalau sebagian sudah terpasang - apt melewati
yang sudah ada. Yang biasanya belum ada di Pi yang sudah dipakai web lama:
`php-mbstring`, `php-xml`, `php-zip`, `python3-opencv`, dan `composer`
(`sudo apt install composer`). Node hanya perlu kalau aset dibangun di Pi
(cara 1 pada 0d).

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

Salin **seluruh isi folder `ASV2`** apa adanya. Lima berkas, satu folder,
**tanpa mengganti nama apa pun**:

```
/home/pi/asv/
├── telemetry_motor_controller_turn_speed.py   <- satu-satunya yang dijalankan
├── buoy_detection.py                          <- diimpor, JANGAN diganti nama
├── mission_controller.py                      <- diimpor; penulis foto misi
├── docking.py                                 <- diimpor mission_controller
├── stream_server.py                           <- diimpor; MJPEG + /control
└── mission_images/                            <- dibuat sendiri saat foto pertama
```

**Semuanya satu proses.** `mission_controller` dan `stream_server` adalah modul
yang diimpor (`from mission_controller import MissionController`, `import
stream_server`), bukan program terpisah - jadi hanya ada **satu** unit systemd,
dan tidak ada dua proses yang berebut kamera.

Uji impornya:

```bash
cd /home/pi/asv
python3 -c "import buoy_detection, mission_controller, docking, stream_server; print('import OK')"
```

> **Versi dokumen sebelumnya menyuruh mengganti nama berkas ini menjadi
> `buoy_detection_full.py`. Itu keliru dan justru merusak.** Baris impor di
> controller berbunyi `from buoy_detection import (...)`, jadi kalau namanya
> diganti, program mati dengan `ModuleNotFoundError: buoy_detection`. Biarkan
> `buoy_detection.py`.

Dashboard hanya **membaca** `mission_images/`. Nama berkas ditulis
`mission_controller.py` sebagai `<warna>_<epoch>.jpg` - warnanya bahasa Inggris
huruf kecil, `blue_1786677240.jpg` dan `green_1786677277.jpg`. Berkas di luar
pola itu diabaikan diam-diam oleh saringan di `MissionImageMirror`. Waktu foto
dibaca dari nama berkasnya, bukan dari mtime, karena mtime berubah saat berkas
dicermin.

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
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http

CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=

ASV_INGEST_TOKEN=<token; buat dengan: php -r "echo bin2hex(random_bytes(24));">
ASV_CONTROL_URL=http://127.0.0.1:8000
```

Perhatikan tiga peran yang berbeda - tidak ada satu pun di antaranya yang
menjadi alamat bagi browser:

| | Untuk siapa | Nilai |
|---|---|---|
| `REVERB_SERVER_HOST/PORT` | tempat server Reverb mengikat diri | `127.0.0.1:8081` |
| `REVERB_HOST/PORT` | dipakai **Laravel** menyetor siaran ke Reverb (`config/broadcasting.php` baris 39) | `127.0.0.1:8081` |
| — | alamat yang dituju **browser** | tidak diatur di `.env`; `echo.js` memakai `window.location.hostname` |

## A6. Database dan aset

```bash
sudo mysql -e "CREATE DATABASE dashboard_monitoring_asv
               CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed        # kalau perlu akun admin
```

Bangun aset. Satu-satunya nilai `.env` yang ikut tertanam saat build adalah
`VITE_REVERB_APP_KEY`, jadi hanya perubahan pada `REVERB_APP_KEY` yang
mewajibkan build ulang - lihat 0d.

Cara termudah: bangun di laptop lalu salin, karena Node di Pi lambat.

```bash
# di laptop
npm run build
scp -r public/build pi@<ip-pi>:/var/www/dashboard_monitoring_asv/public/
```

Izin folder tulis, dan symlink storage untuk galeri foto misi:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

Lalu kerjakan langkah 0e - izin baca folder foto dan cron penjadwal berlaku
sama untuk pemasangan baru.

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

## B3. Queue — terminal 2 (opsional)

```bash
cd /var/www/dashboard_monitoring_asv
php artisan queue:work
```

**Tidak lagi berada di jalur realtime.** `SensorDataUpdated` sekarang
`ShouldBroadcastNow`, jadi siaran dikirim ke Reverb di dalam request
`POST /api/telemetry` itu juga - tanpa lewat tabel `jobs`. Dulu lewat antrean,
dan tiap pembacaan sensor tertahan 2,2-2,6 detik karena `queue:work` tidur 3
detik saat antrean kosong.

Jalankan hanya kalau nanti ada pekerjaan lain yang benar-benar diantrekan.
Angka yang diam **bukan** lagi gejala queue worker mati.

## B4. Python — terminal 3

```bash
cd /home/pi/asv
export ASV_INGEST_TOKEN='<token yang sama persis dengan .env Laravel>'

python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 \
    --source 0 \
    --no-display \
    --stream \
    --stream-port 8000 \
    --image-dir /home/pi/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry
```

Tiga argumen yang mudah terlewat, dan akibatnya kalau lupa:

| Argumen | Kalau tidak ditulis |
|---|---|
| `--stream` | `stream_server` **tidak pernah dinyalakan**. Kotak kamera kosong DAN tombol berhenti darurat mati - Laravel mengetuk `127.0.0.1:8000` yang tidak ada isinya. Bawaannya mati, harus disebut eksplisit. |
| `--image-dir` | bawaannya `mission_images` - **relatif terhadap folder kerja**. Kalau folder kerjanya bergeser, foto tertulis di tempat lain dan galeri tetap kosong. Tulis absolut, dan samakan dengan `ASV_MISSION_IMAGES_PATH` di `.env`. |
| `--source-bawah` | kamera bawah tidak pernah menyiarkan apa pun. Isi (misal `--source-bawah 1`) hanya kalau memang ada kamera kedua, lalu isi `CAMERA_BAWAH_URL=/stream/bawah`. |

Yang harus terlihat:

```
[TELEMETRY] batt=50% heading=337.3 sat=5 -> POST 201
```

- `POST 401` -> token tidak sama dengan `.env` Laravel
- `POST GAGAL (ConnectionError)` -> nginx/php-fpm mati, ulangi B1
- `Permission denied` pada serial -> A2 belum dijalankan atau belum reboot
- `ModuleNotFoundError: buoy_detection` -> berkasnya kurang atau namanya diganti, lihat A3
- `[INFO] Flask tidak terpasang` -> `pip install flask --break-system-packages`
- `[STREAM-GAGAL] Tidak bisa mengikat port 8000` -> proses lama masih hidup
  (`sudo lsof -i :8000`), atau pindah ke `--stream-port 8001` **dan** ubah
  `proxy_pass` di nginx serta `ASV_CONTROL_URL` supaya ikut

Kalau ESP32 belum tercolok dan kamu cuma ingin menguji rantai webnya, tambahkan
`--no-serial`. Token juga bisa diberikan lewat `--token '<token>'` kalau sering
tertukar antar terminal.

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
3. Kalau koneksi terbentuk lalu langsung tertutup, `REVERB_APP_KEY` di `.env`
   berbeda dari yang tertanam di aset -> `npm run build` ulang lalu salin lagi.
   Browser tidak pernah salah alamat: `echo.js` selalu memakai host yang sedang
   dibuka.

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
    --stream \
    --stream-port 8000 \
    --image-dir /home/pi/asv/mission_images \
    --api-url http://127.0.0.1/api/telemetry
Restart=always
RestartSec=5
UMask=0002

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now nginx mysql asv-reverb asv-queue asv-vision
```

`WorkingDirectory` pada `asv-vision` wajib menunjuk folder berisi kelima berkas
Python - keempat modul lainnya diimpor secara relatif.

`UMask=0002` membuat foto misi lahir dengan izin baca untuk grupnya, supaya
`www-data` bisa menyalinnya. Tanpa itu foto bisa tertulis `0600` dan pencerminan
gagal diam-diam meski langkah 0e sudah dikerjakan.

Penjadwal Laravel **tidak** memakai systemd, melainkan cron (langkah 0e nomor
3). Jangan lupa dipasang - tanpa itu galeri hanya terisi saat ada orang membuka
halamannya, dan pemangkasan data harian tidak pernah jalan.

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

Dengan ngrok, `.env` **tidak perlu diubah dan aset tidak perlu dibangun ulang**:
`REVERB_HOST` tetap `127.0.0.1:8081` (itu jalur Laravel->Reverb di dalam Pi),
sedangkan browser otomatis memakai domain ngrok yang sedang dibuka lengkap
dengan `https`, karena `echo.js` membacanya dari `window.location`.

Syaratnya blok `location /app` di nginx sudah benar - lalu lintas WebSocket dari
ngrok tetap masuk lewat port 80 Pi.

---

# BAGIAN D - Uji sesungguhnya

```bash
sudo reboot
```

Tunggu satu menit, lalu tanpa menjalankan apa pun secara manual:

```bash
systemctl status asv-reverb asv-queue asv-vision --no-pager | grep -E "●|Active"
cd /var/www/dashboard_monitoring_asv && php artisan asv:doctor

# Galeri tidak diperiksa asv:doctor - uji terpisah
sudo -u www-data php artisan asv:sync-mission-images
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
| Angka diam, `sensor_data` bertambah | Reverb mati, `REVERB_APP_KEY` beda dari yang tertanam di aset, atau `REVERB_HOST` server salah |
| 404 di semua URL | `root` nginx salah path, atau situs lama masih memegang `default_server` |
| Angka diam, `sensor_data` kosong | token salah (401) atau Python mati |
| `.env` diubah tapi tidak berpengaruh | `php artisan config:cache` belum diulang |
| Kamera kotak kosong | `--stream` tidak ditulis, `CAMERA_ATAS_URL` kosong, atau `proxy_buffering off` belum ada |
| Galeri kosong padahal foto ada di Pi | `www-data` tidak bisa baca folder Python (0e langkah 2) |
| Galeri berhenti bertambah sendiri | cron dipasang bukan sebagai `www-data`, atau belum dipasang |
| Foto tampil sebagai ikon rusak | `php artisan storage:link` belum dijalankan |
| Tombol berhenti tidak berpengaruh | `--stream` tidak ditulis (`stream_server` tidak pernah menyala), atau Python mati |
| Halaman polos tanpa gaya | `public/build` belum disalin |

---

# Yang masih kosong

- **URL ngrok gratis berubah setiap restart.** Untuk lomba, pakai domain statis
  supaya tautan yang dibagikan ke juri tidak basi. Sisi teknisnya sendiri sudah
  tahan ganti domain - `echo.js` mengikut host yang dibuka.
- **`stream_server.py` mengikat ke `0.0.0.0`.** Karena nginx mem-proxy dari
  `127.0.0.1`, sebaiknya diubah ke `127.0.0.1` supaya `/control/resume` tidak
  bisa dipanggil siapa pun di WiFi yang sama.
- **Foto misi tidak pernah dipangkas.** `asv:prune-sensor-data` hanya menyentuh
  tabel telemetri. Foto menumpuk di **dua** tempat sekaligus - folder Python dan
  `storage/app/public/mission_images` - jadi tiap foto memakan dua kali ruang
  kartu SD. Untuk pemakaian berhari-hari, perlu pembersih terjadwal atau
  penghapusan manual sesudah lomba.
- **`asv:doctor` belum memeriksa rantai galeri.** Symlink storage, izin baca
  folder Python, dan cron penjadwal tidak ikut diperiksa - kalau galeri
  bermasalah, doctor tetap melaporkan semuanya sehat. Periksa manual dengan
  `sudo -u www-data php artisan asv:sync-mission-images`.
