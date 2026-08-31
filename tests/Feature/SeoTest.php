<?php

use App\Models\PaketWisata\TravelPackage;
use App\Support\Seo;

test('tiap halaman punya keterangannya sendiri, bukan satu kalimat untuk semua', function () {
    /*
     | Sebelumnya seluruh halaman publik memakai SATU keterangan yang sama.
     | Google menampilkannya di bawah tiap tautan, jadi hasil pencarian untuk
     | "sewa hiace jogja" dan "study tour" terbaca persis sama — dan tidak satu
     | pun menjawab yang dicari.
     */
    $sewa = $this->get(route('sewa-kendaraan'))->assertOk()->getContent();
    $paket = $this->get(route('paket-wisata'))->assertOk()->getContent();

    expect($sewa)->toContain('Sewa mobil, HiAce, dan bus pariwisata di Yogyakarta')
        ->and($paket)->toContain('Daftar paket open trip, private trip, dan study tour');

    // Dan keduanya memang berbeda
    preg_match('/<meta name="description" content="([^"]*)"/', $sewa, $a);
    preg_match('/<meta name="description" content="([^"]*)"/', $paket, $b);

    expect($a[1])->not->toBe($b[1]);
});

test('halaman punya alamat kanonis tanpa tempelan pelacak', function () {
    /*
     | Satu halaman bisa dicapai lewat beberapa alamat — dengan tempelan
     | ?utm_source dari kiriman WhatsApp, misalnya. Tanpa kanonis, mesin
     | pencari memperlakukannya sebagai halaman berbeda dan nilai satu halaman
     | terpecah ke beberapa alamat.
     */
    $isi = $this->get(route('destinasi').'?utm_source=wa&utm_campaign=agustus')
        ->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $isi, $cocok);

    expect($cocok[1] ?? '')->toBe(rtrim(route('destinasi'), '/'))
        ->and($cocok[1] ?? '')->not->toContain('utm_');
});

test('formulir sekali pakai dijauhkan dari hasil pencarian', function () {
    /*
     | Tidak ada yang mencarinya lewat Google, dan yang muncul justru
     | menyesatkan. Halaman riwayat kesehatan bahkan memuat pertanyaan medis
     | peserta.
     */
    foreach (['pendaftaran-open-trip', 'riwayat-kesehatan', 'pembatalan', 'konfirmasi-pembayaran'] as $rute) {
        expect($this->get(route($rute))->getContent())
            ->toContain('name="robots" content="noindex, nofollow"');
    }

    // Halaman jualan TIDAK boleh ikut terlarang
    foreach (['home', 'paket-wisata', 'sewa-kendaraan', 'destinasi'] as $rute) {
        expect($this->get(route($rute))->getContent())
            ->not->toContain('noindex');
    }
});

test('pratinjau tautan terisi judul, keterangan, dan gambar', function () {
    /*
     | Sebagian besar pengunjung Orcha datang dari tautan yang diteruskan di
     | grup WhatsApp. Tautan tanpa gambar dan judul tampil sebagai alamat
     | mentah yang jarang diketuk.
     */
    $isi = $this->get(route('home'))->assertOk()->getContent();

    expect($isi)
        ->toContain('property="og:title"')
        ->toContain('property="og:description"')
        ->toContain('property="og:image"')
        ->toContain('property="og:url"')
        ->toContain('name="twitter:card" content="summary_large_image"');
});

test('data terstruktur menyatakan situs ini agen perjalanan', function () {
    $isi = $this->get(route('home'))->assertOk()->getContent();

    // TravelAgency, bukan Organization umum — itu yang membuat Google boleh
    // menampilkan wilayah layanan dan kontaknya langsung di hasil pencarian.
    expect($isi)->toContain('"@type":"TravelAgency"')
        ->toContain('"name":"Orcha Journey"')
        ->toContain('Yogyakarta');
});

test('peta situs memuat halaman jualan dan paket yang tayang saja', function () {
    $tayang = TravelPackage::create([
        'name' => 'Open Trip Bromo', 'category' => 'open_trip',
        'price' => 750000, 'status' => 'terbit',
    ]);

    // Draf: belum diterbitkan admin, halaman publiknya menjawab 404
    $sembunyi = TravelPackage::create([
        'name' => 'Paket Lama', 'category' => 'open_trip',
        'price' => 500000, 'status' => 'draf',
    ]);

    $isi = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->getContent();

    /*
     | Namespace-nya dituntut PERSIS.
     |
     | Sempat saya tulis "http://www.w3.org/1999/sitemap/0.9" — alamat yang
     | tidak pernah ada. XML-nya tetap sah dan tetap bisa dibaca, jadi tidak
     | ada yang gagal di sini; Google yang menolaknya dengan "Namespace tidak
     | benar", dan itu baru ketahuan setelah peta situsnya dikirim.
     */
    expect($isi)->toContain('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');

    expect($isi)->toContain(route('home'))
        ->toContain(route('paket-wisata'))
        ->toContain(route('paket-detail', $tayang->uuid))
        /*
         | Paket yang disembunyikan admin menjawab 404 di halaman publik, dan
         | mengirimkannya ke mesin pencari berarti sengaja menunjukkan pintu
         | yang terkunci — alamat yang menjawab 404 membuat mesin pencari
         | mengunjungi situs ini lebih jarang.
         */
        ->not->toContain(route('paket-detail', $sembunyi->uuid));
});

