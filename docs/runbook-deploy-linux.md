# Runbook Deploy di Linux (Raspberry Pi 4)

Urutan kerja siap salin-tempel, untuk keadaan: **php, nginx, dan mysql sudah
terpasang di Pi, dan repo ini sudah pernah di-clone di sana.**

Alasan di balik tiap langkah ada di [deploy-raspberry-pi.md](deploy-raspberry-pi.md).
Berkas ini sengaja pendek: perintah, lalu apa yang harus terlihat.

**Aturan main: jangan lanjut ke tahap berikutnya sebelum titik periksa tahap
sekarang terlewati.** Kalau semuanya dipasang dulu baru diuji, kamu tidak akan
tahu bagian mana yang putus.

---

## Isi dulu tiga nilai ini

Jalankan di Pi, catat hasilnya - dipakai berulang di bawah:

```bash
hostname -I | awk '{print $1}'      # IP Pi        -> misal 192.168.1.20
ls /run/php/php*-fpm.sock           # soket php    -> misal /run/php/php8.2-fpm.sock
ls -d /var/www/*/artisan            # letak repo   -> misal /var/www/dashboard_monitoring_asv
```

Kalau `ls -d /var/www/*/artisan` kosong, repo lamanya ada di tempat lain:

```bash
sudo find / -name artisan -maxdepth 6 2>/dev/null
```

Sebutan `<ip-pi>` dan `/var/www/dashboard_monitoring_asv` di bawah ini ganti
dengan hasil di atas.

---

# TAHAP 0 - Di laptop, bukan di Pi

Tanpa ini `git pull` di Pi tidak membawa apa-apa yang baru.

```bash
cd /d/Laravel/laragon/www/dashboard_monitoring_asv
git status --short          # 28 termodifikasi + 8 baru saat catatan ini ditulis
git add -A
git commit -m "Galeri foto misi, trajectory map, dan pembaruan dashboard"
git push
```

Pastikan berkas baru ikut terbawa - terutama `app/Services/`,
`app/Http/Controllers/GalleryController.php`, `resources/js/trajectory-map.js`,
dan folder `resources/views/gallery/`. Kalau `resources/js/trajectory-map.js`
tertinggal, `npm run build` di Pi langsung gagal karena diimpor `app.js`.

**Titik periksa:** `git status --short` bersih, dan berkas-berkas itu terlihat
di GitHub.

---

# TAHAP 1 - Paket yang mungkin belum ada di Pi

```bash
sudo apt update
sudo apt install -y php-mysql php-mbstring php-xml php-curl php-zip \
                    composer git python3-opencv python3-pip
pip install pyserial requests flask --break-system-packages
```

Aman diulang - apt melewati yang sudah terpasang. Pakai `python3-opencv` dari
apt, jangan `pip install opencv-python`: versi pip harus dikompilasi di Pi dan
makan waktu sangat lama.

**Titik periksa:**

```bash
php -v                                    # harus 8.2 atau lebih baru
python3 -c "import cv2, serial, requests, flask; print('python OK')"
```

---

# TAHAP 2 - Izin perangkat (serial + kamera)

```bash
sudo usermod -aG dialout,video pi
sudo reboot
```

**Reboot wajib.** Tanpa itu `serial.Serial()` gagal `Permission denied` saat
boot, diam-diam.

**Titik periksa** setelah Pi hidup lagi:

```bash
groups pi | grep -o 'dialout\|video'      # keduanya harus muncul
ls /dev/ttyUSB* /dev/video*               # ESP32 dan kamera terlihat
```

---

# TAHAP 3 - Berkas Python

Salin **seluruh isi folder `ASV2`** apa adanya ke `/home/pi/asv/`. Dari laptop:

```bash
scp -r /g/ASV/ASV2/*.py pi@<ip-pi>:/home/pi/asv/
```

**Jangan mengganti nama berkas apa pun.** Kelimanya saling mengimpor:

```
telemetry_motor_controller_turn_speed.py   <- satu-satunya yang dijalankan
buoy_detection.py
mission_controller.py
docking.py
stream_server.py
```

**Titik periksa** di Pi:

