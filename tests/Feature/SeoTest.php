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
