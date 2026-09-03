<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;

/**
 * Mendaftarkan rombongan dari sisi admin.
 *
 * Private trip dan study tour tidak pernah mendaftar lewat website, dan itu
 * bukan kekurangan melainkan bentuk jualannya: harganya dirundingkan, jumlah
 * pesertanya berubah sampai menit terakhir, dan seluruh percakapannya di
 * WhatsApp.
 *
 * Tetapi begitu disepakati, rombongannya HARUS masuk sistem — kalau tidak, ia
 * tidak punya kode pemesanan, tidak bisa mengisi riwayat kesehatan, tidak
 * masuk manifes tour leader, dan tidak terhitung di laporan keuntungan.
 */
function kepalaRombongan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-rombongan');

    return ['X-Orcha-Key' => 'kunci-uji-rombongan', 'Accept' => 'application/json'];
}

function paketRombongan(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Study Tour SMA Uji',
        'category' => 'study_tour',
        'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays(30)->toDateString(),
    ], $ubah));
}

function isianRombongan(TravelPackage $paket, array $ubah = []): array
{
    return array_merge([
        'travel_package_id' => $paket->id,
        'nama' => 'Panitia Sekolah',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 3,
        'peserta' => [
            ['nama' => 'Budi', 'titik_jemput' => 'Sekolah'],
            ['nama' => 'Sari', 'titik_jemput' => 'Sekolah'],
            ['nama' => 'Rian', 'titik_jemput' => 'Sekolah'],
        ],
    ], $ubah);
}

test('rombongan yang didaftarkan admin mendapat kode pemesanan', function () {
    $paket = paketRombongan();

    $data = $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan())
        ->assertCreated()
        ->json('data');

    expect($data['kode'])->toStartWith('OT-')
        ->and($data['nama'])->toBe('Panitia Sekolah')
        ->and($data['jumlah_peserta'])->toBe(3);
});

test('tautan riwayat kesehatan ikut dikirim, dirakit di Orcha', function () {
    /*
     | Alamat publiknya milik Orcha, bukan milik lemon. Merakitnya di lemon
     | berarti nama rutenya ditebak dari sana, dan tebakan itu diam saat
     | rutenya berubah — tautan yang salah membawa panitia ke halaman galat,
     | dan yang menyalahkan dirinya sendiri panitianya.
     */
    $paket = paketRombongan();

    $data = $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan())
        ->json('data');

    expect($data['tautan_kesehatan'])
        ->toContain('/riwayat-kesehatan')
        ->toContain('kode='.$data['kode']);
});

test('nama pesertanya tersimpan lengkap dengan titik jemputnya', function () {
    // Tanpa nama peserta, rombongannya tidak bisa masuk manifes panggil-nama —
    // dan itu baru ketahuan saat rombongannya sudah berkumpul.
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan());

    $daftar = PendaftaranOpenTrip::first();

    expect($daftar->peserta)->toHaveCount(3)
        ->and($daftar->peserta[0]['nama'])->toBe('Budi')
        ->and($daftar->peserta[0]['titik_jemput'])->toBe('Sekolah');
});

test('tanggal berangkatnya ikut paket, bukan diketik ulang', function () {
    // Mengetiknya ulang berarti dua tempat menyimpan tanggal yang sama, dan
    // keduanya akan berbeda begitu jadwalnya digeser.
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan());

    expect(PendaftaranOpenTrip::first()->tanggal_berangkat->toDateString())
        ->toBe($paket->tanggal_berangkat->toDateString());
});

test('harga rundingan yang diisi admin menang atas harga paket', function () {
    /*
     | Paket private trip dan study tour sering belum berharga di sistem karena
     | memang dihitung per rombongan. Tanpa jalan memasukkannya, pendaftarannya
     | masuk dengan tagihan nol dan seluruh laporan keuntungan ikut salah tanpa
     | ada yang menyadarinya.
     */
    $paket = paketRombongan(['price' => 500000]);

    $this->postJson('/api/v1/pendaftaran',
        isianRombongan($paket, ['harga_jual' => 750000, 'harga_modal' => 400000]),
        kepalaRombongan());

    $daftar = PendaftaranOpenTrip::first();

    expect($daftar->harga_jual)->toBe(750000)
        ->and($daftar->harga_modal)->toBe(400000);
});

test('tanpa harga rundingan, harga paket yang dipakai', function () {
    $paket = paketRombongan(['price' => 500000]);

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan());

    expect(PendaftaranOpenTrip::first()->harga_jual)->toBe(500000);
});

test('statusnya baru, bukan langsung dianggap sudah membayar', function () {
    /*
     | Memasukkan rombongan bukan berarti uangnya sudah diterima, dan status
     | yang memajukan dirinya sendiri membuat laporan keuangan menyebut uang
     | yang belum ada.
     */
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan());

    expect(PendaftaranOpenTrip::first()->status)->toBe('baru');
});

test('paket yang tidak ada ditolak', function () {
    $this->postJson('/api/v1/pendaftaran', [
        'travel_package_id' => 99999, 'nama' => 'Panitia',
        'whatsapp' => '081234567890', 'jumlah_peserta' => 3,
    ], kepalaRombongan())->assertStatus(422);
});

test('nama pemesan dan nomor WhatsApp wajib', function () {
    // Nomornya satu-satunya jalur yang pasti sampai — dan lewat sanalah kode
    // pemesanan beserta tautannya dikirimkan.
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', ['travel_package_id' => $paket->id],
        kepalaRombongan())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nama', 'whatsapp', 'jumlah_peserta']);
});

test('boleh mendaftarkan tanpa nama peserta dulu', function () {
    /*
     | Panitia sering menyepakati jumlahnya lebih dulu dan menyusul daftar
     | namanya seminggu kemudian. Menahan pendaftarannya sampai daftar nama
     | lengkap berarti rombongannya tidak punya kode pemesanan justru pada
     | masa ia paling perlu dikonfirmasi.
     */
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran',
        isianRombongan($paket, ['peserta' => []]), kepalaRombongan())
        ->assertCreated();

    expect(PendaftaranOpenTrip::first()->peserta)->toBe([]);
});

test('pendaftarannya ikut tercatat di jejak audit', function () {
    // Rombongan yang muncul di daftar tanpa jejak siapa yang memasukkannya
    // adalah pertanyaan yang tidak bisa dijawab lagi sebulan kemudian.
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket), kepalaRombongan());

    expect(\App\Models\JejakAudit::where('aksi', 'daftarkan rombongan dari admin')->exists())
        ->toBeTrue();
});

test('tanpa kunci API, jalurnya tertutup', function () {
    $paket = paketRombongan();

    $this->postJson('/api/v1/pendaftaran', isianRombongan($paket),
        ['Accept' => 'application/json'])->assertStatus(401);
});