```bash
cd /home/pi/asv
python3 -c "import buoy_detection, mission_controller, docking, stream_server; print('import OK')"
mkdir -p /home/pi/asv/mission_images
```

---

# TAHAP 4 - Tarik kode Laravel

```bash
cd /var/www/dashboard_monitoring_asv
git remote -v                   # pastikan .../dashboard_monitoring_asv.git
git status                      # harus bersih; stash kalau ada yang tercecer
git pull
composer install --no-dev --optimize-autoloader
```

**Titik periksa:** `ls app/Services/MissionImageMirror.php` ada. Kalau tidak,
Tahap 0 belum ter-push.

---

# TAHAP 5 - Berkas `.env`

`.env` ada di `.gitignore`, jadi **`git pull` tidak pernah menyentuhnya.** Kunci
baru harus ditambahkan manual. Lihat dulu mana yang kurang:

```bash
diff <(grep -oP '^[A-Z_]+(?==)' .env.example | sort) \
     <(grep -oP '^[A-Z_]+(?==)' .env | sort)
```

Baris berawalan `<` berarti belum ada di `.env` Pi. Sunting:

```bash
nano .env
```

Nilai yang wajib benar di Pi:

```env
APP_KEY=<JANGAN DISENTUH - lihat catatan di bawah>
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<ip-pi>
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_DATABASE=dashboard_monitoring_asv
DB_USERNAME=root
DB_PASSWORD=<password mysql di Pi>

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<salin dari .env laptop>
REVERB_APP_KEY=<salin dari .env laptop>
REVERB_APP_SECRET=<salin dari .env laptop>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8081
REVERB_HOST=127.0.0.1                 # lihat catatan di bawah - BUKAN IP Pi
REVERB_PORT=8081
REVERB_SCHEME=http

# WAJIB ADA. Ini satu-satunya nilai .env yang ditanam ke dalam berkas JS saat
# `npm run build`. Kalau barisnya hilang, import.meta.env.VITE_REVERB_APP_KEY
# menjadi undefined, konstruktor Pusher melempar, dan SELURUH bundel JS gagal
# dievaluasi - bukan cuma WebSocket yang mati, tapi juga layar penuh kamera dan
# peta lintasan. Gejalanya menyesatkan: berkas JS terkirim 200 dengan MIME
# benar, tapi window.Echo tetap undefined.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"

CAMERA_ATAS_URL=/stream/atas
CAMERA_BAWAH_URL=/stream/bawah        # kosongkan kalau kamera kedua tidak dipasang

ASV_INGEST_TOKEN=<token; lihat cara membuatnya di bawah>
ASV_CONTROL_URL=http://127.0.0.1:8000
ASV_MISSION_IMAGES_PATH=/home/pi/asv/mission_images
```

## APP_KEY - jangan dibuat ulang

Web lama di Pi sudah punya `APP_KEY`. **Biarkan apa adanya.** `php artisan
key:generate` akan menimpanya, dan begitu berubah, seluruh sesi login serta data
terenkripsi lama tidak terbaca lagi. Perintah itu hanya untuk `.env` yang
`APP_KEY`-nya benar-benar masih kosong.

## Dari mana REVERB_APP_KEY dan REVERB_APP_SECRET?

Reverb adalah server WebSocket milik sendiri, bukan layanan pihak ketiga seperti
Pusher - **tidak ada tempat untuk mendaftar dan mengambil kunci.** Ketiganya
cuma string acak yang harus sama antara server dan klien.

Kamu sudah punya di `.env` laptop. **Salin apa adanya** - jangan buat yang baru
di Pi. Alasannya: `REVERB_APP_KEY` ikut tertanam ke dalam berkas JS saat
`npm run build`. Kalau nilai di Pi berbeda sementara asetnya dibangun di laptop,
browser menyodorkan key lama, Reverb menolak, dan koneksi gagal tanpa pesan yang
jelas di layar.

Kalau memang harus membuat dari nol (misalnya `.env` benar-benar baru):

```bash
php artisan install:broadcasting      # mengisi ketiganya dengan nilai acak
```

