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
    $berkas = public_path('images/HERO/sewa-kendaraan.webp');

    expect(file_exists($berkas))->toBeTrue();

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('images/HERO/sewa-kendaraan.webp', false);
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

test('tombol menu tiga garis hanya untuk layar sempit', function () {
    // Kelas .menu-tombol ditulis di luar lapisan CSS, sedangkan utilitas
    // Tailwind v4 berada di dalam @layer utilities — dan aturan tanpa lapisan
    // selalu mengalahkan yang berlapis. Akibatnya `lg:hidden` di markup tidak
    // pernah menang, dan tombolnya ikut tampil di layar laptop berdampingan
    // dengan menu lengkap yang sudah ada.
    $css = file_get_contents(resource_path('css/new-homepage.css'));

    expect($css)->toContain('.menu-tombol {')
        // Penyembunyiannya harus ada di berkas yang sama dengan kelasnya.
        ->and($css)->toMatch('/@media \(min-width: 80rem\) \{\s*\.menu-tombol \{\s*display: none;/');
});

test('markup navbar tetap menyatakan maksudnya', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/guest.blade.php'));

    // Menu lengkap muncul mulai xl, tombol tiga garis berhenti di sana.
    //
    // Ambangnya bukan lg: pada 1024px — lebar iPad Pro tegak — enam tautan dan
    // satu tombol ajakan tidak muat, lalu melipat jadi dua baris yang menabrak
    // logo.
    expect($blade)->toContain('hidden gap-6 xl:flex')
        ->and($blade)->toContain('menu-tombol rounded-xl xl:hidden')
        // Tautan yang tidak muat harus meluber, bukan melipat diam-diam.
        ->and($blade)->toContain('flex-nowrap')
        ->and($blade)->toContain('whitespace-nowrap');
});

test('galeri beranda memakai foto admin walau baru sedikit', function () {
    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/rombongan.webp', 'urutan' => 1]);

    /*
     | Dulu ada aturan "kalau kurang dari enam, ganti foto bawaan" — masuk akal
     | waktu sumbernya foto destinasi yang mungkin cuma ada dua, tetapi merusak
     | begitu galerinya diisi admin: mengunggah lima foto berarti kelimanya
     | dibuang diam-diam, dan admin melihat beranda yang sama sekali tidak
     | berubah tanpa tahu apa yang salah.
     |
     | Jalur fotonya diulang sampai memenuhi pita berjalan, jadi satu foto pun
     | sudah cukup.
     */
    $isi = $this->get('/')->assertOk()->getContent();

    // Diukur di dalam bagian galerinya saja: pantai-wide.webp juga dipakai
    // sebagai poster video hero, jadi mencarinya di seluruh halaman tidak
    // mengukur apa pun tentang galeri.
    $galeri = substr($isi, strpos($isi, 'id="galeri"'));

    expect($galeri)
        ->toContain('/storage/galeri/rombongan.webp')
        ->not->toContain('images/pantai-wide.webp');
});

test('galeri yang benar-benar kosong tetap memakai foto cadangan', function () {
    // Beranda tidak boleh tampak kosong melompong hanya karena galerinya belum
    // pernah diisi.
    $isi = $this->get('/')->assertOk()->getContent();

    expect(substr($isi, strpos($isi, 'id="galeri"')))
        ->toContain('pantai-wide.webp');
});

test('keterangan galeri ikut tampil dan jadi alt gambarnya', function () {
    App\Models\Etalase\Galeri::create([
        'foto' => '/storage/galeri/rombongan.webp',
        'keterangan' => 'Rombongan SMA 1 di Kawah Ijen',
        'urutan' => 1,
    ]);

    /*
     | Keterangannya dipakai dua kali. Sebagai alt: yang dibaca pembaca layar
     | dan mesin telusur, dan sebelum ini seragam "Dokumentasi perjalanan"
     | untuk SEMUA foto — tidak memberi tahu apa pun. Sebagai keterangan yang
     | muncul saat tilenya disentuh: bukti bahwa yang dipajang rombongan
     | sungguhan, bukan foto stok.
     */
    $isi = $this->get('/')->assertOk()->getContent();
    $galeri = substr($isi, strpos($isi, 'id="galeri"'));

    expect($galeri)
        ->toContain('alt="Rombongan SMA 1 di Kawah Ijen"')
        // Pil kaca berlapis: selubung gradien, pil, lalu tulisannya.
        ->toContain('galeri-selubung')
        ->toContain('<span>Rombongan SMA 1 di Kawah Ijen</span>');
});

test('foto tanpa keterangan tidak menampilkan pita kosong', function () {
    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/a.webp', 'urutan' => 1]);

    // Pita gelap tanpa tulisan cuma menutupi foto tanpa memberi apa pun.
    $isi = $this->get('/')->assertOk()->getContent();
    $galeri = substr($isi, strpos($isi, 'id="galeri"'));

    expect($galeri)
        ->not->toContain('galeri-selubung')
        // altnya kembali ke kalimat umum, bukan kosong.
        ->toContain('alt="Dokumentasi perjalanan Orcha Journey"');
});

