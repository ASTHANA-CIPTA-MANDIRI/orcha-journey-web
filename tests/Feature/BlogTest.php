<?php

use App\Models\Blog\Artikel;

/**
 * Blog publik.
 *
 * Yang paling dijaga di sini bukan tampilannya melainkan satu hal: tulisan
 * yang BELUM siap tidak boleh terbaca siapa pun. Draf yang bocor tidak
 * menimbulkan galat apa pun — halamannya tampil rapi seperti artikel biasa —
 * jadi tidak ada yang menyadarinya sampai ada yang telanjur membacanya.
 */
function artikelTayang(array $ubah = []): Artikel
{
    return Artikel::create(array_merge([
        'judul' => 'Bawa Apa Saja ke Bromo',
        'ringkasan' => 'Daftar bawaan yang benar-benar terpakai saat trip dini hari ke Bromo.',
        'isi' => '<p>Suhu di Penanjakan bisa turun sampai lima derajat.</p>',
        'kategori' => 'panduan',
        'penulis' => 'Tim Orcha',
        'status' => 'tayang',
        'terbit_pada' => now()->subDay(),
    ], $ubah));
}

/* ------------------------------------------------------------------ Halaman */

test('halaman blog bisa dibuka meski belum ada satu tulisan pun', function () {
    // Halaman kosong tetap harus utuh: blog yang baru dipasang belum berisi,
    // dan yang muncul jangan sampai galat.
    $this->get(route('blog'))
        ->assertOk()
        ->assertSee('Tulisan pertama sedang disiapkan');
});

test('artikel tayang muncul di daftar dan bisa dibuka', function () {
    $artikel = artikelTayang();

    $this->get(route('blog'))
        ->assertOk()
        ->assertSee('Bawa Apa Saja ke Bromo');

    $this->get(route('blog.detail', $artikel))
        ->assertOk()
        ->assertSee('Bawa Apa Saja ke Bromo')
        ->assertSee('Suhu di Penanjakan', false);
});

/* ------------------------------------------------------- Yang belum tayang */

test('draf tidak muncul di daftar dan tidak bisa dibuka lewat tautan', function () {
    $draf = artikelTayang(['judul' => 'Draf Belum Matang', 'status' => 'draf']);

    $this->get(route('blog'))->assertOk()->assertDontSee('Draf Belum Matang');
    $this->get(route('blog.detail', $draf))->assertNotFound();
});

test('artikel terjadwal belum terbaca sebelum tanggalnya', function () {
    $besok = artikelTayang([
        'judul' => 'Terbit Minggu Depan',
        'terbit_pada' => now()->addWeek(),
    ]);

    $this->get(route('blog'))->assertOk()->assertDontSee('Terbit Minggu Depan');
    $this->get(route('blog.detail', $besok))->assertNotFound();
});

test('status tayang tanpa tanggal terbit tetap dianggap belum tayang', function () {
    // Kalau tidak, tulisan yang statusnya telanjur diubah sebelum tanggalnya
    // ditentukan langsung muncul di beranda blog.
    $tanpaTanggal = artikelTayang(['judul' => 'Lupa Tanggal', 'terbit_pada' => null]);

    expect($tanpaTanggal->sedang_tayang)->toBeFalse();
    $this->get(route('blog.detail', $tanpaTanggal))->assertNotFound();
});

/* -------------------------------------------------------------------- Slug */

test('slug dibuat sendiri dari judul dan tidak pernah bentrok', function () {
    $satu = artikelTayang(['judul' => 'Panduan Open Trip']);
    $dua = artikelTayang(['judul' => 'Panduan Open Trip']);

    expect($satu->slug)->toBe('panduan-open-trip')
        ->and($dua->slug)->toBe('panduan-open-trip-2');

    // Keduanya benar-benar bisa dibuka — inti dari menjaga slug tetap unik.
    $this->get(route('blog.detail', $satu))->assertOk();
    $this->get(route('blog.detail', $dua))->assertOk();
});

