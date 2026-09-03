<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rujukan\KodeRujukan;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Pelanggan: orang, bukan pesanan.
 *
 * Seluruh layar lain menyusun data menurut pesanan. Yang tidak terjawab di
 * mana pun: siapa saja yang pernah memesan, dan siapa yang sudah memesan
 * berkali-kali — pertanyaan yang muncul justru saat paling berharga, ketika
 * ada trip baru yang perlu ditawarkan.
 */
function kepalaPelanggan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-pelanggan');

    return ['X-Orcha-Key' => 'kunci-uji-pelanggan', 'Accept' => 'application/json'];
}

function tripPelanggan(string $nama, string $whatsapp, ?string $email = null, string $status = 'lunas'): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
    ]);

    return PendaftaranOpenTrip::create([
        'nama' => $nama, 'whatsapp' => $whatsapp, 'email' => $email,
        'jumlah_peserta' => 1, 'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name, 'status' => $status,
    ])->fresh();
}

function sewaPelanggan(string $nama, string $whatsapp): PenyewaanKendaraan
{
    // Bentuknya disalin dari SewaKendaraanTest — nama kolomnya bahasa Inggris,
    // peninggalan tabel cars bawaan yang belum ikut diterjemahkan.
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'price_per_day' => 350000, 'harga_per_jam' => 55000,
        'harga_12_jam' => 280000, 'harga_sopir' => 150000,
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual', 'Matic'],
        'capacity' => 7, 'is_available' => true,
    ]);

    return PenyewaanKendaraan::create([
        'nama' => $nama, 'whatsapp' => $whatsapp, 'car_id' => $mobil->id,
        'nama_kendaraan' => $mobil->name, 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 2, 'estimasi_biaya' => 700000,
        'tanggal_mulai' => now()->addDays(3)->toDateString(),
        'jam_mulai' => '08:00', 'status' => 'baru',
    ])->fresh();
}

test('nomor yang ditulis berbeda-beda tetap satu orang', function () {
    /*
     | Inti layar ini, dan yang paling mudah salah. Nomornya tersimpan apa
     | adanya seperti yang diketik: "+62812...", "0812...", dan
     | "0812-3456-7890" adalah tiga teks berbeda untuk satu orang yang sama.
     |
     | GROUP BY di SQL akan menghitungnya sebagai tiga pelanggan — dan daftar
     | yang memecah satu orang jadi tiga membuat seluruh angkanya tidak bisa
     | dipercaya.
     */
    tripPelanggan('Budi', '081234567890');
    tripPelanggan('Budi', '+6281234567890');
    tripPelanggan('Budi', '0812-3456-7890');

    $data = $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['jumlah_trip'])->toBe(3);
});

test('pelanggan trip dan pelanggan sewa berkumpul di satu baris', function () {
    // Orang yang pernah ikut trip lalu menyewa mobil adalah satu pelanggan,
    // bukan dua. Memisahkannya menyembunyikan justru pelanggan yang paling
    // sering kembali.
    tripPelanggan('Budi', '081234567890');
    sewaPelanggan('Budi', '081234567890');

    $baris = $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data.0');

    expect($baris['jumlah_trip'])->toBe(1)
        ->and($baris['jumlah_sewa'])->toBe(1);
});

test('surel diambil dari pesanan mana pun yang mencantumkannya', function () {
    /*
     | Kolomnya opsional, jadi orang yang sama bisa mengisinya sekali dan
     | mengosongkannya lain kali. Yang berguna alamat yang PERNAH diberikannya,
     | bukan yang kebetulan ada di pesanan terakhir.
     */
    tripPelanggan('Budi', '081234567890', 'budi@contoh.test');
    tripPelanggan('Budi', '081234567890', null);

    expect($this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data.0.email'))
        ->toBe('budi@contoh.test');
});

test('yang terakhir memesan berada di atas', function () {
    // Itu yang paling mungkin sedang dibicarakan saat layar ini dibuka.
    $lama = tripPelanggan('Lama', '081200000001');

    // created_at tidak fillable, jadi update() mengabaikannya diam-diam —
    // dan tesnya lulus tanpa pernah menguji urutannya.
    $lama->forceFill(['created_at' => now()->subMonths(3)])->save();

    tripPelanggan('Baru', '081200000002');

    $data = $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data');

    expect($data[0]['nama'])->toBe('Baru')
        ->and($data[1]['nama'])->toBe('Lama');
});

test('pesanan batal tetap terhitung, tetapi ditandai', function () {
    /*
     | Orangnya tetap pelanggan — ia pernah sampai ke titik mengisi formulir.
     | Tetapi menawarkan trip baru kepada orang yang seluruh pesanannya batal
     | menuntut kalimat pembuka yang berbeda, dan itu hanya bisa dipilih kalau
     | keadaannya terlihat.
     */
    tripPelanggan('Budi', '081234567890', null, 'batal');

    $baris = $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data.0');

    expect($baris['jumlah_trip'])->toBe(1)
        ->and($baris['jumlah_batal'])->toBe(1);
});

test('keadaan kode rujukan ikut tergambar', function () {
    $daftar = tripPelanggan('Budi', '081234567890');
    KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);

    $baris = $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data.0');

    expect($baris['kode_rujukan'])->toStartWith('BUDI-')
        ->and($baris['rujukan_dipakai'])->toBe(0)
        ->and($baris['komisi_belum_dibayar'])->toBe(0);
});

