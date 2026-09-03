<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;

/**
 * Saringan "perlu ditagih" di jalur pendaftaran.
 *
 * Pengingat otomatis mengurus yang normal — yang membaca suratnya lalu
 * mentransfer. Yang tersisa justru yang TIDAK bergerak setelah dikirimi, dan
 * itu hanya bisa diselesaikan lewat telepon.
 *
 * Tanpa saringan ini admin membuka pendaftaran satu per satu dan menghitung
 * tanggalnya di kepala — pekerjaan yang cukup melelahkan sehingga tidak pernah
 * benar-benar dikerjakan.
 */
function kepalaTagihan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-tagihan');

    return ['X-Orcha-Key' => 'kunci-uji-tagihan', 'Accept' => 'application/json'];
}

function paketTagihan(int $hariLagi): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Trip '.$hariLagi, 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
        'tanggal_berangkat' => now()->addDays($hariLagi)->toDateString(),
    ]);
}

function daftarTagihan(int $hariLagi, string $status): PendaftaranOpenTrip
{
    $paket = paketTagihan($hariLagi);

    return PendaftaranOpenTrip::create([
        'nama' => 'Pemesan', 'whatsapp' => '0812', 'jumlah_peserta' => 2,
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'tanggal_berangkat' => $paket->tanggal_berangkat, 'status' => $status,
    ])->fresh();
}

beforeEach(function () {
    config()->set('orcha.pembayaran.pelunasan_hari_sebelum', 5);
    config()->set('orcha.pengingat.pelunasan_hari_sebelum_batas', 3);
});

test('hanya yang sudah DP dan sudah dekat yang masuk daftar tagihan', function () {
    $mepet = daftarTagihan(4, 'dp_masuk');
    $jauh = daftarTagihan(60, 'dp_masuk');
    $lunas = daftarTagihan(4, 'lunas');
    $belumBayar = daftarTagihan(4, 'baru');

    $kode = collect(
        $this->getJson('/api/v1/pendaftaran?perlu_ditagih=1', kepalaTagihan())
            ->assertOk()
            ->json('data')
    )->pluck('kode');

    expect($kode)->toContain($mepet->kode)
        ->and($kode)->not->toContain($jauh->kode)
        ->and($kode)->not->toContain($lunas->kode)
        ->and($kode)->not->toContain($belumBayar->kode);
});

test('yang sudah berangkat tidak lagi masuk daftar tagihan', function () {
    /*
     | Ini penyelamatan kursi, bukan penagihan biasa. Yang tanggalnya sudah
     | lewat tidak punya kursi untuk diselamatkan, dan mencampurnya membuat
     | daftar ini memanjang sendiri sepanjang tahun sampai tidak ada yang
     | membukanya lagi.
     */
    $lewat = daftarTagihan(-2, 'dp_masuk');

    $kode = collect(
        $this->getJson('/api/v1/pendaftaran?perlu_ditagih=1', kepalaTagihan())->json('data')
    )->pluck('kode');

    expect($kode)->not->toContain($lewat->kode);
});

test('yang paling mepet berada di atas, bukan yang paling baru mendaftar', function () {
    /*
     | Daftar tagihan yang diurutkan menurut waktu pendaftaran menaruh yang
     | berangkat besok di halaman tiga — dan yang paling mendesak justru yang
     | paling tidak terlihat.
     */
    $jauh = daftarTagihan(7, 'dp_masuk');   // didaftarkan lebih dulu
    $dekat = daftarTagihan(2, 'dp_masuk');  // didaftarkan belakangan

    $kode = collect(
        $this->getJson('/api/v1/pendaftaran?perlu_ditagih=1', kepalaTagihan())->json('data')
    )->pluck('kode')->all();

    expect($kode[0])->toBe($dekat->kode)
        ->and($kode[1])->toBe($jauh->kode);
});

test('tanpa saringan, daftarnya tetap seperti semula', function () {
    // Saringannya menyala hanya bila diminta. Kalau tidak, layar pendaftaran
    // biasa mendadak menyembunyikan sebagian besar isinya.
    $lunas = daftarTagihan(4, 'lunas');

    $kode = collect(
        $this->getJson('/api/v1/pendaftaran', kepalaTagihan())->json('data')
    )->pluck('kode');

    expect($kode)->toContain($lunas->kode);
});

test('selisih harinya ikut dikirim supaya lemon tidak menghitung sendiri', function () {
    daftarTagihan(3, 'dp_masuk');

    $baris = $this->getJson('/api/v1/pendaftaran?perlu_ditagih=1', kepalaTagihan())
        ->json('data.0');

    expect($baris['hari_ke_berangkat'])->toBe(3)
        // Belum pernah dikirimi pengingat: nada teleponnya berbeda dengan
        // orang yang sudah dikirimi dan tetap diam.
        ->and($baris['pengingat_pelunasan_pada'])->toBeNull();
});
