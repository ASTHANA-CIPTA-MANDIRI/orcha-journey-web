@php
    /*
     | Kerangka berkas DAFTAR Orcha — dipakai PDF vendor dan PDF master harga.
     |
     | Ditarik ke sini, bukan disalin, karena dua berkas yang mengaku berasal
     | dari perusahaan yang sama tetapi kopnya sedikit berbeda terbaca seperti
     | dua perusahaan — dan bedanya baru ketahuan saat keduanya tergeletak di
     | meja yang sama.
     |
     | Bentuknya mengikuti berkas itinerary: kop navy berlogo, pita emas,
     | judul di tengah, lalu kaki krem berisi alamat kantor dan nomor halaman.
     |
     | Yang mengisi: @section('judul'), @section('sub'), @section('ringkas'),
     | @section('isi'). Variabel: $lencana, $jenisDok, $hitungDok, $terbit,
     | $kontak, $logo.
     */
    $navy = '#0f2d4a';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $merah = '#b42318';
    $kabut = '#eef4f9';

    // Panah & centang tidak ada di Helvetica bawaan dompdf; dibiarkan, ia
    // tergambar sebagai kotak kosong yang terbaca seperti berkas rusak.
    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>@yield('judul-berkas', 'Daftar Orcha Journey')</title>
