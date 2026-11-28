<?php

use App\Models\Etalase\Galeri;
use Illuminate\Support\Facades\File;

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

test('kepala halaman memakai label yang sama dengan beranda', function () {
    // Dulu kepala halaman dibuka kaligrafi Great Vibes, dan uji ini
    // mewajibkannya. Aturan itu DICABUT: huruf sambung tersebut tidak dipakai
    // di satu tempat pun di beranda, sehingga tiap halaman lain terbuka dengan
    // gaya tulisan yang tidak pernah muncul lagi sesudahnya.
    //
    // Sekarang semua kepala halaman memakai .label-bagian — kapital kecil
    // berjarak lebar berwarna emas — persis seperti label tiap bagian di
    // beranda.
    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('label-bagian', false)
        ->assertDontSee('aksen-orcha', false);

    // Hurufnya ikut dilepas dari muatan: satu keluarga huruf utuh yang tidak
    // dipakai siapa pun tetap diunduh setiap kunjungan.
    foreach (['/', route('sewa-kendaraan'), route('tentang-kami')] as $alamat) {
        $this->get($alamat)
            ->assertOk()
            ->assertDontSee('Great+Vibes', false);
    }
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

    foreach (File::allFiles(resource_path('views')) as $tampilan) {
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
    expect($blade)->toMatch('/class="[^"]*\bhidden\b[^"]*\bxl:flex\b[^"]*"/')
        ->and($blade)->toMatch('/class="[^"]*menu-tombol[^"]*\bxl:hidden\b[^"]*"/')
        // Tautan yang tidak muat harus meluber, bukan melipat diam-diam.
        ->and($blade)->toContain('flex-nowrap')
        ->and($blade)->toContain('whitespace-nowrap');

    // Perangkap yang sama dengan .menu-tombol di atas, pada tombol lain:
    // .btn-orcha menetapkan display di luar lapisan CSS, jadi kelas `hidden`
    // milik Tailwind tidak pernah menang. Tombol ajakan yang disembunyikan
    // begitu tetap tampil di ponsel dan menghimpit tombol menu sampai
    // terpotong tepi layar. Penyembunyiannya harus lewat pembungkus.
    expect($blade)->not->toMatch('/class="[^"]*\bhidden\b[^"]*btn-orcha/');
});

test('kelas yang menetapkan display tidak disembunyikan dengan utilitas hidden', function () {
    /*
     | Perangkap yang sama sudah tiga kali muncul: .menu-tombol, .btn-orcha,
     | lalu .tombol-bulat. Ketiganya menetapkan display di new-homepage.css —
     | berkas yang isinya TIDAK berada di dalam @layer — sedangkan utilitas
     | `hidden` milik Tailwind v4 ada di dalamnya. CSS tanpa lapisan selalu
     | menang, jadi `hidden` pada elemen berkelas itu tidak melakukan apa pun
     | dan tidak ada satu pun galat yang muncul.
     |
     | Jalan keluarnya selalu sama: sembunyikan PEMBUNGKUSNYA.
     */
    $kelasBerdisplay = ['btn-orcha', 'tombol-bulat', 'menu-tombol', 'chip-orcha', 'ubin-orcha', 'kartu-destinasi'];

    $berkas = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'));

    $pelanggaran = [];

    foreach ($berkas as $f) {
        foreach (file($f->getPathname()) as $nomor => $baris) {
            if (! preg_match_all('/class="([^"]*)"/', $baris, $cocok)) {
                continue;
            }

            foreach ($cocok[1] as $kelas) {
                if (! preg_match('/(^|\s)hidden(\s|$)/', $kelas)) {
                    continue;
                }

                foreach ($kelasBerdisplay as $k) {
                    if (preg_match('/(^|\s)'.preg_quote($k, '/').'(\s|$)/', $kelas)) {
                        $pelanggaran[] = $f->getRelativePathname().':'.($nomor + 1).' — '.$k;
                    }
                }
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});

test('galeri beranda memakai foto admin walau baru sedikit', function () {
    Galeri::create(['foto' => '/storage/galeri/rombongan.webp', 'urutan' => 1]);

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
    Galeri::create([
        'foto' => '/storage/galeri/rombongan.webp',
        'keterangan' => 'Rombongan SMA 1 di Kawah Ijen',
        'urutan' => 1,
    ]);

    /*
     | Keterangannya dipakai dua kali. Sebagai alt: yang dibaca pembaca layar
     | dan mesin telusur, dan sebelum ini seragam "Dokumentasi perjalanan"
     | untuk SEMUA foto — tidak memberi tahu apa pun. Sebagai keterangan yang
     | tampil di foto: bukti bahwa yang dipajang rombongan sungguhan, bukan
     | foto stok.
     |
     | Dulu uji ini mengunci nama kelas `galeri-selubung` dan susunan
     | <span> milik rancangan pita berjalan. Galerinya kini korsel 3D dan
     | keterangannya satu <figcaption>, jadi yang diperiksa dikembalikan ke
     | PERILAKUNYA — keterangan dipakai sebagai alt dan digambar — bukan ke
     | nama kelas yang kebetulan dipakai rancangan saat itu.
     */
    $isi = $this->get('/')->assertOk()->getContent();
    $galeri = substr($isi, strpos($isi, 'id="galeri"'));

    expect($galeri)
        ->toContain('alt="Rombongan SMA 1 di Kawah Ijen"')
        // <span> di dalamnya: pil keterangan pada tata letak DASAR, yaitu yang
        // terlihat kalau JavaScript gagal dimuat. Saat korsel 3D hidup,
        // keterangannya dipindah ke lajur kiri dan pil ini disembunyikan CSS —
        // markahnya tetap ada karena ia juga sumber kalimat untuk lajur itu.
        ->toContain('<figcaption><span>Rombongan SMA 1 di Kawah Ijen</span></figcaption>');
});

test('foto tanpa keterangan tidak menampilkan pita kosong', function () {
    Galeri::create(['foto' => '/storage/galeri/a.webp', 'urutan' => 1]);

    // Pita gelap tanpa tulisan cuma menutupi foto tanpa memberi apa pun.
    $isi = $this->get('/')->assertOk()->getContent();
    $galeri = substr($isi, strpos($isi, 'id="galeri"'));

    expect($galeri)
        // Tanpa keterangan, <figcaption>-nya tidak digambar sama sekali.
        ->not->toContain('<figcaption')
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

    foreach (File::allFiles(resource_path('views')) as $tampilan) {
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

test('kipas galeri dipangkas di kiri supaya tidak menindih judulnya', function () {
    /*
     | Kartu terjauh pada kipas galeri berdiri 1,28 kali lebarnya sendiri di
     | kiri kartu tengah. Pada SETIAP lebar layar besar itu lebih jauh ke kiri
     | daripada tepi panggungnya, jadi fotonya menyeberang ke lajur tulisan dan
     | menutupi judul "Momen perjalanan pelanggan kami." Terukur di halaman
     | hidup 27 Nov 2026: 118px pada 1024px, 124px pada 1280px, 128px pada
     | 1536px, dan 47px pada 1920px.
     |
     | z-index tidak bisa menolong: lajur tulisannya memakai .reveal, yang
     | memasang animation ... forwards pada translate dan karena itu mengunci
     | konteks penumpukan sendiri. Yang menahannya adalah clip-path.
     */
    $css = file_get_contents(resource_path('css/new-homepage.css'));

    preg_match('/\.galeri-panggung\.is-3d\[data-korsel="kipas"\] \{(.*?)\}/s', $css, $cocok);

    expect($cocok)->not->toBeEmpty('aturan pemangkas kipas galeri hilang');

    /*
     | Nilai KEEMPAT inset() — sisi kiri — wajib 0, dan hanya itu. Tiga sisi
     | lainnya sengaja -100% supaya deret foto tetap boleh menerobos tepi kanan
     | panel; itu memang rancangannya. Kalau sisi kiri suatu saat ikut dibuat
     | -100%, aturannya tetap ada dan pemeriksaan "ada clip-path" tetap hijau
     | sementara fotonya kembali menutupi judul — jadi yang diperiksa angkanya,
     | bukan keberadaan propertinya.
     */
    expect($cocok[1])->toMatch('/clip-path:\s*inset\([^)]*\s0\)\s*;/');

    // Hanya dari 1024px ke atas: di bawah itu tata letaknya satu lajur dan
    // tidak ada tulisan di samping foto yang perlu dilindungi.
    expect($css)->toContain('@media (min-width: 1024px)');
});

/*
 |--------------------------------------------------------------------------
 | Kartu destinasi berirama
 |--------------------------------------------------------------------------
 | Halaman /destinasi tadinya sembilan persegi yang sama persis. Yang
 | membuatnya datar bukan isinya, melainkan tidak adanya satu pun perbedaan
 | bentuk antarkartu. Iramanya kini datang dari tiga hal yang berganti pada
 | laju berbeda — ukuran, lengkung, dan warna aksen — dan ketiganya gampang
 | hilang tanpa menimbulkan galat: halamannya tetap 200, hanya kembali datar.
 */

test('kartu destinasi berganti bentuk dan warna pada laju berbeda', function () {
    $blade = file_get_contents(resource_path('views/livewire/public/destinasi/index.blade.php'));

    // TIGA rupa lengkung, bukan satu. Menyisakan satu saja tetap merender
    // halaman yang benar — hanya kisinya yang kembali seragam.
    expect(substr_count($blade, "'isi' =>"))->toBe(3)
        ->and(substr_count($blade, "'garis' =>"))->toBe(3);

    // Tiga dan dua sengaja TIDAK sama: kalau keduanya dibuat % 3, rupa dan
    // warna berganti bersamaan dan polanya berulang tiap tiga kartu — pas
    // satu baris kisi, sehingga tiap baris terbaca sebagai salinan baris
    // sebelumnya. Justru itu yang hendak dihindari.
    expect($blade)->toContain('$loop->index % 3')
        ->and($blade)->toContain('$loop->index % 2');

    // Kartu sorot: dua lajur, dan dua baris di layar lebar.
    expect($blade)->toContain('sm:col-span-2 xl:row-span-2');
});

test('foto kartu destinasi tidak pernah memakai tinggi aslinya', function () {
    /*
     | Kartu sorot tidak punya tinggi tetap — tingginya datang dari petak
     | kisi lewat xl:flex-1. Pada wadah yang tingginya auto, `height:100%`
     | luruh jadi auto juga, dan <img> memakai TINGGI ASLINYA: kartunya
     | memanjang sampai 800px, mendorong dua kartu di sebelahnya ikut
     | menjulang, dan halamannya tetap 200 tanpa satu pun galat.
     |
     | Obatnya gambar diposisikan absolut dengan keempat sisi DAN w/h penuh:
     | dengan begitu ia tidak ikut menghitung tinggi wadahnya sama sekali.
     */
    $blade = file_get_contents(resource_path('views/livewire/public/destinasi/index.blade.php'));

    expect($blade)->toContain('absolute inset-0 object-cover w-full h-full transition-transform')
        // Bentuk lamanya tidak boleh kembali diam-diam.
        ->and($blade)->not->toContain('class="object-cover w-full h-full transition-transform');
});

test('lengkung kartu destinasi punya tinggi sendiri', function () {
    /*
     | SVG ber-viewBox dengan preserveAspectRatio="none" tidak punya tinggi
     | bawaan yang berguna. Tanpa tinggi eksplisit peramban memberinya 150px
     | — lengkung putih setinggi itu menutupi lebih dari separuh foto kartu,
     | termasuk judul destinasinya.
     */
    $css = file_get_contents(resource_path('css/new-homepage.css'));

    preg_match('/\.lengkung-dest \{(.*?)\}/s', $css, $cocok);

    expect($cocok)->not->toBeEmpty('aturan tinggi lengkung kartu destinasi hilang')
        ->and($cocok[1])->toMatch('/height:\s*clamp\(/');
});

test('isi lengkung kartu destinasi melewati dasar viewBox', function () {
    /*
     | Satu baris piksel foto bocor melintang penuh di bawah lengkung, di
     | SELURUH kartu. Terbukti dari potret pengguna 27 Nov 2026: pada y=755
     | sebelas titik contoh selebar kartu semuanya berwarna foto, sementara
     | y=754 dan y=756 putih bersih — jadi bukan bayangan atau pembulatan
     | warna, melainkan tepat satu baris yang tidak tertutup.
     |
     | Sebabnya tepi bawah jalur isian berimpit dengan tepi elemen <svg>,
     | dan keduanya jatuh di posisi piksel PECAHAN karena tinggi wadah dan
     | tinggi lengkung sama-sama dari clamp(). Safari membulatkan isiannya ke
     | dalam; Chrome tidak. Karena itu cacat ini TIDAK bisa dijaga dengan
     | potret Chrome — yang dijaga sumbernya.
     |
     | Dua hal yang menahannya, dan keduanya harus ada:
     |   1. jalur isian ditarik sampai y=64, jauh melewati dasar viewBox (48)
     |   2. elemennya digeser bottom: -1px
     */
    $blade = file_get_contents(resource_path('views/livewire/public/destinasi/index.blade.php'));

    preg_match_all("/'isi' => '([^']+)'/", $blade, $jalur);

    expect($jalur[1])->toHaveCount(3);

    foreach ($jalur[1] as $d) {
        expect($d)->toStartWith('M0,64 ')
            ->and($d)->toEndWith('L400,64 Z');
    }

    $css = file_get_contents(resource_path('css/new-homepage.css'));
    preg_match('/\.lengkung-dest \{(.*?)\}/s', $css, $cocok);

    expect($cocok[1])->toMatch('/bottom:\s*-1px\s*;/');
});

test('jendela detail destinasi menutupi kepala halaman dan tombol mengambang', function () {
    /*
     | Tata lapis halaman publik sudah dipakai jauh di atas 50: tombol
     | WhatsApp mengambang dan kepala halaman duduk di ratusan. Jendela
     | detail yang memakai z-50 tetap menggelapkan halaman — tirainya
     | bekerja — TETAPI kepala halaman dan tombol mengambang tetap tajam di
     | atasnya dan masih bisa diklik. Tidak ada galat, halaman tetap 200,
     | dan yang terlihat cuma "kok navigasinya tidak ikut redup".
     */
    $blade = file_get_contents(resource_path('views/livewire/public/destinasi/index.blade.php'));

    expect(preg_match('/class="fixed inset-0 z-\[(\d+)\][^"]*"\s*\n\s*role="dialog"/', $blade, $cocok))
        ->toBe(1, 'wadah jendela detail tidak lagi memakai z-[angka]');

    $jendela = (int) $cocok[1];

    // Dibandingkan dengan angka yang BENAR-BENAR ada di tata letaknya, bukan
    // angka yang ditulis ulang di sini: kalau suatu saat kepala halaman
    // dinaikkan, uji ini ikut naik sendiri dan tetap berarti.
    preg_match_all('/z-\[(\d+)\]/', file_get_contents(resource_path('views/components/layouts/guest.blade.php')), $tata);

    expect($tata[1])->not->toBeEmpty()
        ->and($jendela)->toBeGreaterThan(max(array_map('intval', $tata[1])));

    // Tetapi tetap DI BAWAH layar muat: saat halaman sedang dimuat ulang,
    // layar muat memang harus menutup segalanya, termasuk jendela ini.
    preg_match('/z-\[(\d+)\]/', file_get_contents(resource_path('views/components/loading-spinner.blade.php')), $muat);

    expect($jendela)->toBeLessThan((int) $muat[1]);
});

test('halaman detail paket memakai panel seperti halaman lain, bukan kotak sendiri', function () {
    /*
     | Halaman ini satu-satunya halaman publik yang masih berdiri di luar
     | bahasa rupa yang lain: .card-orcha di atas latar putih, dengan jarak
     | section-orcha yang pada layar lebar menyisakan ~130px bidang kosong
     | antara kepala halaman dan isi pertama. Tidak ada galat apa pun — hanya
     | halaman yang terbaca belum selesai dirapikan, justru di halaman yang
     | dibaca orang saat sedang memutuskan membayar.
     */
    $blade = file_get_contents(resource_path('views/livewire/public/paket-wisata/detail.blade.php'));

    // Panel pertama naik menindih kepala halaman, seperti beranda dan
    // destinasi. Tanpa rak-panel, celah itu kembali.
    expect($blade)->toContain('container-orcha rak-panel')
        ->and($blade)->toContain('panel-orcha');

    // Kotak lama tidak boleh kembali diam-diam. Disaring dari komentar dulu:
    // keterangan yang menjelaskan larangannya sendiri jangan sampai memerahkan
    // ujinya.
    $tanpaKomentar = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

    expect($tanpaKomentar)->not->toContain('card-orcha')
        ->and($tanpaKomentar)->not->toContain('bg-white section-orcha');
});

test('termasuk dan tidak termasuk satu panel, tetapi dua daftar yang tidak bercampur', function () {
    /*
     | Keduanya menjawab satu pertanyaan — "apa yang saya dapat dengan uang
     | ini?" — jadi dijawab di satu panel, bukan dua panel berurutan yang
     | memaksa pembaca mengingat isi panel pertama.
     |
     | TETAPI daftarnya tidak boleh pernah bercampur. Kalau keduanya disatukan
     | jadi satu daftar, "Tiket masuk wisata" terbaca sebagai yang DIDAPAT —
     | kebalikan dari maksudnya, dan itulah yang paling sering berakhir jadi
     | keluhan di lokasi. Karena itu yang diperiksa bukan cuma keduanya
     | tampil, melainkan masing-masing punya kepalanya sendiri.
     */
    $paket = \App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Uji Fasilitas',
        'category' => 'open-trip',
        // 'terbit', bukan 'published': sedang_tayang memeriksa kata itu
        // persis, dan nilai yang salah memulangkan 404 — bukan halaman yang
        // salah rupa, melainkan halaman yang tidak ada.
        'status' => 'terbit',
        'duration' => '2 Hari 1 Malam',
        'price' => 900_000,
        'fasilitas' => ['Transport AC', 'Makan 3x'],
        'tidak_termasuk' => ['Tiket masuk wisata', 'Pengeluaran pribadi'],
    ]);

    $this->get(route('paket-detail', $paket->uuid))
        ->assertOk()
        ->assertSee('Sudah termasuk', false)
        ->assertSee('Disiapkan sendiri', false)
        ->assertSee('Transport AC', false)
        ->assertSee('Tiket masuk wisata', false)
        // Pemisah dua lajurnya ada: tanpa itu keduanya menempel jadi satu
        // kolom panjang di layar lebar.
        ->assertSee('sm:border-l', false);
});

test('kepala harga tampil utuh dengan maupun tanpa diskon', function (bool $pakaiDiskon) {
    /*
     | Dua jalur yang berbeda bentuknya: dengan diskon ada harga coret, segel
     | persen, dan pil penghematan; tanpa diskon ketiganya hilang dan yang
     | tersisa cuma angka beserta satuannya. Jalur kedua paling jarang dilihat
     | saat mengerjakan rupa — semua paket di komputer ini kebetulan berdiskon
     | — jadi justru itu yang paling mudah rusak tanpa ketahuan.
     */
    $paket = \App\Models\PaketWisata\TravelPackage::create(array_merge([
        'name' => 'Open Trip Uji Harga',
        'category' => 'open-trip',
        'status' => 'terbit',
        'duration' => '2 Hari 1 Malam',
        'price' => 985_000,
    ], $pakaiDiskon ? ['original_price' => 1_200_000] : []));

    $hal = $this->get(route('paket-detail', $paket->uuid))->assertOk();

    // Angka dan satuannya selalu ada, apa pun jalurnya.
    $hal->assertSee('Rp 985.000', false)
        ->assertSee('per orang', false)
        ->assertSee('angka-harga', false);

    if ($pakaiDiskon) {
        $hal->assertSee('Rp 1.200.000', false)
            ->assertSee('segel-hemat', false)
            ->assertSee('pita-hemat', false);
    } else {
        // Tanpa diskon ketiganya TIDAK boleh tergambar. Segel "Hemat 0%" atau
        // pil penghematan kosong lebih buruk daripada tidak ada apa-apa.
        $hal->assertDontSee('segel-hemat', false)
            ->assertDontSee('pita-hemat', false)
            ->assertDontSee('harga-coret', false);
    }
})->with([true, false]);

test('pil penghematan tidak memakai flex yang membuang spasinya', function () {
    /*
     | .pita-hemat berisi kalimat biasa dengan <strong> di tengahnya. Kalau
     | pembungkusnya inline-flex, tiap potongan tulisan jadi item flex sendiri
     | dan SPASI DI ANTARANYA DIBUANG — kalimatnya tercetak
     | "Lebih hematRp 215.000per orang". Tidak ada galat, hurufnya semua ada,
     | hanya spasinya lenyap; dan di layar sempit kalimatnya masih terbaca
     | separuh benar sehingga gampang terlewat.
     */
    // Komentar CSS DIBUANG dulu. Keterangan di dalam aturan itu menjelaskan
    // larangannya sendiri dan menyebut kata "inline-flex" — tanpa disaring,
    // justru keterangan itu yang memerahkan ujinya.
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.pita-hemat \{(.*?)\n\}/s', $css, $cocok);

    expect($cocok)->not->toBeEmpty('aturan .pita-hemat hilang')
        ->and($cocok[1])->not->toContain('inline-flex')
        ->and($cocok[1])->not->toMatch('/display:\s*flex/');
});

test('tombol Daftar Sekarang memakai tombol ajakan utama yang sama dengan beranda', function () {
    /*
     | Daftar Sekarang adalah SATU-SATUNYA tindakan utama di halaman detail
     | paket; tombol di bawahnya hanya bertanya lewat WhatsApp. Keduanya
     | tampil dengan bobot yang sama membuat pengunjung yang sudah siap
     | mendaftar tetap memilih bertanya dulu — jalan yang jauh lebih panjang
     | bagi keduanya, dan tidak ada apa pun yang memberi tahu bahwa itu
     | terjadi.
     |
     | .btn-orcha-primary dan .btn-orcha-sun sama-sama emas, jadi tertukar
     | tidak akan terlihat sekilas. Yang membedakan: .btn-orcha-sun punya
     | badan padat di bawahnya, kilau di tepi atas, dan gerak turun saat
     | ditekan — itulah tombol yang dipakai beranda untuk ajakan utamanya.
     */
    $paket = \App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Uji Tombol',
        // Garis bawah, bukan tanda hubung: nilai inilah yang tersimpan di
        // basis data, dan halamannya memeriksa kata ini persis. Dengan
        // 'open-trip' tombol Daftar Sekarang tidak pernah tergambar sama
        // sekali — halamannya tetap 200 dan tidak ada yang merah.
        'category' => 'open_trip',
        'status' => 'terbit',
        'duration' => '2 Hari 1 Malam',
        'price' => 750_000,
        'kuota' => 20,
    ]);

    $html = $this->get(route('paket-detail', $paket->uuid))->assertOk()->getContent();

    expect(preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*Daftar Sekarang(?:(?!<\/a>).)*<\/a>/s', $html, $cocok))
        ->toBe(1, 'tombol Daftar Sekarang tidak tergambar');

    expect($cocok[0])->toContain('btn-orcha-sun')
        ->and($cocok[0])->not->toContain('btn-orcha-primary');

    // Dibandingkan dengan beranda, bukan dengan nama kelas yang dihafal di
    // sini: kalau suatu saat beranda berganti tombol ajakan, uji ini ikut
    // menuntut halaman paket berganti bersamanya.
    expect(file_get_contents(resource_path('views/livewire/public/beranda/index.blade.php')))
        ->toContain('btn-orcha btn-orcha-sun');
});

test('tampilan publik tidak memakai gradasi peninggalan palet biru', function () {
    /*
     | from-orcha-sky ke to-orcha-ocean ditulis saat palet masih BIRU: sky
     | biru langit, ocean biru laut. Palet beranda kemudian menimpa kedua
     | peubah itu dengan warna hangat — sky jadi emas pucat (#eab766), ocean
     | jadi hijau keabuan (#4a5a52) — sehingga gradasinya kini membentang
     | dari emas ke abu dan melewati cokelat kusam di tengah.
     |
     | Yang berbahaya dari cacat ini: kelasnya MASIH ADA dan MASIH
     | menghasilkan warna. Tidak ada galat, tidak ada bidang kosong, tidak
     | ada yang merah — hanya warna lumpur yang baru ketahuan kalau ada yang
     | memandangi layarnya. Karena itu ia kambuh di lima tempat berbeda
     | sebelum dicari sekaligus.
     |
     | Panel admin SENGAJA tidak ikut diperiksa: palet hangat itu milik
     | halaman publik, dan layar admin tidak memuat new-homepage.css yang
     | menimpa peubahnya.
     */
    $pelanggar = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()
        ->in([
            resource_path('views/livewire/public'),
            resource_path('views/components'),
            resource_path('views/partials'),
        ])
        ->name('*.blade.php') as $berkas) {
        // Komentar Blade dibuang dulu: keterangan yang menjelaskan larangan
        // ini menyebut nama kelasnya sendiri.
        $isi = preg_replace('/\{\{--.*?--\}\}/s', '', $berkas->getContents());

        if (preg_match('/orcha-sky\s+to-orcha-ocean|orcha-ocean\s+to-orcha-sky/', $isi)) {
            $pelanggar[] = str_replace(resource_path('views/'), '', $berkas->getPathname());
        }
    }

    expect($pelanggar)->toBe([], 'Pakai .ubin-toska atau .bilah-nilai: '.implode(', ', $pelanggar));
});

test('formulir pendaftaran dipecah jadi tiga langkah dalam satu form', function () {
    /*
     | Yang mengisi halaman ini sering orang yang tidak terbiasa dengan
     | formulir panjang — guru pendamping, orang tua murid, ketua rombongan.
     | Satu kartu panjang berisi tiga judul kecil tidak pernah memberi tahu
     | sudah sampai mana dan masih berapa lagi; yang terbaca cuma "masih
     | panjang".
     |
     | TETAPI tetap SATU <form> dengan SATU tombol kirim. Memecahnya jadi
     | beberapa halaman yang ditekan satu per satu menambah pekerjaan, dan
     | membuat isian yang sudah diketik bisa hilang di tengah jalan — dan
     | kalau <form>-nya sampai terpecah, sebagian isian tidak akan terkirim
     | sama sekali tanpa ada yang merah.
     */
    $blade = file_get_contents(resource_path('views/livewire/public/open-trip/pendaftaran.blade.php'));

    expect(substr_count($blade, 'class="langkah-nomor"'))->toBe(3)
        ->and($blade)->toContain('Langkah 1')
        ->and($blade)->toContain('Langkah 2')
        ->and($blade)->toContain('Langkah 3')
        // Satu form saja.
        ->and(substr_count($blade, '<form wire:submit="daftar"'))->toBe(1)
        ->and(substr_count($blade, '</form>'))->toBe(1)
        ->and(substr_count($blade, 'type="submit"'))->toBe(1);
});

test('isian formulir publik tidak lebih kecil dari 16px', function () {
    /*
     | Dua alasan, keduanya terukur.
     |
     | Keterbacaan: yang mengisi formulir Orcha sering bukan orang yang
     | terbiasa dengan layar, dan tulisan yang sudah diketik justru yang
     | paling perlu bisa diperiksa ulang sebelum dikirim.
     |
     | Dan Safari iOS MEMPERBESAR halaman sendiri saat isian ber-font di
     | bawah 16px disentuh, lalu tidak mengembalikannya — isian berikutnya
     | jadi di luar layar dan pengisinya harus menggeser halaman tiap
     | berpindah kolom. Tidak ada galat; formulirnya cuma terasa melompat.
     */
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.isian-orcha \{(.*?)\n\}/s', $css, $cocok);

    expect($cocok)->not->toBeEmpty('aturan .isian-orcha hilang');

    preg_match('/font-size:\s*([\d.]+)rem/', $cocok[1], $ukuran);

    expect($ukuran)->not->toBeEmpty('.isian-orcha tidak lagi menyebut font-size dalam rem')
        ->and((float) $ukuran[1])->toBeGreaterThanOrEqual(1.0);
});

test('peringatan pembayaran berlatar padat, tidak bisa ditembus apa pun di belakangnya', function () {
    /*
     | Di halaman pendaftaran kotak ini naik menindih foto kepala halaman.
     | Dengan latar bg-orcha-sun/10 fotonya menembus tulisan dan peringatan
     | penipuan itu tidak terbaca sama sekali — kotaknya tetap ada, hanya
     | isinya yang hilang ke dalam gambar, tanpa satu pun galat.
     |
     | Peringatan yang bisa hilang bukan peringatan.
     */
    $komponen = file_get_contents(resource_path('views/components/peringatan-pembayaran.blade.php'));

    expect($komponen)->toContain('peringatan-bayar');

    $tanpaKomentar = preg_replace('/\{\{--.*?--\}\}/s', '', $komponen);

    expect($tanpaKomentar)->not->toContain('bg-orcha-sun/10');

    // Latarnya benar-benar padat, bukan warna ber-alpha.
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));
    preg_match('/\.peringatan-bayar \{(.*?)\n\}/s', $css, $cocok);

    expect($cocok)->not->toBeEmpty('aturan .peringatan-bayar hilang')
        ->and($cocok[1])->toMatch('/background:\s*#[0-9a-fA-F]{6}\s*;/');
});

test('sesudah mendaftar, pembayaran jadi tindakan utama dan riwayat kesehatan menyusul', function () {
    /*
     | Urutan lama: isi riwayat kesehatan dulu, pembayaran menyusul setelah
     | tim mengonfirmasi kursi. Yang paling sering terjadi karenanya:
     | pendaftar mengisi riwayat kesehatan untuk SELURUH rombongan — pekerjaan
     | terberat di alur ini — lalu berhenti, dan kursinya tidak pernah
     | terkunci. Padahal aturan di halaman yang sama sudah berbunyi "kursi
     | dikunci setelah DP masuk".
     |
     | Sekarang pembayaran yang jadi tindakan utama, dan riwayat kesehatan
     | diminta sesudah pembayarannya berhasil — halaman pembayaran-selesai
     | memang sudah menawarkannya di sana.
     */
    $html = \Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('kodeTerdaftar', 'OT-2711-UJICOBA')
        ->html();

    // Tombol utamanya pembayaran, memakai tombol ajakan utama beranda.
    expect(preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*Bayar Uang Muka Sekarang(?:(?!<\/a>).)*<\/a>/s', $html, $cocok))
        ->toBe(1, 'tombol bayar uang muka tidak tergambar');

    expect($cocok[0])->toContain('btn-orcha-sun')
        ->and($cocok[0])->toContain('konfirmasi-pembayaran');

    // Riwayat kesehatan TETAP bisa dibuka — yang membayar lewat transfer
    // manual tidak pernah melewati halaman "pembayaran berhasil" yang
    // menawarkannya — tetapi tidak lagi sebagai tombol.
    expect($html)->toContain('riwayat-kesehatan');

    expect(preg_match('/<a\b[^>]*btn-orcha[^>]*>(?:(?!<\/a>).)*Isi Riwayat Kesehatan(?:(?!<\/a>).)*<\/a>/s', $html))
        ->toBe(0, 'riwayat kesehatan kembali jadi tombol di layar sesudah mendaftar');

    // Langkah pertama menyebut uang muka, bukan riwayat kesehatan.
    preg_match('/Langkah berikutnya.*?<li>(.*?)<\/li>/s', $html, $langkah);

    expect($langkah)->not->toBeEmpty()
        ->and($langkah[1])->toContain('uang muka')
        ->and($langkah[1])->not->toContain('riwayat kesehatan');
});

test('kartu tercatat memakai bentuk abstrak yang sama dengan kartu artikel', function () {
    /*
     | Ini satu-satunya layar yang muncul sesudah kerja mengisi formulir
     | selesai. Sebagai kotak putih biasa ia terbaca sebagai halaman lain yang
     | kebetulan terbuka, bukan sebagai hasil.
     |
     | Yang diperiksa sudut daunnya SAMA dengan .kartu-artikel, bukan sekadar
     | "ada border-radius": dua bentuk membulat yang berbeda-beda di satu
     | situs justru membuatnya terbaca seperti beberapa rancangan yang
     | ditempel berdampingan — persis yang hendak dihindari.
     */
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.kartu-artikel \{(.*?)\n\}/s', $css, $artikel);
    preg_match('/\.kartu-tercatat \{(.*?)\n\}/s', $tercatat = $css, $kartu);

    expect($artikel)->not->toBeEmpty()
        ->and($kartu)->not->toBeEmpty('aturan .kartu-tercatat hilang');

    preg_match('/border-radius:\s*([^;]+);/', $artikel[1], $radiusArtikel);
    preg_match('/border-radius:\s*([^;]+);/', $kartu[1], $radiusKartu);

    expect(trim($radiusKartu[1]))->toBe(trim($radiusArtikel[1]));

    // Ombak di kaki pita kepalanya, bukan garis lurus.
    $blade = file_get_contents(resource_path('views/livewire/public/open-trip/pendaftaran.blade.php'));

    expect($blade)->toContain('ombak-tercatat')
        ->and($blade)->toContain('karcis-kode');
});

test('ringkasan biaya menonjolkan angka yang harus dibayar hari ini dan menyebut sisanya', function () {
    /*
     | Yang lama satu kalimat abu-abu setinggi 14px:
     |
     |     Total Rp 1.350.000 · DP 30% = Rp 405.000
     |
     | Angka yang harus ditransfer hari ini berukuran persis sama dengan kata
     | sambung di sebelahnya, dan SISA PELUNASANNYA tidak disebut sama sekali
     | — pengisinya mengurangi sendiri. Dua-duanya angka yang ditanyakan ulang
     | lewat WhatsApp, dan keduanya sudah dihitung oleh RincianBiaya; cuma
     | tidak ditampilkan.
     |
     | Yang dijaga di sini dua hal yang tidak akan memerahkan apa pun kalau
     | hilang: angka DP berdiri sendiri (bukan di tengah kalimat), dan sisa
     | pelunasan ikut tertulis.
     */
    $paket = \App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Uji Ringkasan Biaya',
        'category' => 'open_trip',
        'status' => 'terbit',
        'duration' => '2 Hari 1 Malam',
        'price' => 1_000_000,
        'kuota' => 20,
    ]);

    $persen = (int) config('orcha.pembayaran.dp_persen');
    $dp = 'Rp '.number_format(1_000_000 * $persen / 100, 0, ',', '.');
    $sisa = 'Rp '.number_format(1_000_000 - 1_000_000 * $persen / 100, 0, ',', '.');

    $html = $this->get(route('pendaftaran-open-trip', ['paket' => $paket->uuid]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kartu-biaya');

    // Angka DP punya wadahnya SENDIRI. Kalau ia kembali masuk ke tengah
    // kalimat, pencocokan ini gagal walaupun angkanya masih tertulis.
    expect(preg_match('/class="angka-dp"[^>]*>\s*'.preg_quote($dp, '/').'/', $html))
        ->toBe(1, "angka DP {$dp} tidak berdiri sendiri di .angka-dp");

    // Sisa pelunasan beserta tenggatnya.
    expect($html)->toContain($sisa)
        ->and($html)->toContain('Sisa pelunasan')
        ->and($html)->toContain('Total biaya');
});

test('angka DP jauh lebih besar daripada angka total dan sisa di bawahnya', function () {
    /*
     | Tiga angka rupiah dalam satu kartu. Kalau ukurannya berdekatan,
     | pembacanya harus MEMBACA ketiganya untuk tahu mana yang ditagih hari
     | ini — persis keadaan yang hendak ditinggalkan. Satu-satunya yang perlu
     | ditindaklanjuti sekarang adalah DP; dua lainnya keterangan.
     */
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.angka-dp \{(.*?)\n\}/s', $css, $dp);
    preg_match('/\.baris-biaya dd \{(.*?)\n\}/s', $css, $baris);

    expect($dp)->not->toBeEmpty('aturan .angka-dp hilang')
        ->and($baris)->not->toBeEmpty('aturan .baris-biaya dd hilang');

    // Dari clamp() diambil nilai TERKECILNYA — ukuran di layar paling sempit,
    // tempat selisihnya paling mudah menyusut.
    preg_match('/font-size:\s*clamp\(\s*([\d.]+)rem/', $dp[1], $ukuranDp);
    preg_match('/font-size:\s*([\d.]+)rem/', $baris[1], $ukuranBaris);

    expect($ukuranDp)->not->toBeEmpty('.angka-dp tidak lagi memakai clamp() dalam rem')
        ->and($ukuranBaris)->not->toBeEmpty()
        ->and((float) $ukuranDp[1])->toBeGreaterThanOrEqual((float) $ukuranBaris[1] * 1.6);
});

test('takik kuitansi sewarna panel di belakang kartunya, bukan sewarna kartunya', function () {
    /*
     | Takik bundar di kiri-kanan garis sobek itu LUBANG: ia terbaca sebagai
     | kertas yang disobek hanya kalau warnanya sama dengan apa pun yang ada
     | di belakang kartu — panel .panel-orcha. Diberi warna kartunya sendiri,
     | yang tergambar cuma bundaran samar di tepi, dan tidak ada galat apa pun
     | yang memberi tahu bahwa maksudnya sudah hilang.
     |
     | Kartunya juga harus overflow: hidden; tanpa itu takiknya menonjol utuh
     | ke luar kartu sebagai dua bulatan mengambang.
     */
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.panel-orcha \{(.*?)\n\}/s', $css, $panel);
    preg_match('/\.takik-biaya \{(.*?)\n\}/s', $css, $takik);
    preg_match('/\.kartu-biaya \{(.*?)\n\}/s', $css, $kartu);

    expect($panel)->not->toBeEmpty()
        ->and($takik)->not->toBeEmpty('aturan .takik-biaya hilang')
        ->and($kartu)->not->toBeEmpty('aturan .kartu-biaya hilang');

    preg_match('/background:\s*(#[0-9a-fA-F]{3,8})\s*;/', $panel[1], $warnaPanel);
    preg_match('/background:\s*(#[0-9a-fA-F]{3,8})\s*;/', $takik[1], $warnaTakik);

    expect($warnaTakik)->not->toBeEmpty('.takik-biaya tidak lagi berlatar warna padat')
        ->and(strtolower($warnaTakik[1]))->toBe(strtolower($warnaPanel[1]))
        ->and($kartu[1])->toContain('overflow: hidden');
});

test('kartu "sudah ditetapkan" memberi tiap keterangan ikonnya sendiri, tanpa warna palet lama', function () {
    /*
     | Empat pasang label-nilai yang seragam di dalam kotak krem rata, dengan
     | DUA warna yang sudah berganti arti diam-diam:
     |
     | - text-orcha-ocean pada judul dan pada tautan itinerary. Pada palet
     |   lama biru; palet hangat beranda menimpanya jadi #4a5a52, dan
     |   tautannya tidak terbaca sebagai tautan sama sekali.
     | - text-slate-500 pada keempat labelnya — abu KEBIRUAN, satu-satunya
     |   nada dingin di kartu yang seluruhnya hangat.
     |
     | Tidak ada galat untuk keduanya; halamannya tetap 200 dan tidak ada
     | bidang yang kosong. Lihat uji "gradasi peninggalan palet biru" untuk
     | jebakan yang sama pada gradasi.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/pendaftaran.blade.php'))
    );

    $awal = strpos($blade, 'class="kartu-ditetapkan"');
    $akhir = strpos($blade, 'Lihat itinerary lengkap', $awal ?: 0);

    expect($awal)->not->toBeFalse('kartu "sudah ditetapkan" hilang')
        ->and($akhir)->not->toBeFalse();

    $potongan = substr($blade, $awal, $akhir - $awal);

    // Empat keterangan, empat ubin ikon, empat label hangat.
    expect(substr_count($potongan, 'label-fakta'))->toBe(4)
        ->and(substr_count($potongan, 'nilai-fakta'))->toBe(4)
        ->and(substr_count($potongan, 'ubin-toska'))->toBe(4);

    // Warna yang artinya bisa berganti tidak dipakai lagi di dalam kartu ini.
    expect($potongan)->not->toContain('text-orcha-ocean')
        ->and($potongan)->not->toContain('text-slate-');

    // Dan kartunya benar-benar tergambar, bukan cuma ada di berkasnya.
    $paket = \App\Models\PaketWisata\TravelPackage::create([
        'name' => 'Open Trip Uji Kartu Ditetapkan',
        'category' => 'open_trip',
        'status' => 'terbit',
        'duration' => '2 Hari 1 Malam',
        'price' => 800_000,
        'kuota' => 20,
        'titik_jemput' => 'Jogja, Magelang',
        'minimal_peserta' => 6,
    ]);

    $html = $this->get(route('pendaftaran-open-trip', ['paket' => $paket->uuid]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kartu-ditetapkan')
        ->and($html)->toContain('tautan-lanjut')
        ->and(substr_count($html, 'label-fakta'))->toBe(4);
});

test('tautan lanjutan tidak digarisbawahi, ditandai warna dan panah yang maju', function () {
    /*
     | Tautan yang BERDIRI SENDIRI — "Lihat Semua" di kepala bagian testimoni,
     | "Lihat itinerary lengkap" di kartu pendaftaran — tidak digarisbawahi,
     | juga saat disentuh. Penandanya warna toska, huruf tebal, dan panah yang
     | maju sedikit.
     |
     | Yang DI TENGAH KALIMAT sengaja dibiarkan tetap bergaris bawah saat
     | disentuh: di sana garisnya satu-satunya penanda bahwa potongan kalimat
     | itu berbeda dari kalimat di sekitarnya. Jadi uji ini TIDAK menyapu
     | hover:underline dari seluruh tampilan publik.
     */
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.tautan-lanjut \{(.*?)\n\}/s', $css, $aturan);

    expect($aturan)->not->toBeEmpty('aturan .tautan-lanjut hilang')
        ->and($aturan[1])->toContain('text-decoration: none');

    // Jaraknya TIDAK dipatok di kelasnya: aturan ini di luar @layer, jadi
    // margin-top di sini mengalahkan mt-* mana pun di markah tanpa satu pun
    // galat, dan jaraknya jadi tidak bisa diatur per tempat.
    expect($aturan[1])->not->toContain('margin-top');

    /*
     | Tulisannya dicari LENGKAP DENGAN <span>-nya. Dicari sebagai tulisan
     | telanjang, "Lihat Semua" juga cocok dengan tombol "Lihat Semua
     | Destinasi" yang muncul lebih dulu di beranda — tombol yang memang
     | bukan tautan lanjutan — dan ujinya merah karena memeriksa elemen yang
     | salah.
     */
    foreach ([
        'views/livewire/public/beranda/index.blade.php' => '<span>Lihat Semua</span>',
        'views/livewire/public/open-trip/pendaftaran.blade.php' => '<span>Lihat itinerary lengkap</span>',
    ] as $berkas => $tulisan) {
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path($berkas)));

        // Potongan anchor yang memuat tulisannya.
        preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*'.preg_quote($tulisan, '/').'(?:(?!<\/a>).)*<\/a>/s', $blade, $tautan);

        expect($tautan)->not->toBeEmpty("tautan \"{$tulisan}\" tidak ketemu di {$berkas}")
            ->and($tautan[0])->toContain('tautan-lanjut')
            ->and($tautan[0])->not->toContain('hover:underline')
            // Panahnya elemen sendiri, bukan &rarr; di dalam tulisannya:
            // hanya begitu ia bisa bergerak saat disentuh.
            ->and($tautan[0])->toContain('arrow-right')
            ->and($tautan[0])->not->toContain('&rarr;');
    }
});

