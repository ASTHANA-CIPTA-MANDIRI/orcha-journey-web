<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rujukan\KodeRujukan;

/**
 * Jalur kode rujukan untuk panel admin.
 *
 * Yang paling sering ditanyakan bukan daftar kodenya, melainkan komisinya:
 * siapa membawa berapa pendaftaran, dan berapa yang belum dibayarkan
 * kepadanya. Tanpa itu, satu-satunya cara mengetahui komisi mana yang sudah
 * dibayar adalah mengingatnya.
 */
function kepalaRujukan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-rujukan');

    return ['X-Orcha-Key' => 'kunci-uji-rujukan', 'Accept' => 'application/json'];
}

function pakaiKode(KodeRujukan $kode, string $whatsapp = '081200000000'): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
    ]);

    return PendaftaranOpenTrip::create([
        'nama' => 'Pendaftar', 'whatsapp' => $whatsapp, 'jumlah_peserta' => 1,
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
        'kode_rujukan' => $kode->kode,
    ])->fresh();
}

beforeEach(function () {
    config()->set('orcha.rujukan', ['aktif' => true, 'potongan' => 50000, 'imbalan' => 75000]);
});

test('daftar kode menyebut berapa kali dipakai dan berapa komisinya', function () {
    $kode = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
    pakaiKode($kode, '081200000001');
    pakaiKode($kode, '081200000002');

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaRujukan())
        ->assertOk()
        ->json('data.0');

    expect($baris['jumlah_dipakai'])->toBe(2)
        ->and($baris['imbalan_total'])->toBe(150000)
        ->and($baris['imbalan_belum_dibayar'])->toBe(150000);
});

test('kode yang belum pernah dipakai mengirim nol, bukan null', function () {
    /*
     | withSum mengembalikan NULL, bukan 0, saat tidak ada barisnya. Yang
     | membaca angkanya di lemon akan menampilkan "Rp " tanpa angka, dan
     | penjumlahannya di layar jadi kosong.
     */
    KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaRujukan())->json('data.0');

    expect($baris['imbalan_total'])->toBe(0)
        ->and($baris['imbalan_belum_dibayar'])->toBe(0);
});

test('nomor yang sudah punya kode ditolak, dengan menyebut kodenya', function () {
    /*
     | Kode kedua untuk orang yang sama memecah imbalannya jadi dua catatan
     | terpisah, dan yang menagih nanti menagih keduanya — sementara laporan
     | kita hanya menunjukkan salah satunya.
     */
    $ada = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);

    $this->postJson('/api/v1/kode-rujukan', [
        'nama' => 'Budi Lagi', 'whatsapp' => '+6281234567890',
    ], kepalaRujukan())
        ->assertStatus(422)
        ->assertSee($ada->kode);
});

test('membayar imbalan menandai waktunya', function () {
    $kode = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
    $daftar = pakaiKode($kode);

    $this->postJson('/api/v1/kode-rujukan/bayar/'.$daftar->id, [], kepalaRujukan())->assertOk();

    expect($daftar->fresh()->imbalan_dibayar_pada)->not->toBeNull();

    // Yang sudah dibayar keluar dari angka utang, tetapi tetap terhitung di
    // total — riwayatnya tidak boleh hilang saat dibayar.
    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaRujukan())->json('data.0');

    expect($baris['imbalan_belum_dibayar'])->toBe(0)
        ->and($baris['imbalan_total'])->toBe(75000);
});

test('membayar dua kali ditolak', function () {
    // Pembayaran ganda tidak bisa ditarik kembali. Ditahan di server meskipun
    // layarnya sudah menyembunyikan tombolnya.
    $kode = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
    $daftar = pakaiKode($kode);

    $this->postJson('/api/v1/kode-rujukan/bayar/'.$daftar->id, [], kepalaRujukan())->assertOk();
    $this->postJson('/api/v1/kode-rujukan/bayar/'.$daftar->id, [], kepalaRujukan())->assertStatus(422);
});

test('pendaftaran tanpa kode rujukan tidak bisa ditandai dibayar', function () {
    $paket = TravelPackage::create([
        'name' => 'Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
    ]);

    $daftar = PendaftaranOpenTrip::create([
        'nama' => 'Tanpa Rujukan', 'whatsapp' => '081200000009', 'jumlah_peserta' => 1,
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
    ]);

    $this->postJson('/api/v1/kode-rujukan/bayar/'.$daftar->id, [], kepalaRujukan())->assertStatus(422);
});

test('pemakaian satu kode bisa ditelusuri satu per satu', function () {
    // Saat komisi dibayarkan, pertanyaannya bukan "berapa" melainkan "untuk
    // pendaftaran yang mana" — dan itu pertanyaan yang harus bisa dijawab
    // sambil menatap mutasi rekening.
    $kode = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
    $daftar = pakaiKode($kode);

    $baris = $this->getJson('/api/v1/kode-rujukan/'.$kode->id.'/pemakaian', kepalaRujukan())
        ->assertOk()
        ->json('data.0');

    expect($baris['kode'])->toBe($daftar->kode)
        ->and($baris['imbalan'])->toBe(75000)
        ->and($baris['dibayar_pada'])->toBeNull();
});

test('kodenya tidak bisa diubah lewat penyuntingan', function () {
    /*
     | Kodenya sudah tersebar di grup WhatsApp temannya dan sudah menempel pada
     | pendaftaran yang lalu. Mengubahnya memutus jejak komisi yang belum
     | dibayarkan, dan membuat kode yang sedang beredar mendadak ditolak.
     */
    $kode = KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
    $semula = $kode->kode;

    $this->putJson('/api/v1/kode-rujukan/'.$kode->id, [
        'nama' => 'Budi Baru', 'whatsapp' => '081234567890', 'kode' => 'PAKSA-9999',
    ], kepalaRujukan())->assertOk();

    expect($kode->fresh()->kode)->toBe($semula)
        ->and($kode->fresh()->nama)->toBe('Budi Baru');
});

test('tanpa kunci API, jalurnya tertutup', function () {
    KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);

    $this->getJson('/api/v1/kode-rujukan', ['Accept' => 'application/json'])
        ->assertStatus(401);
});
