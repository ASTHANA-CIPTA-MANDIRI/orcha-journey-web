<?php

/**
 * Menjaga hal-hal tampilan yang mudah terlewat: favicon, aksen tipografi,
 * dan kolom samping yang ikut menggulung.
 */
test('favicon Orcha tersedia dan tertaut di semua tata letak', function () {
    foreach (['favicon.ico', 'favicon-16.png', 'favicon-32.png', 'apple-touch-icon.png'] as $berkas) {
        expect(file_exists(public_path($berkas)))->toBeTrue("$berkas tidak ada");
    }

    // Bukan lagi favicon bawaan Laravel
    expect(file_exists(public_path('favicon.svg')))->toBeFalse();

    $this->get('/')
        ->assertOk()
        ->assertSee('favicon.ico', false)
        ->assertSee('apple-touch-icon.png', false);
});

test('halaman formulir memakai kolom samping yang ikut menggulung', function (string $nama) {
    $this->get(route($nama))
        ->assertOk()
        ->assertSee('lg:sticky lg:top-24', false);
})->with(['pendaftaran-open-trip', 'riwayat-kesehatan', 'pembatalan']);

test('kepala halaman memakai aksen kaligrafi', function () {
    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('aksen-orcha', false)
        ->assertSee('Great+Vibes', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('aksen-orcha', false);
});

test('hero sewa kendaraan memakai foto kendaraan', function () {
    expect(file_exists(public_path('images/kendaraan.jpg')))->toBeTrue();

    // Ukurannya sudah dipangkas untuk web, bukan berkas mentah 24 MB
    expect(filesize(public_path('images/kendaraan.jpg')))->toBeLessThan(1_000_000);

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('images/kendaraan.jpg', false);
});
