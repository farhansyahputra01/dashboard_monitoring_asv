@extends('layouts.admin')

@section('title', 'Koordinat GPS Lintasan')

@section('content')
{{--
    Halaman pengisian koordinat GPS tiap objek peta.

    Tiap baris di sini adalah satu ikon di peta lintasan - start, bola merah
    gerbang 3, buoy docking 2, dan seterusnya. Yang diisi cuma latitude dan
    longitude; koordinat meternya (x,y) datang dari peta dan hanya ditampilkan.

    KENAPA x/y TIDAK IKUT DISIMPAN: kalau ia disalin ke sini, ia menjadi
    salinan kedua yang harus dijaga sejalan - dan begitu seseorang menggeser
    gerbang 3 di editor peta, koordinat di halaman ini diam-diam menunjuk
    tempat yang sudah bukan gerbang 3 lagi. Yang disimpan hanya penunjuk
    objeknya (ref), koordinatnya dibaca dari peta saat dipakai.
--}}

@php
    /**
     * Tampilkan lat/lon apa adanya, hanya dirapikan minimal 6 desimal.
     *
     * 102.1288 dan 102.128800 adalah angka yang SAMA, tapi yang pertama
     * terlihat seperti data yang terpotong. Nol di belakang ditambahkan
     * supaya kolomnya sejajar dan tidak menimbulkan keraguan - tanpa
     * membulatkan apa pun: 7 desimal yang tersimpan tetap utuh.
     */
    $angka = function ($v) {
        if ($v === null || $v === '') {
            return '';
        }

        $s = rtrim(number_format((float)$v, 7, '.', ''), '0');
        [$bulat, $pecahan] = array_pad(explode('.', $s), 2, '');

        return $bulat . '.' . str_pad($pecahan, 6, '0');
    };
@endphp

<div class="koordinat-halaman">

    <div class="koordinat-header">
        <div>
            <h2>Koordinat GPS Lintasan</h2>
            <p>
                Isi latitude dan longitude tiap objek yang sudah diukur di lapangan.
                Objek yang belum diukur biarkan kosong - tidak masalah.
            </p>
        </div>
        <a class="lintasan-btn" href="{{ route('admin.monitoring') }}">&larr; Kembali ke Monitoring</a>
    </div>

    @if (session('success'))
        <div class="koordinat-pesan baik">{{ session('success') }}</div>
    @endif

    {{-- isset(): $errors hanya disediakan middleware sesi. Tanpa penjaga ini,
         halaman meledak saat dirender di luar permintaan HTTP biasa. --}}
    @if (isset($errors))
        @foreach ($errors->all() as $galat)
            <div class="koordinat-pesan buruk">{{ $galat }}</div>
        @endforeach
    @endif

    <div class="koordinat-tab">
        @foreach (['A', 'B'] as $k)
            <a href="{{ route('admin.monitoring.koordinat', ['lintasan' => $k]) }}"
               class="lintasan-btn {{ $aktif === $k ? 'aktif' : '' }}">Lintasan {{ $k }}</a>
        @endforeach
    </div>

    {{--
        Penjelasan yang menentukan cara kerja seluruh sistem posisi, jadi
        ditaruh di layar - bukan disembunyikan di dokumentasi.
    --}}
    <div class="koordinat-catatan">
        <strong>Start adalah titik yang paling penting.</strong>
        Di situlah kapal diletakkan saat pertama dinyalakan, jadi posisinya
        diketahui pasti tanpa bantuan GPS. Koordinat GPS start dipakai untuk
        menerjemahkan pembacaan GPS berikutnya menjadi posisi di peta - sebagai
        <em>acuan pendukung</em>, bukan penentu.
        <br><br>
        <strong>Titik kedua menghapus ketergantungan pada kompas.</strong>
        Dengan satu titik saja, arah arena masih diambil dari kompas saat kapal
        dinyalakan - dan kompas adalah sensor paling rentan di kapal ini karena
        duduk dekat kabel ESC berarus puluhan amper. Begitu ada titik kedua yang
        berjauhan, arah arena dihitung dari garis antar keduanya dan kompas
        tidak dipakai sama sekali untuk membentuk kerangka peta.
    </div>

    @foreach (['A', 'B'] as $k)
        <form method="POST" action="{{ route('admin.monitoring.koordinat.simpan') }}"
              class="koordinat-form" @if ($aktif !== $k) style="display:none" @endif>
            @csrf
            <input type="hidden" name="lintasan" value="{{ $k }}">

            <table class="koordinat-tabel">
                <thead>
                    <tr>
                        <th>Objek di peta</th>
                        <th class="sempit">x (m)</th>
                        <th class="sempit">y (m)</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar[$k] as $baris)
                        <tr>
                            <td>
                                <span class="koordinat-ikon {{ $baris['jenis'] }}"></span>
                                {{ $baris['nama'] }}
                            </td>
                            <td class="sempit angka">{{ $baris['x'] }}</td>
                            <td class="sempit angka">{{ $baris['y'] }}</td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="titik[{{ $baris['ref'] }}][lat]"
                                       value="{{ $angka($baris['lat']) }}"
                                       placeholder="1.492171">
                            </td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="titik[{{ $baris['ref'] }}][lon]"
                                       value="{{ $angka($baris['lon']) }}"
                                       placeholder="102.128800">
                            </td>
                            <input type="hidden" name="titik[{{ $baris['ref'] }}][nama]"
                                   value="{{ $baris['nama'] }}">
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                Belum ada objek di lintasan {{ $k }}. Buat dulu di
                                editor peta halaman Monitoring.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="koordinat-kaki">
                <label>
                    Arah sumbu +y (derajat sejati, opsional)
                    <input type="text" inputmode="decimal" name="bearing_sumbu_deg"
                           value="{{ $geometri['lintasan'][$k]['acuan_gps']['bearing_sumbu_deg'] ?? '' }}"
                           placeholder="kosongkan kalau tidak diukur">
                </label>
                <span class="koordinat-bantuan">
                    Diisi hanya kalau arah arena diukur langsung (mis. dengan kompas
                    survei). Kalau ada dua titik ber-GPS, angka ini tidak diperlukan.
                </span>

                <button type="submit" class="lintasan-btn utama">Simpan Lintasan {{ $k }}</button>
            </div>
        </form>
    @endforeach

    <div class="koordinat-catatan">
        Setelah disimpan, salin <code>public/data/lintasan.json</code> ke
        <code>/home/pi/asv/lintasan.json</code> di kapal. Dashboard dan kapal
        membaca berkas yang sama tapi dari dua salinan - kalau berbeda, yang
        tergambar bukan yang dipakai kapal.
    </div>
</div>
@endsection
