{{--
    Tombol kendali kapal: BERHENTI DARURAT / MULAI.

    SATU tombol dengan dua keadaan, bukan dua tombol terpisah. Dua kontrol
    yang sama-sama berarti "jalan" adalah cara termudah membuat operator
    menekan yang salah saat panik - dan saat panik itulah tombol ini paling
    dibutuhkan.

    Program di kapal sengaja MULAI DALAM KEADAAN BERHENTI. Jadi tombol ini
    juga yang memulai lomba: nyalakan program, pastikan peta dan kamera sudah
    benar, baru tekan MULAI.

    $ringkas = true  -> tombol sebesar kontrol lain di barisnya, untuk halaman
    Monitoring yang tombolnya duduk sejajar pemilih lintasan. Tanpa itu,
    tombolnya memakai ukuran penuh seperti di kartu Dashboard.
--}}
<div
    class="kendali-kapal {{ !empty($ringkas) ? 'kendali-kapal--ringkas' : '' }}"
    data-kendali-kapal
    data-stop-url="{{ route('admin.control.stop') }}"
    data-resume-url="{{ route('admin.control.resume') }}"
    data-status-url="{{ route('admin.control.status') }}"
>
    <button type="button" class="dashboard-emergency-btn" data-kendali-tombol>
        Memeriksa keadaan kapal...
    </button>

    <p class="dashboard-emergency-msg" data-kendali-pesan></p>
</div>
