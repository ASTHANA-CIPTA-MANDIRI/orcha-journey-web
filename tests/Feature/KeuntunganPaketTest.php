<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\Keuntungan;

const KUNCI_UNTUNG = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_UNTUNG);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kepalaUntung(): array
{
    return [
        'X-Orcha-Key' => KUNCI_UNTUNG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

function paketUntung(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'harga_modal' => 1400000,
        'minimal_peserta' => 6,
    ], $ubah));
}

function daftarUntung(TravelPackage $paket, array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'status' => 'lunas',
    ], $ubah));
}

/* ---------------------------- MARGIN PAKET ---------------------------- */

test('margin per orang adalah selisih harga jual dan modal', function () {
    $paket = paketUntung();

    expect($paket->margin_per_orang)->toBe(30000)
        ->and($paket->margin_per_orang_teks)->toBe('Rp 30.000')
        ->and($paket->margin_persen)->toBe(2.1)
        ->and($paket->modal_terisi)->toBeTrue();
});

test('modal kosong berarti belum dihitung, bukan untung penuh', function () {
    $paket = paketUntung(['harga_modal' => null]);

    expect($paket->modal_terisi)->toBeFalse()
        ->and($paket->margin_per_orang)->toBeNull()
        ->and($paket->margin_persen)->toBeNull()
        ->and($paket->margin_per_orang_teks)->toBe('Belum dihitung');
});

test('paket yang dijual di bawah modal dilaporkan rugi apa adanya', function () {
    $paket = paketUntung(['price' => 1350000]);

    expect($paket->margin_per_orang)->toBe(-50000);
});

test('modal tidak pernah ikut saat paket diubah jadi larik', function () {
    $paket = paketUntung();

    expect($paket->toArray())->not->toHaveKey('harga_modal');
});

test('modal tidak bocor ke halaman paket yang dibuka pengunjung', function () {
    $paket = paketUntung(['harga_modal' => 1234567]);

    $halaman = $this->get('/paket/'.$paket->uuid);

    $halaman->assertOk();
    expect($halaman->getContent())
        ->not->toContain('1234567')
        ->and($halaman->getContent())->not->toContain('1.234.567');
});

/* ------------------------- JEJAK DI PENDAFTARAN ------------------------- */

test('harga jual dan modal dibekukan saat pendaftaran dibuat', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    expect($daftar->harga_jual)->toBe(1430000)
        ->and($daftar->harga_modal)->toBe(1400000);

    // Modal naik bulan berikutnya — pendaftaran lama tidak ikut berubah.
    $paket->update(['harga_modal' => 1410000, 'price' => 1500000]);

    $daftar = $daftar->fresh();

    expect($daftar->modal_satuan)->toBe(1400000)
        ->and($daftar->jual_satuan)->toBe(1430000)
        ->and($daftar->margin_satuan)->toBe(30000)
        ->and($daftar->keuntungan)->toBe(60000);
});

test('pendaftaran tanpa jejak meminjam angka paketnya', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    // Meniru baris yang masuk sebelum pembekuan ini ada.
    $daftar->forceFill(['harga_jual' => null, 'harga_modal' => null])->save();

    expect($daftar->fresh()->margin_satuan)->toBe(30000);
});

test('omzet dan keuntungan mengikuti jumlah peserta', function () {
    $daftar = daftarUntung(paketUntung(), ['jumlah_peserta' => 5]);

    expect($daftar->omzet)->toBe(7150000)
        ->and($daftar->modal_total)->toBe(7000000)
        ->and($daftar->keuntungan)->toBe(150000);
});

/* ----------------------------- LAPORAN ----------------------------- */

test('hanya pendaftaran lunas yang dihitung sebagai keuntungan', function () {
    $paket = paketUntung();

    daftarUntung($paket, ['status' => 'lunas', 'jumlah_peserta' => 2]);
    daftarUntung($paket, ['status' => 'dp_masuk', 'jumlah_peserta' => 3]);
    daftarUntung($paket, ['status' => 'batal', 'jumlah_peserta' => 4]);

    $ringkas = Keuntungan::laporan()['ringkasan'];

    expect($ringkas['pendaftaran'])->toBe(1)
        ->and($ringkas['peserta'])->toBe(2)
        ->and($ringkas['keuntungan'])->toBe(60000)
        ->and($ringkas['keuntungan_teks'])->toBe('Rp 60.000')
        // Yang ber-DP tercatat terpisah; yang batal tidak di mana-mana.
        ->and($ringkas['potensi_pendaftaran'])->toBe(1)
        ->and($ringkas['potensi_peserta'])->toBe(3)
        ->and($ringkas['potensi_keuntungan'])->toBe(90000);
});

test('paket yang modalnya belum diisi dihitung sebagai belum lengkap', function () {
    $adaModal = paketUntung();
    $tanpaModal = paketUntung(['name' => 'Private Trip Dieng', 'harga_modal' => null]);

    daftarUntung($adaModal);
    daftarUntung($tanpaModal);

    $ringkas = Keuntungan::laporan()['ringkasan'];

    expect($ringkas['pendaftaran'])->toBe(2)
        ->and($ringkas['belum_lengkap'])->toBe(1)
        ->and($ringkas['paket_belum_lengkap'])->toBe(['Private Trip Dieng'])
        // Omzetnya tetap terhitung, keuntungannya tidak dikarang.
        ->and($ringkas['keuntungan'])->toBe(60000)
        ->and($ringkas['omzet'])->toBe(5720000);
});