test('kaki kartu destinasi sorot memakai seluruh lebarnya, bukan dipatok 24rem', function () {
    /*
     | Tombol "Lihat Detail" memang tidak boleh meregang selebar kartu sorot —
     | selebar itu ia tidak terbaca sebagai tombol lagi, melainkan sebagai
     | pita. Jalan keluar yang lama mematok SELURUH kelompok tindakan di
     | sm:max-w-sm (24rem), dan itu memindahkan masalahnya, bukan
     | menyelesaikannya: terukur di layar 1440px, kartunya 791px sedangkan
     | kelompok tindakannya 384px — 405px di kanan tombol WhatsApp kosong
     | melompong.
     |
     | Yang dipatok sekarang cukup tombolnya (sm:flex-none); sisa lebarnya
     | dipakai galeri kecil beserta hitungan fotonya, yang naik jadi sebaris.
     |
     | Kartu biasa yang sempit TIDAK ikut berubah: di sana keduanya memang
     | harus bertumpuk.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/destinasi/index.blade.php'))
    );

    // Patokan lama benar-benar hilang, bukan sekadar ditimpa.
    expect($blade)->not->toContain('sm:max-w-sm');

    // Kelompok tindakannya jadi baris terbalik di kartu sorot: tombol di
    // kiri, galeri kecil di kanan.
    preg_match('/<div\s+class="relative mt-auto \{\{ \$loop->first \? \x27([^\x27]*)\x27/', $blade, $kelompok);

    expect($kelompok)->not->toBeEmpty('kelompok tindakan kartu destinasi berubah bentuk')
        ->and($kelompok[1])->toContain('sm:flex-row-reverse')
        ->and($kelompok[1])->toContain('sm:justify-between');

    /*
     | Dan tombolnya berhenti meregang justru di kartu sorot.
     |
     | Dicari dari wire:click sampai tulisannya, BUKAN dengan <button[^>]*>:
     | atribut kelasnya memuat {{ $loop->first ? ... }}, dan tanda ">" di
     | dalam "$loop->first" menghentikan [^>]* di tengah tag. Pencocokannya
     | pulang kosong walaupun tombolnya ada — persis jebakan ">" sesudah
     | direktif yang sudah tercatat di proyek ini.
     */
    preg_match('/wire:click="buka\(.*?Lihat Detail/s', $blade, $tombol);

    expect($tombol)->not->toBeEmpty()
        ->and($tombol[0])->toContain('flex-1')
        ->and($tombol[0])->toContain('sm:flex-none');

    $this->get(route('destinasi'))->assertOk();
});

