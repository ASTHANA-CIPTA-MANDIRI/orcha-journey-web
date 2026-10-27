@extends('pdf.daftar-orcha', [
    'lencana' => 'JANGAN DIBERIKAN KE PELANGGAN — INI HARGA BELI',
    'jenisDok' => 'Master Harga',
    'hitungDok' => count($baris).' harga',
])

@section('judul-berkas', 'Master Harga Orcha Journey')
@section('judul', 'Master Harga')
@section('sub', 'Harga yang kita bayar ke vendor, dipakai saat menghitung biaya trip. Bukan harga yang dibayar pelanggan.')

@php
    // $aman diulang di sini: @section adalah closure atas ruang lingkup
    // berkas ANAK — lihat keterangan panjangnya di pdf/vendor.blade.php.
    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '']);
    $rp = fn ($n) => \App\Support\RincianBiaya::rupiah((int) $n);

    $semua = collect($baris);
    $dipakai = $semua->filter(fn ($m) => ($m['jumlah_rab'] ?? 0) > 0)->count();
    $tanpaVendor = $semua->filter(fn ($m) => empty($m['vendor']))->count();
    $mati = $semua->where('aktif', false)->count();
    $lewat = $semua->filter(fn ($m) => ! empty($m['kedaluwarsa']))->count();

    /*
     | Warna per kategori, sepasang dengan layar Master Harga di lemon.
     | Berkas dan layar yang memberi warna berbeda untuk hal yang sama
     | membuat keduanya harus dihafal terpisah.
     */
    $rupaKategori = [
        'Transportasi' => '#0369a1', 'Akomodasi' => '#6d28d9', 'Konsumsi' => '#e0912f',
        'Tiket & Retribusi' => '#1f7a44', 'Pemandu & Kru' => '#0e7490',
        'Asuransi Perjalanan' => '#be123c', 'Dokumentasi' => '#b45309',
        'Operasional Tim' => '#4f46e5', 'Konsumsi & Rokok Sopir' => '#a16207',
    ];
    $warnaKategori = fn ($k) => $rupaKategori[$k] ?? '#64748b';

    $kelompok = $semua->groupBy(fn ($m) => $m['kategori_label'] ?: 'Lain-lain')->sortKeys();
@endphp

@section('ringkas')
    <table class="ubin"><tr>
        <td><div class="ubin-isi">
            <div class="ubin-label">Jumlah harga</div>
            <div class="ubin-nilai">{{ $semua->count() }}</div>
            <div class="ubin-ket">dari {{ $kelompok->count() }} kategori</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:#15803d">
            <div class="ubin-label">Sudah pernah dipakai</div>
            <div class="ubin-nilai" style="color:#15803d">{{ $dipakai }}</div>
            <div class="ubin-ket">{{ $semua->count() - $dipakai }} belum pernah</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:{{ $tanpaVendor ? '#b42318' : '#15803d' }}">
            <div class="ubin-label">Belum punya vendor</div>
            <div class="ubin-nilai" style="color:{{ $tanpaVendor ? '#b42318' : '#15803d' }}">{{ $tanpaVendor }}</div>
            <div class="ubin-ket">{{ $tanpaVendor ? 'tidak ada nomor yang bisa dihubungi' : 'semuanya ada nomornya' }}</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:#ffc74e">
            <div class="ubin-label">Sudah dimatikan</div>
            <div class="ubin-nilai">{{ $mati }}</div>
            <div class="ubin-ket">tidak muncul lagi saat membuat RAB</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:{{ $lewat ? '#b42318' : '#15803d' }}">
            <div class="ubin-label">Masa berlakunya lewat</div>
            <div class="ubin-nilai" style="color:{{ $lewat ? '#b42318' : '#15803d' }}">{{ $lewat }}</div>
            <div class="ubin-ket">{{ $lewat ? 'perlu ditanyakan ulang ke vendor' : 'tidak ada yang lewat' }}</div>
        </div></td>
    </tr></table>
@endsection

@section('gaya')
    /* Merah, bukan abu-abu seperti MATI: yang satu keputusan yang memang
       diambil, yang satu tanggal yang terlewat tanpa ada yang tahu. */
    .pil-lewat {
        display: inline-block; background-color: #fee2e2; color: #b42318;
        font-size: 7px; font-weight: bold; letter-spacing: .6px; padding: 1px 6px; margin-left: 5px;
    }
@endsection

