<?php

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\Rab\BerkasRab;

/**
 * Kartu angka di kedua PDF harus sama besar.
 *
 * Dua cacat yang sama-sama tidak kelihatan di layar dan jelas di berkas jadi:
 *
 * 1. TINGGI. Kartunya dulu div di dalam sel tabel. Sel sebaris selalu
 *    setinggi yang tertinggi, tetapi div di dalamnya hanya setinggi isinya —
 *    jadi kartu yang keterangannya turun ke baris kedua berdiri lebih tinggi
 *    daripada tetangganya, dan yang pendek menyisakan lubang putih.
 * 2. LEBAR. Jaraknya dulu dibuat dari padding sel, lalu padding itu dicabut
 *    di sel pertama dan terakhir supaya rata dengan tepi halaman. Kartu
 *    pertama jadi 8px lebih lebar daripada yang di tengah.
 *
 * Keduanya pernah ditambal dengan `height: 34px` — yang menyamakan tinggi
 * sambil MEMOTONG isi begitu nama tujuannya cukup panjang.
 *
 * Yang dijaga di sini aturannya, bukan angkanya: kartu = SELNYA sendiri,
 * jaraknya kolom tersendiri, dan tidak ada tinggi yang dipatok.
 */
function sumberPdf(string $nama): string
{
    return file_get_contents(base_path("resources/views/pdf/{$nama}.blade.php"));
}

test('kartu angka adalah selnya sendiri, bukan div di dalam sel', function () {
    foreach (['rab-penawaran', 'rab-internal'] as $berkas) {
        $s = sumberPdf($berkas);

        expect($s)->not->toContain('ubin-isi')
            ->and($s)->toContain('td.ubin-sel');
    }
});

test('lebar kartu dikunci, tidak dihitung dari isinya', function () {
    foreach (['rab-penawaran', 'rab-internal'] as $berkas) {
        $s = sumberPdf($berkas);

        // Tanpa table-layout: fixed, lebar kolom diambil dari isi tiap sel —
        // "Banyuwangi, Jawa Timur" membuat kartunya melebar sendiri.
        expect($s)->toMatch('/\.ubin\s*\{[^}]*table-layout:\s*fixed/', "{$berkas}: .ubin belum table-layout: fixed")
            // Penambal lama. Menyamakan tinggi dengan memotong isi.
            ->and($s)->not->toMatch('/\.ubin[^}]*\bheight:\s*\d/', "{$berkas}: tinggi kartu masih dipatok")
            // Padding yang dicabut di ujung = kartu ujung lebih lebar.
            ->and($s)->not->toContain('.ubin td:first-child');
    }
});

test('setiap kartu memakai kelas yang sama, jadi lebarnya tidak bisa berselisih', function () {
    foreach (['rab-penawaran' => 4, 'rab-internal' => 5] as $berkas => $jumlah) {
        $s = sumberPdf($berkas);

        expect(substr_count($s, '<td class="ubin-sel"'))->toBe($jumlah, "{$berkas}: jumlah kartu berubah")
            // Jarak antarkartu: selalu satu kurang dari jumlah kartunya,
            // supaya tidak ada jarak menggantung di ujung tabel.
            ->and(substr_count($s, '<td class="ubin-sela">'))->toBe($jumlah - 1, "{$berkas}: kolom jarak tidak pas");
    }
});

test('dua kotak hitungan di RAB internal juga setinggi yang sama', function () {
    $s = sumberPdf('rab-internal');

    expect($s)->toContain('td.duo-sel')
        ->and(substr_count($s, '<td class="duo-sel"'))->toBe(2)
        ->and(substr_count($s, '<td class="duo-sela">'))->toBe(1)
        // Kotak di dalam sel akan mengembalikan persis masalahnya.
        ->and($s)->toContain('duo-tanda');
});

test('kartu tetap utuh saat isinya turun ke baris kedua', function () {
    // Pembuktian di berkas jadi, bukan di sumbernya: nama tujuan yang panjang
    // dan keterangan "balik modal" yang dua baris adalah dua hal yang dulu
    // memecah barisnya.
    $rab = Rab::create([
        'judul' => 'Private Trip Banyuwangi 3H2M', 'nama_pelanggan' => 'Endah Andayani',
        'provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi',
        'jumlah_hari' => 3, 'jumlah_malam' => 2, 'jumlah_peserta' => 5,
        'margin_jenis' => 'persen', 'margin_nilai' => 5, 'pembulatan' => 1_000,
    ]);
    RabBiaya::create(['rab_id' => $rab->id, 'kategori' => 'transportasi', 'nama' => 'Innova Reborn',
        'satuan' => 'unit_hari', 'harga_satuan' => 800_000, 'kapasitas' => 6, 'jumlah' => 1]);

    foreach (['penawaran', 'internal'] as $jenis) {
        [$view, $data] = BerkasRab::bahan($rab->fresh(), $jenis);
        $html = view($view, $data)->render();

        // Satu baris, lima (atau empat) sel kartu, tanpa div pembungkus.
        expect($html)->toContain('class="ubin-sel"')
            ->and($html)->not->toContain('ubin-isi');
    }
});
