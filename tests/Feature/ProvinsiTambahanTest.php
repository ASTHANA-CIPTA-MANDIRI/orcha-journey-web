<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\ProvinsiTambahan;

/**
 * Provinsi yang ditambahkan admin sendiri.
 *
 * Daftar bawaan 38 provinsi cukup untuk hari ini, tetapi provinsi bisa
 * dimekarkan — 2022 saja bertambah empat sekaligus. Tanpa jalur ini admin harus
 * menunggu rilis kode hanya untuk mencatat destinasi di provinsi baru.
 */
function kirimProvinsi(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->json($metode, '/api/v1/provinsi'.($id ? "/{$id}" : ''), $isi, [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('provinsi baru tersimpan dan ikut daftar gabungan', function () {
    kirimProvinsi(['nama' => 'Papua Barat Laut', 'wilayah' => 'papua'])
        ->assertCreated()
        ->assertJsonPath('data.0.nama', 'Papua Barat Laut');

    expect(ProvinsiTambahan::gabungan())->toHaveKey('Papua Barat Laut')
        ->and(ProvinsiTambahan::gabungan()['Papua Barat Laut'])->toBe('papua');
});

test('provinsi yang sudah ada tidak digandakan dan bukan dianggap gagal', function () {
    // Admin memang menginginkan provinsi itu ada di daftar, dan ia sudah ada.
    // Menjawabnya dengan galat membuat admin mengira ada yang salah.
    kirimProvinsi(['nama' => 'jawa timur', 'wilayah' => 'jawa'])->assertOk();

    expect(ProvinsiTambahan::count())->toBe(0);
});

test('ejaan disamakan dengan daftar bawaan', function () {
    // " bali " dan "Bali" lolos batasan unik padahal maksudnya satu.
    expect(ProvinsiTambahan::rapikan('  bali '))->toBe('Bali');
});

test('wilayah wajib disebut dan harus dikenal', function () {
    // Tanpa wilayah, provinsi tambahan tidak masuk penyaring mana pun di halaman
    // publik dan destinasinya menghilang dari daftar.
    kirimProvinsi(['nama' => 'Provinsi Baru'])->assertStatus(422)
        ->assertJsonValidationErrors('wilayah');

    kirimProvinsi(['nama' => 'Provinsi Baru', 'wilayah' => 'antah_berantah'])
        ->assertStatus(422)->assertJsonValidationErrors('wilayah');
});

test('menghapus provinsi tambahan tidak menyentuh destinasinya', function () {
    $provinsi = ProvinsiTambahan::create(['nama' => 'Papua Barat Laut', 'wilayah' => 'papua']);

    DestinationPopuler::create([
        'destination_name' => 'Raja Ampat Baru', 'wilayah' => 'papua',
        'provinsi' => 'Papua Barat Laut', 'total_visitor' => 10,
    ]);

    kirimProvinsi([], 'delete', $provinsi->id)->assertOk();

    // Yang hilang hanya pilihannya di formulir, bukan datanya.
    expect(DestinationPopuler::first()->provinsi)->toBe('Papua Barat Laut')
        ->and(ProvinsiTambahan::count())->toBe(0);
});

test('rujukan mengirim gabungan beserta penanda mana yang boleh dihapus', function () {
    ProvinsiTambahan::create(['nama' => 'Papua Barat Laut', 'wilayah' => 'papua']);

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $data = $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data');

    expect($data['provinsi_wilayah'])->toHaveKey('Papua Barat Laut')
        ->and($data['provinsi_wilayah'])->toHaveKey('Jawa Timur')
        // Hanya entri tambahan yang boleh dihapus; yang bawaan ikut versi kode.
        ->and(collect($data['provinsi_kustom'])->pluck('nama')->all())->toBe(['Papua Barat Laut']);
});

/* -------- USULAN PROVINSI DARI NAMA DESTINASI -------- */

function cariLokasi(string $nama)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->getJson('/api/v1/cari-lokasi?nama='.urlencode($nama), [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('destinasi yang sudah tercatat dipakai lebih dulu, tanpa menembak peta', function () {
    \Illuminate\Support\Facades\Http::fake();

    DestinationPopuler::create([
        'destination_name' => 'Bromo Tengger Semeru', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'total_visitor' => 26700,
    ]);

    // Gratis, seketika, dan mencerminkan keputusan admin sendiri — "Bromo" yang
    // dulu ditempatkan di Jawa Timur tidak perlu ditanyakan lagi ke siapa pun.
    cariLokasi('Bromo')->assertOk()
        ->assertJsonPath('data.provinsi', 'Jawa Timur')
        ->assertJsonPath('data.wilayah', 'jawa')
        ->assertJsonPath('data.sumber', 'destinasi');

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('nama yang belum pernah dicatat ditanyakan ke peta', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Pantai Melasti',
            'address' => ['state' => 'Bali'],
        ]]),
    ]);

    cariLokasi('Pantai Melasti')->assertOk()
        ->assertJsonPath('data.provinsi', 'Bali')
        ->assertJsonPath('data.wilayah', 'bali')
        ->assertJsonPath('data.sumber', 'peta');
});

test('ejaan provinsi dari peta disamakan dengan daftar kita', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Candi Prambanan',
            'address' => ['state' => 'Daerah Istimewa Yogyakarta'],
        ]]),
    ]);

    // Tanpa penyamaan ini keduanya dianggap provinsi berbeda, dan usulannya
    // dibuang justru untuk provinsi yang paling ramai.
    cariLokasi('Candi Prambanan')->assertOk()
        ->assertJsonPath('data.provinsi', 'DI Yogyakarta')
        ->assertJsonPath('data.wilayah', 'jawa');
});

