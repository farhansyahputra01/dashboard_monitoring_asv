# Peta Lintasan: posisi kapal dalam meter, bukan lat/lon

Menggantikan peta jejak GPS di halaman monitoring. Alih-alih menggambar
koordinat satelit apa adanya, dashboard sekarang menggambar **arena lomba yang
sudah diketahui bentuknya** (30 x 30 m, kotak 1 meter) lalu menaruh kapal di
atasnya.

---

## Kenapa GPS saja tidak cukup

Modul GPS kelas hobi berdesir 2-5 meter walau kapal terikat diam. Itu bukan
dugaan - `trajectory-map.js` di repo ini terpaksa memasang `MIN_GERAK_M = 3.0`
dan `MAX_LOMPAT_M = 15` justru untuk melawannya, dan komentarnya mencatat
gejalanya: angka "jejak ... m" terus bertambah padahal kapal berhenti.

Di arena 30 meter dengan kotak 1 meter, desiran sebesar itu berarti **2-5
kotak**. Peta bergrid 1 m yang digambar dari lat/lon mentah akan berbohong.

## Tiga sumber, satu posisi

| Sumber | Peran | Sifat |
|---|---|---|
| Kompas + kecepatan (dead reckoning) | penggerak utama | halus, tapi galat menumpuk |
| **Gerbang slalom** | penambat | **mengembalikan galat ke nol** |
| GPS | pembatas kasar | absolut, tapi kasar |

Yang membuat ini bekerja adalah penambat gerbang. `mission_controller.py`
sudah menghitung pasangan bola yang terlewati; saat `pair_count` naik dari 5 ke
6, posisi kapal bukan tebakan lagi - ia ada di gerbang ke-6, yang koordinatnya
tertulis di `lintasan.json`. Di situ dead reckoning di-nol-kan ulang.

Tanpa penambat, dead reckoning meleleh setelah 30-60 m. Dengan penambat, galat
tidak pernah sempat tumbuh besar.

GPS sengaja **tidak** dipakai menggambar. Ia hanya menahan supaya perkiraan
tidak melayang jauh: koreksi baru ditarik kalau selisihnya melewati 6 m
(`RADIUS_PERCAYA_GPS`), dan ditarik pelan (15% per pembacaan) supaya jejaknya
tidak patah-patah.

## Galat terbesar ada di kompas, bukan di GPS

CMPS12 duduk dekat kabel ESC yang menarik puluhan amper. **Kompas meleset 5
derajat = posisi meleset 2,6 m setelah 30 m lurus.** Sebelum angka 1 meter
punya arti, kompas wajib dikalibrasi di kapal yang sudah terpasang lengkap,
dengan motor menyala - bukan di atas meja.

Kolom `selisih_gps_m` di telemetri adalah alat ukurnya: kalau angka itu terus
membesar sepanjang lomba, dead reckoning sedang melenceng.

**Haluan dikirim 10x per detik**, terpisah dari telemetri lengkap yang tetap
1x per detik (baris `H#137.5` di firmware, lihat `HEADING_INTERVAL` di
`src/main.cpp`). Alasannya aritmetika: kapal yang berputar 60 derajat/detik,
dengan haluan berumur sampai 1 detik, dihitung berjalan lurus ke arah lama
sepanjang satu meter penuh sebelum arahnya diperbarui. Pada kotak 1 meter itu
galat yang tidak bisa diabaikan.

Yang TIDAK ikut dipercepat: suhu, tegangan, arus, GPS. Semuanya berubah jauh
lebih lambat, dan mengirimkannya 10x/detik berarti sepuluh kali baris database
serta sepuluh kali kerja Laravel untuk data yang sebagian besar sama persis.

---

## Berkas

```
public/data/lintasan.json    <- SUMBER KEBENARAN geometri arena
  (salinannya di kapal: /home/pi/asv/lintasan.json)

resources/js/lintasan-map.js               penggambar kanvas
resources/views/partials/lintasan-map.blade.php

G:\ASV\asv\posisi_lintasan.py              perhitungan posisi di kapal
```

Kolom baru di `sensor_data` (semuanya nullable):
`lintasan`, `x_m`, `y_m`, `pos_sumber`, `jarak_m`, `selisih_gps_m`,
`pair_count`, `phase`.

Nullable dengan sengaja: program kapal yang dijalankan dengan `--tanpa-posisi`
tetap boleh mengirim telemetri, dan dashboard tinggal tidak menggambar
penandanya.

---

## Menyunting peta dari dashboard

Halaman admin punya tombol **Edit Peta**. Mode ini mati secara bawaan dan harus
dinyalakan dengan sengaja - yang digeser bukan sekadar gambar, melainkan
koordinat yang dipakai kapal untuk menambatkan posisinya. Peta yang bisa
tergeser karena salah klik saat lomba berjalan adalah bahaya, bukan kemudahan.

Setelah mode edit menyala:

