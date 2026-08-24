{{-- Surat pernyataan penggantian peserta.

     Kop, garis emas, dan pita kaki disalin sebentuk dengan pdf/kwitansi.blade.php:
     pemesan menerima kedua berkas ini dari satu perjalanan yang sama, dan berkas
     yang rupanya berbeda-beda terbaca seolah berasal dari dua tempat.

     Dompdf tidak mengenal flexbox maupun grid, jadi tata letaknya memakai tabel.
     Seluruh gayanya harus menempel atau berada di <style> dokumen ini sendiri —
     berkas ini dirender di server tanpa aset Vite sama sekali. --}}
@php
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $kabut = '#f4f8fb';
    $logo = public_path('orcha-logo-surat.png');

    $terbit = now();

    /* Nomor surat bergaya persuratan: SPP/<kode>/<bulan romawi>/<tahun>.
       Pemesan institusi mengarsipkan berkas ini bersama surat-surat lain, dan
       berkas tanpa nomor sulit dirujuk kembali dalam korespondensi. */
    $romawi = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$terbit->month - 1];
    $nomorSurat = 'SPP/'.$pendaftaran->kode.'/'.$romawi.'/'.$terbit->year;

    $garis = '……………………………………………………………';
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Pernyataan Penggantian Peserta — {{ $pendaftaran->kode }}</title>
    <style>
        /* Ruang atas DAN bawah dilebihkan: kop dan pita kaki sama-sama dipasang
           mengambang supaya tercetak di setiap halaman. Surat resmi berhalaman
           dua yang halaman keduanya polos tanpa kop terbaca seperti lembar
           lepas yang tercecer dari berkas lain. */
        @page { margin: 106px 0 68px; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #475569;
               margin: 0; padding: 0; }

        .isi { padding: 0 34px 10px; }

        /* ---------- kepala ---------- */
        /* Sama seperti pita kaki: offsetnya negatif sebesar margin halaman,
           karena dompdf mengukur top dari tepi DALAM margin. Tanpa itu kopnya
           mengambang dengan pias putih di atasnya. */
        .kepala-luar { position: fixed; top: -106px; left: 0; right: 0; }
        .pita-kepala { background-color: {{ $navy }}; padding: 18px 34px 16px; }
        .merek { font-size: 16px; font-weight: bold; letter-spacing: .6px; color: #fff; }
        .merek span { color: {{ $emas }}; }
        .slogan { font-size: 7.5px; color: {{ $langit }}; letter-spacing: 2px;
                  text-transform: uppercase; padding-top: 2px; }
        .kontak { font-size: 8.5px; color: {{ $langit }}; line-height: 1.7; }
        .garis-emas { height: 3px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }

        /* ---------- judul ---------- */
        .jenis { font-size: 8.5px; letter-spacing: 2.4px; text-transform: uppercase;
                 color: {{ $ocean }}; font-weight: bold; }
        .judul { font-size: 19px; font-weight: bold; color: {{ $navy }}; padding-top: 3px;
                 line-height: 1.25; }
        .terbit { font-size: 9px; color: #94a3b8; padding-top: 4px; }
        .panel-nomor { background-color: {{ $kabut }}; border: 1px solid #dbe7f0; padding: 8px 14px; }
        .kode { font-family: Courier, monospace; font-size: 11px; font-weight: bold;
                color: {{ $navy }}; letter-spacing: .4px; }

        /* ---------- label & nilai ---------- */
        .label { font-size: 8px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        .bagian { font-size: 8.5px; letter-spacing: 2px; text-transform: uppercase;
                  color: {{ $ocean }}; font-weight: bold; }
        .rule { height: 2px; width: 30px; background-color: {{ $emas }}; font-size: 0; line-height: 0;
                margin-top: 5px; }

        /* ---------- paragraf ---------- */
        .paragraf { font-size: 10.5px; line-height: 1.75; text-align: justify; color: #475569; }

        /* ---------- identitas ---------- */
        .identitas td { padding: 4px 0; font-size: 10.5px; vertical-align: top; }
        .identitas .kunci { width: 118px; color: #64748b; }
        .identitas .titik { width: 12px; color: #64748b; }
        .identitas .isi-nilai { color: {{ $navy }}; font-weight: bold; }
        /* Yang tidak diketahui sistem dicetak sebagai garis isian: NIK dan alamat
           tidak pernah diminta saat mendaftar, dan surat bermaterai memang lazim
           dilengkapi tangan sebelum ditandatangani. */
        .isian { color: #94a3b8; font-weight: normal; letter-spacing: .5px; }

        /* ---------- tabel pergantian ---------- */
        .ganti { border-collapse: collapse; }
        .ganti th { background-color: #eef6fb; color: {{ $ocean }}; font-size: 7.5px;
                    letter-spacing: 1.4px; text-transform: uppercase; font-weight: bold;
                    padding: 7px 12px; text-align: left; border: 1px solid #cfe4f2; }
        .ganti td { padding: 9px 12px; border: 1px solid #cfe4f2; font-size: 12px;
                    font-weight: bold; color: {{ $navy }}; }
        .ganti td.kecil { font-size: 10.5px; }
        .tetap { font-size: 8.5px; color: #94a3b8; font-weight: normal; letter-spacing: .3px; }

        /* ---------- pasal ---------- */
        .pasal-judul { font-size: 9.5px; font-weight: bold; color: {{ $navy }};
                       letter-spacing: .3px; padding-top: 9px; }
        .pasal-isi { font-size: 10px; line-height: 1.7; text-align: justify; color: #475569;
                     padding-top: 2px; }

        /* ---------- kotak dasar hukum ---------- */
        .kotak-dasar { background-color: {{ $kabut }}; border-left: 3px solid {{ $ocean }};
                       padding: 10px 14px; font-size: 9.5px; line-height: 1.65; }

        /* ---------- materai & tanda tangan ---------- */
        .ttd-kolom { font-size: 10px; color: #475569; }
        /* Tinggi kotak materai ditetapkan lewat padding, bukan atribut height:
           dompdf mengabaikan height pada sel lalu melarkan kotaknya mengikuti
           sisa ruang, dan tiga garis tanda tangan jadi bertingkat-tingkat. */
        .materai { border: 1px dashed #cbd5e1; color: #94a3b8; font-size: 8px; text-align: center;
                   padding: 21px 6px; letter-spacing: .4px; line-height: 1.5; }
        /* Ruang kosong di dua kolom lain, setinggi kotak materai + bingkainya,
           supaya ketiga garisnya jatuh pada ketinggian yang sama. */
        .ganjal-materai { font-size: 0; line-height: 0; height: 64px; }
        .garis-ttd { border-bottom: 1px solid {{ $navy }}; font-size: 0; line-height: 0; height: 1px; }
        /* Jarak bawahnya dijaga sendiri: keterangan "nama terang & tanda tangan"
           yang dulu berdiri di bawahnya sudah dibuang — tata letaknya sudah
           menjelaskan sendiri mana garis tanda tangan dan mana namanya — dan
           tanpa penggantinya nama itu menempel ke bagian berikutnya. */
        .nama-ttd { font-size: 10.5px; font-weight: bold; color: {{ $navy }};
                    padding-top: 5px; padding-bottom: 6px; }

        /* ---------- kaki ---------- */
        .kaki-luar { position: fixed; bottom: -68px; left: 0; right: 0; }
        .kaki-emas { height: 2px; background-color: {{ $emas }}; font-size: 0; line-height: 0; }
        .pita-kaki { background-color: {{ $navy }}; padding: 10px 34px 11px; table-layout: fixed; }
        .kaki-merek { font-size: 9.5px; font-weight: bold; color: #fff; letter-spacing: .8px; }
        .kaki-merek span { color: {{ $emas }}; }
        .kaki-slogan { font-size: 7px; color: {{ $langit }}; letter-spacing: 1.6px;
                       text-transform: uppercase; padding-top: 2px; }
        .kaki-kontak { font-size: 8px; color: {{ $langit }}; line-height: 1.6; }
        .kaki-nomor { font-family: Courier, monospace; font-size: 9px; font-weight: bold;
                      color: {{ $emas }}; letter-spacing: .6px; }
        .kaki-sah { font-size: 6.5px; color: #6f9cbd; letter-spacing: .3px; padding-top: 3px; }
        .kaki-nomor-label { font-size: 6.5px; color: #6f9cbd; letter-spacing: 1.6px;
                            text-transform: uppercase; padding-bottom: 1px; }
    </style>
</head>

<body>

    {{-- ============ KEPALA ============
         Mengambang, jadi ikut tercetak di halaman kedua dan seterusnya. --}}
    <div class="kepala-luar">
    <table width="100%" cellpadding="0" cellspacing="0" class="pita-kepala">
        <tr>
            <td width="50" valign="middle">
                <img src="{{ $logo }}" width="44" height="44" alt="">
            </td>
            <td valign="middle">
                <div class="merek">ORCHA <span>JOURNEY</span></div>
                <div class="slogan">{{ config('orcha.slogan') }}</div>
            </td>
            <td valign="middle" align="right" class="kontak">
                {{ config('orcha.alamat') }}<br>
                {{ config('orcha.email') }} &middot; +{{ config('orcha.whatsapp') }}
            </td>
        </tr>
    </table>
    <div class="garis-emas"></div>
    </div>

    <div class="isi">

        {{-- ============ JUDUL & NOMOR SURAT ============ --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
            <tr>
                <td valign="top">
                    <div class="jenis">Dokumen Resmi</div>
                    <div class="judul">Surat Pernyataan<br>Penggantian Peserta</div>
                    <div class="terbit">Diterbitkan {{ $terbit->locale('id')->translatedFormat('j F Y, H:i') }} WIB</div>
                </td>
                <td align="right" valign="top" width="38%">
                    <table cellpadding="0" cellspacing="0" align="right" class="panel-nomor">
                        <tr>
                            <td align="right">
                                <div class="label">Nomor Surat</div>
                                <div class="kode">{{ $nomorSurat }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ============ IDENTITAS PEMESAN ============
             Nama dan kontak diambil dari pendaftarannya; NIK dan alamat tidak
             pernah diminta saat mendaftar, jadi dicetak sebagai garis isian. --}}
        <div class="bagian" style="margin-top:20px;">I. Identitas Pemesan</div>
        <div class="rule"></div>

        <div class="paragraf" style="padding-top:9px;">
            Yang bertanda tangan di bawah ini:
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" class="identitas" style="margin-top:4px;">
            <tr>
                <td class="kunci">Nama lengkap</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->nama ?: $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">Nomor WhatsApp</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->whatsapp ?: $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">Surel</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->email ?: $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">NIK</td>
                <td class="titik">:</td>
                <td class="isian">{{ $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">Alamat</td>
                <td class="titik">:</td>
                <td class="isian">{{ $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">&nbsp;</td>
                <td class="titik">&nbsp;</td>
                <td class="isian">{{ $garis }}</td>
            </tr>
        </table>

        <div class="paragraf" style="padding-top:9px;">
            selanjutnya disebut sebagai <strong style="color:{{ $navy }};">Pemesan</strong>, selaku pihak
            yang melakukan pendaftaran perjalanan dengan rincian sebagaimana tercantum pada bagian II
            surat ini.
        </div>

        {{-- ============ RINCIAN PENDAFTARAN ============ --}}
        <div class="bagian" style="margin-top:18px;">II. Rincian Pendaftaran</div>
        <div class="rule"></div>

        <table width="100%" cellpadding="0" cellspacing="0" class="identitas" style="margin-top:7px;">
            <tr>
                <td class="kunci">Kode pendaftaran</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->kode }}</td>
            </tr>
            <tr>
                <td class="kunci">Paket perjalanan</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->nama_paket ?: $garis }}</td>
            </tr>
            <tr>
                <td class="kunci">Tanggal berangkat</td>
                <td class="titik">:</td>
                <td class="isi-nilai">
                    {{ $pendaftaran->tanggal_berangkat
                        ? $pendaftaran->tanggal_berangkat->locale('id')->translatedFormat('l, j F Y')
                        : $garis }}
                </td>
            </tr>
            <tr>
                <td class="kunci">Jumlah peserta</td>
                <td class="titik">:</td>
                <td class="isi-nilai">{{ $pendaftaran->jumlah_peserta }} orang</td>
            </tr>
        </table>

        {{-- ============ PESERTA YANG DIGANTI ============
             Satu baris per penggantian, bernomor urut kejadian — sama dengan
             penomoran di kartu riwayat pada layar admin, supaya nomor yang
             disebut di telepon merujuk hal yang sama di kedua tempat. --}}
        <div class="bagian" style="margin-top:18px;">III. Peserta yang Digantikan</div>
        <div class="rule"></div>

        <table width="100%" cellpadding="0" cellspacing="0" class="ganti" style="margin-top:9px;">
            <tr>
                <th width="6%" style="text-align:center;">No</th>
                <th width="27%">Nama Lama</th>
                <th width="27%">Nama Pengganti</th>
                <th width="40%">Titik Jemput</th>
            </tr>

            @foreach ($riwayat as $nomor => $ganti)
                @php
                    $dariTitik = $ganti['dari_titik'] ?? null;
                    $keTitik = $ganti['ke_titik'] ?? null;
                    $titikTetap = filled($dariTitik)
                        && mb_strtolower(trim($dariTitik)) === mb_strtolower(trim((string) $keTitik));
                @endphp
                <tr>
                    <td class="kecil" style="text-align:center;color:#94a3b8;">{{ $nomor + 1 }}</td>
                    <td class="kecil">{{ $ganti['dari'] ?: '—' }}</td>
                    <td class="kecil">{{ $ganti['ke'] ?: '—' }}</td>
                    <td class="kecil">
                        @if (blank($dariTitik) && blank($keTitik))
                            <span class="tetap">tidak dicatat</span>
                        @elseif ($titikTetap)
                            {{ $keTitik }} <span class="tetap">(tetap)</span>
                        @else
                            {{-- Ditulis dengan kata, bukan panah.

                                 Helvetica adalah huruf bawaan PDF dan tidak memuat
                                 U+2192; dompdf mencetaknya sebagai tanda tanya, dan
                                 "Jogja ? Surakarta" di surat bermaterai terbaca
                                 seperti data yang rusak. Menyisipkan huruf ber-Unicode
                                 penuh membengkakkan berkas ~1 MB hanya demi satu
                                 tanda. --}}
                            dari {{ $dariTitik ?: '—' }} ke {{ $keTitik ?: 'belum dipilih' }}
                        @endif
                    </td>
                </tr>

                {{-- Waktu pencatatannya ikut tercetak: surat ini menyatakan sesuatu
                     yang sudah terjadi, dan kapan terjadinya adalah bagian dari
                     yang dinyatakan.

                     Yang mencatat disebut sebagai jabatan, bukan surel orangnya.
                     Berkas ini keluar ke pemesan, dan alamat surel staf bukan
                     miliknya untuk dipegang — sementara yang perlu ia tahu cuma
                     bahwa pencatatnya pihak Orcha. Nama admin yang sebenarnya
                     tetap tersimpan di riwayat dan terbaca di layar admin. --}}
                @if (! empty($ganti['pada']))
                    <tr>
                        <td colspan="4" style="padding:4px 12px;border-top:0;">
                            <span class="tetap">
                                Dicatat {{ \Carbon\Carbon::parse($ganti['pada'])->locale('id')->translatedFormat('j F Y, H:i') }} WIB
                                oleh Admin Orcha Journey
                            </span>
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>

        {{-- ============ PERNYATAAN ============ --}}
        <div class="bagian" style="margin-top:18px;">IV. Isi Pernyataan</div>
        <div class="rule"></div>

        <div class="paragraf" style="padding-top:9px;">
            Dengan ini Pemesan menyatakan dengan sebenar-benarnya, tanpa paksaan dari pihak mana pun,
            hal-hal sebagai berikut:
        </div>

        <div class="pasal-judul">Pasal 1 — Kebenaran Penggantian</div>
        <div class="pasal-isi">
            Seluruh penggantian peserta sebagaimana tercantum pada bagian III surat ini,
            sebanyak {{ count($riwayat) }} penggantian, diajukan atas permintaan Pemesan
            sendiri dan telah diketahui serta disetujui oleh masing-masing peserta yang
            digantikan.
        </div>

        <div class="pasal-judul">Pasal 2 — Biaya Penggantian</div>
        <div class="pasal-isi">
            Penggantian nama peserta tidak dikenakan biaya tambahan sepanjang jumlah peserta tidak
            berubah, sesuai Kebijakan Pengembalian dan Pembatalan Orcha Journey. Perubahan jumlah peserta
            tunduk pada ketentuan penyesuaian tagihan yang berlaku dan diberitahukan tersendiri.
        </div>

        <div class="pasal-judul">Pasal 3 — Kewajiban Peserta Pengganti</div>
        <div class="pasal-isi">
            Peserta pengganti wajib mengisi riwayat kesehatan dengan kode pendaftaran yang sama sebelum
            keberangkatan, dan tunduk pada seluruh ketentuan perjalanan yang berlaku bagi peserta lain,
            termasuk jadwal penjemputan pada titik yang tercantum di atas.
        </div>

        <div class="pasal-judul">Pasal 4 — Kedudukan Data Peserta Lama</div>
        <div class="pasal-isi">
            Data peserta yang digantikan tetap tersimpan sebagai arsip pada sistem Orcha Journey dan tidak
            lagi dihitung sebagai peserta yang berangkat. Riwayat kesehatan yang telah diisi peserta lama
            tidak dihapus, melainkan ditandai sebagai arsip.
        </div>

        <div class="pasal-judul">Pasal 5 — Tanggung Jawab</div>
        <div class="pasal-isi">
            Segala akibat hukum yang timbul dari penggantian peserta ini, termasuk namun tidak terbatas
            pada keberatan dari peserta yang digantikan, menjadi tanggung jawab sepenuhnya Pemesan dan
            membebaskan Orcha Journey dari tuntutan pihak mana pun.
        </div>

        <div class="pasal-judul">Pasal 6 — Keberlakuan</div>
        <div class="pasal-isi">
            Surat pernyataan ini berlaku sejak ditandatangani dan menjadi bagian yang tidak terpisahkan
            dari pendaftaran {{ $pendaftaran->kode }}.
        </div>

        <div class="kotak-dasar" style="margin-top:14px;">
            Surat ini dibuat dalam keadaan sadar dan sehat jasmani rohani, untuk dipergunakan sebagaimana
            mestinya. Apabila di kemudian hari pernyataan ini terbukti tidak benar, Pemesan bersedia
            menanggung segala akibat hukumnya.
        </div>

        {{-- ============ TANDA TANGAN ============
             Dua kolom, bukan tiga.

             Kolom "peserta pengganti" gugur begitu suratnya memuat lebih dari
             satu penggantian: tidak ada satu nama yang pantas ditulis di
             situ. Tanda tangan para pengganti pindah ke tabelnya sendiri di
             bawah, satu baris per orang — bentuk yang sama benarnya untuk satu
             pengganti maupun tujuh. --}}
        <table width="100%" cellpadding="0" cellspacing="0"
            style="margin-top:20px;page-break-inside:avoid;">
            <tr>
                <td colspan="2" align="right" class="ttd-kolom" style="padding-bottom:10px;">
                    {{-- Kotanya diambil dari penggal terakhir alamat perusahaan: tidak ada
                         setelan kota tersendiri, dan menuliskannya tetap di sini berarti
                         surat ikut salah begitu kantornya pindah. --}}
                    {{ trim(last(explode(',', config('orcha.alamat')))) }},
                    {{ $terbit->locale('id')->translatedFormat('j F Y') }}
                </td>
            </tr>
            <tr>
                <td width="50%" valign="top" class="ttd-kolom">
                    <span style="color:#94a3b8;font-size:8.5px;">Pemesan / Pemberi Pernyataan</span>

                    <table cellpadding="0" cellspacing="0" width="118" style="margin-top:8px;">
                        <tr>
                            <td class="materai">
                                Materai<br>Rp10.000
                            </td>
                        </tr>
                    </table>

                    <table cellpadding="0" cellspacing="0" width="170" style="margin-top:10px;">
                        <tr><td class="garis-ttd">&nbsp;</td></tr>
                    </table>
                    <div class="nama-ttd">{{ $pendaftaran->nama ?: $garis }}</div>
                </td>

                <td width="50%" valign="top" align="right" class="ttd-kolom">
                    <span style="color:#94a3b8;font-size:8.5px;">Mengetahui, Orcha Journey</span>

                    <div class="ganjal-materai" style="margin-top:8px;">&nbsp;</div>

                    <table cellpadding="0" cellspacing="0" width="170" align="right" style="margin-top:10px;">
                        <tr><td class="garis-ttd">&nbsp;</td></tr>
                    </table>
                    <div class="nama-ttd">Admin Orcha Journey</div>
                </td>
            </tr>
        </table>

        {{-- ============ TANDA TANGAN PARA PENGGANTI ============
             Satu baris per orang. Kolom tanda tangannya dikosongkan untuk diisi
             tangan; nama terangnya sudah dicetak supaya tidak ada keraguan
             siapa yang menandatangani baris mana. --}}
        <div class="bagian" style="margin-top:30px;page-break-inside:avoid;">
            Persetujuan Peserta Pengganti
        </div>
        <div class="rule"></div>

        <table width="100%" cellpadding="0" cellspacing="0" class="ganti"
            style="margin-top:9px;page-break-inside:avoid;">
            <tr>
                <th width="6%" style="text-align:center;">No</th>
                <th width="42%">Nama Peserta Pengganti</th>
                <th width="52%">Tanda Tangan</th>
            </tr>

            @foreach ($riwayat as $nomor => $ganti)
                <tr>
                    <td class="kecil" style="text-align:center;color:#94a3b8;">{{ $nomor + 1 }}</td>
                    <td class="kecil">{{ $ganti['ke'] ?: $garis }}</td>
                    {{-- Tinggi barisnya dilebihkan lewat padding supaya benar-benar
                         ada ruang menulis; sel setinggi teks tidak bisa ditandatangani. --}}
                    <td style="padding:20px 12px;">&nbsp;</td>
                </tr>
            @endforeach
        </table>

    </div>

    {{-- ============ PITA KAKI ============ --}}
    <div class="kaki-luar">
        <div class="kaki-emas"></div>

        <table width="100%" cellpadding="0" cellspacing="0" class="pita-kaki">
            <tr>
                <td width="30%" valign="middle">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="34" valign="middle">
                                <img src="{{ $logo }}" width="26" height="26" alt="">
                            </td>
                            <td valign="middle" style="padding-left:9px;">
                                <div class="kaki-merek">ORCHA <span>JOURNEY</span></div>
                                <div class="kaki-slogan">{{ config('orcha.slogan') }}</div>
                            </td>
                        </tr>
                    </table>
                </td>

                <td width="40%" valign="middle" align="center" class="kaki-kontak">
                    {{ config('orcha.email') }} &middot; +{{ config('orcha.whatsapp') }}
                    &middot; {{ str_replace(['https://', 'http://'], '', config('app.url')) }}
                    {{-- Berbeda dengan kwitansi: surat ini JUSTRU menunggu tanda
                         tangan basah, jadi kalimat "sah tanpa tanda tangan" akan
                         bertentangan dengan blok materai di atasnya. --}}
                    <div class="kaki-sah">Diterbitkan sistem &mdash; berlaku setelah ditandatangani para pihak</div>
                </td>

                <td width="30%" valign="middle" align="right">
                    <div class="kaki-nomor-label">Nomor Berkas</div>
                    <div class="kaki-nomor">{{ $pendaftaran->kode }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