test('robots menunjuk peta situs dan menutup yang memang tertutup', function () {
    $isi = file_get_contents(public_path('robots.txt'));

    expect($isi)->toContain('Sitemap:')
        ->toContain('sitemap.xml')
        ->toContain('Disallow: /admin/')
        ->toContain('Disallow: /riwayat-kesehatan')
        // Tautan pendek berkas pelanggan: satu alamat untuk satu orang
        ->toContain('Disallow: /t/');
});

test('keterangan dipotong pada batas kata, bukan di tengah kata', function () {
    $panjang = str_repeat('Paket wisata Yogyakarta yang menyenangkan. ', 20);

    $hasil = Seo::keterangan('home', $panjang);

    // Lebih panjang dipenggal Google di tengah kata; yang dipotong sendiri
    // pada batas kata setidaknya berakhir utuh.
    expect(mb_strlen($hasil))->toBeLessThanOrEqual(160)
        ->and($hasil)->toEndWith('…');
});

test('bukti kepemilikan google terpasang di seluruh halaman publik', function () {
    /*
     | Google memeriksanya di beranda, tetapi halaman yang kebetulan diperiksa
     | lebih dulu tidak boleh kehilangannya — karena itu tokennya di layout,
     | bukan ditempel di satu halaman.
     |
     | Tokennya BUKAN rahasia: ia memang dirancang untuk tampil di sumber
     | halaman. Yang dijaga uji ini justru jangan sampai hilang — begitu
     | lenyap, Google mencabut verifikasinya dan seluruh laporan Search Console
     | ikut tertutup, biasanya tanpa ada yang menyadarinya sampai berminggu.
     */
    $token = config('orcha.verifikasi_google');

    expect($token)->not->toBeEmpty();

    foreach (['home', 'paket-wisata', 'sewa-kendaraan', 'kontak'] as $rute) {
        expect($this->get(route($rute))->assertOk()->getContent())
            ->toContain('name="google-site-verification" content="'.$token.'"');
    }
});

test('tanpa token, meta verifikasinya tidak digambar sama sekali', function () {
    // Verifikasi lewat catatan TXT di DNS sama sahnya. Meta kosong justru
    // membuat Search Console menolaknya sebagai token yang tidak cocok.
    config()->set('orcha.verifikasi_google', null);

    expect($this->get(route('home'))->assertOk()->getContent())
        ->not->toContain('google-site-verification');
});

test('analytics tidak jalan di luar production', function () {
    /*
     | Tanpa penjagaan ini, tiap kali kita membuka halaman di laptop sendiri
     | angkanya ikut tercatat — dan laporan yang dipakai mengambil keputusan
     | tercampur lalu lintas orang yang sedang mengoding.
     */
    expect(app()->environment())->not->toBe('production');

    expect($this->get(route('home'))->assertOk()->getContent())
        ->not->toContain('googletagmanager.com/gtag/js');
});

test('analytics jalan di halaman jualan saat production', function () {
    app()->detectEnvironment(fn () => 'production');

    $isi = $this->get(route('home'))->assertOk()->getContent();

    expect($isi)->toContain('googletagmanager.com/gtag/js?id='.config('orcha.analitik_google'))
        // Alamat dikirim tanpa query: halaman jualan pun bisa membawa
        // ?cari=... yang isinya ketikan pengunjung sendiri.
        ->toContain('window.location.pathname')
        ->toContain('anonymize_ip');
});

test('analytics dimatikan di halaman yang membawa data pelanggan di alamatnya', function () {
    app()->detectEnvironment(fn () => 'production');

    /*
     | Ini bukan soal SEO melainkan PRIVASI.
     |
     | /riwayat-kesehatan?kode=OT-1608-ZT8K&nama=... membawa kode pesanan dan
     | nama peserta DI ALAMATNYA, dan GA4 merekam alamat halaman berikut
     | seluruh query-nya. Menyalakannya di sana sama dengan mengirim data
     | pelanggan ke Google — dan halaman itu bahkan memuat jawaban pertanyaan
     | medis.
     |
     | Yang tidak dikirim tidak bisa bocor.
     */
    foreach (['riwayat-kesehatan', 'konfirmasi-pembayaran', 'pembatalan', 'pendaftaran-open-trip'] as $rute) {
        expect($this->get(route($rute))->assertOk()->getContent())
            ->not->toContain('googletagmanager.com/gtag/js');
    }
});