<style>
    @page { margin: 0 0 72px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #475569; margin: 0; }
    .isi { padding: 0 32px; }
    table { border-collapse: collapse; width: 100%; }

    .kepala { background-color: {{ $navy }}; padding: 16px 32px 14px; }
    .merek { font-size: 15px; font-weight: bold; color: #fff; letter-spacing: .6px; }
    .merek span { color: {{ $emas }}; }
    .lencana { display: inline-block; background-color: {{ $merah }}; color: #fff; font-size: 7.5px; font-weight: bold; letter-spacing: 1.4px; padding: 3px 8px; margin-top: 4px; }
    .jenis-dok { font-size: 7.5px; color: {{ $emas }}; letter-spacing: 2.4px; text-transform: uppercase; font-weight: bold; text-align: right; }
    .hitung-dok { font-size: 12px; color: #fff; font-weight: bold; text-align: right; padding-top: 3px; }
    .terbit { font-size: 8px; color: {{ $langit }}; text-align: right; padding-top: 2px; }
    .garis-emas { height: 3px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }

    /* Judul & ubin dipusatkan; tabelnya tetap rata kiri, karena mata
       membaca daftar dari tepi yang sama. */
    .judul { font-size: 22px; font-weight: bold; color: {{ $navy }}; padding-top: 20px; text-align: center; }
    .sub { font-size: 10px; color: #64748b; padding-top: 5px; text-align: center; }
    .garis-pendek { width: 54px; height: 3px; background-color: {{ $emas }}; margin: 10px auto 0; font-size: 0; line-height: 0; }

    .ubin { margin-top: 18px; }
    .ubin td { width: 25%; padding: 0 5px; vertical-align: top; }
    .ubin td:first-child { padding-left: 0; }
    .ubin td:last-child { padding-right: 0; }
    .ubin-isi { background-color: {{ $kabut }}; border-top: 3px solid {{ $navy }}; padding: 11px 9px 12px; text-align: center; }
    .ubin-label { font-size: 8px; color: #64748b; font-weight: bold; }
    .ubin-nilai { font-size: 24px; font-weight: bold; color: {{ $navy }}; padding: 4px 0 1px; }
    .ubin-ket { font-size: 8px; color: #94a3b8; }

    .saring { background-color: #fdf6e7; border-top: 1px solid {{ $emas }}; border-bottom: 1px solid {{ $emas }}; padding: 9px 11px; margin-top: 16px; font-size: 9px; color: #6b5c3a; text-align: center; }
    .saring b { color: {{ $navy }}; }

    /* SATU tabel dengan pita kelompok, bukan beberapa tabel bertumpuk:
       tabel bertumpuk mengulang kepala kolomnya, dan yang membedakan
       kelompoknya tenggelam di antara pengulangan itu. */
    .daftar { margin-top: 14px; }
    .daftar th {
        background-color: {{ $navy }}; color: #fff; font-size: 7.5px; letter-spacing: 1px;
        text-transform: uppercase; text-align: left; padding: 7px 8px;
    }
    .daftar td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }

    .pita td {
        background-color: {{ $kabut }}; padding: 6px 8px 6px 10px;
        border-bottom: 1px solid #cbd8e4; border-top: 1px solid #cbd8e4;
    }
    .pita-nama { font-size: 9.5px; font-weight: bold; letter-spacing: .4px; }
    .pita-hitung { font-size: 8px; color: #94a3b8; font-weight: normal; }

    .nama { color: {{ $navy }}; font-weight: bold; font-size: 10px; }
    .meta { color: #94a3b8; font-size: 8px; padding-top: 1px; }
    .nomor { font-family: Courier, monospace; font-size: 9.5px; color: {{ $navy }}; font-weight: bold; }
    .kosong-nomor { color: {{ $merah }}; font-size: 8.5px; font-weight: bold; }
    .kanan { text-align: right; white-space: nowrap; }
    .tengah { text-align: center; white-space: nowrap; }
    .hitung-pakai { font-weight: bold; color: {{ $navy }}; }
    .belum-pakai { font-size: 8px; color: #94a3b8; }
    .mati td { background-color: #f8fafc; color: #94a3b8; }
    .mati .nama, .mati .nomor { color: #94a3b8; }

    /* Hanya yang MENYIMPANG yang ditandai — tidak ada kolom status.
       Satu kolom yang isinya sama di hampir setiap baris tidak memberi tahu
       apa pun; yang dicari mata justru satu-dua yang berbeda. */
    .pil-mati {
        display: inline-block; background-color: #e2e8f0; color: #64748b;
        font-size: 7px; font-weight: bold; letter-spacing: .6px; padding: 1px 6px; margin-left: 5px;
    }

    /* Keterangan dalam kotak, satu baris per hal — bukan satu paragraf
       panjang yang harus dieja dari ujung ke ujung. */
    .legenda { margin-top: 20px; background-color: {{ $kabut }}; padding: 11px 14px 12px; }
    .legenda-judul { font-size: 8px; font-weight: bold; color: {{ $navy }}; letter-spacing: 1.1px; text-transform: uppercase; padding-bottom: 5px; }
    .legenda td { font-size: 8.5px; color: #64748b; padding: 2px 0; vertical-align: top; }
    .legenda .kata { width: 108px; color: {{ $navy }}; font-weight: bold; }

    .nihil { padding: 26px; text-align: center; color: #94a3b8; font-size: 10px; background-color: {{ $kabut }}; margin-top: 14px; }

    .kaki-luar { position: fixed; bottom: -72px; left: 0; right: 0; }
    .kaki { background-color: #fdf6e7; padding: 10px 32px; border-top: 2px solid {{ $emas }}; }
    .kaki td { font-size: 7.5px; color: #6b5c3a; vertical-align: top; }
    .kaki .kantor { font-size: 9px; font-weight: bold; color: {{ $navy }}; padding-bottom: 2px; }
    .kaki .sosial { padding-top: 3px; color: {{ $navy }}; font-weight: bold; }
    /* Nomor halaman: dompdf mengisi counter(page) sendiri saat mencetak.
       counter(pages) TIDAK dipakai — nilainya meleset di berkas banyak
       halaman, dan "Hal. 1/9" yang salah lebih buruk daripada "Hal. 1". */
    .hal { font-size: 8px; color: #6b5c3a; }
    .hal:after { content: "Hal. " counter(page); font-weight: bold; color: {{ $navy }}; }

    @yield('gaya')
</style>
</head>
<body>

<div class="kaki-luar">
    <div class="kaki">
        <table><tr>
            <td style="width:44px">
                @if ($logo)<img src="{{ $logo }}" style="width:34px; height:34px">@endif
            </td>
            <td>
                <div class="kantor">Kantor Orcha Journey</div>
                <div>{{ $kontak['alamat'] }}</div>
                <div class="sosial">
                    orchajourney.com
                    @if ($kontak['instagram']) &nbsp;·&nbsp; {{ $kontak['instagram'] }} @endif
                    @if ($kontak['whatsapp']) &nbsp;·&nbsp; {{ $kontak['whatsapp'] }} @endif
                </div>
            </td>
            <td class="kanan" style="width:120px"><span class="hal"></span></td>
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
            <div class="lencana">{{ $lencana }}</div>
        </td>
        <td style="vertical-align:middle">
            <div class="jenis-dok">{{ $jenisDok }}</div>
            <div class="hitung-dok">{{ $hitungDok }}</div>
            <div class="terbit">Dicetak {{ $terbit }}</div>
        </td>
    </tr></table>
</div>
<div class="garis-emas"></div>

<div class="isi">
    <div class="judul">@yield('judul')</div>
    <div class="sub">@yield('sub')</div>
    <div class="garis-pendek"></div>

    @yield('ringkas')

    {{-- Saringan yang sedang dipakai ikut tercetak: berkas yang isinya
         sebagian, tanpa menyebutkan sebagiannya apa, terbaca sebagai
         daftar lengkap yang ternyata bukan. --}}
    <div class="saring">
        @if ($saringan === [])
            Daftar ini memuat <b>semua</b> data, tanpa saringan.
        @else
            Daftar ini <b>hanya sebagian</b> — @foreach ($saringan as $label => $nilai)<b>{{ $label }}</b> {{ $aman($nilai) }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach
        @endif
    </div>

    @yield('isi')
</div>

</body>
</html>