test('gambar yang dirujuk lewat asset() tetap ringan', function () {
    // Penjaga untuk kesalahan yang PERNAH terjadi dan tidak menimbulkan galat
    // apa pun: berkas gambar masuk pada ukuran aslinya.
    //
    // Logo situs sempat berupa PNG 1500x1500 seberat 815 KB, padahal
    // ditampilkan 40-64 piksel. Halaman tetap terbuka dan tampak benar — yang
    // rusak cuma kecepatannya, dan itu tidak terlihat dari layar mana pun.
    // Beratnya baru ketahuan dari alat ukur di luar, berbulan-bulan kemudian.
    //
    // Diperiksa dari jalur yang BENAR-BENAR tertulis di tampilan, bukan dari
    // daftar nama: daftar nama tidak ikut bertambah saat ada gambar baru.
    $dirujuk = [];

    foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $tampilan) {
        preg_match_all(
            '/asset\(\s*\x27([^\x27{$]+\.(?:webp|jpe?g|png|gif|avif))\x27\s*\)/i',
            $tampilan->getContents(),
            $cocok
        );
        $dirujuk = array_merge($dirujuk, $cocok[1]);
    }

    $dirujuk = array_values(array_unique($dirujuk));

    expect($dirujuk)->not->toBeEmpty();

    // 300 KB. Foto layar penuh yang sudah diringkas ke WebP berada di
    // 100-160 KB, jadi angka ini memberi kelonggaran hampir dua kali lipat
    // sambil tetap menangkap berkas yang masuk mentah.
    $batas = 300 * 1024;

    $hilang = [];
    $berat = [];

    foreach ($dirujuk as $jalur) {
        $berkas = public_path(ltrim($jalur, '/'));

        if (! file_exists($berkas)) {
            $hilang[] = $jalur;

            continue;
        }

        if (filesize($berkas) > $batas) {
            $berat[] = $jalur.' ('.round(filesize($berkas) / 1024).' KB)';
        }
    }

    expect($hilang)->toBe([], 'Gambar dirujuk tampilan tetapi berkasnya tidak ada: '.implode(', ', $hilang));
    expect($berat)->toBe([], 'Gambar melebihi '.round($batas / 1024).' KB, ringkas dulu sebelum dipakai: '.implode(', ', $berat));
});

test('halaman publik tidak pernah memuat gambar mentah', function (string $rute) {
    // Penjaga yang membaca HALAMAN JADI, bukan teks kodenya.
    //
    // Penjaga sebelumnya memindai berkas kode dan mencari asset('...') yang
    // jalurnya tertulis utuh. Itu melewatkan satu tempat yang justru sedang
    // dipakai produksi: cadangan galeri merakit jalurnya saat halaman dibuka
    //
    //     fn ($file) => asset("images/$file")
    //
    // sehingga teks "images/pantai-senja.jpg" tidak pernah ada di berkas mana
    // pun. Delapan foto seberat 400-800 KB tetap terkirim ke pengunjung, dan
    // tidak ada satu pun galat yang muncul — halamannya tampak benar.
    //
    // Yang dibaca di sini jalur yang BENAR-BENAR sampai ke peramban, jadi
    // dirakit atau tidak, tersimpan di basis data atau tidak, sama saja.
    $html = $this->get($rute)->assertOk()->getContent();

    preg_match_all('/(?:src|href)="([^"]*\/(?:images|build)\/[^"]+\.(?:webp|jpe?g|png|gif|avif))"/i', $html, $cocok);

    $jalur = array_values(array_unique($cocok[1]));

    $hilang = [];
    $berat = [];
    $batas = 300 * 1024;

    foreach ($jalur as $url) {
        // Jalur yang menunjuk keluar (foto unggahan di storage, gambar dari
        // layanan lain) tidak bisa diukur dari sini dan bukan urusan tes ini.
        $relatif = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($relatif === '' || ! str_starts_with($relatif, 'images/')) {
            continue;
        }

        $berkas = public_path($relatif);

        if (! file_exists($berkas)) {
            $hilang[] = $relatif;

            continue;
        }

        if (filesize($berkas) > $batas) {
            $berat[] = $relatif.' ('.round(filesize($berkas) / 1024).' KB)';
        }
    }

    expect($hilang)->toBe([], "[$rute] gambar dirujuk tetapi berkasnya tidak ada: ".implode(', ', $hilang));
    expect($berat)->toBe([], "[$rute] gambar melebihi 300 KB: ".implode(', ', $berat));
})->with(['/', '/paket-wisata', '/sewa-kendaraan', '/destinasi', '/kontak', '/tentang-kami']);

/*
 | Layar muat.
 |
 | Bentuknya mengikuti loader Phoenix Digital, tetapi memakai logo dan warna
 | Orcha. Yang dijaga di sini bukan seleranya, melainkan hal-hal yang membuat
 | layar muat berbahaya kalau jebol: ia menutupi SELURUH halaman, jadi kalau ia
 | tidak tahu cara menyingkir, situsnya tampak mati total.
 */
