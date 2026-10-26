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
    $selisihBulat = $r['untung'] - $r['margin_diminta'];
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

    .ubin td { width: 20%; padding: 0 4px; vertical-align: top; }
    .ubin td:first-child { padding-left: 0; }
    .ubin td:last-child { padding-right: 0; }
    .ubin-isi { background-color: {{ $kabut }}; border: 1px solid #dbe7f0; padding: 9px 11px; }
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
    .kotak-judul { font-size: 8px; letter-spacing: 1.5px; color: {{ $ocean }}; text-transform: uppercase; font-weight: bold; padding-bottom: 6px; }
    .peringatan { margin-top: 12px; border-left: 3px solid {{ $merah }}; background-color: #fef3f2; padding: 8px 12px; font-size: 9px; color: {{ $merah }}; }
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
    .keg .jam { width: 80px; font-family: Courier, monospace; font-weight: bold; color: {{ $ocean }}; font-size: 9px; }
    .keg-kepala .jam, .keg-kepala .lama { font-family: Helvetica, Arial, sans-serif; }
    .keg .lama { width: 54px; }
    .pil-lama { display: inline-block; background-color: #eef4f9; color: {{ $ocean }}; font-size: 7px; font-weight: bold; padding: 2px 5px 3px; border-radius: 6px; }
    .keg .tuju { width: 162px; }
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
    .rute-no { width: 24px; padding-right: 0; }
    .bulat-no { display: inline-block; width: 14px; height: 14px; border-radius: 7px; color: #fff; font-size: 7.5px; font-weight: bold; text-align: center; line-height: 14px; }
    .rute-jam { width: 86px; font-family: Courier, monospace; font-size: 8px; color: {{ $ocean }}; font-weight: bold; }
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
            <td style="text-align:right">{{ $rab->kode }} · dicetak {{ $terbit }} &nbsp;·&nbsp; <span class="hal"></span></td>
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
        </td>
    </tr></table>
</div>
<div class="garis-emas"></div>

<div class="isi">
    <div class="judul">{{ $aman($rab->judul) }}</div>
    <div class="sub">
        <b>{{ $aman($rab->nama_pelanggan) }}</b>
        @if ($rab->whatsapp) · {{ $rab->whatsapp }} @endif
        · {{ $aman($tujuan) }} · {{ $rentang ?? 'tanggal belum ditentukan' }} · {{ $durasi }} · {{ $rab->jumlah_peserta }} peserta
    </div>

    <table class="ubin" style="margin-top:14px"><tr>
        <td><div class="ubin-isi">
            <div class="ubin-label">Modal total</div>
            <div class="ubin-nilai">{{ $r['modal_total_teks'] }}</div>
            <div class="ubin-ket">{{ $r['modal_per_kepala_teks'] }} per kepala</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top:3px solid {{ $emas }}">
            <div class="ubin-label">Harga jual / orang</div>
            <div class="ubin-nilai">{{ $r['harga_per_orang_teks'] }}</div>
            <div class="ubin-ket">dibulatkan ke {{ $rp($rab->pembulatan) }}</div>
        </div></td>
        <td><div class="ubin-isi">
            <div class="ubin-label">Harga total</div>
            <div class="ubin-nilai">{{ $r['harga_total_teks'] }}</div>
            <div class="ubin-ket">{{ $rab->jumlah_peserta }} × {{ $r['harga_per_orang_teks'] }}</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top:3px solid {{ ($r['bep_tercapai'] ?? false) ? $hijau : $merah }}">
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
        </div></td>
        <td><div class="ubin-isi" style="border-top:3px solid {{ $hijau }}">
            <div class="ubin-label">Untung</div>
            <div class="ubin-nilai" style="color:{{ $hijau }}">{{ $r['untung_teks'] }}</div>
            <div class="ubin-ket">{{ $r['persen_untung'] !== null ? str_replace('.', ',', $r['persen_untung']).'% dari modal' : '-' }}</div>
        </div></td>
    </tr></table>

    @if ($r['ada_harga_basi'])
        <div class="peringatan"><b>Harga master sudah berubah</b> untuk baris bertanda merah. Angka di RAB ini masih harga lama yang dibekukan — perbarui sebelum mengirim penawaran baru.</div>
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

    {{-- ======================= PERHITUNGAN HARGA ======================= --}}
    <table style="margin-top:18px; page-break-inside:avoid"><tr>
        <td style="width:56%; vertical-align:top; padding-right:10px">
            <div class="kotak">
                <div class="kotak-judul">Perhitungan harga</div>
                <table class="hitung">
                    <tr><td>Modal variabel (dikali peserta)</td><td class="kanan">{{ $r['modal_variabel_teks'] }}</td></tr>
                    <tr><td>Modal tetap rombongan</td><td class="kanan">{{ $r['modal_tetap_teks'] }}</td></tr>
                    <tr class="garis"><td><b>Modal total</b></td><td class="kanan"><b>{{ $r['modal_total_teks'] }}</b></td></tr>
                    <tr><td>Margin diminta ({{ $marginTeks }})</td><td class="kanan">{{ $r['margin_diminta_teks'] }}</td></tr>
                    @if ($selisihBulat > 0)
                        <tr><td>Tambahan dari pembulatan</td><td class="kanan">{{ $rp($selisihBulat) }}</td></tr>
                    @endif
                    <tr class="besar"><td>Harga total</td><td class="kanan">{{ $r['harga_total_teks'] }}</td></tr>
                    <tr class="untung"><td>Untung bersih</td><td class="kanan">{{ $r['untung_teks'] }}</td></tr>
                </table>
            </div>
        </td>
        <td style="width:44%; vertical-align:top">
            <div class="kotak">
                <div class="kotak-judul">Bila dijadikan pendaftaran</div>
                <table class="hitung">
                    <tr><td>Harga jual / orang</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['harga_jual']) }}</td></tr>
                    <tr><td>Harga modal / orang</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['harga_modal']) }}</td></tr>
                    <tr><td>Biaya tetap</td><td class="kanan">{{ $rp($r['untuk_pendaftaran']['biaya_tetap']) }}</td></tr>
                </table>
                <div style="font-size:7.5px; color:#94a3b8; padding:6px 10px 0">Modal pendaftaran = modal/orang × {{ $rab->jumlah_peserta }} + biaya tetap = {{ $r['modal_total_teks'] }}, sama persis dengan RAB.</div>
            </div>
            @if ($rab->kode_pendaftaran)
                <div class="kotak" style="margin-top:8px; border-color:#bfe3cc; background-color:#f1faf4">
                    <div class="kotak-judul" style="color:{{ $hijau }}; padding-bottom:2px">Sudah jadi pendaftaran</div>
                    <div style="font-family:Courier, monospace; font-weight:bold; color:{{ $navy }}">{{ $rab->kode_pendaftaran }}</div>
                </div>
            @endif
        </td>
    </tr></table>

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
</body>
</html>