test('rekap per paket dan per kategori memisahkan sumbernya', function () {
    $open = paketUntung();
    $studi = paketUntung(['name' => 'Study Tour Bali', 'category' => 'study_tour', 'price' => 1600000, 'harga_modal' => 1500000]);

    daftarUntung($open, ['jumlah_peserta' => 2]);   // 60.000
    daftarUntung($studi, ['jumlah_peserta' => 10]); // 1.000.000

    $laporan = Keuntungan::laporan();

    // Diurutkan dari yang paling besar keuntungannya.
    expect($laporan['per_paket'][0]['nama'])->toBe('Study Tour Bali')
        ->and($laporan['per_paket'][0]['keuntungan'])->toBe(1000000)
        ->and($laporan['per_paket'][0]['margin_per_orang'])->toBe(100000)
        ->and($laporan['per_paket'][1]['keuntungan'])->toBe(60000)
        ->and($laporan['per_kategori'][0]['label'])->toBe('Study Tour')
        ->and($laporan['ringkasan']['margin_rata_per_orang'])->toBe(88333);
});

test('rentang tanggal menyaring menurut dasar yang dipilih', function () {
    $paket = paketUntung();

    $lama = daftarUntung($paket, ['tanggal_berangkat' => now()->addMonths(3)->toDateString()]);
    $lama->forceFill(['created_at' => now()->subMonths(2)])->save();

    daftarUntung($paket, ['tanggal_berangkat' => now()->addDays(5)->toDateString()]);

    $dari = now()->subWeek()->toDateString();

    // Menurut tanggal mendaftar: yang dua bulan lalu tersaring keluar.
    expect(Keuntungan::laporan(['dari' => $dari])['ringkasan']['pendaftaran'])->toBe(1);

    // Menurut keberangkatan: keduanya masih di depan, jadi keduanya masuk.
    expect(Keuntungan::laporan(['dari' => $dari, 'dasar' => 'berangkat'])['ringkasan']['pendaftaran'])->toBe(2);
});

/* ------------------------------- API ------------------------------- */

test('laporan keuntungan dijaga kunci api', function () {
    $this->getJson('/api/v1/keuntungan')->assertStatus(401);
});

test('api keuntungan mengirim ringkasan, rekap, dan daftar paket', function () {
    $paket = paketUntung();
    daftarUntung($paket);

    $this->getJson('/api/v1/keuntungan', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.ringkasan.keuntungan', 60000)
        ->assertJsonPath('data.ringkasan.keuntungan_teks', 'Rp 60.000')
        ->assertJsonPath('data.per_paket.0.nama', 'Open Trip Banyuwangi')
        ->assertJsonPath('data.paket.0.margin_per_orang', 30000)
        ->assertJsonPath('data.saringan.dasar', 'daftar');
});

test('api rincian keuntungan berhalaman dan menyebut kodenya', function () {
    $paket = paketUntung();
    $daftar = daftarUntung($paket);

    $this->getJson('/api/v1/keuntungan/rincian?per_halaman=5', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.0.kode', $daftar->kode)
        ->assertJsonPath('data.0.keuntungan', 60000)
        ->assertJsonPath('meta.per_halaman', 5);
});

test('rincian bisa dibatasi hanya yang lunas', function () {
    $paket = paketUntung();
    daftarUntung($paket, ['status' => 'lunas']);
    daftarUntung($paket, ['status' => 'dp_masuk']);
    daftarUntung($paket, ['status' => 'batal']);

    // Bawaannya: semua kecuali batal, supaya admin melihat yang menggantung.
    $this->getJson('/api/v1/keuntungan/rincian', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/keuntungan/rincian?hanya_lunas=1', kepalaUntung())
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('modal paket bisa disimpan dan diubah lewat api', function () {
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Karimunjawa',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1430000,
        'harga_modal' => 1400000,
    ], kepalaUntung());

    $balasan->assertCreated()
        ->assertJsonPath('data.harga_modal', 1400000)
        ->assertJsonPath('data.margin_per_orang', 30000);

    $id = $balasan->json('data.id');

    // Dikosongkan lagi: kembali jadi "belum dihitung", bukan nol.
    $this->postJson("/api/v1/paket-wisata/{$id}", [
        '_method' => 'PUT',
        'nama' => 'Open Trip Karimunjawa',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1430000,
    ], kepalaUntung())
        ->assertOk()
        ->assertJsonPath('data.harga_modal', null)
        ->assertJsonPath('data.modal_terisi', false);
});

test('modal yang dikirim sebagai isian kosong tidak jadi nol', function () {
    // Lemon mengirimkannya begitu saat admin mengosongkan isiannya: perataan
    // multipart membuang nilai null, jadi yang sampai ke sini teks kosong.
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Private Trip Dieng',
        'kategori' => 'private_trip',
        'minimal_peserta' => 4,
        'harga' => 1430000,
        'harga_modal' => '',
    ], kepalaUntung());

    $balasan->assertCreated()
        ->assertJsonPath('data.harga_modal', null)
        ->assertJsonPath('data.modal_terisi', false)
        ->assertJsonPath('data.margin_per_orang', null);
});

test('menu orcha menyebut halaman keuntungan', function () {
    $this->getJson('/api/v1/menu', kepalaUntung())
        ->assertOk()
        ->assertJsonFragment(['jalur' => 'keuntungan', 'label' => 'Keuntungan Paket', 'ikon' => 'chart-bar']);
});
