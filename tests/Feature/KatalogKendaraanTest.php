<?php

use App\Models\SewaKendaraan\Car;
use App\Support\SewaKendaraan\KatalogKendaraan;

const KUNCI_KATALOG = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_KATALOG);
    config()->set('orcha.api.ip_diizinkan', []);
});

function unitKatalog(string $merek, string $nama): Car
{
    return Car::create([
        'name' => $nama, 'brand' => $merek, 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 7, 'price_per_day' => 400000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);
}

test('katalog memuat merek dan model pasar Indonesia', function () {
    $katalog = KatalogKendaraan::pilihan();

    // Model yang benar-benar disewakan di Indonesia. API kendaraan gratis yang
    // ada (vPIC, CarAPI) berisi data pasar Amerika dan tidak memuat satu pun
    // dari ini — itu sebabnya katalognya ditulis sendiri.
    expect(count($katalog))->toBeGreaterThanOrEqual(25)
        ->and(array_sum(array_map('count', $katalog)))->toBeGreaterThanOrEqual(150)
        ->and($katalog)->toHaveKeys(['Toyota', 'Daihatsu', 'Suzuki', 'Mitsubishi', 'Wuling', 'Chery'])
        ->and($katalog['Toyota'])->toContain('Avanza')
        ->and($katalog['Toyota'])->toContain('HiAce Commuter')
        ->and($katalog['Suzuki'])->toContain('Ertiga')
        ->and($katalog['Mitsubishi'])->toContain('Xpander');
});

test('merek dan model milik armada sendiri ikut tercantum', function () {
    // Esemka sengaja dipilih karena TIDAK ada di katalog config — merek yang
    // sudah tercantum di sana tidak membuktikan apa pun tentang penggabungan.
    expect(config('orcha.katalog_kendaraan'))->not->toHaveKey('Esemka');

    unitKatalog('Esemka', 'Bima 1.3');

    $katalog = KatalogKendaraan::pilihan();

    // Tanpa ini, mengubah unit Esemka akan menghadapkan admin pada daftar yang
    // tidak memuat mereknya sendiri — dan satu-satunya pilihan yang tersisa
    // adalah mengubahnya jadi merek lain.
    expect($katalog)->toHaveKey('Esemka')
        ->and($katalog['Esemka'])->toBe(['Bima 1.3']);
});

test('model armada digabung ke merek yang sudah ada di katalog, bukan menggantinya', function () {
    unitKatalog('Toyota', 'Kijang Kapsul');

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect($toyota)->toContain('Kijang Kapsul')
        ->and($toyota)->toContain('Avanza');
});

test('model yang sama tidak tercantum dua kali', function () {
    unitKatalog('Toyota', 'Avanza');
    unitKatalog('Toyota', 'Avanza');

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect(array_count_values($toyota)['Avanza'])->toBe(1);
});

test('merek kosong di armada tidak membuat pilihan tanpa nama', function () {
    unitKatalog('', 'Tanpa Merek');

    expect(array_keys(KatalogKendaraan::pilihan()))->not->toContain('');
});

test('katalog terkirim lewat rujukan supaya lemon tidak menyimpan daftarnya sendiri', function () {
    unitKatalog('Wuling', 'Confero S');

    $data = $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => KUNCI_KATALOG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk()->json('data.katalog_kendaraan');

    // Satu sumber di Orcha, bukan salinan daftar di lemon: kalau disalin,
    // keduanya pasti berbeda suatu saat.
    expect($data)->toHaveKey('Wuling')
        ->and($data['Wuling'])->toContain('Confero S')
        ->and($data['Toyota'])->toContain('Avanza');
});

test('merek terurut supaya dropdown tidak berubah-ubah urutannya', function () {
    unitKatalog('Audi', 'Q3');

    $merek = array_keys(KatalogKendaraan::pilihan());

    expect($merek[0])->toBe('Audi')
        ->and($merek)->toBe(collect($merek)->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all());
});

/* ------------------- TAMBAHAN ADMIN: MASUK DAFTAR & BISA DIHAPUS ------------------- */

function kepalaKatalog(): array
{
    return [
        'X-Orcha-Key' => KUNCI_KATALOG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

test('merek yang ditulis admin masuk daftar tanpa perlu menyimpan unit dulu', function () {
    // Inti permintaannya: sebelum ini merek manual baru bertahan kalau unitnya
    // ikut tersimpan — nama yang diketik lalu dibatalkan hilang begitu saja.
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Esemka'], kepalaKatalog())
        ->assertCreated();

    expect(KatalogKendaraan::pilihan())->toHaveKey('Esemka')
        ->and(Car::count())->toBe(0);
});

test('model yang ditulis admin masuk di bawah mereknya', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima 1.3'], kepalaKatalog())->assertCreated();

    expect(KatalogKendaraan::pilihan()['Esemka'])->toBe(['Bima 1.3']);
});

test('model baru bisa ditambahkan ke merek bawaan', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Toyota', 'model' => 'Kijang Krista'], kepalaKatalog())->assertCreated();

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect($toyota)->toContain('Kijang Krista')
        ->and($toyota)->toContain('Avanza');
});

test('entri tambahan bisa dihapus', function () {
    $tambah = $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima 1.3'], kepalaKatalog())->assertCreated();

    $id = collect($tambah->json('data'))->firstWhere('model', 'Bima 1.3')['id'];

    $this->deleteJson("/api/v1/katalog-kendaraan/{$id}", [], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::pilihan()['Esemka'] ?? [])->not->toContain('Bima 1.3');
});

test('menghapus entri katalog tidak menghapus kendaraan yang memakainya', function () {
    unitKatalog('Esemka', 'Bima 1.3');

    $tambah = $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima Baru'], kepalaKatalog())->assertCreated();
    $id = collect($tambah->json('data'))->firstWhere('model', 'Bima Baru')['id'];

    $this->deleteJson("/api/v1/katalog-kendaraan/{$id}", [], kepalaKatalog())->assertOk();

    // Unitnya utuh, dan mereknya tetap terdaftar karena dibaca dari armada —
    // menghapus entri katalog tidak boleh membuat unit kehilangan mereknya.
    expect(Car::where('brand', 'Esemka')->count())->toBe(1)
        ->and(KatalogKendaraan::pilihan())->toHaveKey('Esemka')
        ->and(KatalogKendaraan::pilihan()['Esemka'])->toContain('Bima 1.3');
});

test('hanya entri tambahan yang tercatat sebagai bisa dihapus', function () {
    unitKatalog('Esemka', 'Bima 1.3');
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertCreated();

    $kustom = collect(KatalogKendaraan::kustom());

    // Katalog bawaan ikut versi kode, dan merek dari armada dipakai unit nyata:
    // keduanya tidak boleh tampak bisa dihapus.
    expect($kustom->pluck('merek')->all())->toBe(['Neta'])
        ->and($kustom->pluck('merek'))->not->toContain('Toyota')
        ->and($kustom->pluck('merek'))->not->toContain('Esemka');
});

test('entri yang sama tidak tercatat dua kali', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertCreated();
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::kustom())->toHaveCount(1);
});