Aman kalau `REVERB_APP_KEY` terlihat di dalam berkas JS - memang untuk browser.
Yang tidak boleh bocor hanya `REVERB_APP_SECRET`, dan itu tidak pernah ikut
di-build.

## Token telemetri - beda dari APP_KEY

`ASV_INGEST_TOKEN` dipakai program Python di header `X-ASV-Token`. Buat sekali,
lalu **nilai yang sama** dipasang di `.env` Laravel dan di
`Environment=` unit systemd Python (Tahap 12):

```bash
php -r "echo bin2hex(random_bytes(24)).PHP_EOL;"
```

Kalau web lama sudah punya token dan program Python sudah memakainya, pakai
yang lama saja - tidak ada gunanya diganti.

## Tiga nilai lain yang sering salah

- **`REVERB_HOST` di proyek ini bukan alamat untuk browser.** Cek
  `config/broadcasting.php` baris 39: nilai itu dipakai **Laravel sisi server**
  untuk menyetorkan siaran ke Reverb. Browser tidak pernah membacanya -
  `resources/js/echo.js` mengambil host dari `window.location.hostname`. Karena
  Reverb ada di mesin yang sama, isi `127.0.0.1:8081` saja: langsung ke Reverb,
  tanpa memutar lewat nginx. Mengisinya dengan IP Pi + port 80 juga jalan, tapi
  tidak ada untungnya.
- **Jangan pernah menulis `dashboard_monitoring_asv.test` di sini.** Domain itu
  buatan Laragon dan tidak ada di Linux - Laravel akan gagal menyetor siaran.
- **`CAMERA_BAWAH_URL` hanya diisi kalau kamera kedua benar-benar dipasang**,
  dan Python dijalankan dengan `--source-bawah`. Diisi tanpa itu, kotak kamera
  bawah menggantung menunggu frame yang tidak pernah datang.

**Titik periksa:** `php artisan tinker --execute="echo config('asv.mission_images_path');"`
mencetak `/home/pi/asv/mission_images`.

---

# TAHAP 6 - Database, izin, dan symlink storage

```bash
# Buat database kalau belum ada (aman diulang)
sudo mysql -e "CREATE DATABASE IF NOT EXISTS dashboard_monitoring_asv
               CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --force        # --force wajib karena APP_ENV=production
php artisan db:seed                # HANYA kalau belum ada akun admin

sudo chown -R $USER:www-data /var/www/dashboard_monitoring_asv
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan storage:link           # BARU - galeri tidak jalan tanpa ini
```

**Titik periksa:**

```bash
ls -l public/storage               # harus symlink -> ../storage/app/public
php artisan migrate:status | tail -5
```

---

# TAHAP 7 - Bangun aset

`/public/build` ada di `.gitignore`, jadi hasil build tidak pernah ikut ter-push
dan harus dibangun sendiri di Pi (atau disalin dari laptop).

**Aset di proyek ini tidak terikat host.** `resources/js/echo.js` sengaja
mengambil host WebSocket dari `window.location.hostname` saat halaman dibuka,
bukan dari `VITE_REVERB_HOST`. Jadi aset yang sama jalan di `.test` laptop
maupun di IP Pi, dan **tidak perlu dibangun ulang setiap IP berganti.** Satu-
satunya nilai `VITE_*` yang benar-benar ditanam saat build adalah
`VITE_REVERB_APP_KEY` - itulah kenapa key di `.env` Pi harus sama dengan yang
dipakai saat aset dibangun (Tahap 5).

**Cara 1 - bangun di Pi** (paling aman, lambat, butuh nodejs):

```bash
sudo apt install -y nodejs npm
cd /var/www/dashboard_monitoring_asv
npm ci
npm run build
```

**Cara 2 - bangun di laptop dengan nilai Pi, lalu salin:**

```bash
# di laptop: ubah SEMENTARA .env -> REVERB_HOST=<ip-pi>, REVERB_PORT=80
npm run build
scp -r public/build pi@<ip-pi>:/var/www/dashboard_monitoring_asv/public/
# lalu kembalikan .env laptop seperti semula
```

**Titik periksa:**

```bash
ls public/build/manifest.json                                   # harus ada
grep -rl "$(grep '^REVERB_APP_KEY=' .env | cut -d= -f2)" \
     public/build/assets | head -1                              # harus ADA
```

