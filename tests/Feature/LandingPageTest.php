<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;

test('landing page tampil dan memuat semua section layanan', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Orcha')
        ->assertSee('Open Trip')
        ->assertSee('Private Trip')
        ->assertSee('Study Tour')
        ->assertSee('Sewa Kendaraan')
        ->assertSee('id="paket"', false)
        ->assertSee('id="armada"', false)
        ->assertSee('id="cara-pesan"', false);
});

test('landing page menampilkan data dari database', function () {
    TravelPackage::create([
        'name' => 'Open Trip Uji',
        'category' => 'open_trip',
        'duration' => '1 hari',
        'price' => 300000,
        'original_price' => 400000,
        'discount_percentage' => 25,
        'is_best_choice' => true,
        'destination_list' => ['Pantai Uji'],
    ]);

    Car::create([
        'name' => 'HiAce Uji',
        'brand' => 'Toyota',
        'type' => 'hiace',
        'price_per_day' => 1000000,
        'transmission' => 'Manual',
        'capacity' => 15,
        'is_available' => true,
    ]);

    DestinationPopuler::create([
        'destination_name' => 'Destinasi Uji',
        'total_visitor' => 1200,
        'main_photo' => '/images/laut.webp',
        'others_photo' => [],
    ]);

    Testimoni::create([
        'customer_name' => 'Pelanggan Uji',
        'rating' => 5,
        'testimonial' => 'Pelayanannya bagus.',
        // Bawaan basis datanya 'menunggu': yang dikirim pelanggan lewat
        // formulir publik harus disetujui dulu sebelum tampil.
        'status' => 'tayang',
    ]);

    Partner::create(['partner_name' => 'Partner Uji']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Open Trip Uji')
        ->assertSee('HiAce Uji')
        ->assertSee('Destinasi Uji')
        ->assertSee('Pelanggan Uji')
        ->assertSee('Partner Uji');
});

test('kendaraan yang tidak tersedia disembunyikan dari landing page', function () {
    Car::create([
        'name' => 'Bus Sedang Perbaikan',
        'brand' => 'Hino',
        'type' => 'bus',
        'price_per_day' => 3000000,
        'transmission' => 'Manual',
        'capacity' => 59,
        'is_available' => false,
    ]);

    $this->get('/')->assertOk()->assertDontSee('Bus Sedang Perbaikan');
});