test('layar muat tampil di halaman publik dengan logo Orcha', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('orcha-loader', false)
        // Logonya logo Orcha, bukan logo Phoenix.
        ->assertSee('orcha-logo-only.png', false)
        ->assertDontSee('phoenix-mark', false);
})->with(['/', '/paket-wisata', '/destinasi', '/kontak']);

test('layar muat punya dua jalan keluar yang berdiri sendiri', function () {
    $partial = file_get_contents(resource_path('views/partials/orcha-loader.blade.php'));

    // 1. Jalan biasa: menyingkir setelah halaman selesai dimuat.
    expect($partial)->toContain("window.addEventListener('load', hide)");

    // 2. Jaring pengaman: menyingkir walau 'load' tidak pernah datang.
    expect($partial)->toContain('8000');

    /*
     | 3. Yang paling penting, dan yang tidak dipunyai loader Phoenix:
     |    kalau skripnya sama sekali tidak jalan, kedua jalan di atas ikut
     |    mati — penghitung 8 detik pun tidak pernah dinyalakan. Aturan CSS
     |    ini yang menanganinya, dan ia tidak bergantung pada JavaScript apa
     |    pun. Menghapusnya berarti satu berkas gagal dimuat = situs tampak
     |    mati total.
     */
    expect($partial)->toContain('.no-js .orc-loader');

    // Penanda no-js memang dipasang di <html> dan baru dilepas oleh skrip.
    expect(file_get_contents(resource_path('views/components/layouts/guest.blade.php')))
        ->toContain('class="no-js scroll-smooth"');
    expect(file_get_contents(resource_path('js/new-homepage.js')))
        ->toContain('classList.remove("no-js")');
});

test('layar muat menghormati pengguna yang mengurangi animasi', function () {
    // Tanpa ini, lencana berdenyut dan cincin berputar terus untuk orang yang
    // secara khusus meminta gerak dikurangi.
    expect(file_get_contents(resource_path('views/partials/orcha-loader.blade.php')))
        ->toContain('prefers-reduced-motion: reduce');
});

test('kunci gulir layar muat selalu dilepas kembali', function () {
    // Layar muat mengunci gulir <body> selama tampil. Kalau ada satu jalan
    // keluar saja yang lupa melepasnya, halaman termuat sempurna tetapi tidak
    // bisa digulung sama sekali — dan itu tidak menimbulkan galat apa pun.
    $partial = file_get_contents(resource_path('views/partials/orcha-loader.blade.php'));

    $dikunci = substr_count($partial, "classList.add('orc-terkunci')");
    $dilepas = substr_count($partial, "classList.remove('orc-terkunci')");

    expect($dilepas)->toBeGreaterThanOrEqual($dikunci);

    // Kelasnya harus benar-benar ada di gaya, bukan sekadar dipasang di skrip.
    expect(file_get_contents(resource_path('css/new-homepage.css')))
        ->toContain('body.orc-terkunci');
});

test('baris hak cipta menyebut merek sekaligus badan hukumnya', function () {
    // Nama badan hukum di footer gampang hilang tanpa disadari saat tata letak
    // kaki halaman dirapikan — dan tidak ada yang menimbulkan galat.
    $this->get('/')
        ->assertOk()
        ->assertSee('Orcha Journey', false)
        ->assertSee(config('orcha.perusahaan'), false)
        ->assertSee('Seluruh hak cipta dilindungi', false);
});

test('nama penerima transfer terpisah dari nama di footer', function () {
    /*
     | Keduanya mengeja badan hukum yang sama, tetapi punya tugas berbeda:
     | 'pembayaran.atas_nama' harus PERSIS seperti tertulis di rekening karena
     | itulah yang dicocokkan pelanggan sebelum mentransfer. Kalau suatu saat
     | keduanya disatukan, sekali seseorang merapikan huruf di footer, patokan
     | anti-penipuan di halaman pembayaran ikut berubah.
     */
    expect(config('orcha.pembayaran.atas_nama'))->toBe('PT ASTHANA CIPTA MANDIRI')
        ->and(config('orcha.perusahaan'))->not->toBe(config('orcha.pembayaran.atas_nama'));
});

test('halaman tentang kami menyebut badan hukum yang menaungi', function () {
    $this->get(route('tentang-kami'))
        ->assertOk()
        ->assertSee('di bawah naungan', false)
        ->assertSee(config('orcha.perusahaan'), false);
});

test('klaim di tentang kami benar: nama penerima memang dipajang ke pelanggan', function (string $url) {
    /*
     | Halaman Tentang Kami menjanjikan "nama yang Anda baca di halaman ini sama
     | dengan nama yang Anda temukan saat mentransfer". Itu janji yang bisa
     | jadi bohong tanpa ada yang sadar — cukup seseorang merapikan halaman
     | pembayaran dan mencopot nama penerimanya.
     |
     | Bukan sekadar soal kerapian: nama penerima adalah satu-satunya hal yang
     | bisa dicek pelanggan sebelum menyerahkan uang muka ke pihak yang baru
     | ditemuinya lewat internet.
     */
    $this->get($url)
        ->assertOk()
        ->assertSee(config('orcha.pembayaran.atas_nama'), false);
})->with(['/ketentuan-pembayaran', '/konfirmasi-pembayaran', '/faq']);
