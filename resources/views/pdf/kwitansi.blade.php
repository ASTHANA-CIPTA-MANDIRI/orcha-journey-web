{{-- Berkas resmi yang dilampirkan di surat pemberitahuan: tagihan
     pendaftaran, tanda terima pembayaran, dan tanda terima pengajuan
     pembatalan — ketiganya memakai kerangka ini.

     Dompdf tidak mengenal flexbox maupun grid, jadi tata letaknya memakai
     tabel. Yang juga perlu diingat: seluruh gaya harus menempel (inline) atau
     berada di <style> dokumen ini sendiri, karena berkas ini dirender di
     server tanpa aset Vite sama sekali. --}}
@php
    $navy = '#0f2d4a';
    $ocean = '#1d6fa5';
    $langit = '#7fb4d6';
    $emas = '#ffc74e';
    $kabut = '#f4f8fb';
    $logo = public_path('orcha-logo-surat.png');

    // Stempel dan tanda tangan bersifat pilihan: begitu berkasnya ditaruh di
    // public/, keduanya langsung ikut tercetak. Selama belum ada, blok tanda
    // tangan tetap tampil rapi memakai logo — berkas tetap sah karena
    // diterbitkan sistem, dan itu memang dinyatakan di bagian bawahnya.
    $stempel = file_exists(public_path('orcha-stempel.png')) ? public_path('orcha-stempel.png') : null;
    $ttd = file_exists(public_path('orcha-ttd.png')) ? public_path('orcha-ttd.png') : null;

    // Tabel biaya hanya dipakai berkas pendaftaran; tanda terima pembayaran
    // dan pembatalan memanggil tanpa itu.
    $biaya = $biaya ?? [];

    // Posisi tagihan: dipakai tanda terima pembayaran. Pertanyaan pertama
    // pelanggan sesudah mentransfer bukan "berapa yang saya kirim" — itu sudah
    // ia tahu — melainkan "berarti sisa saya berapa". Tanpa blok ini, berkasnya
    // hanya mengulang angka yang baru saja ia ketik sendiri.
    $tagihan = $tagihan ?? [];

    // Daftar bebas baris biaya + denda, ditutup satu baris total. Dipakai nota
    // sewa kendaraan, yang tagihannya tersusun dari beberapa hal sekaligus.
    $nota = $nota ?? [];
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $judul }} — {{ $kode }}</title>
    <style>
        /* Ruang bawah dilebihkan untuk pita kaki yang dipasang mengambang. */
        @page { margin: 0 0 68px; }

        /* Helvetica adalah huruf bawaan PDF: tidak ikut disisipkan ke berkasnya.
           Memakai DejaVu membuat berkas membengkak ~1 MB hanya untuk hurufnya,
           padahal lampiran surat sebaiknya ringan. Hierarki dibangun lewat
           ukuran, ketebalan, jarak huruf, dan warna — bukan lewat ragam huruf. */
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #475569;
               margin: 0; padding: 0; }

        .isi { padding: 0 34px 10px; }

        /* ---------- kepala ---------- */
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
        .judul { font-size: 21px; font-weight: bold; color: {{ $navy }}; padding-top: 3px; }
        .terbit { font-size: 9px; color: #94a3b8; padding-top: 4px; }
        .panel-nomor { background-color: {{ $kabut }}; border: 1px solid #dbe7f0; padding: 8px 14px; }
        .kode { font-family: Courier, monospace; font-size: 13.5px; font-weight: bold;
                color: {{ $navy }}; letter-spacing: .5px; }

        /* ---------- label & nilai ---------- */
        .label { font-size: 8px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        .nilai { font-size: 11.5px; color: {{ $navy }}; font-weight: bold; }
        .bagian { font-size: 8.5px; letter-spacing: 2px; text-transform: uppercase;
                  color: {{ $ocean }}; font-weight: bold; }
        .rule { height: 2px; width: 30px; background-color: {{ $emas }}; font-size: 0; line-height: 0;
                margin-top: 5px; }

        /* ---------- kotak jumlah ----------
           Tulang emasnya dibuat dari sel tersendiri, bukan border-left:
           dompdf memotong border pada tabel setinggi barisnya saja. */
        .kotak-jumlah td { background-color: {{ $kabut }}; padding: 13px 18px; }
        .kotak-jumlah td.tulang { background-color: {{ $emas }}; font-size: 0; line-height: 0; padding: 0; }
        .angka-besar { font-size: 23px; font-weight: bold; color: {{ $navy }}; letter-spacing: -.3px; }
        .cap { border: 2px solid {{ $ocean }}; color: {{ $ocean }}; font-size: 11px; font-weight: bold;
               padding: 7px 15px; text-transform: uppercase; letter-spacing: 1.4px; }

        /* ---------- baris rincian ---------- */
        .baris td { padding: 5px 12px; }
        .zebra { background-color: #fafcfd; }

        /* ---------- biaya ---------- */
        .biaya { border-collapse: collapse; }
        .biaya td { padding: 7px 12px; border-bottom: 1px solid #e9eff5; }
        .total td { background-color: {{ $navy }}; color: #fff; padding: 9px 12px; border-bottom: none; }
        .total-teks { font-size: 11px; letter-spacing: 1.4px; text-transform: uppercase; color: {{ $langit }}; }
        .total-angka { font-size: 17px; font-weight: bold; color: #fff; }
        .tempo { font-size: 8.5px; color: #94a3b8; padding-top: 2px; }

        /* ---------- kotak keterangan ---------- */
        .kotak-catatan { background-color: {{ $kabut }}; border-left: 3px solid {{ $ocean }}; padding: 11px 15px; }
        .kotak-bayar { border: 1px solid #dbe7f0; }
        .kepala-bayar { background-color: {{ $navy }}; color: #fff; padding: 8px 16px;
                        font-size: 8.5px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
        .kotak-nama { background-color: {{ $kabut }}; border: 1px solid #cfe4f2; }
        .kotak-nama td { padding: 9px 13px; }
        .nama-penerima { font-size: 12.5px; font-weight: bold; color: {{ $navy }};
                         letter-spacing: .4px; padding-top: 2px; }
        .nomor-langkah { background-color: {{ $ocean }}; color: #fff; font-size: 8px; font-weight: bold;
                         text-align: center; width: 14px; height: 14px; }
        .kotak-awas { background-color: #fff5f5; }
        .kotak-awas td { padding: 8px 12px; font-size: 9px; line-height: 1.55; color: #b91c1c;
                         border-left: 3px solid #dc2626; }

        /* ---------- kaki ---------- */
        .kaki { font-size: 8.5px; color: #94a3b8; line-height: 1.7; }

        /* ---------- pita kaki ----------
           Mengambang di dasar halaman, jadi tingginya harus tetap: apa pun
           panjang daftar pesertanya, pitanya berada di tempat yang sama.
           Garis emas tipis di atasnya menutup halaman seperti kop menutup
           bagian atasnya — berkasnya jadi berbingkai, bukan menggantung. */
        /* Ruang bawah halaman dipakai isinya sebagai batas berhenti, tetapi
           pitanya sendiri harus menempel ke tepi kertas. Karena dompdf
           mengukur bottom dari tepi DALAM margin, offsetnya dibuat negatif
           sebesar margin itu — kalau tidak, pitanya mengambang dengan pias
           putih di bawahnya dan berkasnya terlihat belum selesai. */
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

    {{-- ============ KEPALA ============ --}}
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

    <div class="isi">

        {{-- ============ JUDUL & NOMOR ============
             Nomornya dikotakkan tersendiri, bukan sekadar teks rata kanan:
             itu keterangan pertama yang dicari orang saat menyusun berkas. --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
            <tr>
                <td valign="top">
                    <div class="jenis">Dokumen Resmi</div>
                    <div class="judul">{{ $judul }}</div>
                    <div class="terbit">Diterbitkan {{ now()->translatedFormat('j F Y, H:i') }} WIB</div>
                </td>
                <td align="right" valign="top" width="34%">
                    <table cellpadding="0" cellspacing="0" align="right" class="panel-nomor">
                        <tr>
                            <td align="right">
                                <div class="label">Nomor</div>
                                <div class="kode">{{ $kode }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ============ ANGKA UTAMA ============
             Yang paling dicari mata lebih dulu: berapa, dan statusnya apa. --}}
        @if (! empty($jumlah))
            <table width="100%" cellpadding="0" cellspacing="0" class="kotak-jumlah" style="margin-top:16px;">
                <tr>
                    <td width="4" class="tulang">&nbsp;</td>
                    <td valign="middle">
                        <div class="label">{{ $jumlahLabel ?? 'Jumlah diterima' }}</div>
                        <div class="angka-besar">{{ $jumlah }}</div>
                    </td>
                    <td align="right" valign="middle">
                        <span class="cap">{{ $capStatus ?? 'Diterima' }}</span>
                    </td>
                </tr>
            </table>
        @endif

        {{-- ============ KEADAAN PEMBAYARAN ============
             Satu kalimat yang menyebutkan posisi pembayaran apa adanya.

             Angka besar di atas menjawab "berapa", tapi tidak menjawab "lalu
             saya harus apa". Pelanggan yang sudah membayar DP membaca angka
             sisa lalu bertanya apakah DP-nya sudah masuk; yang belum membayar
             sama sekali membaca angka yang sama tanpa tahu itu uang muka atau
             seluruhnya. Kalimatnya yang menjawab. --}}
        @if (! empty($keadaan['kalimat']))
            @php
                $warnaKeadaan = match ($keadaan['nada'] ?? 'netral') {
                    'aman' => ['#eafaf1', '#b7e4cd', '#0b7a4b'],
                    'awas' => ['#fff4e0', '#f3ddb0', '#8a5a09'],
                    default => ['#f4f8fb', '#dbe6f0', '#0f2d4a'],
                };
            @endphp
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
                <tr>
                    <td style="background-color:{{ $warnaKeadaan[0] }};border:1px solid {{ $warnaKeadaan[1] }};
                        border-radius:8px;padding:11px 14px;font-size:10.5px;line-height:1.6;
                        color:{{ $warnaKeadaan[2] }};">
                        {!! $keadaan['kalimat'] !!}
                    </td>
                </tr>
            </table>
        @endif

        {{-- ============ RINCIAN ============
             Berselang-seling supaya mata tidak melompat baris saat membaca
             daftar panjang berisi nama peserta. --}}
        <div style="margin-top:18px;">
            <div class="bagian">Rincian</div>
            <div class="rule"></div>
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:7px;">
            @foreach (collect($rincian)->filter(fn ($n) => filled($n)) as $label => $nilai)
                <tr class="baris {{ $loop->index % 2 === 1 ? 'zebra' : '' }}">
                    <td width="38%" valign="top"><span class="label">{{ $label }}</span></td>
                    <td align="right" valign="top"><span class="nilai">{!! nl2br(e($nilai)) !!}</span></td>
                </tr>
            @endforeach
        </table>

        {{-- ============ RINCIAN BIAYA ============
             Pertanyaan pertama pelanggan setelah mendaftar selalu sama: berapa
             yang harus ditransfer sekarang, dan berapa sisanya. Angkanya
             dipecah sampai terlihat asal-usulnya — harga satuan dikali jumlah
             orang — supaya tidak ada yang merasa ditagih angka yang tiba-tiba
             muncul. Barisnya berhenti di baris total berlatar gelap: satu
             titik henti yang jelas sebelum masuk ke tenggat pembayaran. --}}
        @if (! empty($biaya))
            <div style="margin-top:16px;">
                <div class="bagian">Rincian Biaya</div>
                <div class="rule"></div>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" class="biaya" style="margin-top:7px;">
                <tr>
                    <td>Harga paket per orang</td>
                    <td align="right"><span class="nilai">{{ $biaya['satuan_teks'] }}</span></td>
                </tr>
                <tr>
                    <td>Jumlah peserta</td>
                    <td align="right"><span class="nilai">&times; {{ $biaya['orang'] }} orang</span></td>
                </tr>
                <tr class="total">
                    <td><span class="total-teks">Total biaya</span></td>
                    <td align="right"><span class="total-angka">{{ $biaya['total_teks'] }}</span></td>
                </tr>
                {{-- Tenggat DP dan pelunasan hanya dicetak selama pembayarannya
                     memang masih ditunggu. Pada pesanan yang sudah lunas — atau
                     yang sudah dibatalkan — dua baris ini menagih uang yang tidak
                     perlu lagi dibayar, dan itu berkas yang dipegang pelanggan. --}}
                @if ($biaya['tempo'] ?? true)
                <tr>
                    <td style="padding-top:11px;">
                        <strong style="color:{{ $ocean }};font-size:11.5px;">
                            DP {{ $biaya['dp_persen'] }}% &mdash; dibayar sekarang
                        </strong>
                        <div class="tempo">paling lambat {{ $biaya['dp_batas_jam'] }} jam sejak pendaftaran</div>
                    </td>
                    <td align="right" valign="top" style="padding-top:11px;">
                        <strong style="color:{{ $ocean }};font-size:14px;">{{ $biaya['dp_teks'] }}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border-bottom:none;">
                        Sisa pelunasan
                        <div class="tempo">paling lambat H-{{ $biaya['pelunasan_hari'] }} sebelum berangkat</div>
                    </td>
                    <td align="right" valign="top" style="border-bottom:none;">
                        <span class="nilai">{{ $biaya['sisa_teks'] }}</span>
                    </td>
                </tr>
                @endif
            </table>
        @endif

        {{-- ============ NOTA ============
             Daftar bebas berisi baris biaya dan denda, ditutup satu baris total.
             Sebelumnya denda hanya jadi baris keterangan di antara data lain dan
             tidak pernah dijumlahkan, jadi penyewa harus menjumlahkan sendiri
             dari nota yang seharusnya menjawab itu. --}}
        @if (! empty($nota))
            <div style="margin-top:16px;">
                <div class="bagian">Rincian Tagihan</div>
                <div class="rule"></div>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" class="biaya" style="margin-top:7px;">
                @foreach ($nota['baris'] ?? [] as $baris)
                    <tr>
                        <td>
                            {{ $baris['label'] }}
                            @if (! empty($baris['keterangan']))
                                <div class="tempo">{{ $baris['keterangan'] }}</div>
                            @endif
                        </td>
                        <td align="right" valign="top">
                            <span class="nilai" style="{{ ! empty($baris['denda']) ? 'color:#b91c1c;' : '' }}">
                                {{ $baris['nilai'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach

                <tr class="total">
                    <td><span class="total-teks">{{ $nota['label_total'] ?? 'Total tagihan' }}</span></td>
                    <td align="right"><span class="total-angka">{{ $nota['total'] }}</span></td>
                </tr>
            </table>
        @endif

        {{-- ============ POSISI TAGIHAN ============
             Dipakai tanda terima pembayaran. Pertanyaan pertama pelanggan
             sesudah mentransfer bukan "berapa yang saya kirim" — itu sudah ia
             tahu — melainkan "berarti sisa saya berapa". Tanpa blok ini,
             berkasnya hanya mengulang angka yang baru saja ia ketik sendiri. --}}
        @if (! empty($tagihan))
            <div style="margin-top:16px;">
                <div class="bagian">Posisi Tagihan</div>
                <div class="rule"></div>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" class="biaya" style="margin-top:7px;">
                <tr>
                    <td>Total tagihan</td>
                    <td align="right"><span class="nilai">{{ $tagihan['total_teks'] }}</span></td>
                </tr>
                <tr>
                    <td>
                        Sudah dilaporkan masuk
                        <div class="tempo">termasuk pembayaran ini, sebelum dicek tim</div>
                    </td>
                    <td align="right" valign="top">
                        <span class="nilai" style="color:{{ $ocean }};">{{ $tagihan['sudah_teks'] }}</span>
                    </td>
                </tr>
                <tr class="total">
                    <td><span class="total-teks">{{ $tagihan['lunas'] ? 'Lunas' : 'Sisa pembayaran' }}</span></td>
                    <td align="right">
                        <span class="total-angka">{{ $tagihan['lunas'] ? 'Rp 0' : $tagihan['sisa_teks'] }}</span>
                    </td>
                </tr>
            </table>
        @endif

        @if (! empty($catatan))
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;">
                <tr>
                    <td class="kotak-catatan">
                        <div class="label" style="color:{{ $ocean }};">Catatan</div>
                        <div style="font-size:10.5px;color:#475569;padding-top:3px;">{!! nl2br(e($catatan)) !!}</div>
                    </td>
                </tr>
            </table>
        @endif

        {{-- ============ CARA PEMBAYARAN & TANDA TANGAN ============
             Nomor rekeningnya sengaja tidak dicetak. Berkas seperti ini paling
             gampang disalin penipu lalu diedarkan dengan rekening lain; yang
             dicantumkan cukup nama penerima, karena nama itulah yang bisa
             dicocokkan sendiri oleh pelanggan di ATM sebelum menekan kirim. --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
            <tr>
                {{-- Cara pembayaran dilewati untuk pesanan yang tidak lagi menunggu
                     uang masuk. Petunjuk transfer di berkas milik pesanan lunas
                     membuat pelanggan mengira masih ada yang kurang. --}}
                @if ($caraBayar ?? true)
                <td width="60%" valign="top">
                    <table width="100%" cellpadding="0" cellspacing="0" class="kotak-bayar">
                        <tr>
                            {{-- Judulnya duduk di pita biru tua, bukan sekadar teks kecil
                                 di dalam kotak: bagian ini yang paling sering dibaca
                                 ulang sebelum orang menekan kirim di aplikasi banknya. --}}
                            <td class="kepala-bayar">
                                {{ ! empty($biaya) ? 'Cara Pembayaran' : 'Perlu Diperhatikan' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px 15px;">

                                {{-- Nama penerima ditaruh di kotaknya sendiri. Inilah
                                     satu-satunya hal yang bisa dicocokkan pelanggan di
                                     layar ATM sebelum uangnya berpindah, jadi tidak
                                     boleh tenggelam di tengah paragraf. --}}
                                <table width="100%" cellpadding="0" cellspacing="0" class="kotak-nama">
                                    <tr>
                                        <td>
                                            <div class="label" style="color:{{ $ocean }};">
                                                Satu-satunya nama penerima yang sah
                                            </div>
                                            <div class="nama-penerima">{{ config('orcha.pembayaran.atas_nama') }}</div>
                                        </td>
                                    </tr>
                                </table>

                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:11px;">
                                    @php
                                        $langkah = ! empty($biaya)
                                            ? [
                                                'Transfer bank — tidak ada cara pembayaran lain.',
                                                'Nomor rekening dikirim tim kami lewat WhatsApp.',
                                                'Setelah transfer, unggah buktinya lewat halaman Konfirmasi Pembayaran.',
                                            ]
                                            : [
                                                'Pembayaran hanya sah ke nama di atas.',
                                                'Simpan berkas ini sampai perjalanan selesai.',
                                            ];
                                    @endphp

                                    @foreach ($langkah as $urut => $satu)
                                        <tr>
                                            <td width="17" valign="top" class="nomor-langkah">{{ $urut + 1 }}</td>
                                            <td valign="top" style="padding:0 0 6px 7px;font-size:10px;line-height:1.55;">
                                                {{ $satu }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>

                                <table width="100%" cellpadding="0" cellspacing="0" class="kotak-awas" style="margin-top:9px;">
                                    <tr>
                                        <td>
                                            Nama penerima selain itu <strong>bukan kami</strong> — termasuk rekening
                                            pribadi yang mengatasnamakan Orcha Journey.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
                @endif

                <td align="center" valign="top" style="font-size:9px;color:#94a3b8;padding-left:18px;">
                    Hormat kami,

                    {{-- Stempel dan tanda tangan ditumpuk: stempel jadi latar,
                         tanda tangan di atasnya — seperti dokumen yang dicap lalu
                         ditandatangani, bukan dua gambar berjajar. --}}
                    <div style="position:relative;height:70px;margin-top:2px;">
                        @if ($stempel)
                            <img src="{{ $stempel }}" width="74" height="74" alt=""
                                style="position:absolute;top:0;left:50%;margin-left:-37px;opacity:.85;">
                        @else
                            <img src="{{ $logo }}" width="44" height="44" alt=""
                                style="position:absolute;top:22px;left:50%;margin-left:-22px;">
                        @endif

                        @if ($ttd)
                            <img src="{{ $ttd }}" width="100" alt=""
                                style="position:absolute;top:20px;left:50%;margin-left:-50px;">
                        @endif
                    </div>

                    <div style="border-top:1px solid #cbd5e1;width:150px;margin:0 auto;padding-top:5px;">
                        <strong style="color:{{ $navy }};font-size:10px;">Orcha Journey</strong><br>
                        <span style="font-size:8px;">{{ config('orcha.pembayaran.atas_nama') }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============ PITA KAKI ============
         Dipasang mengambang supaya tetap menempel di dasar halaman, berapa pun
         panjang daftar pesertanya.

         Isinya bukan sekadar hiasan: berkas begini sering dicetak lalu
         berpindah tangan lepas dari surelnya, jadi kontak dan nomor berkasnya
         harus ikut di lembar yang sama. Nomornya dicetak keemasan di kanan —
         itu yang dicari orang lebih dulu saat berkasnya ditanyakan. --}}
    <div class="kaki-luar">
        <div class="kaki-emas"></div>

        {{-- Lebar ketiga kolomnya ditetapkan 30-40-30 dan tata letaknya dikunci.
             Kalau lebarnya dibiarkan mengikuti isi, titik tengah kolom tengah
             ikut bergeser mengikuti panjang nama merek di kiri dan nomor berkas
             di kanan — teksnya "rata tengah" terhadap kolomnya sendiri, tetapi
             miring terhadap halamannya. Dengan sisi kiri dan kanan sama lebar,
             titik tengah kolom tengah jatuh persis di tengah kertas. --}}
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
                                {{-- Slogannya, bukan nama kota: kotanya sudah tersebut di
                                     alamat pada kop, dan yang pantas mendampingi nama merek
                                     di kaki halaman adalah janji layanannya. --}}
                                <div class="kaki-slogan">{{ config('orcha.slogan') }}</div>
                            </td>
                        </tr>
                    </table>
                </td>

                <td width="40%" valign="middle" align="center" class="kaki-kontak">
                    {{ config('orcha.email') }} &middot; +{{ config('orcha.whatsapp') }}
                    &middot; {{ str_replace(['https://', 'http://'], '', config('app.url')) }}
                    {{-- Keterangan keabsahan ikut di pita, bukan berdiri sendiri di
                         badan berkas: kalimatnya baku dan berlaku untuk semua
                         berkas, jadi tempatnya memang di kaki halaman. --}}
                    <div class="kaki-sah">Dibuat otomatis oleh sistem &mdash; sah tanpa tanda tangan basah</div>
                </td>

                <td width="30%" valign="middle" align="right">
                    <div class="kaki-nomor-label">Nomor Berkas</div>
                    <div class="kaki-nomor">{{ $kode }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