test('pelanggan tanpa kode rujukan dikirim null, bukan kosong yang menyesatkan', function () {
    tripPelanggan('Budi', '081234567890');

    expect($this->getJson('/api/v1/pelanggan', kepalaPelanggan())->json('data.0.kode_rujukan'))
        ->toBeNull();
});

test('pencarian menemukan orang lewat nomor meski ejaannya berbeda', function () {
    // Admin menyalin nomor dari WhatsApp, yang menuliskannya "+62 812-3456-7890".
    tripPelanggan('Budi', '081234567890');

    $data = $this->getJson('/api/v1/pelanggan?cari=%2B62%20812-3456-7890', kepalaPelanggan())
        ->json('data');

    expect($data)->toHaveCount(1);
});

/* --------------------------- KODE RUJUKAN --------------------------- */

test('kode rujukan bisa dibuatkan dari layar pelanggan', function () {
    tripPelanggan('Budi Santoso', '081234567890');

    $hasil = $this->postJson('/api/v1/pelanggan/kode-rujukan', [
        'nama' => 'Budi Santoso', 'whatsapp' => '081234567890',
    ], kepalaPelanggan())->assertCreated()->json('data');

    expect($hasil['kode'])->toStartWith('BUDI-')
        ->and($hasil['baru'])->toBeTrue();
});

test('membuatkan kode dua kali mengembalikan kode yang sama', function () {
    /*
     | Kode kedua untuk orang yang sama memecah imbalannya jadi dua catatan
     | terpisah, dan yang menagih nanti menagih keduanya.
     |
     | Dikembalikan tanpa galat, bukan ditolak: admin yang menekan tombolnya
     | dua kali sedang meminta hal yang sama, bukan melakukan kesalahan.
     */
    tripPelanggan('Budi', '081234567890');

    $satu = $this->postJson('/api/v1/pelanggan/kode-rujukan',
        ['nama' => 'Budi', 'whatsapp' => '081234567890'], kepalaPelanggan())->json('data.kode');

    $dua = $this->postJson('/api/v1/pelanggan/kode-rujukan',
        ['nama' => 'Budi', 'whatsapp' => '+6281234567890'], kepalaPelanggan())->json('data');

    expect($dua['kode'])->toBe($satu)
        ->and($dua['baru'])->toBeFalse()
        ->and(KodeRujukan::count())->toBe(1);
});

test('kode asalnya menyebut pesanan pertama orang itu', function () {
    // Saat komisi dibayarkan, pertanyaan "ini siapa, ya?" hampir selalu muncul.
    $daftar = tripPelanggan('Budi', '081234567890');

    $this->postJson('/api/v1/pelanggan/kode-rujukan',
        ['nama' => 'Budi', 'whatsapp' => '081234567890'], kepalaPelanggan());

    expect(KodeRujukan::first()->kode_pendaftaran_asal)->toBe($daftar->kode);
});

test('membuka daftar TIDAK membuat kode apa pun', function () {
    /*
     | Kalau kodenya dibuat sendiri saat daftarnya digambar, setiap orang yang
     | pernah memesan sekali mendapat kode — termasuk yang pesanannya batal,
     | dan termasuk saat admin cuma sedang mencari nomor telepon seseorang.
     */
    tripPelanggan('Budi', '081234567890');

    $this->getJson('/api/v1/pelanggan', kepalaPelanggan())->assertOk();

    expect(KodeRujukan::count())->toBe(0);
});

test('nomor yang tidak terbaca ditolak, bukan menghasilkan kode tanpa pemilik', function () {
    $this->postJson('/api/v1/pelanggan/kode-rujukan',
        ['nama' => 'Budi', 'whatsapp' => 'bukan nomor'], kepalaPelanggan())
        ->assertStatus(422);
});

test('tanpa kunci API, jalurnya tertutup', function () {
    $this->getJson('/api/v1/pelanggan', ['Accept' => 'application/json'])->assertStatus(401);
});
