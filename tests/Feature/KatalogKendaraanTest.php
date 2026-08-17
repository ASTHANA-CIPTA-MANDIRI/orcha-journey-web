<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\KatalogTambahan;
use App\Support\SewaKendaraan\KatalogKendaraan;

const KUNCI_KATALOG = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_KATALOG);
    config()->set('orcha.api.ip_diizinkan', []);
});

function unitKatalog(string $merek, string $nama): Car
{
    return Car::create([
        'name' => $nama, 'brand' => $merek, 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 7, 'price_per_day' => 400000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);
}

test('katalog memuat merek dan model pasar Indonesia', function () {
    $katalog = KatalogKendaraan::pilihan();

    // Model yang benar-benar disewakan di Indonesia. API kendaraan gratis yang
    // ada (vPIC, CarAPI) berisi data pasar Amerika dan tidak memuat satu pun
    // dari ini — itu sebabnya katalognya ditulis sendiri.
    expect(count($katalog))->toBeGreaterThanOrEqual(25)
        ->and(array_sum(array_map('count', $katalog)))->toBeGreaterThanOrEqual(150)
        ->and($katalog)->toHaveKeys(['Toyota', 'Daihatsu', 'Suzuki', 'Mitsubishi', 'Wuling', 'Chery'])
        ->and($katalog['Toyota'])->toContain('Avanza')
        ->and($katalog['Toyota'])->toContain('HiAce Commuter')
        ->and($katalog['Suzuki'])->toContain('Ertiga')
        ->and($katalog['Mitsubishi'])->toContain('Xpander');
});

test('merek dan model milik armada sendiri ikut tercantum', function () {
    // Esemka sengaja dipilih karena TIDAK ada di katalog config — merek yang
    // sudah tercantum di sana tidak membuktikan apa pun tentang penggabungan.
    expect(config('orcha.katalog_kendaraan'))->not->toHaveKey('Esemka');

    unitKatalog('Esemka', 'Bima 1.3');

    $katalog = KatalogKendaraan::pilihan();

    // Tanpa ini, mengubah unit Esemka akan menghadapkan admin pada daftar yang
    // tidak memuat mereknya sendiri — dan satu-satunya pilihan yang tersisa
    // adalah mengubahnya jadi merek lain.
    expect($katalog)->toHaveKey('Esemka')
        ->and($katalog['Esemka'])->toBe(['Bima 1.3']);
});

test('model armada digabung ke merek yang sudah ada di katalog, bukan menggantinya', function () {
    unitKatalog('Toyota', 'Kijang Kapsul');

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect($toyota)->toContain('Kijang Kapsul')
        ->and($toyota)->toContain('Avanza');
});

test('model yang sama tidak tercantum dua kali', function () {
    unitKatalog('Toyota', 'Avanza');
    unitKatalog('Toyota', 'Avanza');

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect(array_count_values($toyota)['Avanza'])->toBe(1);
});

test('merek kosong di armada tidak membuat pilihan tanpa nama', function () {
    unitKatalog('', 'Tanpa Merek');

    expect(array_keys(KatalogKendaraan::pilihan()))->not->toContain('');
});

test('katalog terkirim lewat rujukan supaya lemon tidak menyimpan daftarnya sendiri', function () {
    unitKatalog('Wuling', 'Confero S');

    $data = $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => KUNCI_KATALOG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ])->assertOk()->json('data.katalog_kendaraan');

    // Satu sumber di Orcha, bukan salinan daftar di lemon: kalau disalin,
    // keduanya pasti berbeda suatu saat.
    expect($data)->toHaveKey('Wuling')
        ->and($data['Wuling'])->toContain('Confero S')
        ->and($data['Toyota'])->toContain('Avanza');
});

test('merek terurut supaya dropdown tidak berubah-ubah urutannya', function () {
    unitKatalog('Audi', 'Q3');

    $merek = array_keys(KatalogKendaraan::pilihan());

    expect($merek[0])->toBe('Audi')
        ->and($merek)->toBe(collect($merek)->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all());
});

/* ------------------- TAMBAHAN ADMIN: MASUK DAFTAR & BISA DIHAPUS ------------------- */

function kepalaKatalog(): array
{
    return [
        'X-Orcha-Key' => KUNCI_KATALOG,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ];
}

