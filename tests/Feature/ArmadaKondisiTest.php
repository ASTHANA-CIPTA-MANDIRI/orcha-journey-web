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

test('pemilik bisa mencatat kondisi sesudah unitnya diperbaiki', function () {
    $mobil = unitArmada([
        'kondisi_terkini' => ['kaca' => 'rusak', 'bodi_depan' => 'lecet'],
        'kondisi_diperiksa_pada' => now()->subWeek(),
    ]);

    // Tanpa jalur ini, unit yang kacanya sudah diganti tetap terbaca "rusak"
    // sampai ada penyewa berikutnya yang mengembalikannya — dan selama itu
    // halaman armada menyuruh admin memperbaiki yang sudah diperbaiki.
    $this->patchJson("/api/v1/kendaraan/{$mobil->id}/kondisi", [
        'kondisi' => ['kaca' => 'baik', 'bodi_depan' => 'lecet'],
        'catatan' => 'Kaca diganti 17 Agustus di bengkel Slamet.',
    ], kepalaArmada())->assertOk();

    $baru = $mobil->fresh();

    expect($baru->kondisi_terkini['kaca'])->toBe('baik')
        ->and($baru->kondisi_catatan)->toContain('bengkel Slamet')
        // Waktunya ikut diperbarui: yang dibaca berikutnya adalah pemeriksaan ini
        ->and($baru->kondisi_diperiksa_pada->isToday())->toBeTrue();

    $baris = $this->getJson('/api/v1/kendaraan', kepalaArmada())->assertOk()->json('data.0');

    expect($baris['kondisi']['perlu_perhatian'])->toBeFalse()
        ->and($baris['kondisi']['lecet'])->toBe(1);
});

test('mencatat perbaikan tidak menghapus jejak denda penyewaan sebelumnya', function () {
    $mobil = unitArmada(['kondisi_terkini' => ['kaca' => 'rusak']]);

    $sewa = sewaUnit($mobil, [
        'status' => 'selesai', 'denda_kerusakan' => 900000,
        'rincian_denda' => [['bagian' => 'Kaca & spion', 'biaya' => 900000]],
        'dikembalikan_pada' => now()->subDay(),
    ]);

    $this->patchJson("/api/v1/kendaraan/{$mobil->id}/kondisi", [
        'kondisi' => ['kaca' => 'baik'],
    ], kepalaArmada())->assertOk();

    // Denda melekat pada penyewaannya, bukan pada unitnya. Memperbaiki mobil
    // tidak boleh menghapus catatan siapa yang merusakkannya.
    expect($sewa->fresh()->denda_kerusakan)->toBe(900000)
        ->and($sewa->fresh()->rincian_denda)->toHaveCount(1);
});

test('bagian yang tidak dikenal diabaikan, bukan ikut tersimpan', function () {
    $mobil = unitArmada();

    $this->patchJson("/api/v1/kendaraan/{$mobil->id}/kondisi", [
        'kondisi' => ['kaca' => 'baik', 'kursi_pijat' => 'baik'],
    ], kepalaArmada())->assertOk();

    // Daftar bagian harus tetap sama dengan yang dipakai serah terima, supaya
    // perbandingan kondisi berikutnya tidak membandingkan hal yang berbeda.
    expect($mobil->fresh()->kondisi_terkini)->toBe(['kaca' => 'baik']);
});

test('nilai kondisi di luar daftar ditolak', function () {
    $mobil = unitArmada();

    $this->patchJson("/api/v1/kendaraan/{$mobil->id}/kondisi", [
        'kondisi' => ['kaca' => 'agak retak sedikit'],
    ], kepalaArmada())->assertStatus(422);
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