test('provinsi asing tidak dipakai', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Gunung Ledang',
            'address' => ['state' => 'Johor'],
        ]]),
    ]);

    // Mengisinya berarti destinasi tercatat di provinsi yang tidak punya
    // wilayah, dan penyaring di halaman publik tidak akan menemukannya.
    cariLokasi('Gunung Ledang')->assertOk()->assertJsonPath('data', null);
});

test('peta yang mati tidak menggagalkan apa pun', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response('', 503),
    ]);

    // Jawaban kosong bukan kegagalan: yang benar adalah admin mengisi sendiri,
    // bukan galat yang menghentikannya.
    cariLokasi('Tempat Antah Berantah')->assertOk()->assertJsonPath('data', null);
});

test('nama terlalu pendek tidak ditanyakan ke mana pun', function () {
    \Illuminate\Support\Facades\Http::fake();

    cariLokasi('Bro')->assertOk()->assertJsonPath('data', null);

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('jawaban peta disimpan, tidak ditanyakan dua kali', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Nusa Penida',
            'address' => ['state' => 'Bali'],
        ]]),
    ]);

    // Provinsi sebuah tempat tidak berubah; menembak layanan yang sama untuk
    // pertanyaan yang sama memperlambat admin sekaligus membebani layanan
    // gratis yang kita menumpang padanya.
    cariLokasi('Nusa Penida Baru')->assertOk();
    cariLokasi('Nusa Penida Baru')->assertOk();

    \Illuminate\Support\Facades\Http::assertSentCount(1);
});

test('pemanggilan menyebut pengenal, sesuai ketentuan nominatim', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Pantai Baru',
            'address' => ['state' => 'Bali'],
        ]]),
    ]);

    cariLokasi('Pantai Baru Sekali')->assertOk();

    // Layanan gratis yang tidak tahu siapa pemanggilnya berhak memblokirnya.
    \Illuminate\Support\Facades\Http::assertSent(fn ($p) => $p->hasHeader('User-Agent')
        && str_contains($p->header('User-Agent')[0], 'OrchaJourney')
        && str_contains($p->url(), 'countrycodes=id'));
});