test('centang "tercatat" digoreskan, dan tetap utuh bagi yang mematikan gerak', function () {
    /*
     | Layar ini muncul sekali, tepat setelah pekerjaan mengisi formulir
     | selesai, dan tidak ada tanda lain bahwa kiriman tadi berhasil.
     |
     | Dua hal yang kalau rusak TIDAK memerahkan apa pun dan tidak kelihatan
     | sampai ada yang memandanginya:
     |
     | 1. Ikonnya harus yang BERGARIS (o-check). Centang padat (s-check)
     |    digambar dengan fill, dan stroke-dasharray tidak berpengaruh apa pun
     |    padanya — goresannya tidak jalan dan centangnya muncul utuh sejak
     |    awal, persis seperti sebelum ada animasinya.
     | 2. Yang mematikan gerak harus melihat centang UTUH. Tanpa
     |    stroke-dashoffset: 0 di blok prefers-reduced-motion, yang tersisa
     |    bagi mereka adalah centang yang tidak pernah tergambar sama sekali —
     |    cakram kosong di layar yang seharusnya mengabarkan keberhasilan.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/pendaftaran.blade.php'))
    );

    preg_match('/<span class="centang-tercatat">(.*?)<\/span>/s', $blade, $centang);

    expect($centang)->not->toBeEmpty('centang tercatat hilang')
        ->and($centang[1])->toContain('heroicon-o-check')
        ->and($centang[1])->not->toContain('heroicon-s-check');

    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    preg_match('/\.centang-tercatat svg \{(.*?)\n\}/s', $css, $goresan);

    expect($goresan)->not->toBeEmpty('aturan .centang-tercatat svg hilang')
        ->and($goresan[1])->toContain('stroke-dasharray')
        ->and($goresan[1])->toContain('animation');

    // Riaknya berhenti. Lingkaran yang berdenyut selamanya menarik mata
    // kembali terus-menerus, padahal yang harus dibaca di bawahnya langkah
    // pembayaran.
    preg_match('/\.centang-tercatat::before,\s*\n\.centang-tercatat::after \{(.*?)\n\}/s', $css, $riak);

    expect($riak)->not->toBeEmpty('aturan riak centang hilang')
        ->and($riak[1])->not->toContain('infinite');

    // Dan blok "kurangi gerak" memulangkan centang yang UTUH.
    preg_match('/@media \(prefers-reduced-motion: reduce\) \{(?:(?!\n\}).)*centang-tercatat(?:(?!\n\}).)*\n\}/s', $css, $diam);

    expect($diam)->not->toBeEmpty('blok kurangi-gerak untuk centang hilang')
        ->and($diam[0])->toContain('stroke-dashoffset: 0');
});

test('peringatan penipuan menyebut nama penerima yang benar untuk tiap jalur', function (bool $gerbangNyala) {
    /*
     | ADA DUA nama penerima yang sah, dan keduanya berbeda:
     |
     | - Lewat halaman pembayaran (DOKU), yang dilihat pembayar di nama virtual
     |   account dan di mutasi banknya adalah nama MERCHANT yang terdaftar di
     |   DOKU — mereknya, "Orcha Journey".
     | - Lewat transfer ke rekening kami, yang tertulis badan hukumnya,
     |   "PT ASTHANA CIPTA MANDIRI".
     |
     | Peringatan penipuan yang hanya menyebut badan hukumnya MENUDUH
     | pembayaran yang sah: pelanggan yang membayar lewat DOKU melihat nama
     | yang menurut situs ini bukan kami. Peringatan yang bertentangan dengan
     | kenyataan melatih orang mengabaikan peringatan berikutnya, termasuk yang
     | benar — dan itu persis kerugian yang hendak dicegah peringatan ini.
     |
     | Tidak ada galat untuk kekeliruan ini. Halamannya tetap 200, kalimatnya
     | tetap utuh, hanya isinya yang salah.
     */
    config()->set('doku.aktif', $gerbangNyala);
    config()->set('doku.client_id', $gerbangNyala ? 'UJI-CLIENT' : null);
    config()->set('doku.secret_key', $gerbangNyala ? 'UJI-SECRET' : null);

    $badanHukum = config('orcha.pembayaran.atas_nama');
    $merek = config('orcha.pembayaran.atas_nama_gerbang');

    expect($merek)->not->toBeEmpty('nama penerima jalur gerbang belum disetel')
        ->and($merek)->not->toBe($badanHukum);

    $html = $this->get(route('ketentuan-pembayaran'))->assertOk()->getContent();

    // Badan hukumnya SELALU disebut: jalur transfer tidak pernah hilang.
    expect($html)->toContain($badanHukum);

    if ($gerbangNyala) {
        expect($html)->toContain($merek);
    }

    // Peringatan di halaman pendaftaran ikut menyesuaikan.
    $daftar = $this->get(route('pendaftaran-open-trip'))->assertOk()->getContent();

    expect($daftar)->toContain($badanHukum);

    if ($gerbangNyala) {
        expect($daftar)->toContain($merek);
    }
})->with([true, false]);

