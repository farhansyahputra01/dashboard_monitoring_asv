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
<<<<<<< HEAD
    data-rth-url="{{ route('admin.control.rth') }}"
=======
    data-pulang-url="{{ route('admin.control.pulang') }}"
>>>>>>> fc27ed5b06fe9959b2cb750ee245fd9ff196f6e8
>
    <div style="display: flex; gap: 10px; width: 100%;">
        <button type="button" class="dashboard-emergency-btn" data-kendali-tombol style="flex: 1;">
            Memeriksa keadaan kapal...
        </button>
        <button type="button" class="dashboard-emergency-btn" data-kendali-rth style="flex: 1; display: none;" title="Kembali ke titik awal">
            Return to Home (RTH)
        </button>
    </div>

    {{--
        PULANG: kapal kembali ke titik start lewat jejak yang sudah dilewatinya.
        Tombol terpisah dan lebih kecil - ini bukan tombol panik. Berhenti
        darurat tetap menang: kapal yang sedang berhenti tidak akan bergerak
        pulang sampai MULAI ditekan.
    --}}
    <button type="button" class="dashboard-emergency-btn dashboard-pulang-btn" data-kendali-pulang hidden>
        PULANG ke Start
    </button>

    <p class="dashboard-emergency-msg" data-kendali-pesan></p>
</div>
