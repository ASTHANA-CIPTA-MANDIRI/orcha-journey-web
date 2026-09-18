{{-- Penawaran perjalanan untuk PELANGGAN.

     Templat ini TIDAK PERNAH menerima angka modal, margin, atau harga satuan —
     BerkasRab hanya menyerahkan harga jual dan nama barang. Jadi templat ini
     bahkan tidak bisa membocorkannya, sekalipun seseorang kelak menyuntingnya
     dengan ceroboh.

     Dompdf tidak mengenal flexbox maupun grid: seluruh tata letaknya tabel, dan
     seluruh gayanya ada di dokumen ini sendiri. Helvetica dipakai karena huruf
     bawaan PDF tidak ikut disisipkan — berkasnya tetap ringan untuk dikirim
     lewat WhatsApp. --}}
@php
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $kabut = '#f4f8fb';
    $hijau = '#1f7a44';

    // Helvetica tidak punya panah maupun tanda minus tipografis.
    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '']);
    $wa = preg_replace('/\D/', '', (string) $kontak['whatsapp']);
    $waTampil = '+'.substr($wa, 0, 2).' '.substr($wa, 2, 3).'-'.substr($wa, 5, 4).'-'.substr($wa, 9);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Penawaran {{ $rab->judul }} — {{ $rab->kode }}</title>