test('tiap tulisan yang menerangkan jalur gerbang menyebut nama merchant-nya', function () {
    /*
     | Enam tempat menyinggung pembayaran. Yang menerangkan jalur gerbang WAJIB
     | menyebut nama merchant DOKU, karena itulah yang dilihat pembayar.
     |
     | Dua yang TIDAK ikut, dan memang benar begitu:
     |
     | - kwitansi.blade.php — kotak "satu-satunya nama penerima yang sah" di
     |   sana sudah dibungkus @unless ($lewatGerbang), jadi ia hanya tampil
     |   pada pembayaran yang memang lewat transfer.
     | - kebijakan-pengembalian.blade.php — dana kembali dikirim DARI rekening
     |   kami, dan rekening itu memang atas nama badan hukumnya.
     */
    foreach ([
        'views/components/peringatan-pembayaran.blade.php',
        'views/emails/tagihan.blade.php',
        'views/emails/pemberitahuan.blade.php',
        'views/livewire/public/informasi/faq.blade.php',
        'views/livewire/public/informasi/ketentuan-pembayaran.blade.php',
        'views/pdf/rab-penawaran.blade.php',
    ] as $berkas) {
        $isi = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path($berkas)));

        /*
         | assertStringContainsString, BUKAN expect()->toContain($isi, $pesan):
         | toContain() menerima BANYAK jarum, jadi kalimat pesannya ikut dicari
         | sebagai jarum kedua. Ujinya merah sambil mengeluh tidak menemukan
         | kalimat pesannya sendiri — bukan menemukan cacat yang dicarinya.
         */
        $this->assertStringContainsString(
            'atas_nama_gerbang',
            $isi,
            "{$berkas} menerangkan pembayaran tanpa menyebut nama penerima jalur gerbang"
        );
    }

    // Kotak nama tunggal di kuitansi tetap hanya untuk pembayaran transfer.
    $kwitansi = file_get_contents(resource_path('views/pdf/kwitansi.blade.php'));

    expect($kwitansi)->toMatch('/@unless \(\$lewatGerbang\)(?:(?!@endunless).)*Satu-satunya nama penerima yang sah/s');
});