/* -------- WILAYAH YANG DITAMBAHKAN ADMIN -------- */

use App\Models\Etalase\WilayahTambahan;

function kirimWilayah(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->json($metode, '/api/v1/wilayah'.($id ? "/{$id}" : ''), $isi, [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('delapan wilayah bawaan menutup seluruh Indonesia', function () {
    // Sebelumnya enam: "Bali & Nusa Tenggara" dan "Maluku & Papua" masing-masing
    // menggabungkan dua kelompok yang jauh berbeda — Bali ke Labuan Bajo hampir
    // 500 km laut, dan yang menyaring "Bali" tidak sedang mencari Sumba.
    expect(array_keys(config('orcha.wilayah')))->toBe([
        'sumatera', 'jawa', 'bali', 'nusa_tenggara',
        'kalimantan', 'sulawesi', 'maluku', 'papua',
    ]);

    // Tiap provinsi tetap menunjuk wilayah yang ada.
    $menyimpang = collect(config('orcha.provinsi_wilayah'))
        ->reject(fn ($w) => array_key_exists($w, config('orcha.wilayah')))
        ->keys()->all();

    expect($menyimpang)->toBe([]);
});

test('wilayah baru tersimpan beserta kuncinya', function () {
    kirimWilayah(['label' => 'Segitiga Terumbu Karang'])->assertCreated();

    // Kunci disimpan tersendiri dari labelnya: label boleh diperbaiki ejaannya
    // kapan saja, kunci sudah terlanjur tersimpan di kolom wilayah destinasi.
    expect(WilayahTambahan::gabungan())->toHaveKey('segitiga_terumbu_karang')
        ->and(WilayahTambahan::gabungan()['segitiga_terumbu_karang'])->toBe('Segitiga Terumbu Karang');
});

test('wilayah yang sudah ada tidak digandakan', function () {
    kirimWilayah(['label' => 'Jawa'])->assertOk();

    expect(WilayahTambahan::count())->toBe(0);
});

test('wilayah tambahan bisa dipakai provinsi baru', function () {
    kirimWilayah(['label' => 'Segitiga Terumbu Karang'])->assertCreated();

    // Tanpa penggabungan di aturan validasinya, provinsi baru tidak bisa
    // ditempatkan di wilayah yang baru saja dibuat admin sendiri.
    kirimProvinsi(['nama' => 'Provinsi Karang', 'wilayah' => 'segitiga_terumbu_karang'])
        ->assertCreated();
});

test('wilayah yang masih dipakai destinasi tidak bisa dihapus', function () {
    $wilayah = WilayahTambahan::create(['kunci' => 'jalur_rempah', 'label' => 'Jalur Rempah']);

    DestinationPopuler::create([
        'destination_name' => 'Banda Neira', 'wilayah' => 'jalur_rempah',
        'provinsi' => 'Maluku', 'total_visitor' => 100,
    ]);

    // Berbeda dari provinsi yang sekadar tulisan di kartu, wilayah adalah
    // pengelompokan: destinasi yang wilayahnya dihapus kehilangan tabnya dan
    // tidak ketemu oleh penyaring mana pun — hilang tanpa ada yang memberitahu.
    kirimWilayah([], 'delete', $wilayah->id)->assertStatus(422);

    expect(WilayahTambahan::count())->toBe(1);
});

test('wilayah tambahan yang kosong boleh dihapus', function () {
    $wilayah = WilayahTambahan::create(['kunci' => 'jalur_rempah', 'label' => 'Jalur Rempah']);

    kirimWilayah([], 'delete', $wilayah->id)->assertOk();

    expect(WilayahTambahan::count())->toBe(0);
});

test('wilayah tambahan ikut jadi tab penyaring di halaman publik', function () {
    WilayahTambahan::create(['kunci' => 'jalur_rempah', 'label' => 'Jalur Rempah']);

    DestinationPopuler::create([
        'destination_name' => 'Banda Neira', 'wilayah' => 'jalur_rempah',
        'provinsi' => 'Maluku', 'total_visitor' => 100,
    ]);

    // Wilayah yang bisa ditambah tetapi tidak muncul di penyaring hanya
    // memindahkan destinasinya ke tempat yang tidak bisa ditemukan siapa pun.
    $this->get(route('destinasi'))->assertOk()
        ->assertSee('Jalur Rempah')
        ->assertSee('Banda Neira');
});

/* -------- KATALOG NAMA DESTINASI -------- */

use App\Models\Etalase\KatalogDestinasi;

function kirimKatalog(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->json($metode, '/api/v1/katalog-destinasi'.($id ? "/{$id}" : ''), $isi, [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('katalog bawaan menyebut provinsi yang dikenal semua', function () {
    // Provinsi yang tidak ada di daftar membuat isian terisi nama yang tidak
    // punya wilayah — destinasinya lalu hilang dari penyaring.
    $menyimpang = collect(config('orcha.katalog_destinasi'))
        ->reject(fn ($baris) => array_key_exists($baris['provinsi'], config('orcha.provinsi_wilayah')))
        ->keys()->all();

    // Daerahnya pun harus benar-benar bisa dipilih pada isian daerah — kalau
    // tidak, satu pilihan mengisi daerah yang tidak ada di daftarnya sendiri.
    $daerahAsing = collect(config('orcha.katalog_destinasi'))
        ->reject(fn ($baris) => array_key_exists($baris['daerah'], config('orcha.katalog_daerah')))
        ->keys()->all();

    expect($menyimpang)->toBe([])
        ->and($daerahAsing)->toBe([])
        ->and(config('orcha.katalog_destinasi'))->toHaveKey('Banyuwangi')
        ->and(config('orcha.katalog_destinasi')['Kawah Ijen']['daerah'])->toBe('Banyuwangi');
});

test('destinasi yang sudah tercatat ikut jadi pilihan', function () {
    DestinationPopuler::create([
        'destination_name' => 'Pantai Rahasia', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'total_visitor' => 10,
    ]);

    // Supaya admin tidak perlu menambahkannya lagi ke katalog hanya untuk bisa
    // memilihnya, dan supaya nama yang sudah dipakai muncul sebagai kemungkinan
    // duplikat.
    expect(KatalogDestinasi::gabungan())->toHaveKey('Pantai Rahasia')
        ->and(KatalogDestinasi::gabungan()['Pantai Rahasia']['provinsi'])->toBe('Jawa Timur');
});

test('nama baru tersimpan beserta provinsi yang dicari sendiri', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Pantai Melasti',
            'address' => ['state' => 'Bali'],
        ]]),
    ]);

    // Gunanya katalog ini bukan sekadar melengkapi nama, melainkan mengisi
    // provinsi dan wilayah sekaligus.
    kirimKatalog(['nama' => 'Pantai Melasti'])->assertCreated();

    expect(KatalogDestinasi::first()->provinsi)->toBe('Bali');
});

