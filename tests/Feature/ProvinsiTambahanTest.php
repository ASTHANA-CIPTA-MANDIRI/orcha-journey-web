<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\ProvinsiTambahan;

/**
 * Provinsi yang ditambahkan admin sendiri.
 *
 * Daftar bawaan 38 provinsi cukup untuk hari ini, tetapi provinsi bisa
 * dimekarkan — 2022 saja bertambah empat sekaligus. Tanpa jalur ini admin harus
 * menunggu rilis kode hanya untuk mencatat destinasi di provinsi baru.
 */
function kirimProvinsi(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->json($metode, '/api/v1/provinsi'.($id ? "/{$id}" : ''), $isi, [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('provinsi baru tersimpan dan ikut daftar gabungan', function () {
    kirimProvinsi(['nama' => 'Papua Barat Laut', 'wilayah' => 'maluku_papua'])
        ->assertCreated()
        ->assertJsonPath('data.0.nama', 'Papua Barat Laut');

    expect(ProvinsiTambahan::gabungan())->toHaveKey('Papua Barat Laut')
        ->and(ProvinsiTambahan::gabungan()['Papua Barat Laut'])->toBe('maluku_papua');
});

test('provinsi yang sudah ada tidak digandakan dan bukan dianggap gagal', function () {
    // Admin memang menginginkan provinsi itu ada di daftar, dan ia sudah ada.
    // Menjawabnya dengan galat membuat admin mengira ada yang salah.
    kirimProvinsi(['nama' => 'jawa timur', 'wilayah' => 'jawa'])->assertOk();

    expect(ProvinsiTambahan::count())->toBe(0);
});

test('ejaan disamakan dengan daftar bawaan', function () {
    // " bali " dan "Bali" lolos batasan unik padahal maksudnya satu.
    expect(ProvinsiTambahan::rapikan('  bali '))->toBe('Bali');
});

test('wilayah wajib disebut dan harus dikenal', function () {
    // Tanpa wilayah, provinsi tambahan tidak masuk penyaring mana pun di halaman
    // publik dan destinasinya menghilang dari daftar.
    kirimProvinsi(['nama' => 'Provinsi Baru'])->assertStatus(422)
        ->assertJsonValidationErrors('wilayah');

    kirimProvinsi(['nama' => 'Provinsi Baru', 'wilayah' => 'antah_berantah'])
        ->assertStatus(422)->assertJsonValidationErrors('wilayah');
});

test('menghapus provinsi tambahan tidak menyentuh destinasinya', function () {
    $provinsi = ProvinsiTambahan::create(['nama' => 'Papua Barat Laut', 'wilayah' => 'maluku_papua']);

    DestinationPopuler::create([
        'destination_name' => 'Raja Ampat Baru', 'wilayah' => 'maluku_papua',
        'provinsi' => 'Papua Barat Laut', 'total_visitor' => 10,
    ]);

    kirimProvinsi([], 'delete', $provinsi->id)->assertOk();

    // Yang hilang hanya pilihannya di formulir, bukan datanya.
    expect(DestinationPopuler::first()->provinsi)->toBe('Papua Barat Laut')
        ->and(ProvinsiTambahan::count())->toBe(0);
});

test('rujukan mengirim gabungan beserta penanda mana yang boleh dihapus', function () {
    ProvinsiTambahan::create(['nama' => 'Papua Barat Laut', 'wilayah' => 'maluku_papua']);

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $data = $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data');

    expect($data['provinsi_wilayah'])->toHaveKey('Papua Barat Laut')
        ->and($data['provinsi_wilayah'])->toHaveKey('Jawa Timur')
        // Hanya entri tambahan yang boleh dihapus; yang bawaan ikut versi kode.
        ->and(collect($data['provinsi_kustom'])->pluck('nama')->all())->toBe(['Papua Barat Laut']);
});
