@extends('pdf.daftar-orcha', [
    'lencana' => 'JANGAN DIBAGIKAN — BERISI NOMOR MITRA',
    'jenisDok' => 'Daftar Vendor',
    'hitungDok' => count($baris).' vendor',
])

@section('judul-berkas', 'Daftar Vendor Orcha Journey')
@section('judul', 'Daftar Vendor')
@section('sub', 'Siapa saja mitra kita, dan ke nomor mana meneleponnya.')

@php
    /*
     | $aman diulang di sini, tidak cukup dideklarasikan di tata letaknya.
     |
     | @section adalah closure atas ruang lingkup berkas ANAK; variabel yang
     | lahir di @php tata letak baru ada saat tata letaknya dirender, yaitu
     | SESUDAH seluruh section dikumpulkan. Gejalanya "Undefined variable
     | $aman" yang menunjuk berkas anak padahal yang dibaca milik induknya.
     */
    $aman = fn ($t) => strtr((string) $t, ['→' => '->', '←' => '<-', '−' => '-', '✓' => '']);

    $semua = collect($baris);
    $aktif = $semua->where('aktif', true)->count();
    $tanpaNomor = $semua->filter(fn ($v) => empty($v['kontak']))->count();
    $wilayah = $semua->pluck('provinsi')->filter()->unique()->count();

    /*
     | Warna per jenis, sepasang dengan layar Vendor di lemon. Berkas dan
     | layar yang memberi warna berbeda untuk hal yang sama membuat keduanya
     | harus dihafal terpisah.
     */
    $rupaJenis = [
        'Armada' => '#0369a1', 'Akomodasi' => '#6d28d9', 'Konsumsi' => '#e0912f',
        'Tiket' => '#1f7a44', 'Pemandu' => '#0e7490',
    ];
    $warnaJenis = fn ($j) => $rupaJenis[$j] ?? '#64748b';

    // Yang belum berjenis dikumpulkan di akhir: ia bukan kategori,
    // melainkan yang belum sempat dikategorikan.
    $kelompok = $semua->groupBy(fn ($v) => $v['jenis'] ?: 'Belum berjenis')
        ->sortBy(fn ($isi, $nama) => ($nama === 'Belum berjenis' ? 'zzz' : $nama));
@endphp

@section('ringkas')
    <table class="ubin"><tr>
        <td><div class="ubin-isi">
            <div class="ubin-label">Jumlah vendor</div>
            <div class="ubin-nilai">{{ $semua->count() }}</div>
            <div class="ubin-ket">dari {{ $kelompok->count() }} jenis</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:#15803d">
            <div class="ubin-label">Masih dipakai</div>
            <div class="ubin-nilai" style="color:#15803d">{{ $aktif }}</div>
            <div class="ubin-ket">{{ $semua->count() - $aktif }} sudah tidak dipakai</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:{{ $tanpaNomor ? '#b42318' : '#15803d' }}">
            <div class="ubin-label">Belum ada nomornya</div>
            <div class="ubin-nilai" style="color:{{ $tanpaNomor ? '#b42318' : '#15803d' }}">{{ $tanpaNomor }}</div>
            <div class="ubin-ket">{{ $tanpaNomor ? 'perlu dilengkapi' : 'semua sudah ada' }}</div>
        </div></td>
        <td><div class="ubin-isi" style="border-top-color:#ffc74e">
            <div class="ubin-label">Daerah layanan</div>
            <div class="ubin-nilai">{{ $wilayah }}</div>
            <div class="ubin-ket">selain yang bisa di mana saja</div>
        </div></td>
    </tr></table>
@endsection

@section('isi')
    @if ($baris === [])
        <div class="nihil">Tidak ada vendor yang cocok dengan saringannya.</div>
    @else
        <table class="daftar">
            <thead>
                <tr>
                    <th>Nama vendor</th>
                    <th style="width:116px">Melayani daerah</th>
                    <th style="width:142px">Nomor yang dihubungi</th>
                    <th style="width:62px" class="tengah">Dipakai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kelompok as $namaJenis => $isi)
                    <tr class="pita">
                        <td colspan="3" style="border-left:3px solid {{ $warnaJenis($namaJenis) }}">
                            <span class="pita-nama" style="color:{{ $warnaJenis($namaJenis) }}">{{ $aman($namaJenis) }}</span>
                            <span class="pita-hitung">&nbsp; {{ $isi->count() }} vendor</span>
                        </td>
                        <td class="tengah pita-hitung">
                            @if ($isi->sum('jumlah_harga')){{ $isi->sum('jumlah_harga') }}&times;@else belum dipakai @endif
                        </td>
                    </tr>

                    @foreach ($isi as $v)
                        <tr class="{{ $v['aktif'] ? '' : 'mati' }}">
                            <td>
                                <div class="nama">
                                    {{ $aman($v['nama']) }}
                                    @unless ($v['aktif'])<span class="pil-mati">MATI</span>@endunless
                                </div>
                                @if (! empty($v['catatan']))
                                    <div class="meta">{{ $aman($v['catatan']) }}</div>
                                @endif
                            </td>
                            {{-- Kosong berarti berlaku di mana saja, bukan belum
                                 diisi — ditulis apa adanya supaya tidak terbaca
                                 sebagai lubang data. --}}
                            <td>{{ $v['provinsi'] ?: 'Semua wilayah' }}</td>
                            <td>
                                @if (! empty($v['kontak']))
                                    <span class="nomor">{{ $aman($v['kontak']) }}</span>
                                @else
                                    <span class="kosong-nomor">belum ada nomornya</span>
                                @endif
                            </td>
                            <td class="tengah">
                                @if ($v['jumlah_harga'])
                                    <span class="hitung-pakai">{{ $v['jumlah_harga'] }}&times;</span>
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
                    <td>Berapa harga di Master Harga yang dibeli dari vendor ini. "belum" berarti vendornya sudah terdaftar tapi belum dipasang ke harga mana pun.</td>
                </tr>
                <tr>
                    <td class="kata">Semua wilayah</td>
                    <td>Vendornya bisa dipakai untuk trip di provinsi mana saja — bukan berarti kolomnya lupa diisi.</td>
                </tr>
                <tr>
                    <td class="kata">Tanda MATI</td>
                    <td>Vendornya sudah tidak dipakai lagi untuk harga baru. Nomornya tetap bisa dilihat di RAB lama yang terlanjur memakainya.</td>
                </tr>
            </table>
        </div>
    @endif
@endsection