test('provinsi dan daerah yang disebut admin dipakai apa adanya, tanpa bertanya ke peta', function () {
    \Illuminate\Support\Facades\Http::fake();

    // Peta ditanya hanya bila ada yang belum diketahui — dua panggilan untuk
    // satu pertanyaan hanya memperlambat admin dan membebani layanan gratisnya.
    kirimKatalog([
        'nama' => 'Pantai Rahasia', 'provinsi' => 'Jawa Timur', 'daerah' => 'Situbondo',
    ])->assertCreated();

    expect(KatalogDestinasi::first()->provinsi)->toBe('Jawa Timur')
        ->and(KatalogDestinasi::first()->daerah)->toBe('Situbondo');

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('daerah ikut dicari peta bila belum disebut', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Pantai Baru',
            'address' => ['state' => 'Jawa Timur', 'county' => 'Kabupaten Banyuwangi'],
        ]]),
    ]);

    kirimKatalog(['nama' => 'Pantai Baru Sekali'])->assertCreated();

    expect(KatalogDestinasi::first()->daerah)->toBe('Banyuwangi');
});

test('nama yang tidak ketemu di peta tetap tersimpan', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([], 404),
    ]);

    // Separuh bantuan lebih baik daripada menolak menyimpan.
    kirimKatalog(['nama' => 'Tempat Antah Berantah'])->assertCreated();

    expect(KatalogDestinasi::first())->provinsi->toBeNull();
});