test('tanpa id pengukuran, skrip analytics tidak digambar sama sekali', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('orcha.analitik_google', null);

    expect($this->get(route('home'))->assertOk()->getContent())
        ->not->toContain('googletagmanager.com');
});

test('meta pixel jalan di halaman jualan saat production', function () {
    app()->detectEnvironment(fn () => 'production');

    $isi = $this->get(route('home'))->assertOk()->getContent();

    expect($isi)->toContain('connect.facebook.net/en_US/fbevents.js')
        ->toContain("fbq('init', \"".config('orcha.meta_pixel').'")')
        ->toContain("fbq('track', 'PageView')")
        // Jaring untuk peramban tanpa JavaScript
        ->toContain('facebook.com/tr?id='.config('orcha.meta_pixel'));
});

test('meta pixel dimatikan di halaman yang memuat data pelanggan', function () {
    app()->detectEnvironment(fn () => 'production');

    /*
     | Untuk Pixel ini lebih penting daripada untuk Google Analytics.
     |
     | Meta menyalakan "Otomatis sertakan info halaman dan produk yang lebih
     | detail" secara bawaan, dan fitur itu tidak sekadar mencatat alamat: ia
     | MEMBACA ISI HALAMAN dengan AI lalu mengirimkannya ke Meta.
     |
     | Di /riwayat-kesehatan yang dibacanya jawaban pertanyaan medis peserta.
     | Karena itu Pixel tidak dimuat sama sekali di sana — bukan sekadar
     | dikurangi datanya. Yang tidak dimuat tidak bisa membaca apa pun.
     */
    foreach (['riwayat-kesehatan', 'konfirmasi-pembayaran', 'pembatalan', 'pendaftaran-open-trip'] as $rute) {
        expect($this->get(route($rute))->assertOk()->getContent())
            ->not->toContain('fbevents.js')
            ->not->toContain('facebook.com/tr?id=');
    }
});

test('kedua pelacak memakai penjagaan yang sama, tidak mungkin salah satu lolos', function () {
    /*
     | Dua syarat yang ditulis terpisah lambat laun berbeda, dan yang berbeda
     | diam-diam justru yang berbahaya: Pixel yang tetap menyala di halaman
     | medis tidak akan ketahuan sampai ada yang memeriksa Events Manager.
     */
    $layout = file_get_contents(resource_path('views/components/layouts/guest.blade.php'));

    expect($layout)->toContain('$bolehMelacak = app()->isProduction() && $bolehIndeks')
        ->toContain('$bolehAnalitik = filled($analitik) && $bolehMelacak')
        ->toContain('$bolehPixel = filled($pixel) && $bolehMelacak');

    // Dan di luar production keduanya diam
    expect(app()->environment())->not->toBe('production');

    $isi = $this->get(route('home'))->assertOk()->getContent();

    expect($isi)->not->toContain('fbevents.js')
        ->not->toContain('googletagmanager');
});

/*
 |--------------------------------------------------------------------------
 | Halaman yang punya isi sendiri
 |--------------------------------------------------------------------------
 |
 | Halaman paket dan halaman destinasi berjumlah puluhan dan isinya berbeda
 | satu sama lain. Keterangan per-rute di App\Support\Seo tidak bisa melayani
 | keduanya — satu kalimat untuk "halaman paket" akan sama untuk semua paket —
 | jadi keduanya mengirim judul dan keterangannya sendiri.
 */

test('halaman paket memakai nama paketnya sendiri, bukan judul beranda', function () {
    $paket = App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Bromo Midnight',
        'category' => 'open_trip',
        'price' => 750000,
        'status' => 'terbit',
        'duration' => '2 hari 1 malam',
    ]);

    $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    /*
     | Halaman ini SUDAH lama didaftarkan di peta situs, tetapi tidak pernah
     | mengirim judul maupun keterangannya sendiri — jadi tiap paket muncul di
     | hasil pencarian dengan judul dan kalimat yang persis sama dengan
     | beranda. Sepuluh hasil yang berbeda terbaca sebagai sepuluh salinan
     | halaman yang sama, dan tidak satu pun menyebut paket mana yang dilihat.
     */
    expect($isi)
        ->toContain('<title>Open Trip Bromo Midnight — Open Trip | Orcha Journey</title>')
        ->not->toContain('<title>Orcha Journey — Open Trip, Private Trip, Study Tour');

    preg_match('/<meta name="description" content="([^"]*)"/', $isi, $ket);

    // Yang MEMBEDAKAN paket ini dari paket lain: harga dan lamanya, bukan
    // kalimat pemasaran umum yang sama untuk semuanya.
    expect($ket[1])->toContain('Rp 750.000')->toContain('2 hari 1 malam');
});

