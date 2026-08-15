<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;

/** Ambil hanya bagian <nav>…</nav> (menu header) dari halaman. */
function menuHeader(string $html): string
{
    preg_match('#<nav\b.*?</nav>#s', $html, $cocok);

    return $cocok[0] ?? '';
}

$halamanPublik = ['home', 'tentang-kami', 'paket-wisata', 'sewa-kendaraan', 'destinasi', 'kontak'];

test('semua halaman menu utama bisa dibuka', function (string $nama) {
    $this->get(route($nama))->assertOk();
})->with($halamanPublik);

test('menu header hanya berisi enam tautan utama', function () {
    $menu = menuHeader($this->get(route('home'))->getContent());

    expect($menu)
        ->toContain('Beranda')
        ->toContain('Tentang Kami')
        ->toContain('Paket Wisata')
        ->toContain('Sewa Kendaraan')
        ->toContain('Destinasi')
        ->toContain('Kontak')
        // Testimoni cukup di beranda, FAQ cukup di footer
        ->not->toContain('>Testimoni<')
        ->not->toContain('>FAQ<');
});

test('faq dan testimoni tetap tertaut dari footer', function () {
    $halaman = $this->get(route('home'))->getContent();

    expect($halaman)
        ->toContain(route('faq'))
        ->toContain(route('testimoni'))
        ->and(menuHeader($halaman))->not->toContain(route('faq'));
});

test('menu menandai halaman yang sedang dibuka', function (string $nama) {
    $menu = menuHeader($this->get(route($nama))->getContent());

    expect($menu)->toContain('aria-current="page"');
})->with($halamanPublik);

test('halaman paket wisata bisa disaring per kategori', function () {
    TravelPackage::create([
        'name' => 'Open Trip Contoh', 'category' => 'open_trip', 'price' => 300000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Pantai Contoh'],
    ]);
    TravelPackage::create([
        'name' => 'Study Tour Contoh', 'category' => 'study_tour', 'price' => 900000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Museum Contoh'],
    ]);

    $this->get(route('paket-wisata'))
        ->assertOk()
        ->assertSee('Open Trip Contoh')
        ->assertSee('Study Tour Contoh');

    $this->get(route('paket-wisata', 'open-trip'))
        ->assertOk()
        ->assertSee('Paket Open Trip')
        ->assertSee('Open Trip Contoh')
        ->assertDontSee('Study Tour Contoh');
});

test('kategori paket yang tidak dikenal menghasilkan 404', function () {
    $this->get('/paket-wisata/paket-ngawur')->assertNotFound();
});

test('halaman sewa kendaraan bisa disaring per jenis', function () {
    Car::create([
        'name' => 'Bus Contoh', 'brand' => 'Hino', 'type' => 'bus', 'price_per_day' => 3000000,
        'transmission' => 'Manual', 'capacity' => 59, 'is_available' => true,
    ]);
    Car::create([
        'name' => 'Avanza Contoh', 'brand' => 'Toyota', 'type' => 'mobil', 'price_per_day' => 350000,
        'transmission' => 'Manual', 'capacity' => 7, 'is_available' => true,
    ]);

    $this->get(route('sewa-kendaraan', 'bus'))
        ->assertOk()
        ->assertSee('Sewa Bus')
        ->assertSee('Bus Contoh')
        ->assertDontSee('Avanza Contoh');
});

test('jenis kendaraan yang tidak dikenal menghasilkan 404', function () {
    $this->get('/sewa-kendaraan/helikopter')->assertNotFound();
});

test('halaman destinasi menampilkan data dan bisa dicari', function () {
    DestinationPopuler::create([
        'destination_name' => 'Pantai Contoh', 'total_visitor' => 5000,
        'main_photo' => '/images/laut.jpg', 'others_photo' => [],
    ]);
    DestinationPopuler::create([
        'destination_name' => 'Candi Contoh', 'total_visitor' => 9000,
        'main_photo' => '/images/gapura.jpg', 'others_photo' => [],
    ]);

    $this->get(route('destinasi'))
        ->assertOk()
        ->assertSee('Pantai Contoh')
        ->assertSee('Candi Contoh');

    $this->get(route('destinasi', ['cari' => 'Candi']))
        ->assertOk()
        ->assertSee('Candi Contoh')
        ->assertDontSee('Pantai Contoh');
});

test('beranda hanya menampilkan sorotan dan menaut ke halaman lengkap', function () {
    foreach (range(1, 9) as $i) {
        TravelPackage::create([
            'name' => "Paket Nomor $i", 'category' => 'open_trip', 'price' => 100000 * $i,
            'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
            'destination_list' => ['Destinasi'],
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Paket Nomor 1')
        ->assertDontSee('Paket Nomor 9')       // dibatasi 6 kartu di beranda
        ->assertSee('Lihat Semua Paket Wisata')
        ->assertSee('Lihat Semua Armada');
});

test('halaman kontak memuat kanal komunikasi', function () {
    $this->get(route('kontak'))
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertSee(config('orcha.email'))
        ->assertSee('Jam Operasional');
});

test('halaman tentang kami memakai slogan', function () {
    $this->get(route('tentang-kami'))
        ->assertOk()
        ->assertSee('Teman Setia Perjalanan Anda', false);
});