test('nama yang sudah ada tidak digandakan', function () {
    kirimKatalog(['nama' => 'Banyuwangi'])->assertOk();

    expect(KatalogDestinasi::count())->toBe(0);
});

test('menghapus entri katalog tidak menghapus destinasinya', function () {
    $katalog = KatalogDestinasi::create(['nama' => 'Pantai Rahasia', 'provinsi' => 'Jawa Timur']);

    DestinationPopuler::create([
        'destination_name' => 'Pantai Rahasia', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'total_visitor' => 10,
    ]);

    kirimKatalog([], 'delete', $katalog->id)->assertOk();

    // Namanya tetap muncul di daftar karena ikut dibaca dari destinasi yang ada.
    expect(DestinationPopuler::count())->toBe(1)
        ->and(KatalogDestinasi::gabungan())->toHaveKey('Pantai Rahasia');
});

test('rujukan mengirim katalog beserta penanda mana yang boleh dihapus', function () {
    KatalogDestinasi::create(['nama' => 'Pantai Rahasia', 'provinsi' => 'Jawa Timur']);

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $data = $this->getJson('/api/v1/rujukan', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data');

    expect($data['katalog_destinasi'])->toHaveKey('Banyuwangi')
        ->and($data['katalog_destinasi']['Banyuwangi']['provinsi'])->toBe('Jawa Timur')
        ->and($data['katalog_destinasi']['Banyuwangi']['daerah'])->toBe('Banyuwangi')
        ->and(collect($data['katalog_destinasi_kustom'])->pluck('nama')->all())->toBe(['Pantai Rahasia']);
});

/* -------- DAERAH (KABUPATEN / KOTA / KAWASAN) -------- */

use App\Models\Etalase\DaerahTambahan;

function kirimDaerah(array $isi, string $metode = 'post', ?int $id = null)
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->json($metode, '/api/v1/daerah'.($id ? "/{$id}" : ''), $isi, [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ]);
}

test('katalog daerah bawaan menyebut provinsi yang dikenal semua', function () {
    $menyimpang = collect(config('orcha.katalog_daerah'))
        ->reject(fn ($provinsi) => array_key_exists($provinsi, config('orcha.provinsi_wilayah')))
        ->keys()->all();

    expect($menyimpang)->toBe([])
        ->and(config('orcha.katalog_daerah'))->toHaveKey('Banyuwangi')
        ->and(config('orcha.katalog_daerah'))->toHaveKey('Karimunjawa')
        ->and(config('orcha.katalog_daerah'))->toHaveKey('Nusa Penida');
});

test('daerah baru tersimpan beserta provinsinya', function () {
    kirimDaerah(['nama' => 'Kabupaten Baru', 'provinsi' => 'Jawa Timur'])->assertCreated();

    expect(DaerahTambahan::gabungan())->toHaveKey('Kabupaten Baru')
        ->and(DaerahTambahan::gabungan()['Kabupaten Baru'])->toBe('Jawa Timur');
});

test('daerah wajib menyebut provinsinya', function () {
    // Daerah dipilih SESUDAH provinsi, dan daftar yang tidak tahu provinsinya
    // tidak bisa disaring.
    kirimDaerah(['nama' => 'Kabupaten Baru'])->assertStatus(422)
        ->assertJsonValidationErrors('provinsi');
});

