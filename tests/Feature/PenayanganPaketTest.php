<?php

use App\Models\PaketWisata\TravelPackage;

function buatPaket(array $ubah = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'name' => 'Open Trip Uji',
        'category' => 'open_trip',
        'price' => 1000000,
        'minimal_peserta' => 6,
    ], $ubah));
}

/* --------------------------- ATURAN TAYANG --------------------------- */

test('paket terbit tanpa jadwal langsung tayang', function () {
    $paket = buatPaket();

    expect($paket->sedang_tayang)->toBeTrue()
        ->and($paket->status_tayang_label)->toBe('Tayang');
});

test('draf dan arsip tidak pernah tayang', function (string $status, string $label) {
    $paket = buatPaket(['status' => $status]);

    expect($paket->sedang_tayang)->toBeFalse()
        ->and($paket->status_tayang_label)->toBe($label);
})->with([['draf', 'Draf'], ['arsip', 'Arsip']]);

test('paket terjadwal belum tayang sampai waktunya tiba', function () {
    $paket = buatPaket(['tayang_mulai' => now()->addDays(3)]);

    expect($paket->sedang_tayang)->toBeFalse()
        ->and($paket->status_tayang_label)->toBe('Terjadwal');

    // Begitu waktunya lewat, tayang sendiri tanpa ada yang menekan apa pun
    $this->travelTo(now()->addDays(4));

    expect($paket->fresh()->sedang_tayang)->toBeTrue();
});

test('paket berhenti tayang setelah batas waktunya', function () {
    $paket = buatPaket(['tayang_sampai' => now()->addDay()]);

    expect($paket->sedang_tayang)->toBeTrue();

    $this->travelTo(now()->addDays(2));

    expect($paket->fresh()->sedang_tayang)->toBeFalse()
        ->and($paket->fresh()->status_tayang_label)->toBe('Berakhir');
});

test('trip berhenti tayang begitu hari keberangkatan tiba', function () {
    $paket = buatPaket([
        'tanggal_berangkat' => now()->addDays(2)->toDateString(),
        'tanggal_pulang' => now()->addDays(4)->toDateString(),
    ]);

    expect($paket->sedang_tayang)->toBeTrue();

    // Sehari sebelum berangkat masih boleh mendaftar
    $this->travelTo(now()->addDay()->setTime(23, 0));
    expect($paket->fresh()->sedang_tayang)->toBeTrue();

    // Hari keberangkatan: pendaftaran tutup, paket tidak tampil lagi
    $this->travelTo(now()->addDays(2)->setTime(6, 0));
    expect($paket->fresh()->sedang_tayang)->toBeFalse()
        ->and($paket->fresh()->status_tayang_label)->toBe('Berakhir');
});

test('paket dengan tanggal contoh bisa dibebaskan dari berakhir otomatis', function () {
    $paket = buatPaket([
        'tanggal_berangkat' => now()->subMonth()->toDateString(),
        'berakhir_otomatis' => false,
    ]);

    expect($paket->sedang_tayang)->toBeTrue();
});

/* ------------------------- HALAMAN PUBLIK ------------------------- */

test('hanya paket tayang yang muncul di halaman publik', function () {
    $tampil = buatPaket(['name' => 'Trip Yang Tayang']);
    buatPaket(['name' => 'Trip Masih Draf', 'status' => 'draf']);
    buatPaket(['name' => 'Trip Belum Waktunya', 'tayang_mulai' => now()->addWeek()]);

    $this->get(route('paket-wisata'))
        ->assertOk()
        ->assertSee('Trip Yang Tayang')
        ->assertDontSee('Trip Masih Draf')
        ->assertDontSee('Trip Belum Waktunya');

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Trip Masih Draf');

    expect($tampil->sedang_tayang)->toBeTrue();
});

test('halaman detail paket yang belum tayang tidak bisa dibuka', function () {
    $draf = buatPaket(['status' => 'draf']);
    $tayang = buatPaket(['name' => 'Trip Tayang']);

    $this->get(route('paket-detail', $draf->uuid))->assertNotFound();
    $this->get(route('paket-detail', $tayang->uuid))->assertOk();
});

test('paket yang belum tayang tidak bisa dipilih di pendaftaran open trip', function () {
    buatPaket(['name' => 'Trip Draf', 'status' => 'draf']);
    buatPaket(['name' => 'Trip Siap']);

    $this->get(route('pendaftaran-open-trip'))
        ->assertOk()
        ->assertSee('Trip Siap')
        ->assertDontSee('Trip Draf');
});
