{{-- Kwitansi/tanda terima yang dilampirkan di surat pemberitahuan.

     Dompdf tidak mengenal flexbox maupun grid, jadi tata letaknya memakai
     tabel — sama seperti berkas surat. --}}
@php
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $emas = '#ffc74e';
    $logo = public_path('orcha-logo-surat.png');

    // Stempel dan tanda tangan bersifat pilihan: begitu berkasnya ditaruh di
    // public/, keduanya langsung ikut tercetak. Selama belum ada, blok tanda
    // tangan tetap tampil rapi memakai logo — berkas tetap sah karena
    // diterbitkan sistem, dan itu memang dinyatakan di bagian bawahnya.
    $stempel = file_exists(public_path('orcha-stempel.png')) ? public_path('orcha-stempel.png') : null;
    $ttd = file_exists(public_path('orcha-ttd.png')) ? public_path('orcha-ttd.png') : null;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $kode }}</title>
    <style>
        @page { margin: 26px 30px; }
        /* Helvetica adalah huruf bawaan PDF: tidak ikut disisipkan ke berkasnya.
           Memakai DejaVu membuat berkas membengkak ~1 MB hanya untuk hurufnya,
           padahal lampiran surat sebaiknya ringan. */
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #334155; }
        .kepala { background-color: {{ $navy }}; color: #fff; padding: 16px 18px; }
        .merek { font-size: 15px; font-weight: bold; letter-spacing: .4px; }
        .merek span { color: {{ $emas }}; }
        .slogan { font-size: 8px; color: #a9c9de; letter-spacing: 1.6px; text-transform: uppercase; }
        .judul { font-size: 17px; font-weight: bold; color: {{ $navy }}; }
        .kode { font-family: Courier, monospace; font-size: 13px; font-weight: bold; color: {{ $navy }}; }
        .label { font-size: 8.5px; color: #8496a8; text-transform: uppercase; letter-spacing: .5px; }
        .nilai { font-size: 11.5px; color: {{ $navy }}; font-weight: bold; }
        .garis { border-bottom: 1px solid #e6edf3; }
        .kotak-jumlah { background-color: #eef6fb; border: 1px solid #cfe4f2; padding: 12px 16px; }
        .kaki { font-size: 8.5px; color: #94a3b8; line-height: 1.6; }
        .cap { border: 2px solid {{ $ocean }}; color: {{ $ocean }}; font-size: 12px; font-weight: bold;
               padding: 7px 16px; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>

<body>
    <table width="100%" cellpadding="0" cellspacing="0" class="kepala">
        <tr>
            <td width="46" valign="middle">
                <img src="{{ $logo }}" width="42" height="42" alt="">
            </td>
            <td valign="middle">
                <div class="merek">ORCHA <span>JOURNEY</span></div>
                <div class="slogan">Teman Setia Perjalanan Anda</div>
            </td>
            <td valign="middle" align="right" style="font-size:9px;color:#a9c9de;">
                {{ config('orcha.alamat') }}<br>
                {{ config('orcha.email') }} · +{{ config('orcha.whatsapp') }}
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;">
        <tr>
            <td>
                <div class="judul">{{ $judul }}</div>
                <div style="font-size:9.5px;color:#64748b;margin-top:3px;">
                    Diterbitkan {{ now()->translatedFormat('j F Y, H:i') }} WIB
                </div>
            </td>
            <td align="right">
                <div class="label">Nomor</div>
                <div class="kode">{{ $kode }}</div>
            </td>
        </tr>
    </table>

    <div style="height:3px;width:52px;background-color:{{ $emas }};margin:14px 0 4px;"></div>

    @if (! empty($jumlah))
        <table width="100%" cellpadding="0" cellspacing="0" class="kotak-jumlah" style="margin-top:14px;">
            <tr>
                <td>
                    <div class="label">{{ $jumlahLabel ?? 'Jumlah diterima' }}</div>
                    <div style="font-size:20px;font-weight:bold;color:{{ $navy }};margin-top:2px;">{{ $jumlah }}</div>
                </td>
                <td align="right" valign="middle">
                    <span class="cap">{{ $capStatus ?? 'Diterima' }}</span>
                </td>
            </tr>
        </table>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
        @foreach ($rincian as $label => $nilai)
            @continue(blank($nilai))
            <tr>
                <td class="garis" width="38%" style="padding:9px 0;" valign="top">
                    <span class="label">{{ $label }}</span>
                </td>
                <td class="garis" align="right" style="padding:9px 0;" valign="top">
                    <span class="nilai">{!! nl2br(e($nilai)) !!}</span>
                </td>
            </tr>
        @endforeach
    </table>

    @if (! empty($catatan))
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
            <tr>
                <td style="background-color:#f7fbfd;padding:11px 14px;border-left:3px solid {{ $ocean }};">
                    <div class="label" style="color:{{ $ocean }};">Catatan</div>
                    <div style="font-size:10.5px;color:#475569;margin-top:3px;">{!! nl2br(e($catatan)) !!}</div>
                </td>
            </tr>
        </table>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px;">
        <tr>
            <td class="kaki" width="62%" valign="top">
                <strong style="color:{{ $navy }};">Perlu diperhatikan</strong><br>
                Pembayaran hanya sah ke rekening atas nama
                <strong>{{ config('orcha.pembayaran.atas_nama') }}</strong>.
                Nama selain itu bukan kami.<br>
                Simpan berkas ini sampai perjalanan selesai.
            </td>
            <td align="center" valign="top" style="font-size:9px;color:#94a3b8;">
                Hormat kami,

                {{-- Stempel dan tanda tangan ditumpuk: stempel jadi latar,
                     tanda tangan di atasnya — seperti dokumen yang dicap lalu
                     ditandatangani, bukan dua gambar berjajar. --}}
                <div style="position:relative;height:96px;margin-top:2px;">
                    @if ($stempel)
                        <img src="{{ $stempel }}" width="92" height="92" alt=""
                            style="position:absolute;top:0;left:50%;margin-left:-46px;opacity:.85;">
                    @else
                        <img src="{{ $logo }}" width="46" height="46" alt=""
                            style="position:absolute;top:24px;left:50%;margin-left:-23px;">
                    @endif

                    @if ($ttd)
                        <img src="{{ $ttd }}" width="104" alt=""
                            style="position:absolute;top:22px;left:50%;margin-left:-52px;">
                    @endif
                </div>

                <div style="border-top:1px solid #cbd5e1;width:130px;margin:0 auto;padding-top:4px;">
                    <strong style="color:{{ $navy }};font-size:10px;">Orcha Journey</strong><br>
                    <span style="font-size:8px;">{{ config('orcha.pembayaran.atas_nama') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top:18px;padding-top:9px;border-top:1px solid #e6edf3;" class="kaki">
        Berkas ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan basah.
    </div>
</body>

</html>