test('merek yang ditulis admin masuk daftar tanpa perlu menyimpan unit dulu', function () {
    // Inti permintaannya: sebelum ini merek manual baru bertahan kalau unitnya
    // ikut tersimpan — nama yang diketik lalu dibatalkan hilang begitu saja.
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Esemka'], kepalaKatalog())
        ->assertCreated();

    expect(KatalogKendaraan::pilihan())->toHaveKey('Esemka')
        ->and(Car::count())->toBe(0);
});

test('model yang ditulis admin masuk di bawah mereknya', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima 1.3'], kepalaKatalog())->assertCreated();

    expect(KatalogKendaraan::pilihan()['Esemka'])->toBe(['Bima 1.3']);
});

test('model baru bisa ditambahkan ke merek bawaan', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Toyota', 'model' => 'Kijang Krista'], kepalaKatalog())->assertCreated();

    $toyota = KatalogKendaraan::pilihan()['Toyota'];

    expect($toyota)->toContain('Kijang Krista')
        ->and($toyota)->toContain('Avanza');
});

test('entri tambahan bisa dihapus', function () {
    $tambah = $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima 1.3'], kepalaKatalog())->assertCreated();

    $id = collect($tambah->json('data'))->firstWhere('model', 'Bima 1.3')['id'];

    $this->deleteJson("/api/v1/katalog-kendaraan/{$id}", [], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::pilihan()['Esemka'] ?? [])->not->toContain('Bima 1.3');
});

test('menghapus entri katalog tidak menghapus kendaraan yang memakainya', function () {
    unitKatalog('Esemka', 'Bima 1.3');

    $tambah = $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Esemka', 'model' => 'Bima Baru'], kepalaKatalog())->assertCreated();
    $id = collect($tambah->json('data'))->firstWhere('model', 'Bima Baru')['id'];

    $this->deleteJson("/api/v1/katalog-kendaraan/{$id}", [], kepalaKatalog())->assertOk();

    // Unitnya utuh, dan mereknya tetap terdaftar karena dibaca dari armada —
    // menghapus entri katalog tidak boleh membuat unit kehilangan mereknya.
    expect(Car::where('brand', 'Esemka')->count())->toBe(1)
        ->and(KatalogKendaraan::pilihan())->toHaveKey('Esemka')
        ->and(KatalogKendaraan::pilihan()['Esemka'])->toContain('Bima 1.3');
});

test('hanya entri tambahan yang tercatat sebagai bisa dihapus', function () {
    unitKatalog('Esemka', 'Bima 1.3');
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertCreated();

    $kustom = collect(KatalogKendaraan::kustom());

    // Katalog bawaan ikut versi kode, dan merek dari armada dipakai unit nyata:
    // keduanya tidak boleh tampak bisa dihapus.
    expect($kustom->pluck('merek')->all())->toBe(['Neta'])
        ->and($kustom->pluck('merek'))->not->toContain('Toyota')
        ->and($kustom->pluck('merek'))->not->toContain('Esemka');
});

test('entri yang sama tidak tercatat dua kali', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertCreated();
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta'], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::kustom())->toHaveCount(1);
});

test('ejaan berbeda huruf besar tidak membuat merek kedua', function () {
    // " toyota " dan "Toyota" maksudnya satu. Tanpa penormalan, daftar pilihan
    // memuat dua baris yang tampak sama.
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => '  toyota ', 'model' => 'Kijang Krista'], kepalaKatalog())->assertCreated();

    $katalog = KatalogKendaraan::pilihan();

    expect($katalog)->not->toHaveKey('toyota')
        ->and($katalog['Toyota'])->toContain('Kijang Krista');
});

test('model yang sudah ada di katalog bawaan tidak ditambahkan lagi', function () {
    $this->postJson('/api/v1/katalog-kendaraan',
        ['merek' => 'Toyota', 'model' => 'Avanza'], kepalaKatalog())->assertOk();

    expect(KatalogKendaraan::kustom())->toBeEmpty()
        ->and(array_count_values(KatalogKendaraan::pilihan()['Toyota'])['Avanza'])->toBe(1);
});

test('merek kosong ditolak', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => '   '], kepalaKatalog())
        ->assertStatus(422);
});