test('halaman paket mengirim harga sebagai data, bukan hanya kalimat', function () {
    $paket = App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Private Trip Dieng',
        'category' => 'private_trip',
        'price' => 1250000,
        'status' => 'terbit',
    ]);

    $isi = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    /*
     | Harga yang dikirim sebagai data terstruktur boleh ditampilkan Google
     | langsung di bawah tautan; harga yang cuma tertulis di kalimat harus
     | ditebaknya sendiri dari teks halaman, dan sering tidak ditampilkan sama
     | sekali.
     */
    expect($isi)
        ->toContain('"@type":"TouristTrip"')
        ->toContain('"price":"1250000"')
        ->toContain('"priceCurrency":"IDR"');
});

test('tiap destinasi punya halaman dan judulnya sendiri', function () {
    $destinasi = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Raja Ampat',
        'wilayah' => 'papua',
        'provinsi' => 'Papua Barat Daya',
        'daerah' => 'Raja Ampat',
        'deskripsi' => 'Gugusan karst di atas laut paling jernih di Indonesia.',
    ]);

    /*
     | Sebelumnya nama destinasi hanya muncul sebagai teks di tengah satu
     | halaman daftar yang panjang: tidak ada alamat yang bisa diberikan ke
     | mesin pencari, jadi "Raja Ampat" tidak pernah bisa menjadi hasil
     | pencariannya sendiri.
     */
    $isi = $this->get(route('destinasi.detail', $destinasi))->assertOk()->getContent();

    expect($isi)
        ->toContain('Raja Ampat — Raja Ampat, Papua Barat Daya | Orcha Journey')
        ->toContain('Gugusan karst di atas laut paling jernih di Indonesia.')
        // TouristAttraction, bukan sekadar halaman yang menyebut nama tempat.
        ->toContain('"@type":"TouristAttraction"')
        ->toContain('"addressRegion":"Papua Barat Daya"');
});

test('slug destinasi tidak berubah saat namanya disunting', function () {
    $destinasi = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Pantai Indrayanti',
        'wilayah' => 'jawa',
    ]);

    expect($destinasi->slug)->toBe('pantai-indrayanti');

    /*
     | Alamatnya sudah beredar — dibagikan di WhatsApp, dicatat mesin pencari.
     | Memperbaiki ejaan nama tidak sepadan dengan mematikan semua tautan yang
     | sudah tersebar, jadi slugnya sengaja tidak ikut berubah.
     */
    $destinasi->update(['destination_name' => 'Pantai Indrayanti Gunungkidul']);

    expect($destinasi->fresh()->slug)->toBe('pantai-indrayanti');
});

test('destinasi bernama sama tetap dapat alamat masing-masing', function () {
    $satu = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Pantai Selatan', 'wilayah' => 'jawa',
    ]);

    // Dua tempat boleh bernama sama di kabupaten yang berbeda. Tanpa akhiran
    // angka, yang kedua ditolak kunci unik saat admin menekan simpan.
    $dua = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Pantai Selatan', 'wilayah' => 'jawa',
    ]);

    expect($satu->slug)->toBe('pantai-selatan')
        ->and($dua->slug)->toBe('pantai-selatan-2');
});

test('nama destinasi di daftar bisa diikuti mesin pencari', function () {
    $destinasi = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Kawah Ijen', 'wilayah' => 'jawa',
    ]);

    $isi = $this->get(route('destinasi'))->assertOk()->getContent();

    /*
     | Tombol "Lihat Detail" membuka panel di halaman yang sama — itu memang
     | disengaja. Tetapi tombol bukan tautan: mesin pencari tidak menekannya,
     | sehingga tanpa <a> pada namanya tidak ada satu pun jalan menuju halaman
     | destinasi selain peta situs.
     */
    expect($isi)->toContain('href="'.route('destinasi.detail', $destinasi).'"');
});

test('peta situs memuat tiap destinasi', function () {
    $destinasi = App\Models\Etalase\DestinationPopuler::create([
        'destination_name' => 'Labuan Bajo', 'wilayah' => 'nusa-tenggara',
    ]);

    $isi = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($isi)->toContain(route('destinasi.detail', $destinasi));
});
