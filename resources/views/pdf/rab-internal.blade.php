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

    .ubin td { width: 25%; padding: 0 4px; vertical-align: top; }
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

    .jadwal td { padding: 4px 8px; border-bottom: 1px solid #eef3f7; font-size: 9px; vertical-align: top; }
    .jadwal .hr { width: 46px; color: {{ $ocean }}; font-weight: bold; }
    .jadwal .jm { width: 40px; font-family: Courier, monospace; color: #64748b; }

    .kaki-luar { position: fixed; bottom: -56px; left: 0; right: 0; }
    .kaki { background-color: {{ $navy }}; padding: 9px 32px 10px; border-top: 2px solid {{ $merah }}; }
    .kaki td { font-size: 7.5px; color: {{ $langit }}; }
</style>
</head>
<body>

<div class="kaki-luar">
    <div class="kaki">
        <table><tr>
            <td><b style="color:#fff">DOKUMEN INTERNAL</b> — berisi modal & margin. Jangan dikirim ke pelanggan; kirim PDF Penawaran.</td>
            <td style="text-align:right">{{ $rab->kode }} · dicetak {{ $terbit }}</td>
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

    {{-- ======================= JADWAL RINGKAS ======================= --}}
    <div class="bagian" style="page-break-inside:avoid">
        <div class="bagian-judul">Itinerary Ringkas</div>
        <div class="bagian-garis"></div>
        <table class="jadwal" style="margin-top:8px">
            @foreach ($hari as $h)
                @forelse ($h['kegiatan'] as $i => $k)
                    <tr>
                        <td class="hr">{{ $i === 0 ? 'Hari '.$h['hari_ke'] : '' }}</td>
                        <td class="jm">{{ $k->jam ?: '' }}</td>
                        <td><b style="color:{{ $navy }}">{{ $aman($k->nama) }}</b>@if ($k->destinasi) <span style="color:#94a3b8">· {{ $aman($k->destinasi) }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td class="hr">Hari {{ $h['hari_ke'] }}</td><td class="jm"></td><td style="color:#94a3b8; font-style:italic">Waktu bebas</td></tr>
                @endforelse
            @endforeach
        </table>
    </div>
</div>
</body>
</html>
