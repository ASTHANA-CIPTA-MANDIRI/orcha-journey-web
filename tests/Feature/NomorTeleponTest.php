<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\NomorTelepon;
use Livewire\Volt\Volt;

test('nomor dirapikan apa pun cara menuliskannya', function (string $ditulis, string $rapi, string $wa) {
    expect(NomorTelepon::rapi($ditulis))->toBe($rapi)
        ->and(NomorTelepon::wa($ditulis))->toBe($wa);
})->with([
    ['0812 3456 7890', '0812-3456-7890', '6281234567890'],
    ['+62 812-3456-7890', '0812-3456-7890', '6281234567890'],
    ['6281234567890', '0812-3456-7890', '6281234567890'],
    // Lupa angka nol di depan
    ['81234567890', '0812-3456-7890', '6281234567890'],
    ['(0812) 3456-7890', '0812-3456-7890', '6281234567890'],
]);

test('nomor yang tidak masuk akal ditolak', function (?string $nomor) {
    expect(NomorTelepon::sah($nomor))->toBeFalse();
})->with([null, '', 'abcd', '0812', '0812345678901234567']);

test('isian whatsapp dirapikan saat kursor meninggalkan kolom', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);

    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid)
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '+62 812 3456 7890')
        ->assertSet('whatsapp', '0812-3456-7890')
        ->set('jumlahPeserta', 1)
        ->set('peserta', [['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja']])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    // Yang tersimpan bentuk baku yang sama dengan yang dilihat pelanggan
    expect(PendaftaranOpenTrip::firstOrFail()->whatsapp)->toBe('0812-3456-7890');
});

test('nomor yang belum benar ditegur dengan contoh bentuknya', function () {
    $komponen = Volt::test('public.open-trip.pendaftaran')
        ->set('nama', 'Siti Aminah')
        ->set('whatsapp', '0812')
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasErrors(['whatsapp']);

    expect($komponen->errors()->first('whatsapp'))->toContain('0812-3456-7890');
});

test('semua formulir publik memakai perapi yang sama', function (string $berkas, string $medan) {
    $isi = file_get_contents(base_path("resources/views/livewire/public/{$berkas}.blade.php"));

    expect($isi)->toContain('NomorTelepon::sah')
        ->and($isi)->toContain('NomorTelepon::rapi')
        // Ditandai supaya ikut dirapikan sambil diketik
        ->and($isi)->toContain('orcha-telp')
        ->and($isi)->toContain('<x-skrip-isian />')
        ->and($isi)->toContain('placeholder="0812-3456-7890"');
})->with([
    ['open-trip/pendaftaran', 'd-wa'],
    ['open-trip/pembatalan', 'pb-wa'],
    ['sewa-kendaraan/pemesanan', 'sk-wa'],
    ['kontak/index', 'kontak-wa'],
]);