test('katalog kustom terkirim lewat rujukan', function () {
    $this->postJson('/api/v1/katalog-kendaraan', ['merek' => 'Neta', 'model' => 'V'], kepalaKatalog());

    $data = $this->getJson('/api/v1/rujukan', kepalaKatalog())->assertOk()->json('data');

    expect($data['katalog_kustom'])->toHaveCount(1)
        ->and($data['katalog_kustom'][0]['merek'])->toBe('Neta')
        ->and($data['katalog_kendaraan']['Neta'])->toBe(['V']);
});

/* ---------------------- KAPASITAS SEMI OTOMATIS ---------------------- */

test('kursi per model tersedia untuk mengisi kapasitas otomatis', function () {
    $kursi = KatalogKendaraan::kapasitas();

    // Angka yang membedakan: MPV 7, HiAce 15, bus besar 59. Kalau ini keliru,
    // kapasitas terisi otomatis dengan angka yang salah dan cenderung tidak
    // diperiksa lagi karena isiannya sudah terlihat penuh.
    expect($kursi['Toyota']['Avanza'])->toBe(7)
        ->and($kursi['Toyota']['HiAce Commuter'])->toBe(15)
        ->and($kursi['Hino']['Bus RK'])->toBe(59)
        ->and($kursi['Daihatsu']['Gran Max Pick Up'])->toBe(2);
});

test('kapasitas dari armada menimpa angka bawaan', function () {
    // Avanza yang dipasangi 6 kursi di armada tidak seharusnya terus menawarkan 7.
    Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 6, 'price_per_day' => 400000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);

    expect(KatalogKendaraan::kapasitas()['Toyota']['Avanza'])->toBe(6);
});

test('model tambahan admin tidak mengarang kapasitas', function () {
    KatalogTambahan::create(['merek' => 'Esemka', 'model' => 'Bima 1.3']);

    $kursi = KatalogKendaraan::kapasitas();

    // Belum ada angka yang bisa dipertanggungjawabkan, jadi tidak disertakan:
    // isian kapasitas dibiarkan apa adanya, bukan diisi angka karangan.
    expect($kursi['Esemka'] ?? [])->not->toHaveKey('Bima 1.3')
        ->and(KatalogKendaraan::pilihan()['Esemka'])->toBe(['Bima 1.3']);
});

test('kapasitas kosong di armada tidak menghapus angka bawaan', function () {
    Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 0, 'price_per_day' => 400000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);

    expect(KatalogKendaraan::kapasitas()['Toyota']['Avanza'])->toBe(7);
});

test('kapasitas terkirim lewat rujukan', function () {
    $data = $this->getJson('/api/v1/rujukan', kepalaKatalog())->assertOk()->json('data');

    expect($data['kapasitas_kendaraan']['Suzuki']['Ertiga'])->toBe(7)
        ->and($data['katalog_kendaraan']['Suzuki'])->toContain('Ertiga');
});

test('daftar model dan daftar kursi tidak mungkin berbeda isinya', function () {
    // Keduanya berasal dari satu struktur, jadi tidak ada model yang tercantum
    // di daftar pilihan tapi hilang dari daftar kursi karena lupa disalin.
    $pilihan = KatalogKendaraan::pilihan();
    $kursi = KatalogKendaraan::kapasitas();

    foreach ($kursi as $merek => $model) {
        expect(array_keys($model))->each->toBeIn($pilihan[$merek]);
    }
});

/* -------------- JENIS, TIPE, TAHUN, CC -------------- */

test('jenis disimpulkan dari jumlah kursi', function () {
    $jenis = KatalogKendaraan::jenis();

    // Batas 12 dipilih supaya MPV mewah berkursi 11 tetap terbaca mobil,
    // sedangkan HiAce 14-15 masuk kelas minibus — sesuai cara unit itu benar-
    // benar disewakan.
    expect($jenis['Toyota']['Avanza'])->toBe('mobil')
        ->and($jenis['Kia']['Carnival'])->toBe('mobil')
        ->and($jenis['Toyota']['HiAce Premio'])->toBe('hiace')
        ->and($jenis['Mercedes-Benz']['Sprinter'])->toBe('hiace')
        ->and($jenis['Hino']['Bus RK'])->toBe('bus')
        ->and($jenis['Golden Dragon']['Bus Pariwisata'])->toBe('bus');
});

test('jenis yang disimpulkan selalu kunci yang sah di config', function () {
    $sah = array_keys(config('orcha.jenis_kendaraan'));

    foreach (KatalogKendaraan::jenis() as $model) {
        expect(array_values($model))->each->toBeIn($sah);
    }
});