**Kalau yang kedua kosong, JANGAN lanjut.** Artinya `VITE_REVERB_APP_KEY` tidak
terbaca saat build - biasanya barisnya memang tidak ada di `.env` (Tahap 5).
Akibatnya bukan sekadar WebSocket mati: bundel JS gagal dievaluasi seluruhnya,
sehingga layar penuh kamera dan peta lintasan ikut mati tanpa gejala yang
mengarah ke sana. Perbaiki `.env` lalu bangun ulang - `.env` dulu, build
kemudian, tidak bisa dibalik.

Yang kedua membuktikan aset dibangun dengan `REVERB_APP_KEY` yang sekarang ada
di `.env`. Kalau kosong, `.env` diubah **setelah** build - ulangi `npm run build`.

Jangan mencari IP Pi atau `dashboard_monitoring_asv.test` di dalam aset:
keduanya memang tidak akan pernah ada di sana, karena host diambil saat halaman
dibuka. Hasil grep yang kosong di situ bukan tanda kegagalan.

---

# TAHAP 8 - nginx

Perhatikan `listen 80 default_server` - tanpa itu, situs web lama yang mungkin
masih terpasang bisa merebut semua permintaan dan kamu dapat 404 di mana-mana.

```bash
sudo tee /etc/nginx/sites-available/asv > /dev/null <<'EOF'
server {
    listen 80 default_server;
    server_name _;

    root __APPDIR__/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

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
EOF

# Tanam soket php-fpm DAN letak repo yang sebenarnya
sudo sed -i "s|__PHPSOCK__|$(ls /run/php/php*-fpm.sock | head -1)|" \
     /etc/nginx/sites-available/asv
sudo sed -i "s|__APPDIR__|/var/www/dashboard_monitoring_asv|" \
     /etc/nginx/sites-available/asv     # ganti dengan letak repo di Pi-mu

# Matikan SEMUA situs lama, sisakan satu
sudo rm -f /etc/nginx/sites-enabled/*
sudo ln -sf /etc/nginx/sites-available/asv /etc/nginx/sites-enabled/asv

sudo nginx -t && sudo systemctl reload nginx
```

**Titik periksa:**

```bash
grep -E "root|fastcgi_pass" /etc/nginx/sites-available/asv   # tidak ada __...__ tersisa
ls $(grep -oP 'root \K[^;]+' /etc/nginx/sites-available/asv)/index.php
curl -I http://localhost/login                               # harus HTTP/1.1 200
```

Kalau dapat **404**, hampir pasti nginx tidak melayani aplikasi ini sama sekali.
Periksa berurutan:

```bash
curl -I http://localhost/            # ikut 404? berarti bukan soal rute Laravel
ls -l /etc/nginx/sites-enabled/      # cuma boleh ada 'asv'
sudo nginx -T | grep -E "listen|root"
```

Tiga penyebabnya, dari yang paling sering:

1. **`root` menunjuk folder yang salah** - `__APPDIR__` diganti dengan path yang
   bukan letak repo di Pi-mu. `index.php` tidak ketemu, nginx menjawab 404 untuk
   semua URL. Ini yang paling umum kalau web lama tidak berada di
   `/var/www/dashboard_monitoring_asv`.
2. **Situs lama masih terpasang** dan memegang `default_server`, jadi
   permintaanmu tidak pernah sampai ke blok `asv`.
3. **Cache rute lama** dari deploy sebelumnya:
   `php artisan route:clear && php artisan config:clear`.

Kode galat lainnya:

- **502** -> php-fpm mati atau soketnya salah
- **500** `Target class ... does not exist` -> `composer dump-autoload -o && php artisan config:clear`
- **Halaman polos tanpa gaya** -> Tahap 7 belum beres

---

# TAHAP 9 - Galeri foto misi

Foto ditulis program Python sebagai user biasa, tapi yang menyalinnya php-fpm
sebagai `www-data`. Di Raspberry Pi OS baru folder home ber-mode `0750`, jadi
`www-data` tidak bisa masuk sama sekali - dan `copy()` di `MissionImageMirror`
dibungkus `@`, sehingga gagalnya **tanpa pesan apa pun**: galeri sekadar kosong
selamanya.