| Tindakan | Hasil |
|---|---|
| Seret penanda | Pindah, **terkunci ke kotak 1 meter** |
| Tahan `Shift` sambil menyeret | Bebas kunci (ketelitian 0,1 m), untuk titik kolam yang tidak jatuh pas di garis |
| Klik ganda di dalam arena | Sisipkan titik kolam baru pada ruas terdekat |
| Klik kanan pada penanda | Hapus titik kolam / gerbang / buoy docking |
| **+ Gerbang** | Tambah gerbang baru di tengah arena, lalu seret ke tempatnya |
| **Terapkan** | Simpan ke `public/data/lintasan.json` |
| **Batal** | Buang perubahan, muat ulang dari server |

Yang bisa digeser: titik-titik kolam, kedua bola tiap gerbang, tiga buoy
docking, dua kotak imaging, dan titik start.

Sesudah Terapkan, **salin ulang `public/data/lintasan.json` ke kapal**
(`/home/pi/asv/lintasan.json`). Dashboard dan kapal membaca berkas yang sama
tapi dari dua salinan; kalau berbeda, yang tergambar bukan yang dipakai kapal.

Nomor gerbang **ditulis ulang berurutan** oleh server setiap kali disimpan,
tidak diambil dari browser. Kapal mencocokkan `pair_count` dengan nomor ini,
jadi nomor yang bolong atau kembar akan menambatkan posisi ke gerbang yang
salah. Versi sebelumnya disimpan sebagai `lintasan.bak.json` satu tingkat.

## Ganti lintasan hanya saat kapal berhenti

Kapal membaca pilihan Lintasan **sekali**, saat programnya dinyalakan, lewat
`GET /monitoring/active-track`. Urutan kerjanya karena itu:

```
BERHENTI DARURAT  ->  pilih Lintasan A/B  ->  nyalakan ulang program di kapal
```

Server menegakkan urutan itu, bukan sekadar menganjurkannya: `POST
/admin/monitoring/track` ditolak selama kendali kapal menjawab bahwa kapal
sedang berjalan. Kendali yang tidak terjangkau justru dianggap boleh - itu
berarti program kapal memang sedang mati, keadaan terbaik untuk mengganti arena.

`--lintasan A|B` di baris perintah tetap ada sebagai penimpa manual untuk uji
coba tanpa dashboard. Tanpa argumen itu, pilihan dashboard yang menang.

## GPS: acuan pendukung, bukan penentu

Ada tiga aturan yang membuat GPS tidak bisa lagi mengacaukan posisi:

1. **Tanpa dorongan motor, kapal dianggap diam - titik.** Kalau |(L+R)/2|
   di bawah `MOTOR_DIAM`, posisi tidak disentuh dan jarak tempuh tidak
   bertambah, apa pun kata GPS. Sumbernya ditampilkan `DIAM`.
2. **Kecepatan GPS baru dipercaya di atas 0,25 m/s** (0,9 km/jam). Di bawah
   itu ia desiran, bukan gerakan.
3. **Butuh tiga fix meleset berturut-turut** sebelum koreksi posisi ditarik.
   Galat yang nyata bertahan di beberapa fix; desiran tidak.

Aturan nomor 1 dan 2 lahir dari gejala yang sempat terlihat di lapangan:
penanda berkelok-kelok di sekitar start dengan "16 m tempuh" padahal kapal
belum bergerak. Yang menggerakkannya bukan posisi GPS - itu sudah ditahan -
melainkan **kecepatan** GPS: modul melaporkan 0,2-1,5 km/jam saat kapal
terikat, dan dead reckoning dengan patuh mengintegrasikannya. Kapal ini tidak
punya penggerak selain thruster, jadi perintah motor adalah keterangan yang
jauh lebih dapat dipercaya daripada Doppler GPS pada kecepatan rendah.

Berputar di tempat (L = -R) juga bukan perpindahan: (L+R)/2 menghasilkan nol,
dan itu memang jawaban yang benar.

Terukur: dengan GPS sengaja dilesetkan 12 m sementara kapal diam, penanda tidak
bergerak satu sentimeter pun. Begitu kapal berjalan dan selisihnya bertahan,
koreksi baru masuk - halus, 15% per pembacaan.

Selisihnya tetap dicatat di kolom `selisih_gps_m` dan ditampilkan di peta
sebagai `GPS Δ...`. Itu bukan koreksi, melainkan **alat ukur kesehatan**: kalau
angka itu terus membesar sepanjang lomba, dead reckoning sedang melenceng dan
kompas perlu dikalibrasi.

## Yang WAJIB dikoreksi sebelum dipakai

**Angka di `lintasan.json` masih perkiraan dari gambar lintasan, bukan dari
CAD.** Yang pasti hanya: tiap arena 30 x 30 m, dua arena berdampingan, paddock
di sisi bawah. Yang masih tebakan: bentuk kolam, letak tiap gerbang, letak buoy
docking, dan letak kotak imaging.

