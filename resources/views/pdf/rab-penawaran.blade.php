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
    /*
     | Warna per jenis kegiatan. Satu penanda berwarna menjawab "ini
     | perjalanan atau destinasi" sebelum kalimatnya dibaca — jadwal yang
     | seluruh barisnya tampil sama menuntut pembacanya mengeja tiap baris.
     |
     | Jenisnya DITEBAK dari nama kegiatan (lihat BerkasRab::jenis), bukan
     | dipilih admin: memaksa satu isian lagi pada pekerjaan yang paling
     | sering dilakukan, demi sesuatu yang hampir selalu terbaca dari namanya
     | sendiri, bukan pertukaran yang sepadan.
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

    /* ---------- cip destinasi ---------- */
    .cip {
        display: inline-block; background-color: {{ $kabut }}; border: 1px solid #cfe3f0;
        color: {{ $navy }}; font-size: 8.5px; font-weight: bold;
        padding: 3px 9px 4px; margin: 0 4px 4px 0; border-radius: 9px;
    }

    /* ---------- keterangan penanda ---------- */
    .legenda { padding: 10px 0 2px; }
    .legenda-butir { display: inline-block; font-size: 8px; color: #64748b; margin-right: 12px; }
    .legenda-titik { display: inline-block; width: 6px; height: 6px; border-radius: 3px; margin-right: 5px; }

    /* ---------- itinerary ---------- */
    .hari { margin-top: 12px; border: 1px solid #e2eaf1; page-break-inside: avoid; }
    .hari-kepala { background-color: {{ $navy }}; padding: 9px 14px; }
    .hari-lencana { background-color: {{ $ocean }}; padding: 4px 0 5px; text-align: center; border-radius: 7px; }
    .hari-lencana-kata { display: block; font-size: 5.5px; letter-spacing: 1.4px; color: {{ $langit }}; font-weight: bold; }
    .hari-lencana-no { display: block; font-size: 15px; color: #fff; font-weight: bold; line-height: 1.1; }
    .hari-judul { font-size: 12px; color: #fff; font-weight: bold; }
    .hari-sub { font-size: 8px; color: {{ $langit }}; padding-top: 2px; line-height: 1.45; }
    .hari-tgl { font-size: 8.5px; color: {{ $langit }}; text-align: right; vertical-align: middle; width: 108px; }

    .keg td { padding: 7px 12px; border-bottom: 1px solid #eef3f7; vertical-align: top; }
    .keg tr:last-child td { border-bottom: none; }
    .keg-kepala td {
        background-color: {{ $kabut }}; font-size: 7.5px; letter-spacing: 1.2px; text-transform: uppercase;
        font-weight: bold; color: {{ $ocean }}; border-bottom: 1px solid #dbe7f0;
    }
    .keg .jam { width: 86px; font-family: Courier, monospace; font-weight: bold; color: {{ $ocean }}; font-size: 9.5px; }
    .keg-kepala .jam, .keg-kepala .lama { font-family: Helvetica, Arial, sans-serif; }
    .keg .lama { width: 58px; }
    .pil-lama {
        display: inline-block; background-color: #eef4f9; color: {{ $ocean }};
        font-size: 7.5px; font-weight: bold; padding: 2px 6px 3px; border-radius: 7px;
    }
    .keg .tuju { width: 168px; }
    .keg .nama { font-weight: bold; color: {{ $navy }}; font-size: 9.5px; }
    .keg .ket { color: #64748b; font-size: 9px; line-height: 1.5; }
    .keg .kosong { color: #cbd5e1; }
    .bebas { padding: 10px 14px; color: #94a3b8; font-style: italic; font-size: 9.5px; }

    /* ---------- urutan singgah ---------- */
    .rute-ket { font-size: 8.5px; color: #94a3b8; padding-top: 8px; }
    .rute-hari { margin-top: 10px; border: 1px solid #e2eaf1; page-break-inside: avoid; }
    .rute-hari-judul {
        background-color: {{ $kabut }}; padding: 6px 12px; font-size: 8.5px; font-weight: bold;
        color: {{ $navy }}; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid #dbe7f0;
    }
    .rute td { padding: 5px 12px; border-bottom: 1px solid #f2f6fa; vertical-align: middle; }
    .rute tr:last-child td { border-bottom: none; }
    .rute-no { width: 26px; padding-right: 0; }
    .bulat-no {
        display: inline-block; width: 15px; height: 15px; border-radius: 8px;
        color: #fff; font-size: 8px; font-weight: bold; text-align: center; line-height: 15px;
    }
    .rute-jam { width: 92px; font-family: Courier, monospace; font-size: 8.5px; color: {{ $ocean }}; font-weight: bold; }
    .rute-nama { font-size: 9.5px; color: {{ $navy }}; font-weight: bold; }

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
    /* Nomor halaman: dompdf mengisi counter(page) sendiri saat mencetak.
       Penawaran yang dicetak lalu tercecer di meja pelanggan tidak bisa
       disusun ulang tanpa nomor. */
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
    <div class="kaki-emas"></div>
    <div class="kaki">
        <table><tr>
            <td>{{ $aman($kontak['alamat']) }}</td>
            <td style="text-align:right">WA {{ $waTampil }} &nbsp;·&nbsp; @ {{ $kontak['instagram'] }} &nbsp;·&nbsp; <span class="hal"></span></td>
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

        {{-- Destinasinya disebut di depan, bukan menunggu dibaca di jadwal.
             Yang membuat seseorang membuka penawaran sampai halaman kedua
             adalah nama tempat yang ia kenali, bukan tanggalnya. --}}
        @if ($sorotan !== [])
            <div style="padding-top:9px">
                @foreach ($sorotan as $d)
                    <span class="cip">{{ $aman($d) }}</span>
                @endforeach
            </div>
        @endif
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

        {{-- Arti warnanya disebut sekali di sini, bukan ditebak pembaca. --}}
        <div class="legenda">
            @foreach ($rupa as $kunci => [$label, $warna])
                <span class="legenda-butir"><span class="legenda-titik" style="background-color: {{ $warna }}"></span>{{ $label }}</span>
            @endforeach
        </div>

        @foreach ($hari as $h)
            <div class="hari">
                <div class="hari-kepala">
                    <table><tr>
                        <td style="width:46px; vertical-align:middle">
                            <div class="hari-lencana"><span class="hari-lencana-kata">HARI</span><span class="hari-lencana-no">{{ $h['hari_ke'] }}</span></div>
                        </td>
                        <td style="vertical-align:middle; padding-left:10px">
                            <div class="hari-judul">{{ $aman($h['judul']) }}</div>
                            @if ($h['ringkasan'])
                                <div class="hari-sub">{{ $aman($h['ringkasan']) }}</div>
                            @endif
                        </td>
                        <td class="hari-tgl">{{ $h['tanggal'] ?? '' }}</td>
                    </tr></table>
                </div>

                @if (count($h['kegiatan']) === 0)
                    <div class="bebas">Waktu bebas — jadwal hari ini disusun bersama sesuai keinginan rombongan.</div>
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
                                {{-- Rentang, bukan satu jam: yang ditanyakan pembaca
                                     jadwal adalah berapa lama, bukan mulai kapan. --}}
                                <td class="jam">{{ $k->jam ?: '' }}@if ($k->jam && $k->jam_selesai) – {{ $k->jam_selesai }}@endif</td>
                                <td class="lama">@if ($k->durasi_teks)<span class="pil-lama">{{ $k->durasi_teks }}</span>@endif</td>
                                <td class="tuju">
                                    <span class="legenda-titik" style="background-color: {{ $warnaJenis }}"></span><span class="nama">{{ $aman($k->nama) }}</span>
                                </td>
                                <td>
                                    @if ($k->keterangan)
                                        <span class="ket">{{ $aman($k->keterangan) }}</span>
                                    @else
                                        <span class="kosong">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach

        {{-- ======================= URUTAN SINGGAH ======================= --}}
        @if ($rute !== [])
            {{-- Urutan kunjungan, BUKAN peta.

                 Peta menuntut data geografis yang tidak ada saat berkas ini
                 dicetak, dan peta yang dikarang lebih menyesatkan daripada
                 tidak ada peta sama sekali. Yang diberikan di sini isi yang
                 sama dengan daftar di samping peta: nomor, jam, dan nama
                 singgahannya, berurut. --}}
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
                                    <td class="rute-no"><span class="bulat-no" style="background-color: {{ $warnaT }}">{{ $t['no'] }}</span></td>
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