## Cara A - folder netral (disarankan)

Menaruh foto di luar folder home menghapus seluruh persoalan ini sekaligus:
tidak ada `/home` yang harus ditembus, dan tidak peduli user Pi-mu bernama `pi`
atau bukan.

```bash
sudo mkdir -p /var/lib/asv/mission_images
sudo chown $USER:www-data /var/lib/asv/mission_images
sudo chmod 2775 /var/lib/asv/mission_images
```

Angka `2` di depan itu **setgid**: berkas baru yang lahir di sana otomatis
bergrup `www-data`, jadi php-fpm selalu bisa membacanya tanpa perlu ditambal
lagi. Digabung `UMask=0002` di unit systemd (Tahap 12), foto lahir ber-mode
`0664` - penulisnya user Pi, pembacanya `www-data`.

Lalu samakan **dua** tempat ini:

```env
# .env Laravel
ASV_MISSION_IMAGES_PATH=/var/lib/asv/mission_images
```

```bash
# argumen Python, di Tahap 11 dan di ExecStart Tahap 12
--image-dir /var/lib/asv/mission_images
```

Sesudah `.env` berubah, ulangi `php artisan config:cache` (Tahap 10).

## Cara B - bertahan di folder home

Kalau foto harus tetap di `/home/<user>/asv/mission_images`:

```bash
PIUSER=$(whoami)                             # jangan asumsikan namanya 'pi'
sudo usermod -aG $PIUSER www-data
sudo chmod g+x /home/$PIUSER                 # g+x, BUKAN o+x - lihat catatan
sudo chmod -R g+rX /home/$PIUSER/asv /home/$PIUSER/asv/mission_images
sudo systemctl restart php8.4-fpm            # sesuaikan versi php
```

Restart php-fpm wajib - keanggotaan grup hanya dibaca saat proses lahir.

> **Kenapa `g+x`, bukan `o+x`?** Kernel memakai **kelas izin pertama yang
> cocok**, lalu berhenti: pemilik -> grup -> lainnya. Begitu `www-data` menjadi
> anggota grup `$PIUSER`, ia berhenti di kelas **grup** dan bit `other` tidak
> pernah dibaca lagi. Jadi `chmod o+x /home/$PIUSER` pada folder bermode `0700`
> tidak berpengaruh sama sekali - hasilnya `0701`, dan bit grup tetap `---`.
> Gejalanya: `usermod` sudah benar, `id www-data` sudah memuat grupnya, tapi
> `ls` tetap `Permission denied`. Baca `namei -l` sampai ke baris folder home:
> kolom grupnya harus punya `x`.
>
> Efek sampingnya justru terbalik dari dugaan - **masuk grup tanpa `g+x`
> membuat aksesnya lebih buruk** daripada tidak masuk grup sama sekali.

`0711` sudah cukup: grup boleh menelusuri, tetap tidak boleh melihat isi folder
home. Cara ini tetap lebih rapuh daripada Cara A - sekali ada tingkat folder
yang dikembalikan ke `0700`, galeri mati lagi tanpa pesan.

## Cron penjadwal

Lalu pasang cron penjadwal. **Sekarang wajib**, bukan lagi opsional:
`asv:sync-mission-images` berjalan tiap menit supaya foto tetap tersalin walau
tidak ada yang membuka dashboard - persis situasi saat lomba berlangsung.

```bash
sudo tee /etc/cron.d/asv > /dev/null <<'EOF'
* * * * * www-data cd /var/www/dashboard_monitoring_asv && php artisan schedule:run >> /dev/null 2>&1
EOF
sudo chmod 644 /etc/cron.d/asv
```

Kolom keenam (`www-data`) itu yang menentukan siapa penjalannya. Harus
`www-data`, supaya berkas hasil salinan sekepemilikan dengan php-fpm.

**Titik periksa:**

```bash
FOTO=$(php artisan tinker --execute="echo config('asv.mission_images_path');")
sudo -u www-data ls "$FOTO"                            # harus terbaca
sudo -u www-data php artisan asv:sync-mission-images
```