test('daerah yang sudah ada di provinsi yang sama tidak digandakan', function () {
    kirimDaerah(['nama' => 'Banyuwangi', 'provinsi' => 'Jawa Timur'])->assertOk();

    expect(DaerahTambahan::count())->toBe(0);
});

test('daerah yang dipakai destinasi ikut jadi pilihan', function () {
    DestinationPopuler::create([
        'destination_name' => 'Pantai Rahasia', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'daerah' => 'Situbondo', 'total_visitor' => 10,
    ]);

    expect(DaerahTambahan::gabungan())->toHaveKey('Situbondo');
});

test('menghapus daerah tambahan tidak menyentuh destinasinya', function () {
    $daerah = DaerahTambahan::create(['nama' => 'Situbondo', 'provinsi' => 'Jawa Timur']);

    DestinationPopuler::create([
        'destination_name' => 'Pantai Rahasia', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'daerah' => 'Situbondo', 'total_visitor' => 10,
    ]);

    kirimDaerah([], 'delete', $daerah->id)->assertOk();

    expect(DestinationPopuler::first()->daerah)->toBe('Situbondo');
});

test('daerah tersimpan lewat api dan terkirim di daftar', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    test()->postJson('/api/v1/destinasi', [
        'nama' => 'Kawah Ijen', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi',
    ], [
        'X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertCreated();

    $baris = $this->getJson('/api/v1/destinasi', [
        'X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data.0');

    expect($baris['daerah'])->toBe('Banyuwangi')
        ->and($baris['alamat_singkat'])->toBe('Banyuwangi, Jawa Timur');
});

test('kartu publik menyebut daerah lebih dulu, lalu provinsinya', function () {
    DestinationPopuler::create([
        'destination_name' => 'Kawah Ijen', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'daerah' => 'Banyuwangi', 'total_visitor' => 900,
    ]);

    // "Jawa Timur" saja membentang 47 ribu km persegi — daerahnya yang dicari
    // dan ditanyakan penyewa.
    $this->get(route('destinasi'))->assertOk()->assertSee('Banyuwangi, Jawa Timur');
});

test('destinasi tanpa daerah tetap terbaca wajar', function () {
    DestinationPopuler::create([
        'destination_name' => 'Tempat Lama', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'total_visitor' => 10,
    ]);

    // Koma menggantung membuatnya tampak seperti data yang rusak.
    expect(DestinationPopuler::first()->alamat_singkat)->toBe('Jawa Timur');
});

test('usulan peta ikut menyebut daerahnya', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Kawah Ijen',
            'address' => ['state' => 'Jawa Timur', 'county' => 'Kabupaten Banyuwangi'],
        ]]),
    ]);

    // "Kabupaten" dibuang: yang dicari dan disebut penyewa "Banyuwangi".
    cariLokasi('Kawah Ijen')->assertOk()
        ->assertJsonPath('data.provinsi', 'Jawa Timur')
        ->assertJsonPath('data.daerah', 'Banyuwangi');
});

test('jawaban peta berbentuk lama tidak dipakai lagi setelah bentuknya berubah', function () {
    // Simpanan tiga puluh hari berbentuk lama diam-diam kehilangan medan baru:
    // provinsi terisi, daerah tidak, dan tidak ada satu pun tanda bahwa
    // penyebabnya cuma jawaban lama yang masih tersimpan.
    cache()->put('orcha.lokasi.'.md5('djawatan'), [
        'provinsi' => 'Jawa Timur', 'wilayah' => 'jawa', 'sumber' => 'peta',
    ], now()->addDays(30));

    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Djawatan',
            'address' => ['state' => 'Jawa Timur', 'county' => 'Kabupaten Banyuwangi'],
        ]]),
    ]);

    cariLokasi('Djawatan')->assertOk()->assertJsonPath('data.daerah', 'Banyuwangi');
});

/* -------- NAMA YANG BELUM SELESAI DIKETIK, DAN NAMA YANG BUKAN SATU TEMPAT -------- */