test('layar cadangan pembayaran menawarkan jalan keluar berlangkah, bukan pengumuman kerusakan', function () {
    /*
     | Layar ini menemui orang yang SEDANG INGIN MEMBAYAR. Yang lama memajang
     | ikon kunci inggris setinggi 4rem di tengah kartu putih rata: perkakas
     | raksasa terbaca "rusak", dan yang membaca "rusak" di halaman pembayaran
     | menutup tab — padahal jalurnya tidak hilang, hanya berpindah lewat tim.
     |
     | Yang dijaga di sini bukan rupanya, melainkan dua hal yang menentukan
     | apakah orangnya jadi membayar atau tidak:
     |
     | 1. Janji "kursinya tidak hilang" ada dan menonjol. Sebagai kalimat
     |    ketiga di paragraf abu ia terbaca belakangan, padahal justru itu yang
     |    menahan orang menutup tab.
     | 2. Langkahnya tetap tiga dan tetap bernomor.
     |
     | Dan satu larangan lama yang tidak boleh kembali: formulir unggah bukti
     | TIDAK dihidupkan lagi di sini. Memunculkannya diam-diam saat gerbang
     | bermasalah memulihkan persis yang hendak dihilangkan — pembayaran yang
     | dinyatakan lewat gambar, dicek manusia, sementara kursinya menggantung.
     */
    config()->set('doku.aktif', false);

    $html = $this->get(route('konfirmasi-pembayaran'))->assertOk()->getContent();

    expect($html)->toContain('kartu-alihan')
        ->and($html)->toContain('lencana-aman')
        ->and($html)->toContain('kursinya tidak hilang')
        ->and(substr_count($html, 'langkah-nomor'))->toBe(3);

    // Tidak ada jalan mengunggah bukti di layar ini.
    expect($html)->not->toContain('type="file"');

    // Kode pesanan dipajang sebagai potongan karcis — barang yang disalin ke
    // percakapan WhatsApp, bukan disebut di tengah kalimat.
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/konfirmasi-pembayaran.blade.php'))
    );

    expect($blade)->toMatch('/@if \(\$pesanan\)(?:(?!@endif).)*karcis-kode/s');
});

