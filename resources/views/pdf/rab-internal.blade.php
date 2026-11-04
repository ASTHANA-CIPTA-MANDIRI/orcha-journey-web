{{-- RAB INTERNAL — untuk kantor, BUKAN untuk pelanggan.

     Berisi modal, harga satuan, margin, dan untung. Lencana merah di kepala dan
     di kaki setiap halaman ada supaya berkas ini tidak terkirim ke pelanggan
     karena salah pilih lampiran di WhatsApp.

     Tata letak mengikuti rab-penawaran: tabel saja (dompdf tidak mengenal
     flexbox/grid), Helvetica, gaya di dokumen ini sendiri. --}}
@php
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $kabut = '#f4f8fb';
    $hijau = '#1f7a44';

    /*
     | Warna per jenis kegiatan — peta yang SAMA dengan PDF penawaran, supaya
     | dua berkas tentang perjalanan yang sama tidak memakai warna berbeda
     | untuk hal yang sama. Jenisnya ditebak di BerkasRab::jenis.
     */
    $rupa = [
        'destinasi' => ['Destinasi', '#1f7a44'],
        'perjalanan' => ['Perjalanan', '#0369a1'],
        'makan' => ['Makan / singgah', '#e0912f'],
        'snack' => ['Snack', '#0e7490'],
        'istirahat' => ['Istirahat', '#6d28d9'],
        'lain' => ['Lainnya', '#94a3b8'],
    ];
    $jenisKeg = fn ($k) => \App\Support\Rab\BerkasRab::jenis($k->nama, $k->keterangan, $k->destinasi);
    $merah = '#b42318';

    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '', '×' => 'x']);
    $rp = fn ($n) => \App\Support\RincianBiaya::rupiah((int) $n);

    $marginTeks = match ($rab->margin_jenis) {
        'persen' => rtrim(rtrim(number_format((float) $rab->margin_nilai, 2, ',', '.'), '0'), ',').'% dari modal',
        'per_orang' => $rp($rab->margin_nilai).' per orang',
        'total' => $rp($rab->margin_nilai).' untuk rombongan',
        default => '-',
    };
    $kelompok = collect($r['baris'])->groupBy('kategori_label');
    // Dari Orcha, tidak diturunkan di sini: untung - margin diminta hanya
    // kebetulan sama saat tidak ada nego dan peserta = peserta dasar.
    $selisihBulat = $r['tambahan_pembulatan'];
    $n = $r['nego'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>RAB Internal {{ $rab->judul }} — {{ $rab->kode }}</title>
<style>
    @page { margin: 0 0 56px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #475569; margin: 0; }
    .isi { padding: 0 32px; }
    table { border-collapse: collapse; width: 100%; }

    .kepala { background-color: {{ $navy }}; padding: 16px 32px 14px; }
    .merek { font-size: 15px; font-weight: bold; color: #fff; letter-spacing: .6px; }
    .merek span { color: {{ $emas }}; }
    .lencana { display: inline-block; background-color: {{ $merah }}; color: #fff; font-size: 7.5px; font-weight: bold; letter-spacing: 1.4px; padding: 3px 8px; margin-top: 4px; }
    .jenis-dok { font-size: 7.5px; color: {{ $emas }}; letter-spacing: 2.4px; text-transform: uppercase; font-weight: bold; text-align: right; }
    .kode-dok { font-family: Courier, monospace; font-size: 12px; color: #fff; font-weight: bold; text-align: right; padding-top: 3px; }
    .terbit { font-size: 8px; color: {{ $langit }}; text-align: right; padding-top: 2px; }
    .garis-emas { height: 3px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }

    .judul { font-size: 19px; font-weight: bold; color: {{ $navy }}; padding-top: 18px; }
    .sub { font-size: 9.5px; color: #64748b; padding-top: 4px; }
    .sub b { color: {{ $navy }}; }

    /* Kartunya adalah SELNYA sendiri, bukan div di dalam sel.
       Dua sebab, keduanya kelihatan di berkas jadi:
       1. Tinggi. Sel-sel dalam satu baris selalu setinggi yang tertinggi,
          sedangkan div di dalamnya hanya setinggi isinya — jadi kartu yang
          keterangannya dua baris ("biaya tetap ... ÷ ...") berdiri lebih
          tinggi daripada empat tetangganya.
       2. Lebar. Jarak antarkartu dulu dibuat dari padding sel, lalu padding
          itu dicabut di sel pertama dan terakhir supaya rata dengan tepi
          halaman. Akibatnya kartu pertama 8px lebih lebar daripada yang di
          tengah — selisih yang tidak pernah kelihatan di layar, tetapi jelas
          di PDF. Sekarang jaraknya kolom tersendiri, jadi kelima kartu
          benar-benar selebar yang sama dan tetap rata di kedua ujung. */
    .ubin { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .ubin td.ubin-sel {
        width: 18.4%; vertical-align: top;
        background-color: {{ $kabut }}; border: 1px solid #dbe7f0; padding: 9px 11px;
    }
    .ubin td.ubin-sela { width: 2%; padding: 0; border: 0; }
    .ubin-label { font-size: 6.5px; letter-spacing: 1.5px; color: #94a3b8; text-transform: uppercase; font-weight: bold; }
    .ubin-nilai { font-size: 13px; color: {{ $navy }}; font-weight: bold; padding-top: 3px; }
    .ubin-ket { font-size: 7.5px; color: #64748b; padding-top: 2px; }

    .bagian { padding-top: 18px; }
    .bagian-judul { font-size: 8.5px; letter-spacing: 2.2px; color: {{ $ocean }}; font-weight: bold; text-transform: uppercase; }
    .bagian-garis { height: 2px; width: 30px; background-color: {{ $emas }}; margin-top: 4px; font-size: 0; line-height: 0; }

    .biaya { margin-top: 10px; }
    .biaya th { background-color: {{ $navy }}; color: #fff; font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; text-align: left; padding: 6px 8px; }
    .biaya td { padding: 6px 8px; border-bottom: 1px solid #eef3f7; vertical-align: top; }
    .biaya .kat td { background-color: {{ $kabut }}; color: {{ $ocean }}; font-weight: bold; font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; }
    .biaya .nama { color: {{ $navy }}; font-weight: bold; }
    .biaya .basi { color: {{ $merah }}; font-size: 7.5px; padding-top: 2px; }
    /* Sebab harga: penjelas, bukan peringatan — warnanya sengaja bukan
       merah, supaya tidak terbaca sebagai sesuatu yang harus dibereskan. */
    .biaya .sebab { color: #92400e; font-size: 7.5px; padding-top: 2px; }
    .biaya .vendor { color: {{ $ocean }}; font-size: 7.5px; padding-top: 2px; }
    .biaya .jelas { color: #64748b; font-size: 8.5px; }
    .kanan { text-align: right; white-space: nowrap; }
    .jenis { font-size: 7px; font-weight: bold; letter-spacing: .8px; padding: 1px 5px; }
    .jenis-v { background-color: #e0effa; color: {{ $ocean }}; }
    .jenis-t { background-color: #f1f5f9; color: #64748b; }
    .biaya .subkat td { font-weight: bold; color: {{ $navy }}; border-bottom: 1px solid #dbe7f0; }

    .hitung td { padding: 5px 10px; font-size: 9.5px; }
    .hitung .garis td { border-top: 1px solid #dbe7f0; }
    .hitung .besar td { font-size: 12px; font-weight: bold; color: {{ $navy }}; background-color: {{ $kabut }}; padding: 8px 10px; }
    .hitung .untung td { color: {{ $hijau }}; font-weight: bold; }

    .kotak { border: 1px solid #e2eaf1; padding: 10px 12px; }
    /* Dua kotak berdampingan — alasannya sama dengan .ubin di atas: sel
       sebaris selalu setinggi yang tertinggi, sedangkan div di dalam sel
       hanya setinggi isinya. "Perhitungan harga" punya tujuh baris dan
       "Bila dijadikan pendaftaran" tiga, jadi yang kanan dulu berhenti di
       tengah dan menyisakan lubang putih di bawahnya. */
    .duo { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .duo td.duo-sel { vertical-align: top; border: 1px solid #e2eaf1; padding: 10px 12px; }
    .duo td.duo-sela { width: 2%; padding: 0; border: 0; }
    .duo-nota { font-size: 7.5px; color: #94a3b8; padding-top: 6px; }
    /* Dipisah garis, bukan jadi kotak tersendiri: kotak di dalam sel akan
       mengembalikan persis masalah yang baru saja dibereskan. */
    .duo-tanda { margin-top: 9px; border-top: 1px solid #bfe3cc; padding-top: 7px; }
    .kotak-judul { font-size: 8px; letter-spacing: 1.5px; color: {{ $ocean }}; text-transform: uppercase; font-weight: bold; padding-bottom: 6px; }
    .peringatan { margin-top: 12px; border-left: 3px solid {{ $merah }}; background-color: #fef3f2; padding: 8px 12px; font-size: 9px; color: {{ $merah }}; }
    /* Kuning, bukan merah: harga nol bukan galat — yang gratis memang ada.
       Yang dituju cuma supaya tidak terlewat saat RAB-nya dikirim. */
    .awas { margin-top: 12px; border-left: 3px solid #d97706; background-color: #fffbeb; padding: 8px 12px; font-size: 9px; color: #92400e; }
    /* Hari ke berapa baris ini terpakai. Tanpa ini "Tiket Baluran" harus
       dicocokkan sendiri ke itinerary di halaman lain. */
    .hari-pakai { display: inline-block; font-size: 7px; letter-spacing: .4px; color: {{ $ocean }}; background-color: #eff6ff; border: 1px solid #dbe7f0; padding: 1px 5px; margin-top: 3px; }
    /* Syarat vendor & master harga: jam tutup, DP, siapa yang ditanya.
       Dibaca orang yang tidak menyusun RAB-nya, di tempat tanpa sinyal. */
    .syarat { font-size: 7.5px; color: #92400e; background-color: #fffbeb; border-left: 2px solid #fcd34d; padding: 2px 6px; margin-top: 3px; line-height: 1.45; }
    .nol { font-size: 7.5px; color: #92400e; font-weight: bold; padding-top: 2px; }
    /* Rekap vendor & daftar siap-berangkat: dua kolom yang tingginya ikut
       baris, bukan isinya — lihat catatan .duo di atas. */
    .vd { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 10px; }
    .vd td.vd-sel { vertical-align: top; border: 1px solid #e2eaf1; padding: 10px 12px; }
    .vd td.vd-sela { width: 2%; padding: 0; border: 0; }
    .vd-baris { font-size: 9px; color: #334155; padding: 4px 0; border-top: 1px solid #eef3f8; }
    .vd-baris:first-child { border-top: 0; }
    .vd-nama { font-weight: bold; color: {{ $navy }}; }
    .vd-ket { font-size: 7.5px; color: #94a3b8; }
    .vd-total { float: right; font-weight: bold; color: {{ $navy }}; }
    .vd-syarat { font-size: 7.5px; color: #92400e; }
    .siap { font-size: 9px; color: #334155; padding: 4px 0; }
    .siap-kotak { display: inline-block; width: 7px; height: 7px; border: 1px solid #94a3b8; margin-right: 6px; }
    .catatan { margin-top: 12px; border-left: 3px solid {{ $emas }}; background-color: #fffbf0; padding: 8px 12px; font-size: 9px; color: #5b4a1a; line-height: 1.55; }

    /* ---------- keterangan penanda ---------- */
    .legenda { padding: 9px 0 1px; }
    .legenda-butir { display: inline-block; font-size: 7.5px; color: #64748b; margin-right: 11px; }
    .legenda-titik { display: inline-block; width: 6px; height: 6px; border-radius: 3px; margin-right: 5px; }

    /* ---------- itinerary ---------- */
    .hari { margin-top: 10px; border: 1px solid #e2eaf1; page-break-inside: avoid; }
    .hari-kepala { background-color: {{ $navy }}; padding: 8px 12px; }
    .hari-lencana { background-color: {{ $ocean }}; padding: 3px 0 4px; text-align: center; border-radius: 6px; }
    .hari-lencana-kata { display: block; font-size: 5px; letter-spacing: 1.3px; color: {{ $langit }}; font-weight: bold; }
    .hari-lencana-no { display: block; font-size: 13px; color: #fff; font-weight: bold; line-height: 1.1; }
    .hari-judul { font-size: 11px; color: #fff; font-weight: bold; }
    .hari-sub { font-size: 7.5px; color: {{ $langit }}; padding-top: 2px; line-height: 1.4; }
    .hari-tgl { font-size: 8px; color: {{ $langit }}; text-align: right; vertical-align: middle; width: 100px; }

    .keg td { padding: 6px 11px; border-bottom: 1px solid #eef3f7; vertical-align: top; }
    .keg tr:last-child td { border-bottom: none; }
    .keg-kepala td {
        background-color: {{ $kabut }}; font-size: 7px; letter-spacing: 1.1px; text-transform: uppercase;
        font-weight: bold; color: {{ $ocean }}; border-bottom: 1px solid #dbe7f0;
    }
    /* Jam & durasi ditengahkan — sama alasannya dengan berkas penawaran:
       empat kolom rata kiri membuat sisi kanan tiap kolom compang-camping,
       sedangkan jam dan durasi panjangnya seragam. */
    .keg .jam { width: 86px; font-family: Courier, monospace; font-weight: bold; color: {{ $ocean }}; font-size: 9px; text-align: center; }
    .keg-kepala .jam, .keg-kepala .lama { font-family: Helvetica, Arial, sans-serif; }
    .keg .lama { width: 58px; text-align: center; }
    .pil-lama { display: inline-block; background-color: #eef4f9; color: {{ $ocean }}; font-size: 7px; font-weight: bold; padding: 2px 5px 3px; border-radius: 6px; }
    .keg .tuju { width: 178px; }
    .keg .nama { font-weight: bold; color: {{ $navy }}; font-size: 9px; }
    .keg .dest { color: #94a3b8; font-size: 8px; }
    .keg .ket { color: #64748b; font-size: 8.5px; line-height: 1.45; }
    .keg .kosong { color: #cbd5e1; }
    .bebas { padding: 9px 12px; color: #94a3b8; font-style: italic; font-size: 9px; }

    /* ---------- simulasi peserta ---------- */
    .sim td { padding: 4px 10px; border-bottom: 1px solid #f2f6fa; font-size: 8.5px; }
    .sim tr:last-child td { border-bottom: none; }
    .sim-kepala td {
        background-color: {{ $kabut }}; font-size: 7px; letter-spacing: 1px; text-transform: uppercase;
        font-weight: bold; color: {{ $ocean }}; border-bottom: 1px solid #dbe7f0;
    }
    .sim .kanan { text-align: right; }
    .sim .samar { color: #94a3b8; }
    .sim-aman td { background-color: #f3faf6; }
    .sim-tanda {
        display: inline-block; background-color: {{ $hijau }}; color: #fff;
        font-size: 6.5px; font-weight: bold; padding: 1px 5px 2px; border-radius: 6px; margin-left: 4px;
    }

    /* ---------- urutan singgah ---------- */
    .rute-ket { font-size: 8px; color: #94a3b8; padding-top: 7px; }
    .rute-hari { margin-top: 9px; border: 1px solid #e2eaf1; page-break-inside: avoid; }
    .rute-hari-judul {
        background-color: {{ $kabut }}; padding: 5px 11px; font-size: 8px; font-weight: bold;
        color: {{ $navy }}; letter-spacing: .9px; text-transform: uppercase; border-bottom: 1px solid #dbe7f0;
    }
    .rute td { padding: 4px 11px; border-bottom: 1px solid #f2f6fa; vertical-align: middle; }
    .rute tr:last-child td { border-bottom: none; }
    .rute-no { width: 28px; padding-left: 12px; padding-right: 0; text-align: center; }
    /* Sama seperti di berkas penawaran: dompdf tidak menengahkan isi
       inline-block lewat line-height, jadi lingkarannya dirakit sebagai
       tabel satu sel. */
    .bulat-no { width: 14px; height: 14px; border-radius: 7px; }
    .bulat-no td {
        width: 14px; height: 14px; padding: 0; border: none;
        color: #fff; font-size: 7.5px; font-weight: bold;
        text-align: center; vertical-align: middle;
    }
    .rute-jam { width: 82px; font-family: Courier, monospace; font-size: 8px; color: {{ $ocean }}; font-weight: bold; text-align: center; }
    .rute-nama { font-size: 9px; color: {{ $navy }}; font-weight: bold; }

    .kaki-luar { position: fixed; bottom: -56px; left: 0; right: 0; }
    .kaki { background-color: {{ $navy }}; padding: 9px 32px 10px; border-top: 2px solid {{ $merah }}; }
    .kaki td { font-size: 7.5px; color: {{ $langit }}; }
    /* Nomor halaman: dompdf mengisi counter(page) sendiri saat mencetak.
       Berkas tanpa nomor halaman yang tercetak lalu tercecer di meja tidak
       bisa disusun ulang oleh siapa pun. */
    /* HANYA nomor halamannya, tanpa totalnya.

       counter(pages) di dompdf memulangkan angka yang bukan jumlah halaman
       — berkas tiga halaman tercetak "Hal. 1/9". Total yang salah lebih buruk
       daripada tanpa total: yang membacanya akan mengira ada enam halaman
       yang tidak ikut tercetak. Totalnya menuntut berkas dirender dua kali
       (sekali untuk menghitung), dan itu belum sepadan harganya di hosting
       ini. */
    .hal:after { content: "Hal. " counter(page); color: #fff; font-weight: bold; }
</style>
</head>
<body>

<div class="kaki-luar">
    <div class="kaki">
        <table><tr>
            <td><b style="color:#fff">DOKUMEN INTERNAL</b> — berisi modal & margin. Jangan dikirim ke pelanggan; kirim PDF Penawaran.</td>
            {{-- Siapa yang menyusun. Saat angkanya dipertanyakan berminggu
                 kemudian, tanpa baris ini tidak ada yang bisa ditanya. --}}
            <td style="text-align:right">{{ $rab->kode }}@if ($rab->dibuat_oleh) · disusun {{ $aman($rab->dibuat_oleh) }}@endif · dicetak {{ $terbit }} &nbsp;·&nbsp; <span class="hal"></span></td>
        </tr></table>
    </div>
</div>

<div class="kepala">
    <table><tr>
        <td style="width:44px; vertical-align:middle">
            @if ($logo)<img src="{{ $logo }}" style="width:36px; height:36px">@endif
        </td>
        <td style="vertical-align:middle; padding-left:8px">
            <div class="merek">ORCHA <span>JOURNEY</span></div>
            <div class="lencana">INTERNAL — JANGAN DIKIRIM KE PELANGGAN</div>
        </td>
        <td style="vertical-align:middle">
            <div class="jenis-dok">Rencana Anggaran Biaya</div>
            <div class="kode-dok">{{ $rab->kode }}</div>
            <div class="terbit">Status: {{ $rab->status_label }}</div>
            {{-- Masa berlaku ada di PDF penawaran tetapi tidak pernah di sini,
                 padahal yang perlu tahu harganya akan kedaluwarsa adalah
                 kantor — pelanggan justru tidak rugi kalau lupa. --}}
            @if ($berlaku)
                <div class="terbit">Berlaku sampai {{ $berlaku }}</div>
            @endif
        </td>
    </tr></table>
</div>
<div class="garis-emas"></div>

<div class="isi">
    <div class="judul">{{ $aman($rab->judul) }}</div>
    <div class="sub">
        <b>{{ $aman($rab->nama_pelanggan) }}</b>
        @if ($rab->whatsapp) · {{ $rab->whatsapp }} @endif
        @if ($rab->email) · {{ $aman($rab->email) }} @endif
        · {{ $aman($tujuan) }} · {{ $rentang ?? 'tanggal belum ditentukan' }} · {{ $durasi }} · {{ $rab->jumlah_peserta }} peserta
    </div>

    <table class="ubin" style="margin-top:14px"><tr>
        <td class="ubin-sel">
            <div class="ubin-label">Modal total</div>
            <div class="ubin-nilai">{{ $r['modal_total_teks'] }}</div>
            <div class="ubin-ket">{{ $r['modal_per_kepala_teks'] }} per kepala</div>
        </td>
        <td class="ubin-sela"></td>
        <td class="ubin-sel" style="border-top:3px solid {{ $emas }}">
            <div class="ubin-label">Harga jual / orang</div>
            <div class="ubin-nilai">{{ $r['harga_per_orang_teks'] }}</div>
            <div class="ubin-ket">dibulatkan ke {{ $rp($rab->pembulatan) }}</div>
        </td>
        <td class="ubin-sela"></td>
        <td class="ubin-sel">
            <div class="ubin-label">Harga total</div>
            <div class="ubin-nilai">{{ $r['harga_total_teks'] }}</div>
            <div class="ubin-ket">{{ $rab->jumlah_peserta }} × {{ $r['harga_per_orang_teks'] }}</div>
        </td>
        <td class="ubin-sela"></td>
        <td class="ubin-sel" style="border-top:3px solid {{ ($r['bep_tercapai'] ?? false) ? $hijau : $merah }}">
            <div class="ubin-label">Balik modal mulai</div>
            <div class="ubin-nilai" style="color:{{ ($r['bep_tercapai'] ?? false) ? $hijau : $merah }}">
                {{ $r['bep_peserta'] !== null ? $r['bep_peserta'].' peserta' : '-' }}
            </div>
            <div class="ubin-ket">
                @if ($r['bep_peserta'] !== null)
                    biaya tetap {{ $r['modal_tetap_teks'] }} ÷ {{ $r['sumbangan_per_orang_teks'] }}/kepala
                @else
                    harga belum menutup biaya per orang
                @endif
            </div>
        </td>
        <td class="ubin-sela"></td>
        <td class="ubin-sel" style="border-top:3px solid {{ $hijau }}">
            <div class="ubin-label">Untung</div>
            <div class="ubin-nilai" style="color:{{ $hijau }}">{{ $r['untung_teks'] }}</div>
            <div class="ubin-ket">{{ $r['persen_untung'] !== null ? str_replace('.', ',', $r['persen_untung']).'% dari modal' : '-' }}</div>
        </td>
    </tr></table>

    @if ($r['ada_harga_basi'])
        <div class="peringatan"><b>Harga master sudah berubah</b> untuk baris bertanda merah. Angka di RAB ini masih harga lama yang dibekukan — perbarui sebelum mengirim penawaran baru.</div>
    @endif

    {{-- Tanggalnya bergeser sesudah biaya ditarik. Barisnya memang sudah
         ditandai satu per satu, tetapi di RAB dua puluh baris tanda sekecil
         itu lewat begitu saja — dan penawaran yang terkirim dengan tarif
         akhir pekan untuk kunjungan hari Senin adalah kemahalan yang tidak
         akan pernah dicurigai siapa pun. --}}
    @if ($r['ada_harga_salah_hari'])
        <div class="peringatan"><b>Tanggal perjalanan bergeser</b> setelah biaya ditarik, jadi ada baris yang masih memakai tarif hari yang lama. Harganya sengaja tidak berubah sendiri — barisnya ditandai di bawah.</div>
    @endif

    {{-- Harganya masih nol: barisnya IKUT tercetak di penawaran sebagai yang
         sudah termasuk, tetapi tidak menyumbang satu rupiah pun ke harga
         jual. Di layar sudah ditandai; lembar inilah yang dicetak dan dibawa,
         jadi tandanya harus ikut. --}}
    @if (($r['harga_nol'] ?? []) !== [])
        <div class="awas"><b>{{ $aman(implode(', ', $r['harga_nol'])) }}</b> masih berharga Rp 0 — ikut dijanjikan ke pelanggan, tetapi belum terhitung di harga jual. Kalau memang gratis, biarkan.</div>
    @endif

    {{-- ======================= RINCIAN BIAYA ======================= --}}
    <div class="bagian">
        <div class="bagian-judul">Rincian Biaya</div>
        <div class="bagian-garis"></div>

        @if (count($r['baris']) === 0)
            <div class="catatan">Belum ada baris biaya.</div>
        @else
            <table class="biaya">
                <tr>
                    <th>Barang / Layanan</th>
                    <th style="width:48px">Jenis</th>
                    <th>Perhitungan</th>
                    <th class="kanan" style="width:88px">Subtotal</th>
                </tr>
                @foreach ($kelompok as $label => $isi)
                    <tr class="kat"><td colspan="4">{{ $label }}</td></tr>
                    @foreach ($isi as $b)
                        <tr>
                            <td>
                                <div class="nama">{{ $aman($b['nama']) }}</div>
                                {{-- Vendor & nomornya ikut dicetak: lembar ini yang
                                     dibawa saat menyiapkan keberangkatan, dan nomor
                                     yang harus dicari di menu lain adalah nomor yang
                                     tidak sempat dicari. --}}
                                @if (! empty($b['vendor']))
                                    <div class="vendor">{{ $aman($b['vendor']) }}@if (! empty($b['vendor_kontak'])) &middot; {{ $aman($b['vendor_kontak']) }}@endif</div>
                                @endif
                                @if ($b['harga_master_kini'] !== null)
                                    <div class="basi">Harga master kini {{ $rp($b['harga_master_kini']) }}</div>
                                @endif
                                {{-- MENGAPA harganya sekian. Berkas ini dibawa ke
                                     lapangan dan dibaca orang yang tidak menyusunnya;
                                     tiket Rp 31.000 tanpa sebab akan disangka salah
                                     ketik, lalu dibayarkan Rp 21.000 di gerbang dan
                                     rombongannya tertahan di sana. --}}
                                @if (! empty($b['harga_sebab']))
                                    <div class="sebab">{{ $aman($b['harga_sebab']) }}</div>
                                @endif
                                @if (! empty($b['harga_sebab_seharusnya']))
                                    <div class="basi">Tanggalnya bergeser — sekarang {{ $aman($b['harga_sebab_seharusnya']) }}</div>
                                @endif
                                @if ((int) $b['harga_satuan'] === 0)
                                    <div class="nol">Harga belum diisi — ikut dijanjikan, belum dianggarkan</div>
                                @endif
                                {{-- Syaratnya dibaca di lapangan, oleh orang yang
                                     tidak menyusun RAB ini. "Tutup hari Senin" yang
                                     hanya ada di layar kantor sama saja dengan tidak
                                     ditulis. --}}
                                @if (! empty($b['master_catatan']))
                                    <div class="syarat">{{ $aman($b['master_catatan']) }}</div>
                                @endif
                                @if (! empty($b['vendor_catatan']))
                                    <div class="syarat">{{ $aman($b['vendor_catatan']) }}</div>
                                @endif
                                @if (! empty($b['hari']))
                                    <div class="hari-pakai">Hari {{ implode(', ', $b['hari']) }}</div>
                                @elseif ($b['seluruh_perjalanan'] ?? false)
                                    <div class="hari-pakai">Sepanjang perjalanan</div>
                                @endif
                            </td>
                            <td>
                                @if ($b['variabel'])
                                    <span class="jenis jenis-v">PER ORG</span>
                                @else
                                    <span class="jenis jenis-t">TETAP</span>
                                @endif
                            </td>
                            <td class="jelas">{{ $aman($b['penjelasan']) }}</td>
                            <td class="kanan">{{ $b['subtotal_teks'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="subkat">
                        <td colspan="3" class="kanan" style="font-size:8px; color:#64748b">Jumlah {{ strtolower($label) }}</td>
                        <td class="kanan">{{ $rp($isi->sum('subtotal')) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    {{-- ============ SIAPA DIBAYAR BERAPA & APA YANG DIBAWA ============ --}}
    {{-- Dua pertanyaan yang ditanyakan sebelum berangkat, dan dua-duanya
         TIDAK terjawab oleh tabel rincian di atas. Tabel itu diurutkan
         menurut kategori — urutan menghitung harga — sedangkan yang
         dibutuhkan pagi itu urutan membayar dan urutan memasukkan barang ke
         mobil. --}}
    @if ($rekap_vendor['daftar'] !== [] || $siapkan !== [])
        <div class="bagian" style="page-break-inside:avoid">
            <table class="vd"><tr>
                <td class="vd-sel" style="width:{{ $siapkan !== [] ? '60%' : '100%' }}">
                    <div class="kotak-judul">Yang harus dibayar</div>
                    @foreach ($rekap_vendor['daftar'] as $v)
                        <div class="vd-baris">
                            <span class="vd-total">{{ $v['total_teks'] }}</span>
                            <span class="vd-nama">{{ $aman($v['nama']) }}</span>
                            <span class="vd-ket">· {{ $v['jumlah_baris'] }} baris{{ $v['kontak'] ? ' · '.$aman($v['kontak']) : '' }}</span>
                            @if ($v['catatan'])
                                <div class="vd-syarat">{{ $aman($v['catatan']) }}</div>
                            @endif
                        </div>
                    @endforeach
                    {{-- Baris tanpa vendor ikut disebut. Yang tidak terlihat di
                         rekap akan dikira sudah ada yang mengurus. --}}
                    @if ($rekap_vendor['tanpa_vendor'] > 0)
                        <div class="vd-baris">
                            <span class="vd-total">{{ $rekap_vendor['tanpa_vendor_teks'] }}</span>
                            <span class="vd-nama" style="color:#94a3b8">Belum ada vendornya</span>
                            <span class="vd-ket">· {{ $rekap_vendor['tanpa_vendor'] }} baris</span>
                        </div>
                    @endif
                </td>
                @if ($siapkan !== [])
                    <td class="vd-sela"></td>
                    <td class="vd-sel" style="width:38%">
                        <div class="kotak-judul">Disiapkan tim sendiri</div>
                        @foreach ($siapkan as $nama)
                            <div class="siap"><span class="siap-kotak"></span>{{ $aman($nama) }}</div>
                        @endforeach
                    </td>
                @endif
            </tr></table>
        </div>
    @endif

    {{-- ======================= PERHITUNGAN HARGA ======================= --}}
    <table class="duo" style="margin-top:18px; page-break-inside:avoid"><tr>
        <td class="duo-sel" style="width:55%">
                <div class="kotak-judul">Perhitungan harga</div>
                <table class="hitung">
                    <tr><td>Modal variabel (dikali peserta)</td><td class="kanan">{{ $r['modal_variabel_teks'] }}</td></tr>
                    <tr><td>Modal tetap rombongan</td><td class="kanan">{{ $r['modal_tetap_teks'] }}</td></tr>
                    <tr class="garis"><td><b>Modal total</b></td><td class="kanan"><b>{{ $r['modal_total_teks'] }}</b></td></tr>
                    <tr><td>Margin diminta ({{ $marginTeks }})</td><td class="kanan">{{ $r['margin_diminta_teks'] }}</td></tr>
                    {{-- Saat harganya disepakati, margin berhenti jadi SEBAB
                         harga dan jadi sekadar titik tolak. Tanpa dua baris
                         ini tabelnya melompat dari margin ke harga total yang
                         tidak cocok dengannya, dan pembacanya mengira salah
                         hitung. --}}
                    @if ($r['nego_aktif'])
                        <tr><td>Harga hitungan / orang</td><td class="kanan">{{ $n['sebelum']['harga_per_orang_teks'] }}</td></tr>
                        <tr><td>Disepakati / orang</td><td class="kanan">{{ $r['harga_per_orang_teks'] }}</td></tr>
                        <tr><td>Selisih kesepakatan / orang</td><td class="kanan">{{ $rp($n['selisih']['harga_per_orang']) }}</td></tr>
                    @elseif ($selisihBulat > 0)
                        <tr><td>Tambahan dari pembulatan</td><td class="kanan">{{ $rp($selisihBulat) }}</td></tr>
                    @endif
                    <tr class="besar"><td>Harga total</td><td class="kanan">{{ $r['harga_total_teks'] }}</td></tr>
                    <tr class="untung"><td>Untung bersih</td><td class="kanan">{{ $r['untung_teks'] }}</td></tr>
                </table>
        </td>
        <td class="duo-sela"></td>
        <td class="duo-sel" style="width:43%">
                <div class="kotak-judul">Bila dijadikan pendaftaran</div>
                <table class="hitung">
                    <tr><td>Harga jual / orang</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['harga_jual']) }}</td></tr>
                    <tr><td>Harga modal / orang</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['harga_modal']) }}</td></tr>
                    <tr><td>Biaya tetap</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['biaya_tetap']) }}</td></tr>
                </table>
                <div class="duo-nota">Modal pendaftaran = modal/orang × {{ $rab->jumlah_peserta }} + biaya tetap = {{ $r['modal_total_teks'] }}, sama persis dengan RAB.</div>
            @if ($rab->kode_pendaftaran)
                <div class="duo-tanda">
                    <div class="kotak-judul" style="color:{{ $hijau }}; padding-bottom:2px">Sudah jadi pendaftaran</div>
                    <div style="font-family:Courier, monospace; font-weight:bold; color:{{ $navy }}">{{ $rab->kode_pendaftaran }}</div>
                </div>
            @endif
        </td>
    </tr></table>

    {{-- Kesepakatan harga dibandingkan berdampingan, bukan diringkas jadi
         satu kalimat. Yang ditanyakan saat tawarannya disetujui bukan "turun
         berapa", melainkan "sesudah turun, kita masih untung berapa". --}}
    @if ($r['nego_aktif'])
        <div class="kotak" style="margin-top:12px; border-color:{{ $r['nego_rugi'] ? '#f3c3bd' : '#c7d2fe' }}; background-color:{{ $r['nego_rugi'] ? '#fef3f2' : '#f8f9ff' }}">
            <div class="kotak-judul">Harga disepakati vs harga hitungan</div>
            <table class="hitung" style="width:100%">
                <tr>
                    <td style="width:46%"></td>
                    <td class="kanan" style="width:27%"><b>Hitungan</b></td>
                    <td class="kanan" style="width:27%"><b>Disepakati</b></td>
                </tr>
                <tr class="garis">
                    <td>Harga per orang</td>
                    <td class="kanan">{{ $n['sebelum']['harga_per_orang_teks'] }}</td>
                    <td class="kanan"><b>{{ $n['sesudah']['harga_per_orang_teks'] }}</b></td>
                </tr>
                <tr>
                    <td>Harga total {{ $rab->jumlah_peserta }} peserta</td>
                    <td class="kanan">{{ $n['sebelum']['harga_total_teks'] }}</td>
                    <td class="kanan">{{ $n['sesudah']['harga_total_teks'] }}</td>
                </tr>
                <tr>
                    <td>Untung bersih</td>
                    <td class="kanan">{{ $n['sebelum']['untung_teks'] }}</td>
                    <td class="kanan"><b>{{ $n['sesudah']['untung_teks'] }}</b></td>
                </tr>
                <tr>
                    <td>Untung per orang</td>
                    <td class="kanan">{{ $n['sebelum']['untung_per_orang_teks'] }}</td>
                    <td class="kanan">{{ $n['sesudah']['untung_per_orang_teks'] }}</td>
                </tr>
                <tr>
                    <td>Margin</td>
                    <td class="kanan">{{ $n['sebelum']['persen_untung'] !== null ? str_replace('.', ',', $n['sebelum']['persen_untung']).'%' : '-' }}</td>
                    <td class="kanan">{{ $n['sesudah']['persen_untung'] !== null ? str_replace('.', ',', $n['sesudah']['persen_untung']).'%' : '-' }}</td>
                </tr>
                <tr>
                    <td>Balik modal mulai</td>
                    <td class="kanan">{{ $n['sebelum']['bep_peserta'] ? $n['sebelum']['bep_peserta'].' peserta' : 'tidak tercapai' }}</td>
                    <td class="kanan">{{ $n['sesudah']['bep_peserta'] ? $n['sesudah']['bep_peserta'].' peserta' : 'tidak tercapai' }}</td>
                </tr>
            </table>
            <div style="font-size:8.5px; color:#475569; padding:7px 10px 0; line-height:1.55">
                Potongan {{ $rp(abs($n['selisih']['harga_per_orang'])) }} per orang
                ({{ $rp(abs($n['selisih']['harga_total'])) }} untuk rombongan ini).
                @if ($n['selisih']['bagian_untung_dilepas'])
                    Itu <b>{{ str_replace('.', ',', $n['selisih']['bagian_untung_dilepas']) }}% dari untung</b>
                    yang dilepas — yang dipotong adalah selisihnya, bukan harganya.
                @endif
                @if ($rab->alasan_nego) Alasan: {{ $aman($rab->alasan_nego) }}. @endif
            </div>
            @if ($r['nego_rugi'])
                <div style="font-size:8.5px; color:{{ $merah }}; font-weight:bold; padding:5px 10px 0">
                    DI BAWAH MODAL — trip ini rugi {{ $rp(abs($r['untung'])) }}.
                    Batas aman {{ $n['batas']['harga_teks'] }} per orang.
                </div>
            @else
                <div style="font-size:8.5px; color:#475569; padding:5px 10px 0">
                    Masih {{ $n['batas']['jarak_teks'] }} per orang di atas batas aman
                    {{ $n['batas']['harga_teks'] }}.
                </div>
            @endif
        </div>
    @endif

    @if ($rab->catatan)
        <div class="catatan"><b>Catatan internal:</b> {!! nl2br(e($aman($rab->catatan))) !!}</div>
    @endif

    {{-- ======================= JADWAL ======================= --}}
    <div class="bagian">
        <div class="bagian-judul">Itinerary</div>
        <div class="bagian-garis"></div>

        <div class="legenda">
            @foreach ($rupa as $kunci => [$label, $warna])
                <span class="legenda-butir"><span class="legenda-titik" style="background-color: {{ $warna }}"></span>{{ $label }}</span>
            @endforeach
        </div>

        @foreach ($hari as $h)
            <div class="hari">
                <div class="hari-kepala">
                    <table><tr>
                        <td style="width:40px; vertical-align:middle">
                            <div class="hari-lencana"><span class="hari-lencana-kata">HARI</span><span class="hari-lencana-no">{{ $h['hari_ke'] }}</span></div>
                        </td>
                        <td style="vertical-align:middle; padding-left:9px">
                            <div class="hari-judul">{{ $aman($h['judul']) }}</div>
                            @if ($h['ringkasan'])<div class="hari-sub">{{ $aman($h['ringkasan']) }}</div>@endif
                        </td>
                        <td class="hari-tgl">{{ $h['tanggal'] ?? '' }}</td>
                    </tr></table>
                </div>

                @if (count($h['kegiatan']) === 0)
                    <div class="bebas">Waktu bebas — belum ada kegiatan yang disusun untuk hari ini.</div>
                @else
                    <table class="keg">
                        <tr class="keg-kepala">
                            <td class="jam">Waktu (WIB)</td>
                            <td class="lama">Durasi</td>
                            <td class="tuju">Tujuan</td>
                            <td>Kegiatan</td>
                        </tr>
                        @foreach ($h['kegiatan'] as $k)
                            @php [$labelJenis, $warnaJenis] = $rupa[$jenisKeg($k)]; @endphp
                            <tr>
                                <td class="jam">{{ $k->jam ?: '' }}@if ($k->jam && $k->jam_selesai) – {{ $k->jam_selesai }}@endif</td>
                                <td class="lama">@if ($k->durasi_teks)<span class="pil-lama">{{ $k->durasi_teks }}</span>@endif</td>
                                <td class="tuju">
                                    <span class="legenda-titik" style="background-color: {{ $warnaJenis }}"></span><span class="nama">{{ $aman($k->nama) }}</span>
                                    @if ($k->destinasi)<span class="dest">· {{ $aman($k->destinasi) }}</span>@endif
                                </td>
                                <td>@if ($k->keterangan)<span class="ket">{{ $aman($k->keterangan) }}</span>@else<span class="kosong">&mdash;</span>@endif</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ======================= SIMULASI PESERTA ======================= --}}
    @if ($rab->jenis === 'open' && ($r['simulasi'] ?? []) !== [])
        {{-- Tabel ini yang menjawab pertanyaan yang sebenarnya ditanyakan
             sebelum memberangkatkan open trip: "dapat sekian orang, kita
             untung atau rugi?"

             Dihitung ulang di tiap jumlah, bukan dibagi rata — biaya tetapnya
             sendiri ikut berubah saat kamar atau armada bertambah, dan tangga
             itulah yang membuat satu peserta tambahan kadang justru menambah
             rugi. --}}
        <div class="bagian" style="page-break-inside:avoid">
            <div class="bagian-judul">Simulasi Jumlah Peserta</div>
            <div class="bagian-garis"></div>
            <div class="rute-ket">
                Harga jual {{ $r['harga_per_orang_teks'] }}/orang, dihitung atas dasar
                {{ $r['peserta_dasar'] }} peserta. Baris bertanda hijau = sudah tidak rugi.
            </div>

            <table class="sim" style="margin-top:9px">
                <tr class="sim-kepala">
                    <td>Peserta</td>
                    <td class="kanan">Modal</td>
                    <td class="kanan">dari biaya tetap</td>
                    <td class="kanan">Pemasukan</td>
                    <td class="kanan">Untung / Rugi</td>
                </tr>
                @foreach ($r['simulasi'] as $s)
                    @php $aman_ = $s['laba'] >= 0; @endphp
                    <tr class="{{ $aman_ ? 'sim-aman' : '' }}">
                        <td><b>{{ $s['peserta'] }}</b>@if ($s['peserta'] === $r['bep_peserta']) <span class="sim-tanda">titik impas</span>@endif</td>
                        <td class="kanan">{{ $s['modal_teks'] }}</td>
                        <td class="kanan samar">{{ \App\Support\RincianBiaya::rupiah($s['modal_tetap']) }}</td>
                        <td class="kanan">{{ $s['pemasukan_teks'] }}</td>
                        <td class="kanan" style="color: {{ $aman_ ? $hijau : $merah }}; font-weight: bold">{{ $s['laba_teks'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- ======================= URUTAN SINGGAH ======================= --}}
    @if ($rute !== [])
        <div class="bagian" style="page-break-inside:avoid">
            <div class="bagian-judul">Urutan Singgah</div>
            <div class="bagian-garis"></div>
            <div class="rute-ket">Nomor mengikuti urutan kunjungan; ruas perjalanan di antaranya tidak dinomori.</div>

            @foreach ($rute as $hr)
                <div class="rute-hari">
                    <div class="rute-hari-judul">Hari {{ $hr['hari_ke'] }}</div>
                    <table class="rute">
                        @foreach ($hr['titik'] as $t)
                            @php [$labelT, $warnaT] = $rupa[$t['jenis']]; @endphp
                            <tr>
                                <td class="rute-no">
                                    <table class="bulat-no" style="background-color: {{ $warnaT }}"><tr><td>{{ $t['no'] }}</td></tr></table>
                                </td>
                                <td class="rute-jam">{{ $t['waktu'] }}</td>
                                <td class="rute-nama">{{ $aman($t['nama']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