test('nama yang menyebut dua tempat dipenggal sampai peta mengenalinya', function () {
    // Kejadian sungguhan: "Pulau Cemara Kecil & Besar" adalah DUA pulau, dan
    // peta menjawab kosong untuk nama itu. "Pulau Cemara Kecil" ada, tercatat
    // rapi di Jepara — tetapi tidak pernah ditanyakan, sehingga formulir
    // tertinggal berisi tebakan dari nama yang belum selesai diketik.
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::sequence()
            ->push([])
            ->push([[
                'name' => 'Pulau Cemoro Kecil',
                'address' => ['state' => 'Jawa Tengah', 'county' => 'Jepara'],
            ]]),
    ]);

    cariLokasi('Pulau Cemara Kecil & Besar')->assertOk()
        ->assertJsonPath('data.provinsi', 'Jawa Tengah')
        ->assertJsonPath('data.daerah', 'Jepara')
        ->assertJsonPath('data.wilayah', 'jawa');

    // Ejaan peta berbeda — "Cemoro", bukan "Cemara" — dan itu memang harus
    // tetap lolos: yang dibandingkan kata per kata, dengan kelonggaran.
    \Illuminate\Support\Facades\Http::assertSentCount(2);
});

test('jawaban yang namanya jauh berbeda ditolak, bukan dipakai apa adanya', function () {
    // "Pula" — empat huruf pertama dari nama yang sedang diketik — dijawab
    // peta dengan sebuah pura di Jimbaran. Nama itu memang memuat kata "Pula",
    // tetapi hanya satu dari lima katanya, dan menerimanya berarti mengisi
    // BALI untuk pulau di Jawa Tengah.
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([[
            'name' => 'Kahyangan Jagat Pula Ulun Swi',
            'address' => ['state' => 'Bali'],
        ]]),
    ]);

    cariLokasi('Pula')->assertOk()->assertJsonPath('data', null);
});

test('yang dipilih calon yang namanya paling mendekati, bukan yang teratas', function () {
    // Urutan peta mengikuti ukuran dan ketenaran tempat, bukan kedekatan
    // namanya dengan yang ditanyakan.
    \Illuminate\Support\Facades\Http::fake([
        '*nominatim*' => \Illuminate\Support\Facades\Http::response([
            ['name' => 'Jalan Pantai Melasti', 'address' => ['state' => 'Jawa Barat']],
            ['name' => 'Pantai Melasti', 'address' => ['state' => 'Bali']],
        ]),
    ]);

    cariLokasi('Pantai Melasti')->assertOk()
        ->assertJsonPath('data.provinsi', 'Bali');
});

test('potongan huruf tidak dianggap cocok dengan destinasi yang tersimpan', function () {
    \Illuminate\Support\Facades\Http::fake();

    DestinationPopuler::create([
        'destination_name' => 'Kepulauan Derawan', 'wilayah' => 'kalimantan',
        'provinsi' => 'Kalimantan Timur', 'total_visitor' => 4100,
    ]);

    // "Pula" ada di dalam "KePULAuan" sebagai potongan huruf, bukan sebagai
    // kata. Dicocokkan dengan LIKE saja, mengetik empat huruf pertama sebuah
    // pulau di Jawa Tengah mengisi formulir dengan Kalimantan Timur.
    cariLokasi('Pula')->assertOk()->assertJsonPath('data', null);

    // Yang longgar pada KATA tetap berjalan: "Bromo" harus tetap menemukan
    // "Bromo Tengger Semeru".
    DestinationPopuler::create([
        'destination_name' => 'Bromo Tengger Semeru', 'wilayah' => 'jawa',
        'provinsi' => 'Jawa Timur', 'total_visitor' => 26700,
    ]);

    cariLokasi('Bromo')->assertOk()->assertJsonPath('data.provinsi', 'Jawa Timur');
});