> **Awas jebakan: `Tidak ada foto baru` BUKAN tanda berhasil.** Kalau folder
> sumber tidak terbaca, `MissionImageMirror::folderSumber()` mengembalikan
> `null` dan pencerminan berhenti dengan hasil 0 - pesannya sama persis dengan
> folder kosong. Yang menentukan hanyalah baris `ls` di atas. Selama itu masih
> `Permission denied`, abaikan pesan perintah artisan.

Kalau `Permission denied` bertahan meski `chmod` sudah diulang, jangan menebak -
lihat izin di tiap tingkat path:

```bash
namei -l "$FOTO"        # tunjukkan folder mana yang menutup jalan
id www-data             # grup yang dibutuhkan sudah ada di daftar?
ls -ld /home/*          # user Pi-mu memang bernama 'pi'?
```

Tiga penyebab tersering:

1. **User Pi bukan `pi`.** Raspberry Pi OS Bookworm tidak lagi membuat user
   `pi` secara bawaan - kamu memilih namanya saat pemasangan. Semua perintah
   yang menyebut `/home/pi` dan grup `pi` jadi salah sasaran, dan
   `usermod -aG pi www-data` gagal karena grupnya tidak ada.
2. **Ada satu tingkat folder yang masih tertutup - biasanya folder home.**
   `namei -l` menunjukkan barisnya. Perhatikan **kolom mana** yang berlaku:
   kalau `www-data` sudah masuk grup pemilik folder, yang dibaca adalah bit
   **grup**, dan bit `other` diabaikan. `drwx-----x` (0701) tetap ditolak
   meski `other` punya `x`. Perbaikannya `chmod g+x`, lihat Cara B.
3. **Foldernya memang kosong.** Kalau Python belum pernah menyelesaikan fase
   imaging di Pi, tidak ada apa pun untuk disalin. Uji dengan foto sungguhan
   dari laptop:

   ```bash
   scp /g/ASV/ASV2/mission_images/*.jpg pi@<ip-pi>:/var/lib/asv/mission_images/
   ```

Kalau ketiganya sudah bersih tapi tetap buntu, pindah ke **Cara A** - folder
netral menghapus seluruh kelas masalah ini.

`public/storage belum ada` -> `php artisan storage:link` di Tahap 6.

---

# TAHAP 10 - Segarkan cache

Wajib diulang **setiap kali `.env` berubah**. Selama cache lama ada, nilai baru
tidak dibaca sama sekali.

```bash
cd /var/www/dashboard_monitoring_asv
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

# TAHAP 11 - Uji manual dulu, jangan langsung systemd

Buka tiga terminal SSH. Kalau langsung dipasang ke systemd lalu ada yang gagal,
semuanya diam di belakang layar dan kamu tidak tahu bagian mana yang salah.

**Terminal 1 - Reverb:**

```bash
cd /var/www/dashboard_monitoring_asv
php artisan reverb:start
```

Harus muncul `Starting server on 127.0.0.1:8081`.

**Terminal 2 - Python:**

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

Harus muncul berulang:

```
[TELEMETRY] batt=50% heading=337.3 sat=5 -> POST 201
```

`--stream` **jangan sampai lupa** - bawaannya mati. Tanpa itu kotak kamera
kosong DAN tombol berhenti darurat ikut mati.
`--image-dir` tulis absolut - bawaannya relatif terhadap folder kerja - dan
**harus sama persis** dengan `ASV_MISSION_IMAGES_PATH` di `.env`. Kalau Tahap 9
memakai Cara A, keduanya `/var/lib/asv/mission_images`.
Belum ada ESP32? Tambahkan `--no-serial` untuk menguji rantai webnya saja.

**Kamera kedua**: tambahkan `--source-bawah <n>` kalau memang dipasang, dan
pastikan sejalan dengan `CAMERA_BAWAH_URL` di `.env` - keduanya harus sama-sama
diisi atau sama-sama kosong. Cari nomor perangkatnya dulu:

```bash
v4l2-ctl --list-devices          # sudo apt install v4l-utils kalau belum ada
```

Tiap kamera USB biasanya memakai dua nomor `/dev/videoN`, jadi kamera kedua
sering bukan `1` melainkan `2`. Coba nomor yang disebut pertama di tiap blok
keluaran perintah di atas.

- `POST 401` -> token beda dengan `.env` Laravel
- `POST GAGAL (ConnectionError)` -> nginx/php-fpm mati, ulangi Tahap 8
- `[STREAM-GAGAL] Tidak bisa mengikat port 8000` -> proses lama masih hidup,
  `sudo lsof -i :8000`

**Terminal 3 - periksa:**

```bash
cd /var/www/dashboard_monitoring_asv
php artisan asv:doctor
curl -s http://localhost/stream/atas | head -c 200 | xxd | head -3
```

`asv:doctor` memeriksa seluruh rantai dan menunjuk bagian yang putus. Stream
harus mengalir terus (hentikan dengan Ctrl+C); kalau langsung berhenti,
`proxy_buffering off` belum ada di nginx.

**Terakhir, buka `http://<ip-pi>/` dari laptop.** Angka harus berubah tiap
detik dan kotak kamera menampilkan video. Kalau angka diam:

