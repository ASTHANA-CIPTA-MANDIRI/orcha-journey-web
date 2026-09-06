{{-- Faktur tagihan pembayaran.

     Ditulis dengan tabel dan gaya menempel (inline), bukan kelas CSS: Gmail
     dan Outlook membuang <style> di kepala berkas, dan tidak mengenal flexbox
     maupun grid. Logo disisipkan lewat $message->embed() sehingga ikut
     terkirim di dalam suratnya — tautan gambar biasa diblokir banyak klien
     surat sampai penerima menekan "tampilkan gambar", dan faktur yang
     gambarnya bolong terbaca seperti surat sampah.

     Bentuknya mengikuti faktur pada umumnya, bukan gaya kami sendiri: siapa
     menagih siapa, rincian berikut jumlahnya, lalu satu angka total. Yang
     berbeda hanya warnanya. Orang sudah tahu cara membaca faktur; melawan
     kebiasaan itu tidak menghasilkan apa-apa selain kebingungan pada surat
     yang paling tidak boleh membingungkan. --}}
@php
    $logo = $message->embed(public_path('orcha-logo-surat.png'));
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $emas = '#ffc74e';

    $rupiah = fn (int $angka) => 'Rp ' . number_format($angka, 0, ',', '.');

    $waPelanggan = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp')
        . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya soal ' . $kode);
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Tagihan Pembayaran {{ $kode }}</title>
</head>

