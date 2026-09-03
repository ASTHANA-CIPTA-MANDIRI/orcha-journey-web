<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;

/**
 * Pembagian bus dan kamar.
 *
 * Rombongan sekolah 120 orang naik tiga bus dan menempati puluhan kamar.
 * Selama ini pembagiannya di kertas — dan kertas itu yang dibawa tour leader,
 * yang tidak punya salinannya kalau hilang, dan yang tidak bisa dibandingkan
 * dengan daftar peserta yang sebenarnya.
 */
function kepalaBus(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-bus');

    return ['X-Orcha-Key' => 'kunci-uji-bus', 'Accept' => 'application/json'];
}

function rombonganBus(array $peserta): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Study Tour Uji', 'category' => 'study_tour', 'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays(20)->toDateString(),
    ]);

    return PendaftaranOpenTrip::create([
        'nama' => 'Panitia', 'whatsapp' => '081234567890',
        'jumlah_peserta' => count($peserta), 'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name, 'daftar_peserta' => $peserta,
    ])->fresh();
}

test('peserta bisa dibagi ke bus dan kamar', function () {
    $daftar = rombonganBus([
        ['nama' => 'Budi', 'bus' => 'Bus 1', 'kamar' => '201'],
        ['nama' => 'Sari', 'bus' => 'Bus 1', 'kamar' => '202'],
        ['nama' => 'Rian', 'bus' => 'Bus 2', 'kamar' => '201'],
    ]);

    expect($daftar->bus_per_kelompok)->toBe([
        ['kelompok' => 'Bus 1', 'anggota' => ['Budi', 'Sari']],
        ['kelompok' => 'Bus 2', 'anggota' => ['Rian']],
    ]);

    expect($daftar->kamar_per_kelompok)->toBe([
        ['kelompok' => '201', 'anggota' => ['Budi', 'Rian']],
        ['kelompok' => '202', 'anggota' => ['Sari']],
    ]);
});

test('yang belum dibagi dikumpulkan, bukan dibuang', function () {
    /*
     | Rombongan yang setengah dibagi adalah keadaan yang paling sering
     | terjadi — pembagiannya dikerjakan beberapa hari sebelum berangkat.
     | Menyembunyikan sisanya membuat orang mengira pembagiannya sudah selesai,
     | dan yang menemukan sisanya adalah tour leader di parkiran.
     */
    $daftar = rombonganBus([
        ['nama' => 'Budi', 'bus' => 'Bus 1'],
        ['nama' => 'Sari'],
        ['nama' => 'Rian'],
    ]);

    expect($daftar->bus_per_kelompok)->toBe([
        ['kelompok' => 'Bus 1', 'anggota' => ['Budi']],
        ['kelompok' => '', 'anggota' => ['Sari', 'Rian']],
    ]);
});

test('data peserta lama tanpa bus dan kamar tetap terbaca', function () {
    // Pendaftaran lama menyimpan nama saja sebagai deretan teks. Ia tidak
    // boleh meledak hanya karena kolom baru ditambahkan.
    $daftar = rombonganBus(['Budi', 'Sari']);

    expect($daftar->peserta[0]['nama'])->toBe('Budi')
        ->and($daftar->peserta[0]['bus'])->toBeNull()
        ->and($daftar->peserta[0]['kamar'])->toBeNull();
});

test('pembagian bisa disimpan lewat jalur peserta', function () {
    $daftar = rombonganBus([['nama' => 'Budi'], ['nama' => 'Sari']]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [
            ['nama' => 'Budi', 'bus' => 'Bus 1', 'kamar' => '201'],
            ['nama' => 'Sari', 'bus' => 'Bus 1', 'kamar' => '201'],
        ],
    ], kepalaBus())->assertOk();

    expect($daftar->fresh()->bus_per_kelompok)
        ->toBe([['kelompok' => 'Bus 1', 'anggota' => ['Budi', 'Sari']]]);
});

test('pembagian ikut dikirim ke lemon lewat satu jalur yang sama', function () {
    /*
     | Dikelompokkan di Orcha, bukan di lemon. Pengelompokan yang dikerjakan
     | dua kali di dua tempat akan berbeda suatu saat — dan yang membacanya
     | tidak punya cara tahu mana yang benar.
     */
    $daftar = rombonganBus([
        ['nama' => 'Budi', 'bus' => 'Bus 1', 'kamar' => '201'],
    ]);

    $data = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kepalaBus())
        ->assertOk()
        ->json('data');

    expect($data['bus_per_kelompok'])->toBe([['kelompok' => 'Bus 1', 'anggota' => ['Budi']]])
        // Inti ujinya: nomor kamar "201" harus SELAMAT melewati JSON.
        ->and($data['kamar_per_kelompok'])->toBe([['kelompok' => '201', 'anggota' => ['Budi']]])
        ->and($data['peserta'][0]['bus'])->toBe('Bus 1');
});

test('pembagian bisa diisi sejak rombongannya didaftarkan', function () {
    $paket = TravelPackage::create([
        'name' => 'Study Tour Uji', 'category' => 'study_tour', 'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays(20)->toDateString(),
    ]);

    $this->postJson('/api/v1/pendaftaran', [
        'travel_package_id' => $paket->id, 'nama' => 'Panitia',
        'whatsapp' => '081234567890', 'jumlah_peserta' => 2,
        'peserta' => [
            ['nama' => 'Budi', 'bus' => 'Bus 1', 'kamar' => '201'],
            ['nama' => 'Sari', 'bus' => 'Bus 2', 'kamar' => '202'],
        ],
    ], kepalaBus())->assertCreated();

    expect(PendaftaranOpenTrip::first()->bus_per_kelompok)->toBe([
        ['kelompok' => 'Bus 1', 'anggota' => ['Budi']],
        ['kelompok' => 'Bus 2', 'anggota' => ['Sari']],
    ]);
});

test('nomor kamar yang berupa angka selamat melewati JSON', function () {
    /*
     | Bug nyata, dan yang paling mudah lolos.
     |
     | PHP memaksa kunci larik yang berupa angka jadi integer, dan larik
     | ber-kunci-integer yang dikirim lewat JSON pulang sebagai daftar biasa —
     | kunci "201" berubah jadi indeks 0. Daftar kamar yang diserahkan ke hotel
     | lalu tertulis "Kamar 0", dan tidak ada satu pun galat yang
     | menjelaskannya.
     |
     | Nomor kamar SELALU angka, jadi ini bukan kasus pinggiran melainkan kasus
     | yang biasa.
     */
    $daftar = rombonganBus([
        ['nama' => 'Budi', 'kamar' => '201'],
        ['nama' => 'Sari', 'kamar' => '202'],
    ]);

    $data = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kepalaBus())->json('data');

    expect(collect($data['kamar_per_kelompok'])->pluck('kelompok')->all())
        ->toBe(['201', '202']);
});
