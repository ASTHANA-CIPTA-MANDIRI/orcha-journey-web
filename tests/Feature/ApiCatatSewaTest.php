<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Mencatat penyewaan dari lemon — pesanan yang datang lewat telepon.
 *
 * Sebelum jalur ini ada, penyewaan HANYA bisa lahir dari borang publik, dan
 * admin yang menerima pesanan lewat telepon terpaksa mengisikan borang itu
 * atas nama pelanggannya.
 *
 * Yang dijaga di sini terutama satu hal: harga, modal, dan tenggat
 * pengembaliannya harus dihitung dengan rumus yang SAMA PERSIS dengan borang
 * publik. Dua rumus sejajar yang berbeda jawabannya baru ketahuan berbulan-
 * bulan kemudian, lewat keuntungan yang tidak pernah cocok.
 */
function kepalaCatatSewa(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-catat-sewa');

    return ['X-Orcha-Key' => 'kunci-uji-catat-sewa', 'Accept' => 'application/json'];
}

function mobilCatatSewa(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza Catat',
        'brand' => 'Toyota',
        'type' => 'mobil',
        'price_per_day' => 350000,
        'harga_per_jam' => 55000,
        'harga_12_jam' => 280000,
        'harga_sopir' => 150000,
        'transmission' => 'Manual',
        'transmisi_tersedia' => ['Manual', 'Matic'],
        'capacity' => 7,
        'is_available' => true,
    ], $ubah));
}

function isiCatatSewa(Car $mobil, array $ubah = []): array
{
    return array_merge([
        'car_id' => $mobil->id,
        'nama' => 'Pak Budi',
        'whatsapp' => '0812-3456-7890',
        'transmisi' => 'Manual',
        'satuan' => 'hari',
        'durasi' => 3,
        'tanggal_mulai' => '2026-12-10',
        'jam_mulai' => '08:00',
        'dengan_sopir' => true,
        'luar_kota' => false,
        'lokasi_antar' => 'Bandara YIA',
        'tujuan' => 'Bromo',
    ], $ubah);
}

test('penyewaan telepon tercatat lengkap dengan tenggat pengembaliannya', function () {
    $mobil = mobilCatatSewa();

    $balasan = test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil), kepalaCatatSewa())
        ->assertCreated();

    $sewa = PenyewaanKendaraan::firstOrFail();

    expect($sewa->nama)->toBe('Pak Budi')
        ->and($sewa->nama_kendaraan)->toBe('Avanza Catat')
        ->and($sewa->dengan_sopir)->toBeTrue()
        ->and($sewa->tujuan)->toBe('Bromo')
        // Status bawaannya 'baru', sama seperti pesanan dari situs.
        ->and($sewa->status)->toBe('baru');

    // Tenggatnya dihitung dengan rumus yang sama dengan borang publik.
    expect($sewa->tanggal_selesai->toDateString())->toBe(
        PenyewaanKendaraan::hitungSelesai('2026-12-10', '08:00', 'hari', 3)->toDateString()
    );

    expect($balasan->json('data.kode'))->not->toBeEmpty();
});

test('harga dan modal disalin dari rumus unit, bukan dihitung ulang di sini', function () {
    $mobil = mobilCatatSewa();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil), kepalaCatatSewa())->assertCreated();

    $sewa = PenyewaanKendaraan::firstOrFail();

    expect($sewa->estimasi_biaya)->toBe($mobil->estimasiBiaya('hari', 3, true, false))
        ->and($sewa->modal)->toBe($mobil->modalEstimasi('hari', 3, true, false));
});

test('admin boleh langsung menandai DP sudah masuk', function () {
    // Pesanan telepon kerap sudah disertai transfer sebelum barisnya dibuat.
    $mobil = mobilCatatSewa();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil, ['status' => 'dp_masuk']), kepalaCatatSewa())
        ->assertCreated();

    expect(PenyewaanKendaraan::firstOrFail()->status)->toBe('dp_masuk');
});

test('satuan yang tidak dijual untuk unit itu ditolak', function () {
    // Bus tidak punya tarif per jam. Menyembunyikan pilihannya di layar tidak
    // cukup — permintaan yang dirakit tangan bisa mengirim apa saja.
    $bus = mobilCatatSewa(['name' => 'Bus Catat', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($bus, ['satuan' => 'jam']), kepalaCatatSewa())
        ->assertStatus(422);

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('transmisi yang tidak tersedia untuk unit itu ditolak', function () {
    $bus = mobilCatatSewa(['name' => 'Bus Catat', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($bus, ['transmisi' => 'Matic']), kepalaCatatSewa())
        ->assertStatus(422);
});

test('unit yang wajib bersopir tidak bisa dicatat sebagai lepas kunci', function () {
    // Aturan ini keputusan admin di halaman armada, dan harus tetap berlaku
    // dari mana pun pesanannya dicatat.
    /*
     | lepas_kunci DITULIS false, tidak diandaikan.
     |
     | Kolomnya ber-default TRUE di basis data, jadi unit yang dibuat tanpa
     | menyebutkannya justru BOLEH lepas kunci — kebalikan dari dugaan versi
     | pertama uji ini.
     |
     | Dan ->fresh() bukan hiasan: nilai bawaan dari basis data TIDAK ikut
     | termuat ke objek hasil create(), sehingga $bus->lepas_kunci di memori
     | berbunyi null sementara barisnya di basis data bernilai true.
     */
    $bus = mobilCatatSewa([
        'name' => 'Bus Catat', 'type' => 'bus', 'harga_per_jam' => null,
        'transmisi_tersedia' => ['Manual'], 'lepas_kunci' => false,
    ])->fresh();

    expect($bus->lepas_kunci)->toBeFalse();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($bus, ['dengan_sopir' => false]), kepalaCatatSewa())
        ->assertStatus(422);
});

test('lokasi pengembalian diisi dari titik penjemputan bila kosong', function () {
    // Pada sewa bersopir unitnya tidak diserahkan ke siapa pun, jadi tidak ada
    // yang mengembalikan apa-apa — tetapi catatannya tetap harus utuh.
    $mobil = mobilCatatSewa();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil), kepalaCatatSewa())->assertCreated();

    expect(PenyewaanKendaraan::firstOrFail()->lokasi_kembali)->toBe('Bandara YIA');
});

test('nomor WhatsApp yang tidak masuk akal ditolak', function () {
    $mobil = mobilCatatSewa();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil, ['whatsapp' => '12']), kepalaCatatSewa())
        ->assertStatus(422);
});

test('tanpa kunci API jalurnya tertutup', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-catat-sewa');
    $mobil = mobilCatatSewa();

    test()->postJson('/api/v1/penyewaan', isiCatatSewa($mobil))->assertStatus(401);

    expect(PenyewaanKendaraan::count())->toBe(0);
});