test('alamat artikel memakai slug, bukan id angka', function () {
    $artikel = artikelTayang(['judul' => 'Tips Study Tour']);

    // Yang diperiksa RUAS TERAKHIR jalurnya, bukan seluruh URL: alamat lengkap
    // memuat "127.0.0.1", jadi mencari angka id di dalamnya selalu ketemu
    // sesuatu dan tidak membuktikan apa pun.
    $ruas = basename(parse_url(route('blog.detail', $artikel), PHP_URL_PATH));

    expect($ruas)->toBe('tips-study-tour')
        ->and($ruas)->not->toBe((string) $artikel->id);
});

/* --------------------------------------------------------------- Penyaring */

test('penyaring kategori dan pencarian menyaring daftar', function () {
    artikelTayang(['judul' => 'Cerita dari Raja Ampat', 'kategori' => 'destinasi']);
    artikelTayang(['judul' => 'Checklist Sebelum Berangkat', 'kategori' => 'tips']);

    $this->get(route('blog', ['kategori' => 'destinasi']))
        ->assertOk()
        ->assertSee('Cerita dari Raja Ampat')
        ->assertDontSee('Checklist Sebelum Berangkat');

    $this->get(route('blog', ['cari' => 'Checklist']))
        ->assertOk()
        ->assertSee('Checklist Sebelum Berangkat')
        ->assertDontSee('Cerita dari Raja Ampat');
});

test('kategori karangan di alamat tidak menyaring apa pun', function () {
    // Alamat datang dari luar dan bisa berisi apa saja. Kategori yang tidak
    // dikenal harus diabaikan, bukan menghasilkan daftar kosong yang terbaca
    // seolah blognya memang belum berisi.
    artikelTayang(['judul' => 'Tetap Harus Tampil']);

    $this->get(route('blog', ['kategori' => 'tidak-ada-kategori-ini']))
        ->assertOk()
        ->assertSee('Tetap Harus Tampil');
});

/* ------------------------------------------------------------------ Aksesor */

test('lama baca dihitung dari jumlah kata isinya', function () {
    $pendek = artikelTayang(['judul' => 'Pendek', 'isi' => '<p>'.str_repeat('kata ', 100).'</p>']);
    $panjang = artikelTayang(['judul' => 'Panjang', 'isi' => '<p>'.str_repeat('kata ', 1000).'</p>']);

    // Selalu minimal satu menit: "0 menit baca" tidak berarti apa-apa.
    expect($pendek->lama_baca)->toBe(1)
        ->and($panjang->lama_baca)->toBe(5);
});

test('ringkasan kosong jatuh ke potongan isi, bukan bidang kosong', function () {
    $artikel = artikelTayang([
        'judul' => 'Tanpa Ringkasan',
        'ringkasan' => null,
        'isi' => '<p>Kalimat pembuka yang menjelaskan isinya.</p>',
    ]);

    expect($artikel->ringkasan_tampil)->toBe('Kalimat pembuka yang menjelaskan isinya.');
});

test('artikel tanpa sampul tetap punya gambar', function () {
    // Kartu tanpa gambar merusak baris kartu di sebelahnya.
    expect(artikelTayang(['sampul' => null])->sampul_tampil)->toContain('.webp');
});

test('hero artikel tanpa sampul persis sama dengan hero halaman daftar', function () {
    /*
     | Keduanya memakai foto yang SAMA, jadi krop-nya harus sama pula. Kalau
     | tidak, hero berubah bentuk tepat saat pembaca menekan satu kartu — dan
     | itu terbaca sebagai halaman yang berpindah tempat, bukan sebagai
     | kelanjutan.
     |
     | Nilainya dibaca dari berkas halaman daftar, bukan ditulis ulang di sini:
     | menyalin '88%' ke uji ini berarti mengubah hero /blog tidak lagi
     | menjatuhkan uji apa pun, dan keduanya diam-diam berpisah lagi.
     */
    $daftar = file_get_contents(resource_path('views/livewire/public/blog/index.blade.php'));

    preg_match('/images\/HERO\/blog\.webp"\s+posisi="([^"]+)"/', $daftar, $cocok);

    expect($cocok[1] ?? null)->not->toBeNull('hero /blog tidak lagi memakai HERO/blog.webp')
        ->and(artikelTayang(['sampul' => null])->posisi_sampul)->toBe($cocok[1]);
});