test('halaman bayar memakai rak panel yang sama dengan beranda', function () {
    /*
     | Halaman ini dulu berdiri sendiri: `section-orcha` di atas latar putih,
     | dan kotak-kotaknya memakai .card-orcha — sudut lebih tajam, bingkai
     | kebiruan, dan jarak atas clamp(4rem, 9vw, 8.5rem) yang di layar lebar
     | menyisakan bidang kosong setinggi 130px antara kepala halaman dan isi
     | pertama. Berdampingan dengan beranda dan halaman paket, halaman inilah
     | yang terbaca "belum selesai dirapikan".
     |
     | Tidak ada galat untuk ini, dan tidak ada yang merah. Yang dijaga: rak
     | panelnya ada, .card-orcha tidak kembali, dan kepala panelnya berbentuk
     | sama dengan panel beranda (label-bagian di atas judul-panel-kecil).
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/konfirmasi-pembayaran.blade.php'))
    );

    expect($blade)->toContain('container-orcha rak-panel')
        ->and($blade)->not->toContain('card-orcha')
        ->and($blade)->not->toContain('section-orcha');

    $partial = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/partials/pencari-pesanan.blade.php'))
    );

    expect($partial)->toContain('label-bagian')
        ->and($partial)->toContain('judul-panel-kecil');

    // Dan dua jalurnya sama-sama masih tergambar.
    config()->set('doku.aktif', false);
    $mati = $this->get(route('konfirmasi-pembayaran'))->assertOk()->getContent();

    expect($mati)->toContain('kartu-alihan')
        ->and($mati)->toContain('rak-panel');

    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'UJI');
    config()->set('doku.secret_key', 'UJI');
    $nyala = $this->get(route('konfirmasi-pembayaran'))->assertOk()->getContent();

    expect($nyala)->toContain('panel-orcha')
        ->and($nyala)->toContain('judul-panel-kecil')
        ->and($nyala)->not->toContain('kartu-alihan');
});

test('panel pencari pesanan: isian dua kolom, ubin ikon di luar label, dan "belum ketemu" menunggu dua isian', function () {
    /*
     | Tiga hal yang masing-masing tidak akan memerahkan apa pun:
     |
     | 1. Ubin ikon harus DI LUAR <label>. .label-orcha memasang display:
     |    block, dan aturan itu di LUAR @layer sedangkan utilitas Tailwind di
     |    dalamnya — `flex` yang ditempel pada <label> kalah tanpa galat, dan
     |    ubinnya berdiri sendiri di baris atas seperti gambar yang lupa
     |    ditaruh.
     |
     | 2. "Kode ini belum kami temukan" hanya muncul setelah KEDUA isian
     |    terisi. Dengan syarat lama (kode saja >= 6 huruf) kotak itu muncul
     |    begitu kodenya selesai diketik, sementara empat digitnya memang
     |    belum diisi — orang yang baru mengetik separuh formulir diberi tahu
     |    pesanannya tidak ada padahal kodenya benar.
     |
     | 3. Kotak "ketemu" tidak boleh sewarna kotak "belum ketemu". Yang lama
     |    memakai border-orcha-sky dan text-orcha-ocean — peubah palet LAMA
     |    yang ditimpa palet hangat jadi emas pucat dan abu kehijauan, persis
     |    nada kotak "belum ketemu" di bawahnya.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/open-trip/partials/pencari-pesanan.blade.php'))
    );

    // 1. Tidak ada <label> yang mencoba jadi flex.
    expect(preg_match('/<label[^>]*class="[^"]*\bflex\b[^"]*"/', $blade))
        ->toBe(0, 'ubin ikon kembali dimasukkan ke dalam <label> yang display-nya block');

    expect($blade)->toContain('ubin-toska');

    // 2. Syarat "belum ketemu" menyebut kedua isian.
    /*
     | Pola memakai `.*`, BUKAN `[^)]*`: syaratnya sendiri memuat tanda kurung
     | — strlen(trim($kode)) — jadi [^)]* berhenti di kurung penutup trim()
     | dan tidak pernah sampai ke $empatDigit. Ujinya merah sambil menuduh
     | syarat yang sebenarnya sudah benar.
     */
    expect($blade)->toMatch('/@elseif \(.*\$kode.*\$empatDigit.*\)/');

    // 3. Dua kotak hasil, dua rupa.
    expect($blade)->toContain('kotak-temuan')
        ->and($blade)->toContain('kotak-nihil')
        ->and($blade)->not->toContain('border-orcha-sky')
        ->and($blade)->not->toContain('text-orcha-ocean');

    // Isiannya dua kolom di layar lebar.
    expect($blade)->toContain('sm:grid-cols-5')
        ->and($blade)->toContain('sm:col-span-3')
        ->and($blade)->toContain('sm:col-span-2');

    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    foreach (['.kotak-temuan', '.kotak-nihil'] as $kelas) {
        preg_match('/'.preg_quote($kelas, '/').' \{(.*?)\n\}/s', $css, $cocok);

        expect($cocok)->not->toBeEmpty("aturan {$kelas} hilang");
    }

    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'UJI');
    config()->set('doku.secret_key', 'UJI');

    $this->get(route('konfirmasi-pembayaran'))->assertOk()->assertSee('Pesanan yang dibayar.');
});

