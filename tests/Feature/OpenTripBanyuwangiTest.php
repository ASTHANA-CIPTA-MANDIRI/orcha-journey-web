<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\ItineraryTeks;
use Livewire\Volt\Volt;

beforeEach(function () {
    (new Database\Seeders\TravelPackageSeeder)->run();

    $this->banyuwangi = TravelPackage::where('name', 'Open Trip Banyuwangi')->firstOrFail();
});

test('data Open Trip Banyuwangi sesuai poster', function () {
    $paket = $this->banyuwangi;

    expect($paket->category)->toBe('open_trip')
        ->and($paket->price)->toBe(1430000)
        ->and($paket->original_price)->toBe(1700000)
        ->and($paket->duration)->toBe('3 hari 2 malam')
        ->and($paket->minimal_peserta)->toBe(6)
        ->and($paket->titik_jemput)->toBe('Jogja, Klaten, Surakarta')
        ->and($paket->tanggal_berangkat->toDateString())->toBe('2026-10-19')
        ->and($paket->tanggal_pulang->toDateString())->toBe('2026-10-21')
        ->and($paket->jadwal_label)->toBe('19 – 21 Oktober 2026')
        ->and($paket->catatan_promo)->toContain('Early Bird')
        ->and($paket->fasilitas)->toContain('Transportasi AC PP', 'Makan 5x', 'Homestay', 'Banner & P3K')
        ->and($paket->destination_list)->toHaveCount(7)
        ->and($paket->destination_list)->toContain('De Djawatan', 'Pulau Tabuhan', 'Baluran', 'Pulau Menjangan', 'Savana Bekol', 'Pantai Bama', 'Grand Watu Dodol')
        ->and($paket->itinerary)->toHaveCount(3);
});

test('batas pelunasan jatuh H-5 sebelum keberangkatan', function () {
    // 19 Oktober dikurangi 5 hari = 14 Oktober
    expect($this->banyuwangi->batas_pelunasan->toDateString())->toBe('2026-10-14');
});

test('halaman detail paket memakai uuid, bukan nomor urut', function () {
    expect($this->banyuwangi->uuid)->toHaveLength(36);

    // Alamat dengan id numerik tidak boleh bisa dibuka
    $this->get('/paket/'.$this->banyuwangi->id)->assertNotFound();
    $this->get('/paket/'.$this->banyuwangi->uuid)->assertOk();
});

test('halaman detail paket menampilkan itinerary dan fasilitas', function () {
    $this->get(route('paket-detail', $this->banyuwangi->uuid))
        ->assertOk()
        ->assertSee('Open Trip Banyuwangi')
        ->assertSee('19 – 21 Oktober 2026')
        ->assertSee('Jogja, Klaten, Surakarta')
        ->assertSee('Penjemputan Meeting Point')
        ->assertSee('Explore De Djawatan Forest')
        ->assertSee('Transportasi AC PP')
        ->assertSee('Savana Bekol')
        ->assertSee('Pantai Bama')
        ->assertSee('Rp 1.430.000');
});

test('formulir pendaftaran memakai tanggal dan titik jemput dari paket', function () {
    Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $this->banyuwangi->uuid)
        ->set('nama', 'Peserta Uji')
        ->set('whatsapp', '081234567890')
        ->set('jumlahPeserta', 2)
        ->set('peserta', [
            ['nama' => 'Peserta Uji', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Peserta Kedua', 'titik_jemput' => 'Surakarta'],
        ])
        ->set('setuju', true)
        ->call('daftar')
        ->assertHasNoErrors();

    $pendaftaran = PendaftaranOpenTrip::firstOrFail();

    expect($pendaftaran->tanggal_berangkat->toDateString())->toBe('2026-10-19')
        // Yang tersimpan titik yang dipakai rombongan ini, bukan seluruh tawaran
        ->and($pendaftaran->titik_jemput)->toBe('Jogja, Surakarta');
});

test('semua halaman menyebut pelunasan H-5', function () {
    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('pelunasan paling lambat <strong>H-5</strong>', false);

    $this->get(route('faq'))
        ->assertOk()
        ->assertSee('Pelunasan paling lambat H-5', false);

    $this->get(route('syarat-ketentuan'))
        ->assertOk()
        ->assertSee('pelunasan paling lambat H-5', false);

    // Halaman detail paket menghitung tanggalnya sendiri
    $this->get(route('paket-detail', $this->banyuwangi->uuid))
        ->assertOk()
        ->assertSee('H-5')
        ->assertSee('14 Oktober 2026');
});

test('slogan tidak lagi memakai emoji', function () {
    expect(config('orcha.slogan'))->toBe('Teman Setia Perjalanan Anda!');

    $this->get('/')->assertOk()->assertDontSee('🌴');
});

test('itinerary bisa bolak-balik antara teks admin dan bentuk tersimpan', function () {
    $teks = ItineraryTeks::keTeks($this->banyuwangi->itinerary);

    expect($teks)->toContain('Day 1')
        ->and($teks)->toContain('18.00 | Penjemputan Meeting Point');

    // Diubah ke array lalu ke teks lagi harus menghasilkan isi yang sama
    expect(ItineraryTeks::keArray($teks))->toBe($this->banyuwangi->itinerary);
});

test('beranda memakai video hero bila berkasnya tersedia', function () {
    expect(file_exists(public_path('videos/hero.mp4')))->toBeTrue();

    $this->get('/')
        ->assertOk()
        ->assertSee('videos/hero.mp4', false)
        ->assertSee('<video', false);
});
