<?php

use App\Models\SewaKendaraan\Car;
use App\Support\SewaKendaraan\NomorPolisi;

/**
 * Nomor polisi tersimpan dalam satu bentuk baku.
 *
 * Diketik dengan bermacam cara — "ab4169te", "AB-4169-TE", "ab 4169  te" — dan
 * semuanya nomor yang sama. Disimpan apa adanya, mencari unit berdasarkan nopol
 * jadi tidak dapat diandalkan dan satu unit bisa tercatat dua kali.
 */
const KUNCI_NOPOL = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_NOPOL);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaNopol(): array
{
    return [
        'X-Orcha-Key' => KUNCI_NOPOL,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

test('nopol dirapikan ke bentuk baku', function (string $masukan, ?string $harapan) {
    expect(NomorPolisi::rapikan($masukan))->toBe($harapan);
})->with([
    ['ab4169te', 'AB 4169 TE'],
    ['AB-4169-TE', 'AB 4169 TE'],
    ['  ab 4169  te ', 'AB 4169 TE'],
    ['ab.4169.te', 'AB 4169 TE'],
    // Plat satu huruf wilayah dan tiga huruf seri
    ['b1234xyz', 'B 1234 XYZ'],
    // Plat tanpa huruf seri
    ['ab1234', 'AB 1234'],
    ['', null],
]);

test('bentuk yang tidak dikenali dikapitalkan, bukan dibuang', function () {
    // Nomor khusus di luar dugaan pola tetap ada. Membuang isinya berarti
    // kehilangan data yang benar hanya karena bentuknya tidak terduga.
    expect(NomorPolisi::rapikan('cd 12 34 khusus'))->toBe('CD 12 34 KHUSUS');
});

test('nopol tersimpan kapital lewat jalur mana pun', function () {
    // Mutator di model, bukan pembersihan di controller: kalau hanya di
    // controller, jalur lain tetap bisa memasukkan "ab-4169-te".
    $unit = Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'nopol' => 'ab-4169-te',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'price_per_day' => 400000, 'is_available' => true,
    ]);

    expect($unit->fresh()->nopol)->toBe('AB 4169 TE');

    $unit->update(['nopol' => 'ab 9999 zz']);

    expect($unit->fresh()->nopol)->toBe('AB 9999 ZZ');
});

test('nopol tersimpan kapital lewat API', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Xenia', 'merek' => 'Daihatsu', 'jenis' => 'mobil', 'nopol' => 'ab4169te',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Manual'], 'tarif_hari' => 350000,
    ], kepalaNopol())->assertCreated();

    expect(Car::first()->nopol)->toBe('AB 4169 TE');
});

test('nopol kosong tersimpan sebagai null, bukan teks kosong', function () {
    // Unit yang platnya masih dalam proses memang belum punya nomor; menyimpan
    // string kosong membuat "belum ada" dan "kosong" tidak bisa dibedakan.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Xenia', 'merek' => 'Daihatsu', 'jenis' => 'mobil', 'nopol' => '   ',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Manual'], 'tarif_hari' => 350000,
    ], kepalaNopol())->assertCreated();

    expect(Car::first()->nopol)->toBeNull();
});

test('nopol yang jelas bukan nomor polisi ditolak', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Xenia', 'merek' => 'Daihatsu', 'jenis' => 'mobil',
        'nopol' => 'mobil bagus banget',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Manual'], 'tarif_hari' => 350000,
    ], kepalaNopol())->assertStatus(422);
});

test('nopol tampil rapi di resource', function () {
    Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'nopol' => 'ab4169te',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'price_per_day' => 400000, 'is_available' => true,
    ]);

    expect($this->getJson('/api/v1/kendaraan', kepalaNopol())->assertOk()->json('data.0.nopol'))
        ->toBe('AB 4169 TE');
});

test('migrasi merapikan nopol yang sudah tersimpan', function () {
    // Mutator hanya berlaku saat menyimpan, jadi baris lama tetap memuat huruf
    // kecil sampai unitnya disunting — dan selama itu daftar armada memuat
    // campuran, yang persis keadaan yang hendak dihilangkan.
    $unit = Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'nopol' => 'AB 4169 TE',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'price_per_day' => 400000, 'is_available' => true,
    ]);

    // Ditulis lewat query builder supaya mutatornya dilewati — meniru data lama.
    Car::query()->whereKey($unit->id)->update(['nopol' => 'ab-4169-te']);
    expect($unit->fresh()->nopol)->toBe('ab-4169-te');

    (require base_path('database/migrations/2026_08_17_080000_rapikan_nopol_yang_sudah_ada.php'))->up();

    expect($unit->fresh()->nopol)->toBe('AB 4169 TE');
});

test('merapikan nopol lama tidak mengubah waktu penyuntingan unitnya', function () {
    $unit = Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'nopol' => 'AB 1 A',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'price_per_day' => 400000, 'is_available' => true,
    ]);

    Car::query()->whereKey($unit->id)->update([
        'nopol' => 'ab1a', 'updated_at' => now()->subMonth(),
    ]);
    $sebelum = $unit->fresh()->updated_at;

    (require base_path('database/migrations/2026_08_17_080000_rapikan_nopol_yang_sudah_ada.php'))->up();

    // Ini pembetulan bentuk, bukan perubahan data unitnya. Menggeser updated_at
    // membuat seluruh armada tampak baru disunting hari deploy.
    expect($unit->fresh()->nopol)->toBe('AB 1 A')
        ->and($unit->fresh()->updated_at->eq($sebelum))->toBeTrue();
});