@section('isi')
    @if ($baris === [])
        <div class="nihil">Tidak ada harga yang cocok dengan pilihan saringan di atas.</div>
    @else
        <table class="daftar">
            <thead>
                <tr>
                    <th>Nama harga</th>
                    <th style="width:104px">Berlaku di</th>
                    <th style="width:112px">Dibeli dari</th>
                    <th style="width:96px" class="kanan">Harga</th>
                    <th style="width:58px" class="tengah">Dipakai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kelompok as $namaKategori => $isi)
                    <tr class="pita">
                        <td colspan="3" style="border-left:3px solid {{ $warnaKategori($namaKategori) }}">
                            <span class="pita-nama" style="color:{{ $warnaKategori($namaKategori) }}">{{ $aman($namaKategori) }}</span>
                            <span class="pita-hitung">&nbsp; {{ $isi->count() }} harga</span>
                        </td>
                        <td class="kanan pita-hitung">termurah {{ $rp($isi->min('harga')) }}</td>
                        <td class="tengah pita-hitung">{{ $isi->sum('jumlah_rab') }}&times;</td>
                    </tr>

                    @foreach ($isi as $m)
                        <tr class="{{ $m['aktif'] ? '' : 'mati' }}">
                            <td>
                                <div class="nama">
                                    {{ $aman($m['nama']) }}
                                    @unless ($m['aktif'])<span class="pil-mati">MATI</span>@endunless
                                    @if (! empty($m['kedaluwarsa']))<span class="pil-lewat">LEWAT</span>@endif
                                </div>
                                @if (! empty($m['destinasi']))
                                    <div class="meta">Tiket untuk {{ $aman($m['destinasi']) }}</div>
                                @elseif (! empty($m['catatan']))
                                    <div class="meta">{{ $aman($m['catatan']) }}</div>
                                @endif
                                @if (! empty($m['berlaku_sampai_teks']))
                                    <div class="meta">Berlaku sampai {{ $m['berlaku_sampai_teks'] }}</div>
                                @endif
                            </td>
                            {{-- Kosong berarti berlaku di provinsi mana saja, bukan
                                 belum diisi — ditulis apa adanya supaya tidak
                                 terbaca sebagai lubang data. --}}
                            <td>{{ $m['provinsi'] ?: 'Semua provinsi' }}</td>
                            <td>
                                @if (! empty($m['vendor']))
                                    {{ $aman($m['vendor']) }}
                                @else
                                    <span class="kosong-nomor">belum ada</span>
                                @endif
                            </td>
                            <td class="kanan">
                                <span class="nomor">{{ $m['harga_teks'] }}</span>
                                <div class="meta">{{ $aman($m['satuan_label']) }}@if ($m['kapasitas']) · muat {{ $m['kapasitas'] }}@endif</div>
                            </td>
                            <td class="tengah">
                                @if ($m['jumlah_rab'])
                                    <span class="hitung-pakai">{{ $m['jumlah_rab'] }}&times;</span>
                                @else
                                    <span class="belum-pakai">belum</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="legenda">
            <div class="legenda-judul">Cara membaca daftar ini</div>
            <table>
                <tr>
                    <td class="kata">Kolom "Dipakai"</td>
                    <td>Berapa RAB (rincian biaya trip) yang sudah memakai harga ini. Kalau harganya diubah di sini, RAB yang sudah terlanjur dibuat TIDAK ikut berubah — angkanya sudah tersimpan di RAB itu. Yang ikut harga baru hanya RAB yang dibuat sesudahnya.</td>
                </tr>
                <tr>
                    <td class="kata">Kolom "Dibeli dari"</td>
                    <td>Nama vendor tempat kita membelinya. "belum ada" berarti harganya sudah dicatat, tetapi tidak ada nomor yang bisa dihubungi saat menyiapkan keberangkatan.</td>
                </tr>
                <tr>
                    <td class="kata">Tanda LEWAT</td>
                    <td>Tanggal berlakunya sudah terlampaui. Harganya masih dipakai apa adanya saat membuat RAB — tanda ini cuma mengingatkan untuk menanyakan ulang ke vendornya.</td>
                </tr>
                <tr>
                    <td class="kata">Tanda MATI</td>
                    <td>Harga ini sengaja disimpan tetapi tidak ditawarkan lagi saat membuat RAB baru. RAB lama yang sudah memakainya tetap utuh.</td>
                </tr>
            </table>
        </div>
    @endif
@endsection
