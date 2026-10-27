@php
    /*
     | Daftar vendor untuk dibawa ke luar aplikasi.
     |
     | Berisi NOMOR TELEPON mitra — itu sebabnya kop & kakinya menyebut
     | "internal". Bukan rahasia dagang, tetapi juga bukan yang pantas
     | beredar: daftar ini memberi tahu siapa pun yang memegangnya dari
     | siapa kita membeli dan ke nomor mana meneleponnya.
     |
     | Susunannya mengikuti berkas itinerary — ubin ringkasan, keterangan
     | lambang, lalu bagian berbadge. Satu tabel panjang memaksa pembacanya
     | mengeja tiap baris untuk menemukan "mana yang armada"; dikelompokkan,
     | pertanyaan itu terjawab tanpa dibaca.
     */
    $navy = '#0f2d4a';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $merah = '#b42318';
    $kabut = '#eef4f9';

    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '']);

    $semua = collect($baris);
    $aktif = $semua->where('aktif', true)->count();
    $tanpaNomor = $semua->filter(fn ($v) => empty($v['kontak']))->count();
    $wilayah = $semua->pluck('provinsi')->filter()->unique()->count();

    /*
     | Warna per jenis, sepasang dengan layar Vendor di lemon. Berkas dan
     | layar yang memberi warna berbeda untuk hal yang sama membuat
     | keduanya harus dihafal terpisah.
     */
    $rupaJenis = [
        'Armada' => '#0369a1',
        'Akomodasi' => '#6d28d9',
        'Konsumsi' => '#e0912f',
        'Tiket' => '#1f7a44',
        'Pemandu' => '#0e7490',
    ];
    $warnaJenis = fn ($j) => $rupaJenis[$j] ?? '#64748b';

    // Yang belum berjenis dikumpulkan di akhir, bukan di tengah: ia bukan
    // kategori, melainkan yang belum sempat dikategorikan.
    $kelompok = $semua->groupBy(fn ($v) => $v['jenis'] ?: 'Belum berjenis')
        ->sortBy(fn ($isi, $nama) => ($nama === 'Belum berjenis' ? 'zzz' : $nama));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Daftar Vendor Orcha Journey</title>
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

    .judul { font-size: 19px; font-weight: bold; color: {{ $navy }}; padding-top: 18px; }
    .sub { font-size: 9.5px; color: #64748b; padding-top: 4px; }

    /* Ubin ringkasan, sebentuk dengan berkas itinerary: empat angka yang
       menjawab "sudah sejauh apa daftar ini" sebelum satu baris pun dibaca. */
    .ubin { margin-top: 14px; }
    .ubin td { width: 25%; padding: 0 4px; vertical-align: top; }
    .ubin td:first-child { padding-left: 0; }
    .ubin td:last-child { padding-right: 0; }
    .ubin-isi { background-color: {{ $kabut }}; border-top: 3px solid {{ $navy }}; padding: 9px 11px 10px; }
    .ubin-label { font-size: 7.5px; color: #64748b; letter-spacing: 1.1px; text-transform: uppercase; font-weight: bold; }
    .ubin-nilai { font-size: 16px; font-weight: bold; color: {{ $navy }}; padding-top: 3px; }
    .ubin-ket { font-size: 8px; color: #94a3b8; padding-top: 2px; }

    .saring { background-color: #fdf6e7; border-left: 3px solid {{ $emas }}; padding: 8px 11px; margin-top: 12px; font-size: 8.5px; color: #6b5c3a; }
    .saring b { color: {{ $navy }}; }

    /* SATU tabel dengan pita kelompok — pola yang sudah dipakai PDF RAB
       (.kat), bukan enam tabel bertumpuk.

       Enam tabel berarti kepala kolom terulang enam kali: "VENDOR WILAYAH
       KONTAK HARGA" yang sama dibaca berulang-ulang, dan yang membedakan
       kelompoknya justru tenggelam di antaranya. */
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
    .mati td { background-color: #f8fafc; color: #94a3b8; }
    .mati .nama, .mati .nomor { color: #94a3b8; }

    /* Hanya yang MENYIMPANG yang ditandai — tidak ada kolom status.

       Satu kolom yang isinya sama di hampir setiap baris tidak memberi tahu
       apa pun; yang dicari mata justru satu-dua yang berbeda, dan itu
       tenggelam di antara delapan baris yang seragam. */
    .pil-mati {
        display: inline-block; background-color: #e2e8f0; color: #64748b;
        font-size: 7px; font-weight: bold; letter-spacing: .6px; padding: 1px 6px; margin-left: 5px;
    }

    /* Keterangan lambang, sebentuk dengan baris legenda di itinerary. */
    .legenda { margin-top: 16px; padding-top: 9px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #94a3b8; }
    .legenda b { color: {{ $navy }}; }

    .nihil { padding: 26px; text-align: center; color: #94a3b8; font-size: 10px; background-color: {{ $kabut }}; margin-top: 14px; }

    /* Kaki tetap di tiap halaman: alamat kantor, sosial, dan nomor halaman —
       bentuk yang sama dengan berkas itinerary. */
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
            <div class="lencana">INTERNAL — BERISI NOMOR MITRA</div>
        </td>
        <td style="vertical-align:middle">
            <div class="jenis-dok">Daftar Vendor</div>
            <div class="hitung-dok">{{ $semua->count() }} vendor</div>
            <div class="terbit">Dicetak {{ $terbit }}</div>
        </td>
    </tr></table>
</div>
<div class="garis-emas"></div>

<div class="isi">
    <div class="judul">Daftar Vendor</div>
    <div class="sub">Dari siapa harga dibeli, dan ke nomor mana meneleponnya.</div>

    {{-- Empat angka yang menjawab "sudah sejauh apa daftar ini" sebelum satu
         baris pun dibaca — sebentuk dengan ubin ringkasan di itinerary. --}}
    <table class="ubin"><tr>
        <td><div class="ubin-isi">
            <div class="ubin-label">Vendor</div>
            <div class="ubin-nilai">{{ $semua->count() }}</div>
            <div class="ubin-ket">{{ $kelompok->count() }} jenis</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:#15803d">
            <div class="ubin-label">Aktif</div>
            <div class="ubin-nilai" style="color:#15803d">{{ $aktif }}</div>
            <div class="ubin-ket">{{ $semua->count() - $aktif }} dimatikan</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:{{ $tanpaNomor ? $merah : '#15803d' }}">
            <div class="ubin-label">Tanpa nomor</div>
            <div class="ubin-nilai" style="color:{{ $tanpaNomor ? $merah : '#15803d' }}">{{ $tanpaNomor }}</div>
            <div class="ubin-ket">{{ $tanpaNomor ? 'perlu dilengkapi' : 'semua punya kontak' }}</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:{{ $emas }}">
            <div class="ubin-label">Wilayah</div>
            <div class="ubin-nilai">{{ $wilayah }}</div>
            <div class="ubin-ket">di luar yang berlaku umum</div>
        </div></td>
    </tr></table>

    {{-- Saringan yang sedang dipakai ikut tercetak: berkas yang isinya
         sebagian, tanpa menyebutkan sebagiannya apa, terbaca sebagai
         daftar lengkap yang ternyata bukan. --}}
    <div class="saring">
        @if ($saringan === [])
            Memuat <b>seluruh vendor</b> yang terdaftar.
        @else
            Disaring: @foreach ($saringan as $label => $nilai)<b>{{ $label }}</b> {{ $aman($nilai) }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach
        @endif
    </div>

    @if ($baris === [])
        <div class="nihil">Tidak ada vendor yang cocok dengan saringannya.</div>
    @else
        <table class="daftar">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th style="width:112px">Wilayah</th>
                    <th style="width:138px">Kontak</th>
                    <th style="width:52px" class="kanan">Harga</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kelompok as $namaJenis => $isi)
                    {{-- Pita kelompok, bukan tabel baru: kepala kolom cukup
                         sekali di atas, dan yang membedakan kelompoknya tidak
                         tenggelam di antara pengulangannya. --}}
                    <tr class="pita">
                        <td colspan="3" style="border-left:3px solid {{ $warnaJenis($namaJenis) }}">
                            <span class="pita-nama" style="color:{{ $warnaJenis($namaJenis) }}">{{ $aman($namaJenis) }}</span>
                            <span class="pita-hitung">&nbsp; {{ $isi->count() }} vendor</span>
                        </td>
                        <td class="kanan pita-hitung">{{ $isi->sum('jumlah_harga') }} harga</td>
                    </tr>

                    @foreach ($isi as $v)
                        <tr class="{{ $v['aktif'] ? '' : 'mati' }}">
                            <td>
                                <div class="nama">
                                    {{ $aman($v['nama']) }}
                                    @unless ($v['aktif'])<span class="pil-mati">MATI</span>@endunless
                                </div>
                                @if (! empty($v['catatan']))
                                    <div class="meta">{{ $aman($v['catatan']) }}</div>
                                @endif
                            </td>
                            {{-- Kosong berarti berlaku di mana saja, bukan belum
                                 diisi — ditulis apa adanya supaya tidak terbaca
                                 sebagai lubang data. --}}
                            <td>{{ $v['provinsi'] ?: 'Semua wilayah' }}</td>
                            <td>
                                @if (! empty($v['kontak']))
                                    <span class="nomor">{{ $aman($v['kontak']) }}</span>
                                @else
                                    <span class="kosong-nomor">belum ada nomor</span>
                                @endif
                            </td>
                            <td class="kanan">{{ $v['jumlah_harga'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="legenda">
            <b>Harga</b> = berapa baris master harga memakainya &nbsp;·&nbsp;
            <b>Semua wilayah</b> = tidak terikat provinsi, bukan belum diisi &nbsp;·&nbsp;
            <b>MATI</b> = tidak ditawarkan lagi, kontaknya tetap terbaca di RAB lama
        </div>
    @endif
</div>

</body>
</html>