test('isi silinder dan tipe tersedia untuk model yang diketahui', function () {
    expect(KatalogKendaraan::mesin()['Toyota']['Agya'])->toBe(1200)
        ->and(KatalogKendaraan::varian()['Toyota']['Agya'])->toContain('GR Sport')
        ->and(KatalogKendaraan::varian()['Mitsubishi']['Xpander'])->toContain('Ultimate');
});

test('model tanpa data cc tidak mengarang angkanya', function () {
    // Menuliskan cc untuk 180 model berarti mengarang untuk sebagian besarnya.
    expect(KatalogKendaraan::mesin()['Toyota'] ?? [])->not->toHaveKey('Sienta');
});

test('tipe yang dipakai unit di armada ikut jadi pilihan', function () {
    Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'varian' => 'Veloz Q', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    // Tipe yang pernah ditulis sekali tidak perlu ditulis ulang untuk unit
    // sejenis berikutnya.
    expect(KatalogKendaraan::varian()['Toyota']['Avanza'])->toContain('Veloz Q')
        ->and(KatalogKendaraan::varian()['Toyota']['Avanza'])->toContain('G');
});

test('cc dari armada menimpa angka bawaan, kosong tidak menghapusnya', function () {
    Car::create([
        'name' => 'Agya', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 5, 'cc' => 1000,
        'price_per_day' => 250000, 'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);
    Car::create([
        'name' => 'Calya', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 7, 'price_per_day' => 300000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);

    expect(KatalogKendaraan::mesin()['Toyota']['Agya'])->toBe(1000)
        ->and(KatalogKendaraan::mesin()['Toyota']['Calya'])->toBe(1200);
});

test('unit tersimpan lengkap dengan tipe, tahun, dan cc', function () {
    $balasan = $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Agya', 'merek' => 'Toyota', 'varian' => 'G', 'tahun' => 2025, 'cc' => 1200,
        'jenis' => 'mobil', 'kapasitas' => 5, 'transmisi_tersedia' => ['Matic'],
        'tarif_hari' => 275000,
    ], kepalaKatalog())->assertCreated();

    $unit = Car::first();

    expect($unit->varian)->toBe('G')
        ->and($unit->tahun)->toBe(2025)
        ->and($unit->cc)->toBe(1200)
        // Sebutannya dirakit sekali di Orcha, supaya lemon dan halaman publik
        // tidak masing-masing menyusun urutannya lalu berbeda.
        ->and($unit->sebutan_lengkap)->toBe('Toyota Agya G 2025 · 1.200 cc');
});

test('tahun jauh di depan ditolak', function () {
    // Salah ketik tahun tidak pernah kelihatan salah, jadi dijaga di validasi.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Agya', 'merek' => 'Toyota', 'tahun' => 2035,
        'jenis' => 'mobil', 'kapasitas' => 5, 'transmisi_tersedia' => ['Matic'],
        'tarif_hari' => 275000,
    ], kepalaKatalog())->assertStatus(422);
});

test('unit lama tanpa tahun dan cc tetap terbaca wajar', function () {
    $unit = Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Manual', 'capacity' => 7, 'price_per_day' => 400000,
        'is_available' => true, 'transmisi_tersedia' => ['Manual'],
    ]);

    expect($unit->sebutan_lengkap)->toBe('Toyota Avanza');
});

test('rincian per model terkirim lewat rujukan', function () {
    $data = $this->getJson('/api/v1/rujukan', kepalaKatalog())->assertOk()->json('data');

    expect($data['jenis_per_model']['Toyota']['HiAce Commuter'])->toBe('hiace')
        ->and($data['cc_per_model']['Toyota']['Agya'])->toBe(1200)
        ->and($data['varian_per_model']['Toyota']['Agya'])->toContain('G');
});

test('kendaraan bisa dibuat dengan hanya isian wajib', function () {
    // Tarif opsional yang tidak dikirim sama sekali sebelumnya membuat
    // permintaan gagal 500, bukan tersimpan tanpa tarif jam.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Xenia', 'merek' => 'Daihatsu', 'jenis' => 'mobil',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Manual'], 'tarif_hari' => 350000,
    ], kepalaKatalog())->assertCreated();

    $unit = Car::first();

    expect($unit->harga_per_jam)->toBeNull()
        ->and($unit->price_per_day)->toBe(350000);
});
