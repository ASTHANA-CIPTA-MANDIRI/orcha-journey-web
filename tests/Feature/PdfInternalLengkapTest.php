<?php

use App\Models\Rab\MasterHarga;
use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RabItinerary;
use App\Models\Rab\Vendor;
use App\Support\Rab\BerkasRab;

/**
 * Yang hanya ada di layar tidak ikut ke lembar yang dibawa.
 *
 * Pola yang berulang tiga kali: syarat vendor, hari pemakaian, dan peringatan
 * harga nol semuanya dikerjakan untuk LAYAR, lalu tidak ikut dibawa ke BERKAS
 * CETAK. Padahal berkas internal itulah yang dicetak dan dibaca di tempat yang
 * tidak ada layarnya.
 *
 * Arah sebaliknya juga dijaga: syarat vendor dan nomor telepon adalah urusan
 * belanja kami, dan tidak boleh ikut ke PDF penawaran.
 */
function rabInternalUji(): Rab
{
    $vendor = Vendor::create([
        'nama' => 'Nge Jeep Yuk Banyuwangi', 'kontak' => '+62 857-0458-0181',
        'provinsi' => 'Jawa Timur', 'catatan' => 'DP 50% H-3, sisanya saat penjemputan.', 'aktif' => true,
    ]);
    $master = MasterHarga::create([
        'kategori' => 'tiket', 'nama' => 'Tiket Masuk Baluran', 'provinsi' => 'Jawa Timur',
        'destinasi' => 'Taman Nasional Baluran', 'satuan' => 'orang', 'harga' => 21_000,
        'aktif' => true, 'catatan' => 'Gerbang tutup 16.00. Bawa uang pas.',
    ]);

    $rab = Rab::create([
        'judul' => 'Private Trip Banyuwangi', 'nama_pelanggan' => 'Endah Andayani',
        'whatsapp' => '+62 818-457-974', 'email' => 'endah@contoh.id',
        'provinsi' => 'Jawa Timur', 'tanggal_mulai' => '2026-12-12',
        'jumlah_hari' => 3, 'jumlah_malam' => 2, 'jumlah_peserta' => 5,
        'margin_jenis' => 'persen', 'margin_nilai' => 5,
        'berlaku_sampai' => '2026-11-18', 'dibuat_oleh' => 'Berto',
    ]);

    RabBiaya::create(['rab_id' => $rab->id, 'vendor_id' => $vendor->id, 'kategori' => 'transportasi',
        'nama' => 'Innova Reborn', 'satuan' => 'unit_hari', 'harga_satuan' => 800_000, 'kapasitas' => 6, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'master_harga_id' => $master->id, 'kategori' => 'tiket',
        'nama' => 'Tiket Masuk Baluran', 'satuan' => 'orang', 'harga_satuan' => 21_000, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'dokumentasi',
        'nama' => 'Dokumentasi Perjalanan', 'satuan' => 'rombongan', 'harga_satuan' => 0, 'jumlah' => 1]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'lainnya',
        'nama' => 'Perlengkapan P3K', 'satuan' => 'rombongan', 'harga_satuan' => 150_000, 'jumlah' => 1]);

    foreach ([[1, 'Taman Nasional Baluran'], [2, 'Pantai Pulau Merah'], [3, 'Taman Nasional Baluran']] as $i => [$h, $d]) {
        RabItinerary::create(['rab_id' => $rab->id, 'hari_ke' => $h, 'jam' => '09:00',
            'nama' => $d, 'destinasi' => $d, 'urutan' => $i]);
    }

    return $rab->fresh();
}

function htmlPdf(Rab $rab, string $jenis): string
{
    return view(...BerkasRab::bahan($rab, $jenis))->render();
}

test('syarat master harga dan vendor ikut tercetak di RAB internal', function () {
    // Dibaca di lapangan, oleh orang yang tidak menyusun RAB-nya, di tempat
    // tanpa sinyal. Yang hanya ada di layar kantor sama saja tidak ditulis.
    $html = htmlPdf(rabInternalUji(), 'internal');

    expect($html)->toContain('Gerbang tutup 16.00. Bawa uang pas.')
        ->and($html)->toContain('DP 50% H-3, sisanya saat penjemputan.');
});

test('syarat vendor dan nomornya TIDAK ikut ke PDF penawaran', function () {
    // Arah sebaliknya. Itu urusan belanja kami, bukan janji ke pelanggan.
    $html = htmlPdf(rabInternalUji(), 'penawaran');

    expect($html)->not->toContain('DP 50% H-3')
        ->and($html)->not->toContain('+62 857-0458-0181')
        ->and($html)->not->toContain('Nge Jeep Yuk')
        ->and($html)->not->toContain('Gerbang tutup');
});