test('artikel bersampul sendiri memakai krop netral, bukan krop foto cadangan', function () {
    // Sampul unggahan admin bisa berupa apa saja; nilai yang dipaskan untuk
    // satu foto akan memenggal foto berikutnya.
    $punya = artikelTayang(['sampul' => '/storage/artikel/foto.webp']);
    $tanpa = artikelTayang(['judul' => 'Tanpa Sampul', 'sampul' => null]);

    expect($punya->posisi_sampul)->not->toBe($tanpa->posisi_sampul);
});

test('penghitung dilihat naik saat artikel dibuka, tanpa menggeser updated_at', function () {
    $artikel = artikelTayang();
    $sebelum = $artikel->updated_at;

    $this->get(route('blog.detail', $artikel))->assertOk();

    $artikel->refresh();

    expect($artikel->dilihat)->toBe(1)
        // Kalau updated_at ikut bergeser, urutan "terbaru" berubah tiap kali
        // ada yang membaca — daftar jadi mengurut popularitas tanpa diminta.
        ->and($artikel->updated_at->timestamp)->toBe($sebelum->timestamp);
});

/* ---------------------------------------------------------------- Peta situs */

test('peta situs memuat artikel tayang dan menyembunyikan yang belum', function () {
    $tayang = artikelTayang(['judul' => 'Sudah Tayang']);
    $draf = artikelTayang(['judul' => 'Masih Draf', 'status' => 'draf']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('blog'))
        ->toContain(route('blog.detail', $tayang))
        ->not->toContain(route('blog.detail', $draf));
});

/* ------------------------------------------------------------------- Navigasi */

test('blog tertaut dari navigasi dan kaki halaman', function () {
    $this->get('/')->assertOk()->assertSee(route('blog'), false);
});

/* ------------------------------------------------- Penyaring isi artikel */

test('skrip di dalam isi artikel tidak pernah sampai ke pengunjung', function () {
    /*
     | Isi artikel datang dari lemon lewat API — aplikasi lain di server lain.
     | Tanpa penyaring, siapa pun yang bisa menulis artikel dapat menjalankan
     | skrip di peramban SETIAP pengunjung orchajourney.com.
     */
    $artikel = artikelTayang([
        'judul' => 'Artikel Berbahaya',
        'isi' => '<p>Teks aman.</p><script>alert(1)</script>'
            .'<img src=x onerror=alert(1)>'
            .'<a href="javascript:alert(1)">klik</a>'
            .'<iframe src="https://jahat.example"></iframe>',
    ]);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    expect($html)->toContain('Teks aman.')
        ->not->toContain('alert(1)')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('jahat.example');
});

test('format tulisan yang wajar tetap utuh setelah disaring', function () {
    // Penyaring yang terlalu galak sama merusaknya: admin kehilangan format
    // dan menyimpulkan penyuntingnya tidak bekerja.
    $artikel = artikelTayang([
        'judul' => 'Artikel Berformat',
        'isi' => '<h2>Judul Bagian</h2><p>Paragraf dengan <strong>tebal</strong> dan '
            .'<a href="https://orchajourney.com">tautan</a>.</p>'
            .'<ul><li>Poin satu</li></ul><blockquote>Kutipan.</blockquote>',
    ]);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    expect($html)->toContain('<h2>Judul Bagian</h2>')
        ->toContain('<strong>tebal</strong>')
        ->toContain('href="https://orchajourney.com"')
        ->toContain('<li>Poin satu</li>')
        ->toContain('<blockquote>Kutipan.</blockquote>');
});

/* ------------------------------------------------ Bentuk halaman artikel */