Selama koordinat gerbang belum benar, penambat gerbang justru **memindahkan
kapal ke tempat yang salah**. Dua pilihan sampai itu beres:

1. Koreksi `lintasan.json` - ini yang benar.
2. Kosongkan array `gerbang` menjadi `[]` - posisi kembali bertumpu pada dead
   reckoning + GPS saja, kasar tapi tidak menyesatkan.

Sistem koordinatnya: `(0,0)` = sudut kiri-bawah arena dilihat dari sisi
paddock, `x` ke kanan, `y` menjauh dari paddock, satuan meter.

## Titik acuan hasil ukur - cara terbaik mengikat peta ke bumi

Kalau punya koordinat GPS pasti dari lokasi lomba, isikan di blok `acuan_gps`
pada `lintasan.json`. **Dua titik sudah cukup**, dan hasilnya jauh lebih baik
daripada kalibrasi otomatis:

```json
"acuan_gps": {
    "bearing_sumbu_deg": null,
    "titik": [
        { "nama": "start",      "x": 26.0, "y": 1.5,  "lat": 1.459439, "lon": 102.149855 },
        { "nama": "sudut jauh", "x": 0.0,  "y": 30.0, "lat": 1.459764, "lon": 102.149785 }
    ]
}
```

Tiap titik memasangkan koordinat **peta** (x, y meter) dengan koordinat
**bumi** (lat, lon).

Yang hilang begitu blok ini terisi:

| Kelemahan kalibrasi otomatis | Dengan titik acuan |
|---|---|
| Fix GPS pertama meleset 2-5 m, seluruh kerangka ikut bergeser | Titik nol dari hasil ukur, bukan dari satu fix |
| Arah arena diambil dari **kompas** - sensor paling rentan di kapal ini | Arah dihitung dari garis antar dua titik, **kompas tidak disentuh** |
| Harus menunggu GPS fix sebelum peta punya arti | Kerangka terbentuk saat program dinyalakan |

Kompas tetap dipakai untuk arah hadap kapal saat berlayar - yang hilang adalah
perannya dalam menentukan bentuk kerangka peta.

**Ambil dua titik yang BERJAUHAN.** Makin panjang garisnya, makin kecil
pengaruh galat GPS terhadap sudut: galat 2 m pada baseline 40 m = 2,9 derajat,
tapi pada baseline 5 m = 22 derajat. Dua sudut arena yang berseberangan jauh
lebih baik daripada dua titik berdekatan di dermaga start.

Program memeriksa sendiri kewarasan datanya: jarak menurut GPS dibandingkan
dengan jarak menurut peta, dan kalau selisihnya lebih dari 15% ia berteriak.

```
[POSISI] Kerangka dari 2 titik acuan hasil ukur: nol di (26.0, 1.5) = 1.459439, 102.149855
         | sumbu +y menghadap 35.0 derajat sejati
[POSISI] Kecocokan jarak GPS vs peta: 1.000 (1,00 = pas)
```

Punya **satu titik saja**? Titik nolnya jadi pasti, tapi arah sumbu masih harus
datang dari kompas - atau ditulis manual di `bearing_sumbu_deg` (derajat sejati
yang dihadap sumbu +y peta). Blok `acuan_gps` yang kosong membuat program
kembali ke kalibrasi otomatis, jadi aman ditinggal kosong.

## Kalibrasi titik nol (cara otomatis, kalau tidak ada titik acuan)

Titik nol diambil **otomatis dari fix GPS pertama yang layak**. Syaratnya kapal
memang sedang berada di titik start peta dan menghadap arah start - jadi
nyalakan programnya saat kapal masih di dermaga start, bukan setelah kapal
bergerak.

Dari satu momen itu dua hal terikat sekaligus: lat/lon menjadi padanan
koordinat start, dan heading kompas saat itu menjadi acuan sumbu lintasan
(sumbu +y peta tidak perlu kebetulan menghadap utara).

## Kecepatan penuh - satu angka yang harus diukur

`--kecepatan-penuh` (bawaan 1.2 m/s) dipakai memperkirakan laju kapal saat
kecepatan GPS tidak tersedia. Ukur sekali: jalankan kapal lurus 20 m dengan
`L=R=255`, catat waktunya, bagi.

## Menjalankan

```bash
python3 telemetry_motor_controller_turn_speed.py \
    --port /dev/ttyUSB0 --source 0 --stream \
    --lintasan A \
    --kecepatan-penuh 1.2 \
    --api-url http://127.0.0.1/api/telemetry
```

`--lintasan` harus sama dengan pilihan Lintasan A/B di halaman admin - kalau
berbeda, kapal menghitung di arena yang satu sementara dashboard menggambar
arena yang lain.

Matikan seluruh fitur ini dengan `--tanpa-posisi`. Kemudi dan misi tidak
terpengaruh sama sekali: peta lintasan murni untuk pemantauan, tidak ada satu
pun keputusan kemudi yang bergantung padanya.