<body
    style="margin:0;padding:0;background:#eef4f8;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#334155;-webkit-font-smoothing:antialiased;">

    {{-- Ringkasan yang tampil di daftar kotak masuk, sebelum surat dibuka.
         Nominalnya disebut di sini supaya pelanggan tahu berapa yang ditagih
         tanpa perlu membuka apa pun. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $kode }} — {{ $jenisLabel }} {{ $rupiah($nominal) }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#eef4f8;padding:32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    style="width:100%;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,45,74,.08);">

                    {{-- ============ KEPALA ============ --}}
                    <tr>
                        <td
                            style="background:{{ $navy }};background-image:linear-gradient(135deg,{{ $ocean }} 0%,{{ $navy }} 70%);padding:28px 32px;">
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
                                        <p
                                            style="margin:3px 0 0;color:#a9c9de;font-size:11px;letter-spacing:2px;text-transform:uppercase;">
                                            {{ config('orcha.slogan') }}
                                        </p>
                                    </td>
                                    <td valign="middle" align="right" style="white-space:nowrap;">
                                        <p
                                            style="margin:0;color:{{ $emas }};font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:bold;">
                                            Tagihan
                                        </p>
                                        <p style="margin:3px 0 0;color:#ffffff;font-size:13px;font-weight:bold;">
                                            {{ $invoice }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ SIAPA MENAGIH SIAPA ============
                         Dua kolom berdampingan. Di layar sempit keduanya
                         menumpuk sendiri karena lebarnya dinyatakan persen,
                         bukan piksel. --}}
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="50%" valign="top" style="padding-right:12px;">
                                        <p
                                            style="margin:0 0 6px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;">
                                            Ditagihkan kepada
                                        </p>
                                        <p style="margin:0;font-size:14px;color:{{ $navy }};font-weight:bold;">
                                            {{ $namaPelanggan }}
                                        </p>
                                        @if ($emailPelanggan)
                                            <p style="margin:3px 0 0;font-size:12px;color:#64748b;">{{ $emailPelanggan }}</p>
                                        @endif
                                        @if ($teleponPelanggan)
                                            <p style="margin:2px 0 0;font-size:12px;color:#64748b;">{{ $teleponPelanggan }}</p>
                                        @endif
                                    </td>
                                    <td width="50%" valign="top" style="padding-left:12px;">
                                        <p
                                            style="margin:0 0 6px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;">
                                            Ditagihkan oleh
                                        </p>
                                        {{-- Nama penerima dana ditulis lengkap, bukan "Orcha Journey".
                                             Itulah nama yang nanti dilihat pelanggan di mesin bank, dan
                                             satu-satunya patokan yang bisa ia cek sendiri. --}}
                                        <p style="margin:0;font-size:14px;color:{{ $navy }};font-weight:bold;">
                                            {{ config('orcha.pembayaran.atas_nama') }}
                                        </p>
                                        <p style="margin:3px 0 0;font-size:12px;color:#64748b;">
                                            {{ config('orcha.email') }}
                                        </p>
                                        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">
                                            +{{ config('orcha.whatsapp') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ TANGGAL & PESANAN ============ --}}
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="background:#f7fbfd;border-radius:12px;">
                                <tr>
                                    <td width="34%" valign="top" style="padding:14px 16px;">
                                        <p
                                            style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;">
                                            Kode pesanan
                                        </p>
                                        <p
                                            style="margin:0;font-family:'SFMono-Regular',Consolas,monospace;font-size:13px;color:{{ $navy }};font-weight:bold;">
                                            {{ $kode }}
                                        </p>
                                    </td>
                                    <td width="33%" valign="top" style="padding:14px 16px;">
                                        <p
                                            style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;">
                                            Tanggal
                                        </p>
                                        <p style="margin:0;font-size:13px;color:{{ $navy }};font-weight:bold;">
                                            {{ now()->translatedFormat('j F Y') }}
                                        </p>
                                    </td>
                                    <td width="33%" valign="top" style="padding:14px 16px;">
                                        <p
                                            style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;">
                                            Berlaku sampai
                                        </p>
                                        {{-- Tanggal dan jamnya dirangkai TERPISAH, tanpa kata
                                             penghubung di dalam pola format. "pukul" pernah ditulis
                                             begitu saja di dalamnya, dan Carbon membaca u, k, dan l
                                             sebagai kode format — hasilnya "p000000k000000Minggu". --}}
                                        <p style="margin:0;font-size:13px;color:{{ $navy }};font-weight:bold;">
                                            @if ($kedaluwarsa)
                                                {{ $kedaluwarsa->translatedFormat('j F Y') }},
                                                {{ $kedaluwarsa->translatedFormat('H:i') }} WIB
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ RINCIAN TAGIHAN ============
                         Kode unik jadi BARISNYA SENDIRI, sama seperti yang kita
                         kirim ke DOKU dan yang tampil di halaman bayar. Tiga
                         tempat, satu penjelasan — angka ganjil di ujung total
                         akan disangka salah hitung kalau hanya muncul sekali
                         tanpa asal-usul. --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td
                                        style="padding:0 0 8px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;border-bottom:2px solid {{ $navy }};">
                                        Rincian
                                    </td>
                                    <td align="right"
                                        style="padding:0 0 8px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8496a8;font-weight:bold;border-bottom:2px solid {{ $navy }};white-space:nowrap;">
                                        Jumlah
                                    </td>
                                </tr>

                                <tr>
                                    <td valign="top"
                                        style="padding:14px 12px 14px 0;border-bottom:1px solid #eef2f7;font-size:14px;color:{{ $navy }};line-height:1.5;">
                                        {{ $barang }}
                                        <span style="display:block;margin-top:3px;font-size:12px;color:#8496a8;">
                                            {{ $jenisLabel }}
                                        </span>
                                    </td>
                                    <td align="right" valign="top"
                                        style="padding:14px 0;border-bottom:1px solid #eef2f7;font-size:14px;color:{{ $navy }};font-weight:bold;white-space:nowrap;line-height:1.5;">
                                        {{ $rupiah($pokok) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td valign="top"
                                        style="padding:14px 12px 14px 0;border-bottom:1px solid #eef2f7;font-size:14px;color:{{ $navy }};line-height:1.5;">
                                        Kode unik pembayaran
                                        <span style="display:block;margin-top:3px;font-size:12px;color:#8496a8;">
                                            Penanda pembayaran ini
                                        </span>
                                    </td>
                                    <td align="right" valign="top"
                                        style="padding:14px 0;border-bottom:1px solid #eef2f7;font-size:14px;color:{{ $ocean }};font-weight:bold;white-space:nowrap;line-height:1.5;">
                                        + {{ number_format($kodeUnik, 0, ',', '.') }}
                                    </td>
                                </tr>

                                {{-- Totalnya sengaja jauh lebih besar dari baris di
                                     atasnya. Ia satu-satunya angka yang harus terbaca
                                     tanpa dicari. --}}
                                <tr>
                                    <td style="padding:18px 12px 0 0;font-size:13px;color:#64748b;font-weight:bold;">
                                        Total yang harus dibayar
                                    </td>
                                    <td align="right" style="padding:18px 0 0;white-space:nowrap;">
                                        <span style="font-size:24px;color:{{ $navy }};font-weight:800;letter-spacing:-.3px;">
                                            {{ $rupiah($nominal) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ KETERANGAN KODE UNIK ============ --}}
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="background:#f7fbfd;border-left:3px solid {{ $ocean }};border-radius:0 10px 10px 0;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p
                                            style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:{{ $ocean }};font-weight:bold;">
                                            Tentang kode unik
                                        </p>
                                        {{-- "Angka di ujung total" TIDAK dipakai, meski itu kalimat
                                             yang lazim untuk kode unik tiga digit.

                                             Kode unik di sini 500–1.500, jadi bisa empat digit — dan
                                             ujung Rp 859.236 adalah 236, bukan 1236. Kalimat yang
                                             menyuruh pelanggan mencocokkan sesuatu yang tidak akan ia
                                             temukan justru membuatnya mengira ada yang salah hitung. --}}
                                        <p style="margin:0;font-size:13px;line-height:1.7;color:#475569;">
                                            <strong>{{ $rupiah($kodeUnik) }}</strong> yang ditambahkan ke tagihan
                                            adalah penanda pembayaran ini, supaya begitu masuk langsung kami
                                            kenali. Harga pesanan Anda <strong>tidak naik</strong> — yang
                                            bertambah hanya penandanya, dan hanya sekali ini.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ TOMBOL BAYAR ============ --}}
                    <tr>
                        <td align="center" style="padding:26px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td
                                        style="background-image:linear-gradient(135deg,{{ $ocean }},{{ $navy }});background-color:{{ $ocean }};border-radius:999px;">
                                        <a href="{{ $url }}"
                                            style="display:inline-block;padding:14px 38px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;letter-spacing:.3px;">
                                            Bayar Sekarang
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0;font-size:12px;color:#94a3b8;line-height:1.6;">
                                Pilih bank, QRIS, atau dompet digital di halaman berikutnya.<br>
                                Pembayaran tercatat sendiri dalam hitungan detik — Anda tidak perlu
                                mengirim bukti apa pun kepada kami.
                            </p>
                        </td>
                    </tr>

                    {{-- Alamat mentahnya ikut ditulis.

                         Sebagian klien surat memotong tombol berbentuk tabel, dan
                         sebagian pembaca memang lebih percaya alamat yang bisa
                         dibacanya sendiri daripada tombol yang tidak kelihatan
                         menuju ke mana. --}}
                    <tr>
                        <td align="center" style="padding:6px 32px 0;">
                            <p style="margin:0;font-size:11px;color:#b6c2ce;line-height:1.6;word-break:break-all;">
                                Tombolnya tidak berfungsi? Buka alamat ini:<br>
                                <a href="{{ $url }}" style="color:{{ $ocean }};text-decoration:none;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- ============ PERINGATAN PENIPUAN ============ --}}
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="background:#fff5f5;border-left:3px solid #dc2626;border-radius:0 10px 10px 0;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p
                                            style="margin:0 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#b91c1c;font-weight:bold;">
                                            Hati-hati Penipuan
                                        </p>
                                        <p style="margin:0;font-size:13px;line-height:1.7;color:#7f1d1d;">
                                            Pembayaran hanya sah lewat halaman pembayaran resmi kami, atas nama
                                            <strong>{{ config('orcha.pembayaran.atas_nama') }}</strong>. Kami tidak
                                            pernah meminta transfer ke rekening pribadi atas nama perorangan, dan
                                            tidak pernah meminta PIN, OTP, atau nomor kartu Anda lewat pesan apa pun.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 32px 28px;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;">
                                Ada yang perlu ditanyakan?
                                <a href="{{ $waPelanggan }}"
                                    style="color:{{ $ocean }};font-weight:bold;text-decoration:none;">
                                    Hubungi kami lewat WhatsApp
                                </a>
                            </p>
                        </td>
                    </tr>

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
                                Pertanyaan silakan lewat WhatsApp di atas.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>