test('halaman artikel memuat bagian yang sama dengan blog Phoenix', function () {
    $artikel = artikelTayang(['judul' => 'Artikel Lengkap']);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    foreach ([
        'orc-art-meta' => 'baris keterangan',
        'orc-art-sampul' => 'sampul di dalam artikel',
        'orc-bagi' => 'tombol bagikan',
        'orc-kembali' => 'tautan kembali ke blog',
        'orc-kartu-ajakan' => 'kartu ajakan',
        'application/ld+json' => 'data terstruktur',
    ] as $penanda => $nama) {
        // assertStringContainsString, bukan expect()->toContain(): argumen kedua
        // toContain() adalah kata KEDUA yang ikut dicari, bukan pesan galat.
        expect(str_contains($html, $penanda))
            ->toBeTrue("bagian '$nama' hilang dari halaman artikel");
    }
});

test('nama penulis tidak pernah terbit ke halaman publik', function () {
    /*
     | Kolom penulis tetap ada untuk keperluan admin, tetapi yang tampil di
     | halaman publik dan data terstruktur adalah nama tim — nama orang tidak
     | perlu ikut masuk hasil pencarian.
     */
    $artikel = artikelTayang(['judul' => 'Tanpa Nama Orang', 'penulis' => 'Budi Santoso']);

    $this->get(route('blog.detail', $artikel))
        ->assertOk()
        ->assertDontSee('Budi Santoso')
        ->assertSee('Tim Orcha Journey');
});

test('halaman artikel memakai hero yang sama dengan halaman blog', function () {
    $artikel = artikelTayang(['judul' => 'Judul Artikelnya']);

    $detail = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();
    $daftar = $this->get(route('blog'))->assertOk()->getContent();

    // Gambar DAN krop yang sama persis — kalau salah satunya berbeda, hero-nya
    // berubah bentuk tepat saat pembaca menekan satu kartu.
    foreach (['images/HERO/blog.webp', 'center 88%'] as $penanda) {
        expect(str_contains($detail, $penanda))->toBeTrue("hero artikel kehilangan '$penanda'");
        expect(str_contains($daftar, $penanda))->toBeTrue("hero daftar kehilangan '$penanda'");
    }

    // Keduanya punya hero gelap sendiri, jadi bilah menu tidak perlu dipaksa.
    expect($detail)->toContain('scrolled: false');
});

test('halaman artikel hanya punya satu judul utama', function () {
    /*
     | Hero sudah memegang <h1>. Kalau judulnya diulang di dalam artikel, ada
     | dua <h1> dalam satu halaman dan mesin pencari harus menebak sendiri mana
     | judul yang sebenarnya.
     */
    $artikel = artikelTayang(['judul' => 'Judul Artikelnya']);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
});

test('gaya tempelan tidak membajak tampilan artikel', function () {
    /*
     | Isi artikel ditempel dari penyunting lain, dan yang ikut terbawa bukan
     | cuma teksnya: tiap potongan membawa background-color dan color miliknya
     | sendiri. Terlihat pada kutipan — latar putih bawaan tempelan menempel di
     | atas latar biru muda milik situs, dan yang terbaca pengunjung adalah pita
     | krem yang tidak pernah dirancang siapa pun.
     */
    $artikel = artikelTayang([
        'judul' => 'Artikel Tempelan',
        'isi' => '<blockquote><span style="background-color: rgb(255,255,255); color: rgb(58,63,74);">'
            .'Kutipan tertempel.</span></blockquote>'
            .'<p style="text-align: center; font-family: Arial;">Paragraf tengah.</p>',
    ]);

    $html = $this->get(route('blog.detail', $artikel))->assertOk()->getContent();

    expect($html)->toContain('Kutipan tertempel.')
        // Warna, latar, dan jenis huruf urusan situs — bukan urusan tempelan.
        ->not->toContain('background-color')
        ->not->toContain('color: rgb')
        ->not->toContain('font-family: Arial')
        // Perataan teks keputusan penulis, jadi dipertahankan.
        ->toContain('text-align: center');
});