test('tiap baris biaya menyebut hari ke berapa ia terpakai', function () {
    $html = htmlPdf(rabInternalUji(), 'internal');

    // Baluran dikunjungi hari 1 dan 3; Innova dipakai sepanjang trip.
    expect($html)->toContain('Hari 1, 3')
        ->and($html)->toContain('Sepanjang perjalanan');
});

test('harga Rp 0 diperingatkan di atas dan ditandai di barisnya', function () {
    $html = htmlPdf(rabInternalUji(), 'internal');

    expect($html)->toContain('masih berharga Rp 0')
        ->and($html)->toContain('Dokumentasi Perjalanan')
        ->and($html)->toContain('belum dianggarkan');
});

test('RAB yang harganya terisi semua tidak memasang peringatan Rp 0', function () {
    $rab = rabInternalUji();
    RabBiaya::where('rab_id', $rab->id)->where('harga_satuan', 0)->update(['harga_satuan' => 500_000]);

    expect(htmlPdf($rab->fresh(), 'internal'))->not->toContain('masih berharga Rp 0');
});

test('rekap vendor menyebut siapa dibayar berapa, berikut yang belum punya vendor', function () {
    // Tabel rincian diurutkan menurut kategori — urutan menghitung harga,
    // bukan urutan membayar. Satu vendor bisa tersebar di beberapa kategori.
    $html = htmlPdf(rabInternalUji(), 'internal');

    expect($html)->toContain('Yang harus dibayar')
        ->and($html)->toContain('Nge Jeep Yuk Banyuwangi')
        ->and($html)->toContain('Rp 2.400.000')            // 800rb x 3 hari
        // Yang tidak terlihat di rekap akan dikira sudah ada yang mengurus.
        ->and($html)->toContain('Belum ada vendornya');
});

test('daftar siap-berangkat memuat barang yang disiapkan tim sendiri', function () {
    // Biaya adalah daftar UANG, bukan daftar BARANG. Banner yang anggarannya
    // sudah benar tetap bisa tertinggal di kantor.
    $html = htmlPdf(rabInternalUji(), 'internal');

    expect($html)->toContain('Disiapkan tim sendiri')
        ->and($html)->toContain('Perlengkapan P3K')
        ->and($html)->toContain('Dokumentasi Perjalanan');
});

test('masa berlaku, email, dan penyusunnya ikut tercetak', function () {
    $html = htmlPdf(rabInternalUji(), 'internal');

    expect($html)->toContain('Berlaku sampai 18 November 2026')
        ->and($html)->toContain('endah@contoh.id')
        ->and($html)->toContain('disusun Berto');
});

test('tanggal yang bergeser diperingatkan di atas, tidak hanya per baris', function () {
    // Dijaga di sumbernya: menyalakan harga_sebab_seharusnya butuh master
    // ber-harga akhir pekan DAN tanggal yang digeser sesudah biaya ditarik —
    // keadaan yang kalau dirakit di uji hanya akan menguji perakitannya.
    $s = file_get_contents(base_path('resources/views/pdf/rab-internal.blade.php'));

    expect($s)->toContain("@if (\$r['ada_harga_salah_hari'])")
        ->and($s)->toContain('Tanggal perjalanan bergeser');
});

test('tidak ada direktif Blade yang menempel huruf di templat PDF', function () {
    // "baris@if" TIDAK dikompilasi — Blade melewati direktif yang didahului
    // huruf — tetapi "@endif"-nya dikompilasi. Hasilnya ParseError yang
    // menunjuk berkas hasil kompilasi, jauh dari baris yang bersalah.
    foreach (['rab-internal', 'rab-penawaran'] as $berkas) {
        $s = file_get_contents(base_path("resources/views/pdf/{$berkas}.blade.php"));

        expect(preg_match('/\w@(if|endif|foreach|endforeach|else|elseif|php|endphp)\b/', $s))
            ->toBe(0, "{$berkas}: ada direktif Blade yang menempel huruf");
    }
});

test('kedua PDF tetap bisa dirakit utuh jadi berkas', function () {
    $rab = rabInternalUji();

    foreach (['internal', 'penawaran'] as $jenis) {
        expect(strlen(BerkasRab::buat($rab, $jenis)))->toBeGreaterThan(1000);
    }
});
