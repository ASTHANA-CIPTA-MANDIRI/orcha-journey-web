<?php

use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

const KUNCI_PESAN = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_PESAN);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaPesan(): array
{
    return [
        'X-Orcha-Key' => KUNCI_PESAN,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

function kirimPesan(array $ubah = []): PesanKontak
{
    return PesanKontak::create(array_merge([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test',
        'keperluan' => 'open_trip',
        'pesan' => 'Halo, saya mau tanya jadwal open trip terdekat.',
    ], $ubah));
}

test('detail pesan menemukan pesanan pengirim lewat nomor yang berbeda bentuk', function () {
    $pesan = kirimPesan();

    // Nomornya tersimpan bertanda hubung di pemesanan, polos di formulir
    // kontak — dan keduanya orang yang sama.
    PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso', 'whatsapp' => '0812-3456-7890', 'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Banyuwangi', 'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'status' => 'dp_masuk',
    ]);

    $balasan = $this->getJson("/api/v1/pesan/{$pesan->id}", kepalaPesan())->assertOk()->json('data');

    expect($balasan['pesanan_terkait'])->toHaveCount(1)
        ->and($balasan['pesanan_terkait'][0]['keterangan'])->toBe('Open Trip Banyuwangi')
        ->and($balasan['pesanan_terkait'][0]['status_label'])->toBe('DP Masuk');
});

test('pesanan sewa kendaraan ikut terbaca lewat email yang sama', function () {
    $pesan = kirimPesan(['whatsapp' => '089999999999']);

    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil', 'transmission' => 'Matic',
        'capacity' => 7, 'price_per_day' => 500000, 'is_available' => true,
        'transmisi_tersedia' => ['Matic'],
    ]);

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Budi',
        'whatsapp' => '0899', 'email' => 'budi@contoh.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => now()->addWeek()->toDateString(),
        'jam_mulai' => '08:00', 'estimasi_biaya' => 1000000, 'status' => 'baru',
    ]);

    $balasan = $this->getJson("/api/v1/pesan/{$pesan->id}", kepalaPesan())->assertOk()->json('data');

    expect($balasan['pesanan_terkait'])->toHaveCount(1)
        ->and($balasan['pesanan_terkait'][0]['jenis'])->toBe('sewa_kendaraan');
});

test('pesan sebelumnya dari pengirim yang sama ikut terbawa', function () {
    $lama = kirimPesan(['pesan' => 'Pertanyaan pertama saya, belum dibalas.']);
    $baru = kirimPesan(['pesan' => 'Halo, saya tanya lagi.']);

    // Orang yang sudah dua kali bertanya perlu diperlakukan berbeda dari yang
    // baru pertama menulis.
    $balasan = $this->getJson("/api/v1/pesan/{$baru->id}", kepalaPesan())->assertOk()->json('data');

    expect($balasan['pesan_lain'])->toHaveCount(1)
        ->and($balasan['pesan_lain'][0]['id'])->toBe($lama->id)
        ->and($balasan['pesan_lain'][0]['sudah_dibaca'])->toBeFalse();
});

test('daftar pesan tetap ringan, tanpa pencarian pesanan per baris', function () {
    kirimPesan();

    // Data rinci hanya untuk satu pesan yang dibuka; di daftar sepuluh baris,
    // beberapa query per baris tidak sebanding dengan manfaatnya.
    $baris = $this->getJson('/api/v1/pesan', kepalaPesan())->assertOk()->json('data.0');

    expect($baris)->not->toHaveKey('pesanan_terkait')
        ->and($baris)->not->toHaveKey('pesan_lain')
        ->and($baris['nama'])->toBe('Budi Santoso');
});

test('jumlah pesan belum dibaca ikut dikirim, terlepas dari saringannya', function () {
    kirimPesan();
    kirimPesan(['nama' => 'Rina']);
    kirimPesan(['nama' => 'Sudah dibaca', 'dibaca_pada' => now()]);

    $semua = $this->getJson('/api/v1/pesan', kepalaPesan())->assertOk()->json('meta');

    expect($semua['total'])->toBe(3)
        ->and($semua['belum_dibaca'])->toBe(2);

    // Angkanya tetap sama saat saringan "belum dibaca" sedang menyala —
    // yang dihitung adalah keadaan kotak masuk, bukan isi halaman ini.
    $tersaring = $this->getJson('/api/v1/pesan?belum_dibaca=1', kepalaPesan())->assertOk()->json('meta');

    expect($tersaring['total'])->toBe(2)
        ->and($tersaring['belum_dibaca'])->toBe(2);
});

test('pesan tanpa nomor maupun email tidak mengaitkan pesanan siapa pun', function () {
    $pesan = kirimPesan(['whatsapp' => '', 'email' => null]);

    PendaftaranOpenTrip::create([
        'nama' => 'Orang Lain', 'whatsapp' => '0811', 'jumlah_peserta' => 1,
        'nama_paket' => 'Open Trip Lain', 'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    // Tanpa penjagaan ini, pencocokan "LIKE %%" akan menarik seluruh tabel dan
    // menempelkan pesanan orang asing ke pesan ini.
    $balasan = $this->getJson("/api/v1/pesan/{$pesan->id}", kepalaPesan())->assertOk()->json('data');

    expect($balasan['pesanan_terkait'])->toBe([]);
});

test('perhatian pesan memisahkan yang baru dari yang menganggur lewat sehari', function () {
    /*
     | Angka untuk penanda di bilah samping dan lonceng lemon.
     |
     | Dihitung dari yang BELUM DIBACA, bukan dari yang belum dibalas —
     | balasannya dikirim lewat WhatsApp, di luar sistem ini, jadi Orcha tidak
     | pernah tahu sebuah pesan sudah dijawab atau belum. Satu-satunya penutup
     | yang benar-benar tercatat adalah "sudah dibaca".
     */
    kirimPesan(['nama' => 'Baru Masuk']);

    // Sudah dibaca: tidak menuntut apa pun lagi
    kirimPesan(['nama' => 'Sudah Dibaca', 'dibaca_pada' => now()]);

    // Belum dibaca dan sudah lewat sehari: inilah yang menyakiti
    $lama = kirimPesan(['nama' => 'Terlantar']);
    $lama->forceFill(['created_at' => now()->subDays(3)])->save();

    $data = $this->getJson('/api/v1/pesan/perhatian', kepalaPesan())
        ->assertOk()->json('data');

    expect($data['belum_dibaca'])->toBe(2)
        ->and($data['baru'])->toBe(1)
        ->and($data['lama'])->toBe(1);
});

test('pesan yang sudah dibaca tidak terhitung di perhatian', function () {
    kirimPesan(['dibaca_pada' => now()]);
    kirimPesan(['dibaca_pada' => now()->subWeek()]);

    $data = $this->getJson('/api/v1/pesan/perhatian', kepalaPesan())
        ->assertOk()->json('data');

    expect($data)->toBe(['belum_dibaca' => 0, 'baru' => 0, 'lama' => 0]);
});

test('jalur perhatian tidak terbaca sebagai nomor pesan', function () {
    // Rutenya harus terdaftar SEBELUM /pesan/{pesan}; kalau tidak, "perhatian"
    // ditangkap sebagai nomor dan jawabannya 404.
    $this->getJson('/api/v1/pesan/perhatian', kepalaPesan())
        ->assertOk()->assertJsonStructure(['data' => ['belum_dibaca', 'baru', 'lama']]);
});
