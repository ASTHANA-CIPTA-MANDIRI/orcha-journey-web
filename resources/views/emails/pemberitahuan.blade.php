{{-- Surat pemberitahuan formulir.

     Ditulis dengan tabel dan gaya menempel (inline), bukan kelas CSS: Gmail
     dan Outlook membuang <style> di kepala berkas, dan tidak mengenal flexbox
     maupun grid. Logo disisipkan lewat $message->embed() sehingga ikut terkirim
     di dalam suratnya — kalau memakai tautan gambar biasa, banyak klien surat
     memblokirnya sampai penerima menekan "tampilkan gambar". --}}
@php
    $logo = $message->embed(public_path('orcha-logo-surat.png'));
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $emas = '#ffc74e';

    // Surat yang sama dipakai dua arah. Yang membacanya berbeda, jadi
    // sapaannya juga berbeda: kotak kantor menerima "pemberitahuan", pelanggan
    // menerima "terima kasih" berikut langkah berikutnya dan peringatan
    // penipuan — kotak masuk adalah tempat penipu paling sering menyamar.
    $untukPelanggan = $untukPelanggan ?? false;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $judul }}</title>
</head>

<body
    style="margin:0;padding:0;background:#eef4f8;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#334155;-webkit-font-smoothing:antialiased;">

    {{-- Ringkasan yang tampil di daftar kotak masuk, sebelum surat dibuka --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $kode }} — {{ collect($rincian)->filter()->take(2)->implode(' · ') }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#eef4f8;padding:32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    style="width:100%;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,45,74,.08);">

                    {{-- ============ KEPALA ============
                         Logo di kiri, keterangan waktu di kanan: barisnya punya
                         dua sisi, tidak semuanya menumpuk ke tepi kiri. --}}
                    <tr>
                        <td style="background:{{ $navy }};background-image:linear-gradient(135deg,{{ $ocean }} 0%,{{ $navy }} 70%);padding:28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="52" valign="middle" style="padding-right:14px;">
                                        <img src="{{ $logo }}" width="52" height="52" alt="Orcha Journey"
                                            style="display:block;width:52px;height:52px;border:0;">
                                    </td>
                                    <td valign="middle">
                                        <p
                                            style="margin:0;color:#ffffff;font-size:19px;font-weight:800;letter-spacing:.3px;line-height:1.2;">
                                            ORCHA <span style="color:{{ $emas }};">JOURNEY</span>
                                        </p>
                                        <p style="margin:3px 0 0;color:#a9c9de;font-size:11px;letter-spacing:2px;text-transform:uppercase;">
                                            {{ config('orcha.slogan') }}
                                        </p>
                                    </td>
                                    <td valign="middle" align="right" style="white-space:nowrap;">
                                        <p style="margin:0;color:#a9c9de;font-size:11px;">
                                            {{ now()->translatedFormat('j M Y') }}
                                        </p>
                                        <p style="margin:2px 0 0;color:#ffffff;font-size:11px;font-weight:bold;">
                                            {{ now()->translatedFormat('H:i') }} WIB
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ JUDUL & KODE ============ --}}
                    <tr>
                        <td align="center" style="padding:32px 32px 8px;">
                            <p
                                style="margin:0 0 10px;color:{{ $ocean }};font-size:11px;letter-spacing:2.5px;text-transform:uppercase;font-weight:bold;">
                                {{ $untukPelanggan ? 'Terima Kasih' : 'Pemberitahuan Baru' }}
                            </p>
                            <h1
                                style="margin:0;color:{{ $navy }};font-size:25px;line-height:1.25;font-weight:800;letter-spacing:-.3px;">
                                {{ $judul }}
                            </h1>

                            @if ($kode)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                    style="margin:16px auto 0;">
                                    <tr>
                                        <td
                                            style="background:#eef6fb;border:1px solid #cfe4f2;border-radius:999px;padding:8px 20px;">
                                            <span
                                                style="font-family:'SFMono-Regular',Consolas,monospace;font-size:15px;font-weight:bold;color:{{ $navy }};letter-spacing:1px;">
                                                {{ $kode }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Garis pemisah pendek di tengah, bukan garis penuh --}}
                    <tr>
                        <td align="center" style="padding:24px 32px 4px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="48" height="3"
                                        style="background:{{ $emas }};border-radius:2px;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ RINCIAN ============
                         Label kecil di kiri, isinya tebal rata kanan — dua sisi,
                         mudah dipindai sekilas. --}}
                    <tr>
                        <td style="padding:20px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                @foreach ($rincian as $label => $nilai)
                                    @continue(blank($nilai))
                                    <tr>
                                        <td
                                            style="padding:13px 0;border-bottom:1px solid #eef2f7;font-size:12px;color:#8496a8;letter-spacing:.4px;text-transform:uppercase;vertical-align:top;width:42%;">
                                            {{ $label }}
                                        </td>
                                        <td align="right"
                                            style="padding:13px 0;border-bottom:1px solid #eef2f7;font-size:14px;color:{{ $navy }};font-weight:bold;line-height:1.6;vertical-align:top;">
                                            {!! nl2br(e($nilai)) !!}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if ($catatan)
                        <tr>
                            <td style="padding:12px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                    style="background:#f7fbfd;border-left:3px solid {{ $ocean }};border-radius:0 10px 10px 0;">
                                    <tr>
                                        <td style="padding:14px 18px;">
                                            <p
                                                style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:{{ $ocean }};font-weight:bold;">
                                                {{ $untukPelanggan ? 'Langkah Berikutnya' : 'Catatan' }}
                                            </p>
                                            <p style="margin:0;font-size:13px;line-height:1.7;color:#475569;">
                                                {!! nl2br(e($catatan)) !!}
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    @if (! empty($lampiran) || ! empty($berkasPdf))
                        <tr>
                            <td style="padding:16px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                    style="background:#fffaf0;border:1px dashed #f0d9a8;border-radius:10px;">
                                    <tr>
                                        <td align="center" style="padding:13px 18px;">
                                            {{-- Sebutannya mengikuti berkas yang benar-benar dilampirkan.
                                                 Menyebut tagihan sebagai "kwitansi" membuat pelanggan
                                                 mengira pembayarannya sudah lunas. --}}
                                            <span style="font-size:13px;color:#8a6410;">
                                                📎
                                                {{ $untukPelanggan ? $labelLampiran : 'Berkas buktinya terlampir di surat ini' }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- ============ PERINGATAN PENIPUAN ============
                         Hanya di surat pelanggan. Penipu paling sering menyamar
                         lewat kotak masuk dan WhatsApp, memakai nama perusahaan
                         yang sama tetapi rekening pribadi. Satu kalimat yang
                         bisa dicek sendiri di ATM lebih berguna daripada
                         imbauan panjang "berhati-hatilah". --}}
                    @if ($untukPelanggan)
                        <tr>
                            <td style="padding:16px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                    style="background:#fff5f5;border-left:3px solid #dc2626;border-radius:0 10px 10px 0;">
                                    <tr>
                                        <td style="padding:14px 18px;">
                                            <p
                                                style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#b91c1c;font-weight:bold;">
                                                Hati-hati Penipuan
                                            </p>
                                            <p style="margin:0;font-size:13px;line-height:1.7;color:#7f1d1d;">
                                                Pembayaran hanya sah ke rekening atas nama
                                                <strong>{{ config('orcha.pembayaran.atas_nama') }}</strong>.
                                                Nama selain itu — termasuk rekening pribadi yang mengatasnamakan kami —
                                                adalah penipuan. Kami juga tidak pernah meminta kode OTP atau
                                                kata sandi apa pun.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- ============ AJAKAN ============
                         Satu tombol utama sesuai langkah berikutnya — untuk surat
                         pendaftaran, itu mengirim bukti transfer. WhatsApp turun jadi
                         tautan kedua: penting, tetapi bukan yang perlu dikerjakan
                         sekarang. Dua tombol setara justru membuat ragu. --}}
                    @php
                        $waPelanggan = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp')
                            . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya soal ' . $kode);

                        $tombolTautan = $untukPelanggan ? ($tautan ?: $waPelanggan) : config('app.url');
                        $tombolLabel = $untukPelanggan
                            ? ($tautan ? ($labelTautan ?: 'Lanjutkan') : 'Tanya Lewat WhatsApp')
                            : 'Buka Website Orcha';
                    @endphp

                    <tr>
                        <td align="center" style="padding:28px 32px {{ $untukPelanggan && $tautan ? '10px' : '32px' }};">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td
                                        style="background-image:linear-gradient(135deg,{{ $ocean }},{{ $navy }});background-color:{{ $ocean }};border-radius:999px;">
                                        <a href="{{ $tombolTautan }}"
                                            style="display:inline-block;padding:13px 32px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;letter-spacing:.3px;">
                                            {{ $tombolLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($untukPelanggan && $tautan)
                        <tr>
                            <td align="center" style="padding:0 32px 30px;">
                                <p style="margin:0;font-size:12px;color:#94a3b8;">
                                    Ada yang perlu ditanyakan?
                                    <a href="{{ $waPelanggan }}"
                                        style="color:{{ $ocean }};font-weight:bold;text-decoration:none;">
                                        Hubungi kami lewat WhatsApp
                                    </a>
                                </p>
                            </td>
                        </tr>
                    @endif

                    {{-- ============ KAKI ============ --}}
                    <tr>
                        <td align="center"
                            style="padding:20px 32px 26px;background:#f7fafc;border-top:1px solid #eef2f7;">
                            <p style="margin:0;font-size:12px;color:{{ $navy }};font-weight:bold;">
                                Orcha Journey &middot; {{ config('orcha.slogan') }}
                            </p>
                            <p style="margin:6px 0 0;font-size:11px;color:#94a3b8;line-height:1.7;">
                                {{ config('orcha.alamat') }}<br>
                                {{ config('orcha.email') }} · +{{ config('orcha.whatsapp') }}
                            </p>
                            <p style="margin:12px 0 0;font-size:10px;color:#b6c2ce;">
                                Surat ini dikirim otomatis oleh website dan tidak dibalas.
                                {{ $untukPelanggan ? 'Pertanyaan silakan lewat WhatsApp di atas.' : '' }}
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>