test('halaman testimoni memakai rak panel dan kartu abstrak, bukan kotak seragam', function () {
    /*
     | Dua hal yang membuat halaman ini terbaca "belum dirapikan" di samping
     | beranda dan blog:
     |
     | 1. .section-orcha di atas latar putih — pita putih kosong setinggi
     |    hampir 150px antara foto kepala halaman dan isi pertama, sementara
     |    halaman lain justru NAIK MENINDIH fotonya.
     | 2. .card-orcha: sembilan persegi panjang bersudut seragam di halaman
     |    yang isinya memang tulisan di atas tulisan. Tidak ada satu pun
     |    bidang yang membedakan satu kartu dari tetangganya.
     |
     | Kartunya kini memakai bahasa rupa .kartu-artikel di blog: sudut daun
     | BERSELANG-SELING, bayangan padat yang menggeser, dan miring sedikit
     | saat disentuh.
     */
    \App\Models\Etalase\Testimoni::create([
        'customer_name' => 'Pelanggan Uji Satu',
        'rating' => 5,
        'testimonial' => 'Perjalanannya menyenangkan.',
        'status' => 'tayang',
    ]);

    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/testimoni/index.blade.php'))
    );

    expect($blade)->toContain('container-orcha rak-panel')
        ->and($blade)->not->toContain('card-orcha')
        ->and($blade)->not->toContain('section-orcha')
        ->and($blade)->toContain('kartu-testimoni');

    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    // Sudutnya berselang-seling. Dengan sudut yang sama semuanya, deretannya
    // kembali rata seperti sebelum ada bentuk sama sekali.
    expect($css)->toContain('.kartu-testimoni:nth-child(even)');

    // Dan geraknya dimatikan bagi yang memintanya.
    expect($css)->toMatch('/@media \(prefers-reduced-motion: reduce\) \{(?:(?!\n\}).)*kartu-testimoni(?:(?!\n\}).)*\n\}/s');

    $html = $this->get(route('testimoni'))->assertOk()->getContent();

    expect($html)->toContain('kartu-testimoni')
        ->and($html)->toContain('rak-panel');
});