1. Developer Tools -> tab Network -> filter `WS`. Harus ada koneksi ke `/app`
   berstatus **101 Switching Protocols**. Kalau 200 atau 404, blok
   `location /app` di nginx belum benar.
2. Kalau koneksi WS terbentuk tapi langsung tertutup, biasanya
   `REVERB_APP_KEY` di `.env` berbeda dari yang tertanam di aset - ulangi
   Tahap 7 setelah `.env` benar.
3. Kalau `asv:doctor` bilang telemetri masuk tapi tidak ada siaran sama sekali,
   periksa `REVERB_HOST`/`REVERB_PORT`: itu alamat yang dipakai **Laravel**
   untuk menyetor ke Reverb (`127.0.0.1:8081`), bukan alamat browser.

---

# TAHAP 12 - systemd (baru setelah Tahap 11 lulus semua)

Hentikan yang berjalan manual (Ctrl+C di tiap terminal), lalu:

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
sudo systemctl enable --now nginx mysql cron asv-reverb asv-vision
```

Catatan:

- **Tidak ada `asv-queue`.** `SensorDataUpdated` sekarang `ShouldBroadcastNow`,
  siaran dikirim di dalam request POST itu juga. Queue worker sudah bukan
  bagian dari jalur realtime.
- **Hanya satu unit untuk Python.** `mission_controller` dan `stream_server`
  adalah modul yang diimpor, bukan program terpisah - tidak ada dua proses yang
  berebut kamera.
- `WorkingDirectory` wajib menunjuk folder berisi kelima berkas Python.
- `UMask=0002` membuat foto misi lahir dengan izin baca untuk grupnya, supaya
  `www-data` bisa menyalinnya.
- **`Environment="ASV_INGEST_TOKEN=..."` harus diisi token yang sama persis
  dengan `.env` Laravel.** Ini penyebab paling umum telemetri tiba-tiba berhenti
  setelah pindah dari uji manual ke systemd: `export` di terminal tidak ikut
  terbawa ke unit systemd.
- Kalau memakai kamera kedua, tambahkan `--source-bawah <n>` di `ExecStart`
  juga - jangan hanya di uji manual Tahap 11.

---

# TAHAP 13 - Uji sesungguhnya

```bash
sudo reboot
```

Tunggu satu menit, lalu **tanpa menjalankan apa pun secara manual**:

```bash
systemctl is-active asv-reverb asv-vision nginx mysql cron
cd /var/www/dashboard_monitoring_asv && php artisan asv:doctor
sudo -u www-data php artisan asv:sync-mission-images
```

Buka `http://<ip-pi>/` dari laptop. Kalau angka berubah, video tampil, dan
halaman `/galeri` terisi tanpa kamu menyentuh terminal - sistemnya siap.

---

# Lampiran A - Update berikutnya (rutin)

Sesudah semua di atas selesai sekali, pembaruan cukup segini:

```bash
cd /var/www/dashboard_monitoring_asv
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm run build                     # atau salin public/build dari laptop
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart asv-reverb
```

