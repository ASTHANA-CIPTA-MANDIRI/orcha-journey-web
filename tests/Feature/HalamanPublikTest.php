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
        'main_photo' => '/images/laut.webp', 'others_photo' => [],
    ]);
    DestinationPopuler::create([
        'destination_name' => 'Candi Contoh', 'total_visitor' => 9000,
        'main_photo' => '/images/gapura.webp', 'others_photo' => [],
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

test('beranda menampilkan tiga paket TERBARU dan menaut ke halaman lengkap', function () {
    foreach (range(1, 9) as $i) {
        TravelPackage::create([
            'name' => "Paket Nomor $i", 'category' => 'open_trip', 'price' => 100000 * $i,
            'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
            'destination_list' => ['Destinasi'],
        ]);
    }

    // Harganya sengaja menaik mengikuti nomornya. Dengan urutan lama — termurah
    // dulu — yang tampil justru Nomor 1, 2, 3; jadi kalau uji ini hijau untuk
    // 9, 8, 7, yang teruji memang urutan TERBARU, bukan kebetulan.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Paket Nomor 9')
        ->assertSee('Paket Nomor 8')
        ->assertSee('Paket Nomor 7')
        ->assertDontSee('Paket Nomor 6')
        ->assertDontSee('Paket Nomor 1')
        // Jalan ke daftar lengkapnya sekarang berupa tab di kepala tiap panel,
        // bukan tombol panjang di bawahnya. Yang diuji tetap hal yang sama:
        // beranda hanya menyorot, daftar lengkapnya ada di halaman sendiri.
        ->assertSee('Semua Paket')
        ->assertSee('Semua Armada')
        ->assertSee(route('paket-wisata'), false)
        ->assertSee(route('sewa-kendaraan'), false);
});

test('hanya satu paket yang diangkat jadi kartu sorotan, berapa pun yang ditandai admin', function () {
    // Penanda "terlaris" di admin boleh menempel di banyak paket sekaligus —
    // saat ini tiga dari lima. Kalau semuanya ikut disorot, tidak ada yang
    // menonjol dan sorotannya kehilangan gunanya.
    foreach (range(1, 4) as $i) {
        TravelPackage::create([
            'name' => "Paket Tandai $i", 'category' => 'open_trip', 'price' => 100000,
            'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => true,
            'destination_list' => ['Destinasi'],
        ]);
    }

    $isi = $this->get(route('home'))->assertOk()->getContent();

    // Satu kartu sorotan, dan hanya satu: yang lain berjajar sebagai kartu
    // biasa di panel di bawahnya.
    expect(substr_count($isi, 'Paket Sorotan'))->toBe(1)
        ->and(substr_count($isi, 'Lihat Detail'))->toBe(2);
});

test('paket yang disorot DIANGKAT ke kartu sorotan, bukan diulang di daftar bawahnya', function () {
    // Tiga paket dibuat berurutan; yang ditandai justru yang PALING LAMA, jadi
    // menurut urutan terbaru ia semestinya duduk paling kanan di daftar. Kalau
    // uji ini hijau, yang teruji memang pemindahannya — bukan kebetulan.
    $tandai = TravelPackage::create([
        'name' => 'Paket Ditandai', 'category' => 'open_trip', 'price' => 100000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => true,
        'destination_list' => ['Destinasi'],
    ]);

    foreach (['Paket Biasa Satu', 'Paket Biasa Dua'] as $nama) {
        TravelPackage::create([
            'name' => $nama, 'category' => 'open_trip', 'price' => 100000,
            'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
            'destination_list' => ['Destinasi'],
        ]);
    }

    $isi = $this->get(route('home'))->assertOk()->getContent();

    // Dipisah lewat id bagiannya, bukan lewat urutan kemunculan huruf: nama
    // paket juga muncul di dalam tautan WhatsApp tiap kartu.
    $awalDaftar = strpos($isi, 'id="paket"');
    $awalSorotan = strpos($isi, 'id="paket-sorotan"');
    $sorotan = substr($isi, $awalSorotan, $awalDaftar - $awalSorotan);
    $daftar = substr($isi, $awalDaftar);

    expect($sorotan)->toContain($tandai->name)
        // Satu paket yang sama tampil dua kali membuat beranda terbaca seperti
        // salah data, bukan seperti sorotan.
        ->and($daftar)->not->toContain($tandai->name)
        ->and($daftar)->toContain('Paket Biasa Satu')
        ->and($daftar)->toContain('Paket Biasa Dua');
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

/* ------------------------------- Sampul yang tidak terpotong */

/*
 | Sampul paket TIDAK boleh dipotong.
 |
 | Sampulnya sering berupa poster dengan tulisan yang sudah menyatu di
 | gambarnya — judul, harga, titik jemput — dan object-cover membuang sisi
 | kiri-kanannya. Terukur pada poster yang tayang: 1585x992 (rasio 1,60) di
 | panel berasio 1,09, jadi 32% lebarnya terbuang.
 |
 | Yang diperiksa markahnya, bukan rupanya: uji ini tidak bisa melihat
 | gambar, tetapi bisa memastikan kelas yang memotong tidak kembali
 | diam-diam ke kartunya.
 */
test('sampul kartu paket memakai contain, bukan cover', function () {
    TravelPackage::create([
        'name' => 'Paket Poster', 'category' => 'open_trip', 'price' => 350000,
        'original_price' => 0, 'discount_percentage' => null, 'is_best_choice' => false,
        'destination_list' => ['Dieng'],
    ]);

    $isi = $this->get(route('paket-wisata'))->assertOk()->getContent();

    expect($isi)
        /*
         | Rasionya ada di GAMBARNYA, bukan di kotaknya.
         |
         | Saat rasio dipasang di kotak dan gambarnya diberi pb-5, kotak ISI-nya
         | jadi lebar x (lebar/1,6 - 20) — bukan 1,60 lagi — dan poster 1,60
         | menyisakan celah TEPAT 16px di kiri dan kanan, berapa pun lebar
         | kartunya.
         */
        ->toContain('block w-full aspect-[16/10] object-contain')
        // 20px untuk panel yang menindih kini padding KOTAKNYA.
        ->and($isi)->toContain('relative pb-5 overflow-hidden')
        // Latar buram pengisi pita kosong untuk sampul yang rasionya lain —
        // tanpa itu panelnya berlubang dan terbaca seperti gambar gagal muat.
        ->and($isi)->toContain('blur-xl')
        // Dua bentuk lama yang TIDAK boleh kembali: object-cover memotong
        // posternya, dan rasio-di-kotak + pb-5 mengembalikan celah 16px.
        ->and($isi)->not->toContain('object-contain w-full h-full pb-5')
        ->and($isi)->not->toContain('aspect-[16/10] max-h-64');
});
