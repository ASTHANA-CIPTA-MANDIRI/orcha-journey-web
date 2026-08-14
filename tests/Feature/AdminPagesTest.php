<?php

use App\Models\Car;
use App\Models\TravelPackage;
use App\Models\User;

$halamanAdmin = [
    '/admin/dashboard',
    '/admin/paket-wisata',
    '/admin/paket-wisata/create',
    '/admin/sewa-kendaraan',
    '/admin/destinasi',
    '/admin/testimoni',
    '/admin/testimoni/create',
    '/admin/partner',
];

test('tamu diarahkan ke halaman login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with($halamanAdmin);

test('admin bisa membuka semua halaman panel', function (string $url) {
    $this->actingAs(User::factory()->create());

    $this->get($url)->assertOk();
})->with($halamanAdmin);

test('halaman ubah paket wisata bisa dibuka', function () {
    $this->actingAs(User::factory()->create());

    $package = TravelPackage::create([
        'name' => 'Paket Uji',
        'category' => 'study_tour',
        'price' => 500000,
        'original_price' => 600000,
        'discount_percentage' => 16,
        'is_best_choice' => false,
        'destination_list' => ['Destinasi Uji'],
    ]);

    $this->get("/admin/paket-wisata/$package->id/edit")
        ->assertOk()
        ->assertSee('Paket Uji');
});

test('jenis kendaraan default adalah mobil', function () {
    $car = Car::create([
        'name' => 'Avanza Uji',
        'brand' => 'Toyota',
        'price_per_day' => 350000,
        'transmission' => 'Manual',
        'capacity' => 7,
        'is_available' => true,
    ]);

    expect($car->fresh()->type)->toBe('mobil')
        ->and($car->fresh()->type_label)->toBe('Mobil');
});
