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

test('hero sewa kendaraan memakai foto armada sendiri', function () {
    $berkas = public_path('images/HERO/sewa-kendaraan.jpg');

    expect(file_exists($berkas))->toBeTrue();

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('images/HERO/sewa-kendaraan.jpg', false);
});

test('tidak ada hero yang dikirim mentah ke pengunjung', function () {
    // Batas ini sudah ada sebelumnya, hanya untuk satu berkas — dan begitu
    // hero lain ditambahkan, batasnya tidak ikut berlaku. Terbukti: tiga hero
    // baru masuk sebagai PNG 2,2-2,4 MB, dan yang menahannya cuma kebetulan
    // salah satunya dipakai halaman yang sudah diuji.
    //
    // Hero dimuat SETIAP halaman itu dibuka, sebelum apa pun terlihat. Dua
    // megabita di sana bukan soal ruang simpan, melainkan detik-detik pertama
    // pengunjung menunggu layar kosong.
    // Yang diperiksa berkas yang BENAR-BENAR DIRUJUK tampilan, bukan seluruh
    // isi foldernya. Berkas asli boleh saja tersimpan di sana; yang tidak
    // pernah diminta peramban tidak membebani siapa pun, dan memaksanya ikut
    // kecil hanya akan membuat penjagaan ini dilangkahi.
    $dirujuk = [];

    foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $tampilan) {
        preg_match_all('#images/HERO/[\w.-]+#', $tampilan->getContents(), $cocok);
        $dirujuk = array_merge($dirujuk, $cocok[0]);
    }

    expect($dirujuk)->not->toBeEmpty();

    $berat = [];

    foreach (array_unique($dirujuk) as $jalur) {
        $berkas = public_path($jalur);

        expect(file_exists($berkas))->toBeTrue("Hero {$jalur} dirujuk tampilan tetapi berkasnya tidak ada.");

        if (filesize($berkas) >= 1_000_000) {
            $berat[] = $jalur.' ('.round(filesize($berkas) / 1_048_576, 1).' MB)';
        }
    }

    expect($berat)->toBe([]);
});