Tambahkan `php artisan storage:link` hanya kalau `public/storage` hilang, dan
sunting `.env` hanya kalau `diff` di Tahap 5 menunjukkan ada kunci baru.

---

# Lampiran B - Kalau macet

```bash
journalctl -u asv-vision -f      # semua print() Python muncul di sini
journalctl -u asv-reverb -f
tail -f storage/logs/laravel.log
```

## Kalau halamannya tampil tapi tidak ada yang bergerak

Periksa dari browser, bukan dari server - gejalanya sering menyesatkan ke arah
nginx atau Reverb padahal masalahnya di bundel JS. Tempel di Console (F12):

```js
console.log({
  echo:   typeof window.Echo,                                    // harus "object"
  helper: typeof window.saatEchoSiap,                            // harus "function"
  kamera: document.querySelectorAll('[data-camera-label]').length,
  skrip:  [...document.querySelectorAll('script[type=module]')].map(s => s.src),
});
```

`echo: "undefined"` sementara `helper` dan `kamera` normal berarti berkas JS
sampai ke browser tapi gagal dijalankan. Tarik pesan galatnya - jangan mencari
manual di antara keluaran Console:

```js
import('/build/assets/app-XXXX.js')       // salin nama dari 'skrip' di atas
  .then(() => console.log('MODUL OK'))
  .catch(e => console.log('GAGAL:', e.message));
```

Modul yang gagal disimpan browser beserta galatnya, jadi ini memunculkan pesan
yang sama persis dalam satu baris bersih. `You must pass your app key when you
instantiate Pusher` berarti `VITE_REVERB_APP_KEY` hilang saat build.

| Gejala | Penyebab tersering |
|---|---|
| Dashboard 500 `Target class ... does not exist` | `composer dump-autoload -o` |
| Halaman polos tanpa gaya | `public/build` belum ada (Tahap 7) |
| Angka diam, `sensor_data` bertambah | Reverb mati, `REVERB_APP_KEY` beda dari yang tertanam di aset, atau `REVERB_HOST` server salah |
| 404 di semua URL | `root` nginx salah path, atau situs lama masih memegang `default_server` |
| Data beku **dan** dblclick kamera mati sekaligus | bundel JS gagal dievaluasi - hampir selalu `VITE_REVERB_APP_KEY` hilang dari `.env` saat build (Tahap 5) |
| Angka diam, `sensor_data` kosong | token salah (401) atau Python mati |
| Kamera kotak kosong | `--stream` tidak ditulis, atau `proxy_buffering off` belum ada |
| Tombol berhenti tidak berpengaruh | `--stream` tidak ditulis, atau Python mati |
| Galeri kosong padahal foto ada di Pi | `www-data` tidak bisa baca folder Python (Tahap 9) |
| Galeri berhenti bertambah sendiri | cron belum dipasang, atau bukan sebagai `www-data` |
| Foto jadi ikon rusak | `php artisan storage:link` belum dijalankan |
| `.env` diubah tapi tak berpengaruh | `php artisan config:cache` belum diulang |
| `ModuleNotFoundError: buoy_detection` | berkas Python kurang atau namanya diganti (Tahap 3) |

---

# Yang belum tertangani

- **Foto misi tidak pernah dipangkas.** `asv:prune-sensor-data` hanya menyentuh
  tabel telemetri. Foto menumpuk di dua tempat sekaligus - folder Python dan
  `storage/app/public/mission_images` - jadi tiap foto memakan dua kali ruang
  kartu SD.
- **`stream_server.py` mengikat ke `0.0.0.0`.** Karena nginx mem-proxy dari
  `127.0.0.1`, sebaiknya diubah ke `127.0.0.1` supaya `/control/resume` tidak
  bisa dipanggil siapa pun di WiFi yang sama.
- **`asv:doctor` belum memeriksa rantai galeri** - symlink, izin folder, dan
  cron tidak ikut diperiksa.
- **URL ngrok gratis berubah tiap restart.** Kalau memantau dari luar jaringan,
  `REVERB_HOST` harus diganti ke domain ngrok, `REVERB_PORT=443`,
  `REVERB_SCHEME=https`, lalu **aset dibangun ulang**.