test('avatar testimoni yang berkasnya hilang tidak meninggalkan gambar rusak', function () {
    /*
     | Huruf awal nama SELALU digambar, dan fotonya menumpang di atasnya.
     |
     | Sebelumnya huruf awal hanya muncul bila kolom avatar KOSONG. Avatar
     | yang terisi tetapi berkasnya hilang — kejadian biasa, berkasnya
     | diunggah di server dan tidak ikut ke mana-mana — menggambar ikon gambar
     | rusak beserta nama pelanggan sebagai alt text, tepat di kartu yang
     | tugasnya meyakinkan calon pembeli. Tidak ada galat; halamannya tetap
     | 200, dan yang rusak cuma rupanya.
     */
    \App\Models\Etalase\Testimoni::create([
        'customer_name' => 'Pelanggan Beravatar',
        'rating' => 5,
        'testimonial' => 'Armadanya terawat.',
        'status' => 'tayang',
        'avatar' => '/storage/testimoni/berkas-yang-hilang.webp',
    ]);

    $html = $this->get(route('testimoni'))->assertOk()->getContent();

    // Huruf awalnya tergambar walaupun kolom avatar terisi.
    expect(preg_match('/bg-orcha-wave[^>]*>\s*P\b/s', $html))
        ->toBe(1, 'huruf awal nama tidak digambar saat avatar terisi');

    // Dan <img>-nya menyingkir sendiri bila berkasnya tidak ada.
    expect($html)->toContain('onerror="this.remove();"');
});

test('panel kirim testimoni memakai seluruh lebarnya, dan jalan pintas WhatsApp datang sesudah formulir', function () {
    /*
     | DUA hal yang terlihat hanya di layar lebar dan di layar sempit
     | masing-masing, dan tidak satu pun memerahkan apa pun:
     |
     | 1. Panel ini selebar halaman (±1.280px di layar 1440) sementara
     |    isiannya hanya memakai separuh kirinya — dan isian "4 digit" yang
     |    dipatok 9rem meninggalkan 520px kosong di kolomnya sendiri. Yang
     |    tergambar: panel terluas di halaman dengan lebih dari separuh
     |    bidangnya kosong, tepat di bawah deretan kartu yang padat.
     |
     | 2. Tombol WhatsApp harus datang SESUDAH formulirnya di markah. Di dalam
     |    kolom kiri, ia muncul sebelum isian pertama di layar sempit, dan
     |    yang menggulung berhenti di situ — mengambil jalan pintas yang
     |    justru lebih panjang bagi kedua belah pihak.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/testimoni/index.blade.php'))
    );

    // Dua kolom, dan isiannya tidak lagi dipatok 9rem.
    expect($blade)->toContain('lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]')
        ->and($blade)->toContain('sm:col-span-3')
        ->and($blade)->toContain('sm:col-span-2')
        ->and($blade)->not->toContain('sm:max-w-[9rem]');

    // Urutan markah: tombol kirim lebih dulu, jalan pintas WhatsApp sesudahnya.
    $html = $this->get(route('testimoni'))->assertOk()->getContent();

    $kirim = strpos($html, 'Kirim Testimoni');
    $wa = strpos($html, 'Lewat WhatsApp');

    expect($kirim)->not->toBeFalse()
        ->and($wa)->not->toBeFalse()
        ->and($wa)->toBeGreaterThan($kirim);
});

test('halaman FAQ memakai rak panel beranda, dan pertanyaannya jadi baris di dalam panel', function () {
    /*
     | Dua hal yang membuat halaman ini berbeda sendiri:
     |
     | 1. .section-orcha di atas latar putih — pita putih kosong setinggi
     |    hampir 150px antara foto kepala halaman dan isi pertama, sementara
     |    beranda, blog, dan testimoni justru NAIK MENINDIH fotonya.
     | 2. Tiap pertanyaan memakai .card-orcha: kartu putih bersudut tajam
     |    dengan bayangannya sendiri, bertumpuk belasan buah di atas latar
     |    putih. Yang tergambar bukan daftar melainkan tumpukan — dan karena
     |    kartunya sudah mirip panel, tidak ada yang menandai mana wadah mana
     |    isi.
     |
     | Sekarang panelnya yang jadi wadah, dan pertanyaannya cukup baris krem
     | bertepi tipis di dalamnya.
     */
    $blade = preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents(resource_path('views/livewire/public/informasi/faq.blade.php'))
    );

    expect($blade)->toContain('container-orcha rak-panel')
        ->and($blade)->not->toContain('card-orcha')
        ->and($blade)->not->toContain('section-orcha')
        ->and($blade)->toContain('tanya-orcha');

    /*
     | Label tiap panel menyebut NOMOR bagiannya, bukan kata "Kategori" yang
     | diulang lima kali. Label yang sama di tiap panel berhenti memberi tahu
     | apa pun, dan hanya menambah satu baris yang harus dilewati mata.
     */
    expect($blade)->toContain('Bagian {{ $loop->iteration }}');

    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/new-homepage.css')));

    // Yang sedang dibuka harus terlihat tanpa perlu dicari.
    expect($css)->toContain('.tanya-orcha[open]');

    $html = $this->get(route('faq'))->assertOk()->getContent();

    expect($html)->toContain('tanya-orcha')
        ->and($html)->toContain('rak-panel')
        ->and($html)->toContain('Bagian 1 dari 5');
});

test('tidak ada halaman publik yang tertinggal memakai rangka lama', function () {
    /*
     | Penyapu, bukan penjaga satu halaman.
     |
     | Sepanjang hari ini halaman dipindahkan ke rangka beranda satu per satu —
     | dan satu-satunya cara halaman berikutnya ketinggalan adalah tidak ada
     | yang menghitungnya. Uji ini menghitungnya, dan MENYEBUT berkas
     | pelanggarnya supaya tidak perlu dicari.
     |
     | Yang dilarang:
     |
     | - .section-orcha di halaman publik. Ia memberi jarak atas clamp(4rem,
     |   9vw, 8.5rem); di layar lebar itu pita putih kosong setinggi hampir
     |   150px antara foto kepala halaman dan isi pertama, sementara halaman
     |   ber-rak justru NAIK MENINDIH fotonya.
     | - .card-orcha sebagai kotak isi. Sudutnya lebih tajam dan bingkainya
     |   kebiruan — peninggalan sebelum .panel-orcha ada.
     |
     | SATU pengecualian yang disengaja: kartu di dalam petak (kelasnya memuat
     | "group", mis. kartu destinasi). Rupanya memang dirancang sendiri —
     | sudut daun berselang-seling, lengkung, semburat — dan .card-orcha di
     | sana cuma lapis dasarnya, bukan rangka halaman.
     */
    $komentarBlade = '/\{\{--.*?--\}\}/s';

    $pelanggar = ['section-orcha' => [], 'card-orcha' => []];

    foreach ([
        resource_path('views/livewire/public'),
        resource_path('views/components'),
        resource_path('views/partials'),
    ] as $akar) {
        if (! is_dir($akar)) {
            continue;
        }

        $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar));

        foreach ($berkas as $berkasnya) {
            if ($berkasnya->getExtension() !== 'php') {
                continue;
            }

            $isi = preg_replace($komentarBlade, '', file_get_contents($berkasnya->getPathname()));
            $nama = str_replace(resource_path('views/'), '', $berkasnya->getPathname());

            preg_match_all('/class="([^"]*)"/', $isi, $cocok);

            foreach ($cocok[1] as $kelas) {
                if (str_contains($kelas, 'section-orcha')) {
                    $pelanggar['section-orcha'][] = $nama;
                }

                // Kartu di dalam petak dikecualikan — lihat keterangan di atas.
                if (str_contains($kelas, 'card-orcha') && ! str_contains($kelas, 'group')) {
                    $pelanggar['card-orcha'][] = $nama;
                }
            }
        }
    }

    expect(array_unique($pelanggar['section-orcha']))
        ->toBe([], 'masih memakai .section-orcha: '.implode(', ', array_unique($pelanggar['section-orcha'])));

    expect(array_unique($pelanggar['card-orcha']))
        ->toBe([], 'masih memakai .card-orcha sebagai kotak isi: '.implode(', ', array_unique($pelanggar['card-orcha'])));
});

test('semua halaman publik berkepala foto memakai rak panel', function () {
    /*
     | Pelengkap uji di atas. Membuang .section-orcha saja tidak cukup: sebuah
     | halaman bisa memakai pembungkus buatannya sendiri (mis. py-12 sm:py-16)
     | dan tetap meninggalkan pita putih yang sama. Itu yang terjadi pada
     | lacak-pesanan, destinasi/detail, dan blog/detail — ketiganya lolos dari
     | pencarian "section-orcha" karena memang tidak memakainya.
     |
     | Patokannya: halaman yang punya <x-page-hero> HARUS punya rak-panel.
     */
    $tertinggal = [];

    $berkas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views/livewire/public'))
    );

    foreach ($berkas as $berkasnya) {
        if ($berkasnya->getExtension() !== 'php') {
            continue;
        }

        $isi = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($berkasnya->getPathname()));

        if (! str_contains($isi, 'x-page-hero')) {
            continue;
        }

        if (! str_contains($isi, 'rak-panel')) {
            $tertinggal[] = str_replace(resource_path('views/'), '', $berkasnya->getPathname());
        }
    }

    expect($tertinggal)->toBe([], 'berkepala foto tetapi tanpa rak-panel: '.implode(', ', $tertinggal));
});
