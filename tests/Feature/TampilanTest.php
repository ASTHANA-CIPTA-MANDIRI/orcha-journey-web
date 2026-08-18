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

test('setiap hero yang dirujuk tampilan ada berkasnya dan tidak dikirim mentah', function () {
    // Dua hal sekaligus, dan keduanya sudah pernah terjadi:
    //
    // 1. hero masuk sebagai PNG 2,2 MB. Batas satu megabita sebenarnya sudah
    //    ada, tetapi hanya menyebut SATU nama berkas — jadi begitu hero lain
    //    ditambahkan, batasnya tidak ikut berlaku dan tiga berkas besar lolos
    //    berturut-turut;
    // 2. berkas hero terhapus dari cakram sementara tampilan masih
    //    menunjuknya. Halamannya tetap terbuka — tidak ada galat sama sekali —
    //    hanya kepala halamannya kosong, dan itu baru ketahuan kalau ada yang
    //    membuka halaman itu.
    //
    // Karena itu yang diperiksa bukan daftar nama, melainkan setiap jalur
    // gambar yang benar-benar tertulis di tampilan.
    $dirujuk = [];

    foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $tampilan) {
        preg_match_all('/image="([^"{$]+)"/', $tampilan->getContents(), $cocok);
        $dirujuk = array_merge($dirujuk, $cocok[1]);
    }

    // Jalur yang dirakit Blade ({{ ... }}, $variabel) sengaja dilewati: isinya
    // baru diketahui saat halaman dibuka.
    $dirujuk = array_unique($dirujuk);

    expect($dirujuk)->not->toBeEmpty();

    $hilang = [];
    $berat = [];

    foreach ($dirujuk as $jalur) {
        $berkas = public_path($jalur);

        if (! file_exists($berkas)) {
            $hilang[] = $jalur;

            continue;
        }

        if (filesize($berkas) >= 1_000_000) {
            $berat[] = $jalur.' ('.round(filesize($berkas) / 1_048_576, 1).' MB)';
        }
    }

    expect($hilang)->toBe([])
        ->and($berat)->toBe([]);
});
