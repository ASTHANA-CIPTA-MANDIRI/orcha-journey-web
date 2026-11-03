{{-- Surat pengantar penawaran perjalanan.

     Sengaja PENDEK. Isi penawarannya ada di PDF yang dilampirkan; surat ini
     cuma memastikan orang tahu lampirannya apa, dari siapa, dan berlaku
     sampai kapan. Surat panjang yang mengulang isi lampiran membuat
     keduanya harus dijaga sama — dan suatu hari keduanya akan berselisih.

     Gaya menempel (inline), bukan kelas CSS: Gmail dan Outlook membuang
     <style> di kepala berkas. Logo lewat $message->embed() supaya ikut
     terkirim di dalam suratnya; tautan gambar biasa diblokir sampai
     penerima menekan "tampilkan gambar", dan surat yang gambarnya bolong
     terbaca seperti surat sampah. --}}
@php
    $logo = $message->embed(public_path('orcha-logo-surat.png'));
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';

    $tanggal = $rab->tanggal_mulai
        ? \Illuminate\Support\Carbon::parse($rab->tanggal_mulai)->locale('id')->translatedFormat('j F Y')
        : null;
    $berlaku = $rab->berlaku_sampai
        ? \Illuminate\Support\Carbon::parse($rab->berlaku_sampai)->locale('id')->translatedFormat('j F Y')
        : null;

    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp')
        . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya soal penawaran ' . $rab->kode);
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Penawaran {{ $rab->kode }}</title>
</head>

<body style="margin:0;padding:0;background:#eef4f8;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#334155;-webkit-font-smoothing:antialiased;">

    {{-- Ringkasan yang tampil di daftar kotak masuk, sebelum surat dibuka. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Penawaran {{ $rab->judul }} terlampir sebagai PDF.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;">

                    <tr>
                        <td style="background:{{ $navy }};padding:20px 24px;" align="left">
                            <img src="{{ $logo }}" alt="Orcha Journey" width="132"
                                style="display:block;border:0;outline:none;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 24px 8px;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                                Halo <strong>{{ $rab->nama_pelanggan }}</strong>,
                            </p>
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                                Terima kasih sudah menghubungi Orcha Journey. Berikut penawaran perjalanan
                                yang Anda tanyakan — rinciannya ada di berkas PDF yang kami lampirkan.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="background:#f4f8fb;border-radius:11px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;">
                                            {{ $rab->kode }}
                                        </p>
                                        <p style="margin:0 0 10px;font-size:17px;font-weight:700;color:{{ $navy }};">
                                            {{ $rab->judul }}
                                        </p>
                                        <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;">
                                            {{ $rab->jumlah_hari }} hari {{ $rab->jumlah_malam }} malam
                                            &middot; {{ $rab->jumlah_peserta }} peserta
                                            @if ($tanggal)
                                                <br>Berangkat {{ $tanggal }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($rab->catatan_penawaran)
                        <tr>
                            <td style="padding:14px 24px 0;">
                                <p style="margin:0;font-size:14px;line-height:1.7;color:#475569;white-space:pre-line;">{{ $rab->catatan_penawaran }}</p>
                            </td>
                        </tr>
                    @endif

                    @if ($berlaku)
                        {{-- Masa berlaku disebut di SURATNYA, bukan cuma di dalam
                             PDF: itu satu-satunya kalimat yang membuat orang
                             membukanya hari ini, bukan bulan depan. --}}
                        <tr>
                            <td style="padding:16px 24px 0;">
                                <p style="margin:0;padding:11px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;font-size:14px;line-height:1.6;color:#92400e;">
                                    Penawaran ini berlaku sampai <strong>{{ $berlaku }}</strong>.
                                </p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:22px 24px 26px;" align="left">
                            <a href="{{ $wa }}"
                                style="display:inline-block;padding:12px 22px;border-radius:10px;background:{{ $ocean }};color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">
                                Tanya lewat WhatsApp
                            </a>
                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Ada yang ingin diubah — tanggal, jumlah peserta, atau tempat yang dikunjungi?
                                Balas surat ini saja, nanti kami susun ulang penawarannya.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fafc;padding:16px 24px;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                Orcha Journey &middot; penawaran ini dibuat khusus untuk Anda.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
