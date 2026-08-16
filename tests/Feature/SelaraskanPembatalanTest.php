<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

const KUNCI_SELARAS = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_SELARAS);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepala(): array
{
    return [
        'X-Orcha-Key' => KUNCI_SELARAS,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

function ajukanBatal(string $kode): Pembatalan
{
    return Pembatalan::create([
        'kode_pendaftaran' => $kode, 'nama_pemohon' => 'Budi', 'whatsapp' => '0812',
        'alasan' => 'kondisi_kesehatan', 'jumlah_dibatalkan' => 1, 'bank' => 'BCA',
        'nomor_rekening' => '123456', 'atas_nama_rekening' => 'Budi',
    ]);
}

function tripBaru(string $status = 'dp_masuk'): PendaftaranOpenTrip
{
    // Paketnya ikut dibuat supaya harganya ada: status hanya bisa dihitung
    // ulang kalau totalnya diketahui.
    $paket = App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip', 'price' => 1000000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    return PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id,
        'nama' => 'Budi', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Uji', 'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'status' => $status,
    ]);
}

function sewaBaru(string $status = 'dp_masuk'): PenyewaanKendaraan
{
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil', 'transmission' => 'Matic',
        'capacity' => 7, 'price_per_day' => 500000, 'is_available' => true,
        'transmisi_tersedia' => ['Matic'],
    ]);

    return PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Rina',
        'whatsapp' => '0812', 'transmisi' => 'Matic', 'satuan' => 'hari', 'durasi' => 2,
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(), 'jam_mulai' => '08:00',
        'estimasi_biaya' => 1000000, 'status' => $status,
    ]);
}

test('pengajuan yang baru masuk belum membatalkan pesanannya', function () {
    $trip = tripBaru();
    $batal = ajukanBatal($trip->kode);

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'diproses'], kepala())
        ->assertOk();

    // Baru permintaan; tim masih boleh menolaknya.
    expect($trip->fresh()->status)->toBe('dp_masuk');
});

test('pembatalan yang disetujui ikut membatalkan pendaftaran open trip', function () {
    $trip = tripBaru();
    $batal = ajukanBatal($trip->kode);

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'disetujui'], kepala())
        ->assertOk();

    // Yang paling sering tertinggal saat dikerjakan tangan: pesanannya tetap
    // terbaca "dp_masuk" padahal dananya sedang dikirim balik.
    expect($trip->fresh()->status)->toBe('batal');
});

test('pembatalan yang disetujui ikut membatalkan sewa kendaraan', function () {
    $sewa = sewaBaru();
    $batal = ajukanBatal($sewa->kode);

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'dana_dikirim'], kepala())
        ->assertOk();

    expect($sewa->fresh()->status)->toBe('batal');
});

test('bukti bayar yang masih menunggu ditandai, bukan diputuskan sendiri', function () {
    $trip = tripBaru();
    $batal = ajukanBatal($trip->kode);

    $menunggu = KonfirmasiPembayaran::create([
        'kode' => $trip->kode, 'jenis' => 'dp', 'nominal' => 600000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'menunggu', 'catatan_admin' => 'Cek mutasi.',
    ]);

    $diterima = KonfirmasiPembayaran::create([
        'kode' => $trip->kode, 'jenis' => 'dp', 'nominal' => 400000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'disetujui'], kepala())
        ->assertOk();

    // Menolaknya berarti menghapus catatan uang yang mungkin benar-benar
    // masuk — padahal jumlah itulah yang menentukan besar pengembalian.
    expect($menunggu->fresh()->status)->toBe('menunggu')
        ->and($menunggu->fresh()->catatan_admin)->toContain('Cek mutasi.')
        ->and($menunggu->fresh()->catatan_admin)->toContain('Pesanan ini dibatalkan')
        // Yang sudah diterima tidak diusik sama sekali
        ->and($diterima->fresh()->status)->toBe('diterima')
        ->and($diterima->fresh()->catatan_admin)->toBeNull();
});

test('pengajuan yang ditolak mengembalikan pesanan ke jalurnya semula', function () {
    $trip = tripBaru('baru');
    KonfirmasiPembayaran::create([
        'kode' => $trip->kode, 'jenis' => 'dp', 'nominal' => 600000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'diterima',
    ]);

    $batal = ajukanBatal($trip->kode);

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'disetujui'], kepala())->assertOk();
    expect($trip->fresh()->status)->toBe('batal');

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'ditolak'], kepala())->assertOk();

    // Dihitung ulang dari pembayaran yang sudah diterima, bukan ditebak:
    // pesanan yang DP-nya sudah masuk kembali ke dp_masuk, bukan ke baru.
    expect($trip->fresh()->status)->toBe('dp_masuk');
});

test('kode yang tidak dikenal tidak menggagalkan perubahan status', function () {
    $batal = ajukanBatal('OT-SALAH-KETIK');

    $this->patchJson("/api/v1/pembatalan/{$batal->id}/status", ['status' => 'disetujui'], kepala())
        ->assertOk();

    expect($batal->fresh()->status)->toBe('disetujui');
});