test('ejaan berbeda huruf besar tidak membuat merek kedua', function () {
    // " toyota " dan "Toyota" maksudnya satu. Tanpa penormalan, daftar pilihan
    // memuat dua baris yang tampak sama.
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => '  toyota ', 'model' => 'Kijang Krista'], kepalaKatalog())->assertCreated();

    $katalog = KatalogKendaraan::pilihan();

    expect($katalog)->not->toHaveKey('toyota')
        ->and($katalog['Toyota'])->toContain('Kijang Krista');
});

test('model yang sudah ada di katalog bawaan tidak ditambahkan lagi', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Toyota', 'model' => 'Avanza'], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::kustom())->toBeEmpty()
        ->and(array_count_values(KatalogKendaraan::pilihan()['Toyota'])['Avanza'])->toBe(1);
});

test('merek kosong ditolak', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => '   '], kepalaKatalog())
        ->assertStatus(422);
});

test('katalog kustom terkirim lewat rujukan', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta', 'model' => 'V'], kepalaKatalog());

    $data = $this->getJson('/api/v1/rujukan', kepalaKatalog())->assertOk()->json('data');

    expect($data['katalog_kustom'])->toHaveCount(1)
        ->and($data['katalog_kustom'][0]['merek'])->toBe('Neta')
        ->and($data['katalog_kendaraan']['Neta'])->toBe(['V']);
});
