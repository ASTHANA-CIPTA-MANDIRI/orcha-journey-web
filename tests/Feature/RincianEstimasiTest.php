<?php

use App\Models\SewaKendaraan\Car;
use Livewire\Volt\Volt;

/**
 * Perkiraan biaya diperinci, bukan satu angka.
 *
 * "Rp 1.800.000" tanpa penjelasan memancing pertanyaan yang sama berulang kali
 * lewat WhatsApp — kenapa segitu, sopirnya sudah termasuk belum, BBM-nya
 * dihitung tidak. Yang paling berbahaya: penyewa menebak sendiri, lalu merasa
 * ditagih lebih saat angka finalnya datang.
 */
function unitRincian(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'],
        'capacity' => 7, 'lepas_kunci' => true, 'termasuk_sopir' => false,
        'price_per_day' => 400000, 'harga_per_jam' => 60000, 'harga_12_jam' => 250000,
        'harga_sopir' => 150000, 'is_available' => true,
    ], $ubah));
}

test('total perkiraan selalu sama dengan jumlah rinciannya', function (string $satuan, int $durasi, bool $sopir, bool $luar) {
    $unit = unitRincian(['harga_luar_kota' => 700000,
        'termasuk_bbm' => true, 'biaya_bbm' => 100000]);

    // Dua hitungan sejajar untuk angka yang sama pasti berselisih suatu saat.
    // Yang dilihat penyewa rinciannya, yang tersimpan totalnya — selisihnya
    // baru ketahuan saat menagih, dan saat itu sudah terlambat.
    $rincian = $unit->rincianEstimasi($satuan, $durasi, $sopir, $luar);

    expect(array_sum(array_column($rincian, 'jumlah')))
        ->toBe($unit->estimasiBiaya($satuan, $durasi, $sopir, $luar));
})->with([
    'harian lepas kunci' => ['hari', 3, false, false],
    'harian bersopir' => ['hari', 3, true, false],
    'per jam bersopir' => ['jam', 5, true, false],
    'paket 12 jam' => ['12jam', 1, true, false],
    'luar kota bersopir' => ['hari', 2, true, true],
]);

test('sopir dan pos operasional dihitung harian walau sewanya per jam', function () {
    $unit = unitRincian(['termasuk_bbm' => true, 'biaya_bbm' => 100000]);

    // Sewa lima jam tetap satu hari kerja bagi sopirnya. Mengalikannya dengan
    // lima akan menagih sopir untuk hari yang tidak pernah ada.
    $rincian = collect($unit->rincianEstimasi('jam', 5, true, false));

    expect($rincian->firstWhere('label', 'Sopir'))
        ->toMatchArray(['keterangan' => 'Rp 150.000 × 1 hari', 'jumlah' => 150000])
        ->and($rincian->firstWhere('label', 'BBM')['jumlah'])->toBe(100000)
        ->and($rincian->firstWhere('label', 'Tarif sewa')['jumlah'])->toBe(300000);
});

test('unit yang tarifnya sudah termasuk sopir tidak menambah baris sopir', function () {
    $unit = unitRincian(['termasuk_sopir' => true, 'harga_sopir' => null]);

    // Menambahkannya berarti menagih sopir dua kali untuk unit yang harganya
    // justru sudah dihitung bersama sopirnya.
    expect(collect($unit->rincianEstimasi('hari', 2, true, false))->pluck('label')->all())
        ->toBe(['Tarif sewa']);
});

test('pos yang ditanggung penyewa tidak masuk perincian', function () {
    $unit = unitRincian(['termasuk_bbm' => false, 'biaya_bbm' => 100000]);

    // Angka yang tertinggal pada pos yang tidak termasuk bukan tagihan.
    expect(collect($unit->rincianEstimasi('hari', 1, false, false))->pluck('label')->all())
        ->not->toContain('BBM');
});

test('satuan yang tidak dijual tidak menghasilkan perincian', function () {
    $unit = unitRincian(['harga_per_jam' => null]);

    expect($unit->rincianEstimasi('jam', 2, false, false))->toBe([])
        ->and($unit->estimasiBiaya('jam', 2, false, false))->toBeNull();
});

test('halaman pemesanan menampilkan perincian, bukan hanya totalnya', function () {
    $unit = unitRincian();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('satuan', 'hari')
        ->set('durasi', 2)
        ->set('denganSopir', 'ya')
        ->assertSee('Tarif sewa')
        ->assertSee('Rp 400.000 × 2 hari')
        ->assertSee('Sopir')
        ->assertSee('Rp 150.000 × 2 hari')
        ->assertSee('Perkiraan total')
        ->assertSee('Rp 1.100.000');
});

test('tarif yang sedang dipakai ditandai di daftar tarif unit', function () {
    $unit = unitRincian();

    // Tiga angka sejajar tanpa penanda membuat penyewa menebak sendiri yang
    // mana yang berlaku untuk pesanannya.
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('satuan', '12jam')
        ->assertSee('Dipakai');
});

test('penanda tarif pindah ke luar kota saat wilayahnya luar kota', function () {
    $unit = unitRincian(['harga_luar_kota' => 700000]);

    // Luar kota selalu dihitung harian, satuan apa pun yang dipilih sebelumnya —
    // jadi penandanya tidak boleh tertinggal di baris satuan yang lama.
    $uji = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('satuan', 'jam')
        ->set('luarKota', true);

    $teks = preg_replace('/\s+/', ' ', strip_tags($uji->html()));

    expect($teks)->toContain('Luar kota per hari Dipakai');
});