<style>
    @page { margin: 0 0 64px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #475569; margin: 0; }
    .isi { padding: 0 36px; }
    table { border-collapse: collapse; width: 100%; }

    /* ---------- kepala ---------- */
    .kepala { background-color: {{ $navy }}; padding: 20px 36px 18px; }
    .merek { font-size: 17px; font-weight: bold; color: #fff; letter-spacing: .6px; }
    .merek span { color: {{ $emas }}; }
    .slogan { font-size: 7px; color: {{ $langit }}; letter-spacing: 2.2px; text-transform: uppercase; padding-top: 3px; }
    .jenis-dok { font-size: 7.5px; color: {{ $emas }}; letter-spacing: 2.4px; text-transform: uppercase; font-weight: bold; text-align: right; }
    .kode-dok { font-family: Courier, monospace; font-size: 12px; color: #fff; font-weight: bold; text-align: right; padding-top: 3px; }
    .terbit { font-size: 8px; color: {{ $langit }}; text-align: right; padding-top: 2px; }
    .garis-emas { height: 3px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }

    /* ---------- judul ---------- */
    .alis { font-size: 8.5px; letter-spacing: 2.6px; color: {{ $ocean }}; font-weight: bold; text-transform: uppercase; }
    .judul { font-size: 25px; font-weight: bold; color: {{ $navy }}; padding-top: 5px; line-height: 1.15; }
    .untuk { font-size: 10.5px; color: #64748b; padding-top: 6px; }
    .untuk b { color: {{ $navy }}; }

    /* ---------- ubin fakta ---------- */
    .ubin td { width: 25%; padding: 0 4px; vertical-align: top; }
    .ubin td:first-child { padding-left: 0; }
    .ubin td:last-child { padding-right: 0; }
    .ubin-isi { background-color: {{ $kabut }}; border: 1px solid #dbe7f0; border-top: 3px solid {{ $ocean }}; padding: 10px 12px 11px; height: 34px; }
    .ubin-label { font-size: 7px; letter-spacing: 1.6px; color: #94a3b8; text-transform: uppercase; font-weight: bold; }
    .ubin-nilai { font-size: 12.5px; color: {{ $navy }}; font-weight: bold; padding-top: 4px; }

    /* ---------- bagian ---------- */
    .bagian { padding-top: 24px; }
    .bagian-judul { font-size: 9px; letter-spacing: 2.4px; color: {{ $ocean }}; font-weight: bold; text-transform: uppercase; }
    .bagian-garis { height: 2px; width: 34px; background-color: {{ $emas }}; margin-top: 5px; font-size: 0; line-height: 0; }

    /* ---------- itinerary ---------- */
    .hari { margin-top: 12px; border: 1px solid #e2eaf1; page-break-inside: avoid; }
    .hari-kepala { background-color: {{ $navy }}; padding: 8px 14px; }
    .hari-no { font-size: 11px; color: #fff; font-weight: bold; letter-spacing: .5px; }
    .hari-no span { color: {{ $emas }}; }
    .hari-tgl { font-size: 8.5px; color: {{ $langit }}; text-align: right; }
    .keg td { padding: 8px 14px; border-bottom: 1px solid #eef3f7; vertical-align: top; }
    .keg tr:last-child td { border-bottom: none; }
    .keg .jam { width: 52px; font-family: Courier, monospace; font-weight: bold; color: {{ $ocean }}; font-size: 10px; }
    .keg .titik { width: 10px; padding-left: 0; padding-right: 0; }
    .titik-bulat { width: 7px; height: 7px; background-color: {{ $emas }}; border-radius: 4px; margin-top: 3px; }
    .keg .nama { font-weight: bold; color: {{ $navy }}; font-size: 10.5px; }
    .keg .ket { color: #64748b; font-size: 9px; padding-top: 2px; line-height: 1.5; }
    .bebas { padding: 10px 14px; color: #94a3b8; font-style: italic; font-size: 9.5px; }

    /* ---------- termasuk ---------- */
    .termasuk td { width: 50%; vertical-align: top; padding: 0 6px 10px 0; }
    .termasuk-kotak { border: 1px solid #e2eaf1; background-color: #fbfdfe; padding: 10px 12px; }
    .termasuk-kat { font-size: 8px; letter-spacing: 1.6px; color: {{ $hijau }}; text-transform: uppercase; font-weight: bold; }
    .termasuk-item { font-size: 9.5px; color: #334155; padding-top: 4px; }
    .centang { display: inline-block; width: 6px; height: 6px; background-color: {{ $hijau }}; margin-right: 6px; }

    /* ---------- harga ---------- */
    .harga { background-color: {{ $navy }}; margin-top: 22px; page-break-inside: avoid; }
    .harga td { padding: 16px 20px; vertical-align: middle; }
    .harga-label { font-size: 8px; letter-spacing: 2px; color: {{ $langit }}; text-transform: uppercase; font-weight: bold; }
    .harga-angka { font-size: 26px; color: {{ $emas }}; font-weight: bold; padding-top: 3px; }
    .harga-angka span { font-size: 11px; color: {{ $langit }}; font-weight: normal; }
    .harga-total { font-size: 11px; color: #fff; text-align: right; line-height: 1.6; }
    .harga-total b { font-size: 14px; }
    .harga-kaki { background-color: #0b2338; padding: 8px 20px; font-size: 8.5px; color: {{ $langit }}; }

    /* ---------- catatan & syarat ---------- */
    .catatan { margin-top: 14px; border-left: 3px solid {{ $emas }}; background-color: #fffbf0; padding: 10px 14px; font-size: 9.5px; color: #5b4a1a; line-height: 1.6; }
    .syarat li { font-size: 9px; color: #475569; padding-bottom: 3px; line-height: 1.55; }
    .syarat { margin: 8px 0 0 0; padding-left: 14px; }
    .ajakan { margin-top: 16px; border: 1px solid #cfe3f0; background-color: {{ $kabut }}; padding: 12px 16px; text-align: center; page-break-inside: avoid; }
    .ajakan-judul { font-size: 11.5px; color: {{ $navy }}; font-weight: bold; }
    .ajakan-sub { font-size: 9px; color: #64748b; padding-top: 3px; }
    .ajakan-wa { font-size: 13px; color: {{ $ocean }}; font-weight: bold; padding-top: 5px; }

    /* ---------- kaki ---------- */
    .kaki-luar { position: fixed; bottom: -64px; left: 0; right: 0; }
    .kaki-emas { height: 2px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }
    .kaki { background-color: {{ $navy }}; padding: 10px 36px 11px; }
    .kaki td { font-size: 7.5px; color: {{ $langit }}; vertical-align: middle; }
</style>
</head>
<body>

<div class="kaki-luar">
    <div class="kaki-emas"></div>
    <div class="kaki">
        <table><tr>
            <td>{{ $aman($kontak['alamat']) }}</td>
            <td style="text-align:right">WA {{ $waTampil }} &nbsp;·&nbsp; {{ $kontak['email'] }} &nbsp;·&nbsp; @ {{ $kontak['instagram'] }}</td>
        </tr></table>
    </div>
</div>

{{-- ======================= KEPALA ======================= --}}
<div class="kepala">
    <table><tr>
        <td style="width:48px; vertical-align:middle">
            @if ($logo)<img src="{{ $logo }}" style="width:40px; height:40px">@endif
        </td>
        <td style="vertical-align:middle; padding-left:10px">
            <div class="merek">ORCHA <span>JOURNEY</span></div>
            <div class="slogan">Teman setia perjalanan Anda</div>
        </td>
        <td style="vertical-align:middle">
            <div class="jenis-dok">Penawaran Perjalanan</div>
            <div class="kode-dok">{{ $rab->kode }}</div>
            <div class="terbit">Diterbitkan {{ $terbit }}</div>
        </td>
    </tr></table>
</div>
<div class="garis-emas"></div>

<div class="isi">

    {{-- ======================= JUDUL ======================= --}}
    <div style="padding-top:24px">
        <div class="alis">Private Trip</div>
        <div class="judul">{{ $aman($rab->judul) }}</div>
        <div class="untuk">Disiapkan khusus untuk <b>{{ $aman($rab->nama_pelanggan) }}</b></div>
    </div>

    {{-- ======================= UBIN FAKTA ======================= --}}
    <table class="ubin" style="margin-top:18px"><tr>
        <td><div class="ubin-isi"><div class="ubin-label">Tujuan</div><div class="ubin-nilai">{{ $aman($tujuan) }}</div></div></td>
        <td><div class="ubin-isi"><div class="ubin-label">Tanggal</div><div class="ubin-nilai">{{ $rentang ?? 'Menyesuaikan' }}</div></div></td>
        <td><div class="ubin-isi"><div class="ubin-label">Durasi</div><div class="ubin-nilai">{{ $durasi }}</div></div></td>
        <td><div class="ubin-isi"><div class="ubin-label">Peserta</div><div class="ubin-nilai">{{ $rab->jumlah_peserta }} orang</div></div></td>
    </tr></table>

    {{-- ======================= ITINERARY ======================= --}}
    <div class="bagian">
        <div class="bagian-judul">Rencana Perjalanan</div>
        <div class="bagian-garis"></div>

        @foreach ($hari as $h)
            <div class="hari">
                <div class="hari-kepala">
                    <table><tr>
                        <td class="hari-no">HARI <span>{{ $h['hari_ke'] }}</span></td>
                        <td class="hari-tgl">{{ $h['tanggal'] ?? '' }}</td>
                    </tr></table>
                </div>

                @if (count($h['kegiatan']) === 0)
                    <div class="bebas">Waktu bebas — jadwal hari ini disusun bersama sesuai keinginan rombongan.</div>
                @else
                    <table class="keg">
                        @foreach ($h['kegiatan'] as $k)
                            <tr>
                                <td class="jam">{{ $k->jam ?: '' }}</td>
                                <td class="titik"><div class="titik-bulat"></div></td>
                                <td>
                                    <div class="nama">{{ $aman($k->nama) }}</div>
                                    @if ($k->keterangan)
                                        <div class="ket">{{ $aman($k->keterangan) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ======================= TERMASUK ======================= --}}
    @if (count($termasuk) > 0)
        <div class="bagian" style="page-break-inside:avoid">
            <div class="bagian-judul">Harga Sudah Termasuk</div>
            <div class="bagian-garis"></div>

            <table class="termasuk" style="margin-top:12px">
                @foreach (array_chunk($termasuk, 2, true) as $pasangan)
                    <tr>
                        @foreach ($pasangan as $kategori => $barang)
                            <td>
                                <div class="termasuk-kotak">
                                    <div class="termasuk-kat">{{ $kategori }}</div>
                                    @foreach ($barang as $b)
                                        <div class="termasuk-item"><span class="centang"></span>{{ $aman($b) }}</div>
                                    @endforeach
                                </div>
                            </td>
                        @endforeach
                        @if (count($pasangan) === 1)<td></td>@endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- ======================= HARGA ======================= --}}
    <table class="harga">
        <tr>
            <td>
                <div class="harga-label">Harga per orang</div>
                <div class="harga-angka">{{ $harga['per_orang_teks'] }} <span>/ orang</span></div>
            </td>
            <td class="harga-total">
                Total untuk {{ $rab->jumlah_peserta }} peserta<br>
                <b>{{ $harga['total_teks'] }}</b>
            </td>
        </tr>
        @if ($berlaku)
            <tr><td colspan="2" class="harga-kaki">Penawaran ini berlaku sampai <b style="color:#fff">{{ $berlaku }}</b>. Harga tiket dan transportasi dapat berubah setelah tanggal tersebut.</td></tr>
        @endif
    </table>

    @if ($rab->catatan_penawaran)
        <div class="catatan">{!! nl2br(e($aman($rab->catatan_penawaran))) !!}</div>
    @endif

    {{-- ======================= SYARAT ======================= --}}
    <div class="bagian" style="page-break-inside:avoid">
        <div class="bagian-judul">Ketentuan Pembayaran</div>
        <div class="bagian-garis"></div>
        <ul class="syarat">
            <li>Uang muka <b>{{ $dp_persen }}%</b> untuk mengunci jadwal, armada, dan akomodasi.</li>
            <li>Pelunasan paling lambat <b>H-{{ $pelunasan_hari }}</b> sebelum keberangkatan.</li>
            <li>Pembayaran hanya sah bila ditujukan atas nama&nbsp;<b>{{ $atas_nama }}</b>. Kami tidak pernah meminta transfer ke rekening pribadi.</li>
            <li>Harga dihitung untuk {{ $rab->jumlah_peserta }} peserta. Perubahan jumlah peserta dapat mengubah harga per orang.</li>
        </ul>
    </div>

    <div class="ajakan">
        <div class="ajakan-judul">Siap berangkat bersama kami?</div>
        <div class="ajakan-sub">Balas penawaran ini lewat WhatsApp dengan menyebut kode {{ $rab->kode }}</div>
        <div class="ajakan-wa">{{ $waTampil }}</div>
    </div>

</div>
</body>
</html>
