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
