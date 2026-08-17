<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

const KUNCI_ARMADA = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_ARMADA);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaArmada(): array
{
    return [
        'X-Orcha-Key' => KUNCI_ARMADA,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

function unitArmada(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ], $ubah));
}

function sewaUnit(Car $mobil, array $ubah = []): PenyewaanKendaraan
{
    return PenyewaanKendaraan::create(array_merge([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => now()->addWeek()->toDateString(), 'jam_mulai' => '08:00',
        'estimasi_biaya' => 1000000, 'status' => 'dp_masuk',
    ], $ubah));
}

test('kondisi unit terbaca di daftar armada, bukan hanya saat serah terima', function () {
    // Kondisi dicatat rapi tiap unit kembali, lalu tidak pernah terbaca lagi:
    // halaman armada hanya menampilkan tarif. Unit yang kacanya retak bisa
    // disewakan lagi tanpa ada yang tahu sampai penyewa berikutnya mengeluh.
    unitArmada(['kondisi_terkini' => [
        'bodi_depan' => 'lecet', 'kaca' => 'rusak', 'ban' => 'baik', 'mesin' => 'baik',
    ], 'kondisi_diperiksa_pada' => now()]);

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    expect($baris['kondisi']['rusak'])->toBe(1)
        ->and($baris['kondisi']['lecet'])->toBe(1)
        ->and($baris['kondisi']['perlu_perhatian'])->toBeTrue()
        ->and($baris['kondisi']['diperiksa_pada'])->not->toBeNull()
        // Rinciannya menyebut bagian mana, bukan sekadar jumlah
        ->and(collect($baris['kondisi']['rincian'])->pluck('kondisi'))->toContain('Rusak');
});

test('lecet saja tidak menahan unit disewakan', function () {
    unitArmada(['kondisi_terkini' => ['bodi_depan' => 'lecet', 'kaca' => 'baik']]);

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    // Lecet lama adalah keadaan wajar armada sewa; yang menahan hanya rusak
    // dan hilang.
    expect($baris['kondisi']['lecet'])->toBe(1)
        ->and($baris['kondisi']['perlu_perhatian'])->toBeFalse();
});

test('unit yang belum pernah diperiksa tidak mengarang kondisi', function () {
    unitArmada();

    expect($this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0.kondisi'))
        ->toBeNull();
});

test('unit yang sedang keluar disebut beserta kapan kembalinya', function () {
    $mobil = unitArmada();

    sewaUnit($mobil, [
        'tanggal_mulai' => now()->subDay()->toDateString(), 'jam_mulai' => '08:00',
        'status' => 'berjalan',
    ]);

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    // "Unit ini bisa dipakai besok?" — pertanyaan yang muncul tiap kali ada
    // calon penyewa menelepon, dan selama ini dijawab dengan membuka halaman
    // lain.
    expect($baris['jadwal']['sedang_disewa'])->toBeTrue()
        ->and($baris['jadwal']['kembali_pada'])->not->toBeNull();
});

test('penyewaan berikutnya ikut disebut walau unitnya sedang bebas', function () {
    $mobil = unitArmada();
    sewaUnit($mobil, ['tanggal_mulai' => now()->addDays(3)->toDateString()]);

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    expect($baris['jadwal']['sedang_disewa'])->toBeFalse()
        ->and($baris['jadwal']['mulai_berikutnya'])->not->toBeNull()
        ->and($baris['jadwal']['kode_berikutnya'])->toStartWith('SK-');
});

test('penyewaan yang sudah selesai tidak lagi menahan unitnya', function () {
    $mobil = unitArmada();
    sewaUnit($mobil, [
        'tanggal_mulai' => now()->subWeek()->toDateString(),
        'status' => 'selesai', 'dikembalikan_pada' => now()->subDays(5),
    ]);

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    expect($baris['jadwal']['sedang_disewa'])->toBeFalse()
        ->and($baris['jadwal']['mulai_berikutnya'])->toBeNull();
});
