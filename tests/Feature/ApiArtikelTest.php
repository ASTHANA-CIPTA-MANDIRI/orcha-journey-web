<?php

use App\Models\Blog\Artikel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * API blog untuk dashboard lemon.
 *
 * Layar Blog di mode Orcha sepenuhnya bergantung pada jalur-jalur ini; kalau
 * salah satunya berubah bentuk, yang rusak adalah aplikasi lain di server lain
 * dan tidak ada satu pun uji di sana yang menyadarinya.
 */
beforeEach(function () {
    config()->set('orcha.api.kunci', 'kunci-uji-artikel');
    $this->kepala = ['X-Orcha-Key' => 'kunci-uji-artikel', 'Accept' => 'application/json'];
});

function artikelApi(array $ubah = []): Artikel
{
    return Artikel::create(array_merge([
        'judul' => 'Bawa Apa ke Bromo',
        'isi' => '<p>Jaket tebal.</p>',
        'kategori' => 'panduan',
        'status' => 'tayang',
        'terbit_pada' => now()->subDay(),
    ], $ubah));
}

/* ---------------------------------------------------------------- Penjagaan */

test('jalur artikel menolak permintaan tanpa kunci', function (string $metode, string $jalur) {
    $this->json($metode, $jalur)->assertUnauthorized();
})->with([
    ['get', '/api/v1/artikel'],
    ['post', '/api/v1/artikel'],
    ['get', '/api/v1/artikel/slug'],
]);

/* -------------------------------------------------------------------- Daftar */

test('daftar admin memuat draf, tidak seperti halaman publik', function () {
    artikelApi(['judul' => 'Sudah Tayang']);
    artikelApi(['judul' => 'Masih Draf', 'status' => 'draf']);

    $data = $this->getJson('/api/v1/artikel', $this->kepala)
        ->assertOk()->json('data');

    // Justru draf itulah yang sedang dikerjakan admin.
    expect(collect($data)->pluck('judul'))->toContain('Sudah Tayang', 'Masih Draf');
});

test('daftar bisa disaring status, kategori, dan kata kunci', function () {
    artikelApi(['judul' => 'Cerita Raja Ampat', 'kategori' => 'destinasi']);
    artikelApi(['judul' => 'Checklist Guru', 'kategori' => 'tips', 'status' => 'draf']);

    $hanyaDraf = $this->getJson('/api/v1/artikel?status=draf', $this->kepala)->assertOk()->json('data');
    expect($hanyaDraf)->toHaveCount(1)->and($hanyaDraf[0]['judul'])->toBe('Checklist Guru');

    $perKategori = $this->getJson('/api/v1/artikel?kategori=destinasi', $this->kepala)->assertOk()->json('data');
    expect($perKategori)->toHaveCount(1)->and($perKategori[0]['judul'])->toBe('Cerita Raja Ampat');

    $pencarian = $this->getJson('/api/v1/artikel?cari=Checklist', $this->kepala)->assertOk()->json('data');
    expect($pencarian)->toHaveCount(1);
});

test('daftar membawa meta paginasi yang dibaca lemon', function () {
    artikelApi();

    $this->getJson('/api/v1/artikel', $this->kepala)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['halaman', 'per_halaman', 'total', 'halaman_terakhir']]);
});

/* ------------------------------------------------------------------ Simpan */

test('artikel bisa dibuat lewat API dan slugnya terisi sendiri', function () {
    $balasan = $this->postJson('/api/v1/artikel', [
        'judul' => 'Panduan Open Trip Pertama',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
    ], $this->kepala)->assertCreated();

    expect($balasan->json('data.slug'))->toBe('panduan-open-trip-pertama');
    expect(Artikel::count())->toBe(1);
});

test('menayangkan tanpa tanggal otomatis memakai waktu sekarang', function () {
    /*
     | Tanpa ini admin yang memilih "tayang" lalu menyimpan akan mendapati
     | artikelnya tetap tidak muncul di mana pun — scopeTayang() menuntut
     | terbit_pada terisi — dan tidak ada pesan yang menjelaskan kenapa.
     */
    $this->postJson('/api/v1/artikel', [
        'judul' => 'Langsung Tayang',
        'isi' => '<p>Isi.</p>',
        'status' => 'tayang',
    ], $this->kepala)->assertCreated();

    $artikel = Artikel::firstOrFail();

    expect($artikel->terbit_pada)->not->toBeNull()
        ->and($artikel->sedang_tayang)->toBeTrue();
});

test('tanggal terbit di masa depan dihormati, bukan ditimpa', function () {
    $this->postJson('/api/v1/artikel', [
        'judul' => 'Terjadwal',
        'isi' => '<p>Isi.</p>',
        'status' => 'tayang',
        'terbit_pada' => now()->addWeek()->toDateTimeString(),
    ], $this->kepala)->assertCreated();

    expect(Artikel::firstOrFail()->sedang_tayang)->toBeFalse();
});

test('kategori di luar daftar ditolak', function () {
    $this->postJson('/api/v1/artikel', [
        'judul' => 'Kategori Ngawur',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'kategori' => 'bukan-kategori',
    ], $this->kepala)->assertJsonValidationErrors('kategori');
});

test('judul dan isi wajib diisi', function () {
    $this->postJson('/api/v1/artikel', ['status' => 'draf'], $this->kepala)
        ->assertJsonValidationErrors(['judul', 'isi']);
});

/* -------------------------------------------------------------------- Ubah */

test('artikel bisa diubah dan slugnya tetap unik', function () {
    $satu = artikelApi(['judul' => 'Judul A']);
    $dua = artikelApi(['judul' => 'Judul B']);

    // Slug yang sudah dipakai artikel lain harus ditolak, bukan menimpanya.
    $this->postJson("/api/v1/artikel/{$dua->id}", [
        'judul' => 'Judul B',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'slug' => $satu->slug,
    ], $this->kepala)->assertJsonValidationErrors('slug');

    // Slug miliknya sendiri tetap boleh — kalau tidak, menyimpan ulang tanpa
    // mengubah slug akan gagal.
    $this->postJson("/api/v1/artikel/{$dua->id}", [
        'judul' => 'Judul B Baru',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'slug' => $dua->slug,
    ], $this->kepala)->assertOk();

    expect($dua->fresh()->judul)->toBe('Judul B Baru');
});

test('jalur admin memakai id, bukan slug yang bisa berubah', function () {
    $artikel = artikelApi(['judul' => 'Judul Lama']);
    $slugLama = $artikel->slug;

    $this->postJson("/api/v1/artikel/{$artikel->id}", [
        'judul' => 'Judul Sudah Diganti',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'slug' => 'judul-sudah-diganti',
    ], $this->kepala)->assertOk();

    // Nomor id tetap menemukan artikelnya walau slugnya sudah berganti.
    $this->getJson("/api/v1/artikel/{$artikel->id}", $this->kepala)->assertOk();
    expect($artikel->fresh()->slug)->toBe('judul-sudah-diganti')->not->toBe($slugLama);
});

/* ------------------------------------------------------------------ Sampul */

test('sampul yang diunggah tersimpan sebagai webp', function () {
    Storage::fake('public');

    $balasan = $this->post('/api/v1/artikel', [
        'judul' => 'Dengan Sampul',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'gambar' => UploadedFile::fake()->image('sampul.jpg', 1600, 900),
    ], $this->kepala)->assertCreated();

    expect($balasan->json('data.sampul'))->toEndWith('.webp');
});

test('menyimpan tanpa mengunggah gambar tidak menghapus sampul lama', function () {
    $artikel = artikelApi(['sampul' => '/storage/artikel/lama.webp']);

    $this->postJson("/api/v1/artikel/{$artikel->id}", [
        'judul' => 'Judul Baru',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
    ], $this->kepala)->assertOk();

    expect($artikel->fresh()->sampul)->toBe('/storage/artikel/lama.webp');
});

test('sampul hanya hilang bila admin memang memintanya', function () {
    $artikel = artikelApi(['sampul' => '/storage/artikel/lama.webp']);

    $this->postJson("/api/v1/artikel/{$artikel->id}", [
        'judul' => 'Judul Baru',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'hapus_sampul' => true,
    ], $this->kepala)->assertOk();

    expect($artikel->fresh()->sampul)->toBeNull();
});

/* -------------------------------------------------------------------- Slug */

test('usulan slug dihitung Orcha, bukan ditebak lemon', function () {
    artikelApi(['judul' => 'Panduan Bromo']);

    // Bentrok diselesaikan di sini karena hanya Orcha yang punya tabel
    // artikelnya — lemon tidak bisa tahu slug mana yang sudah terpakai.
    $this->getJson('/api/v1/artikel/slug?judul=Panduan+Bromo', $this->kepala)
        ->assertOk()
        ->assertJson(['slug' => 'panduan-bromo-2']);

    // Saat menyunting artikel itu sendiri, slugnya sendiri tidak dihitung bentrok.
    $artikel = Artikel::firstOrFail();
    $this->getJson("/api/v1/artikel/slug?judul=Panduan+Bromo&kecuali={$artikel->id}", $this->kepala)
        ->assertOk()
        ->assertJson(['slug' => 'panduan-bromo']);
});

/* ------------------------------------------------------------------- Hapus */

test('artikel bisa dihapus', function () {
    $artikel = artikelApi();

    $this->deleteJson("/api/v1/artikel/{$artikel->id}", [], $this->kepala)->assertOk();

    expect(Artikel::count())->toBe(0);
});

/* ------------------------------------------------------------ SEO otomatis */

test('meta judul dan keterangan tersimpan dan dipakai halaman publik', function () {
    $artikel = artikelApi([
        'judul' => 'Judul Panjang yang Bagus Dibaca Manusia tapi Kepanjangan untuk Google',
        'ringkasan' => 'Ringkasan yang dibaca pengunjung.',
    ]);

    $this->postJson("/api/v1/artikel/{$artikel->id}", [
        'judul' => $artikel->judul,
        'isi' => '<p>Isi.</p>',
        'status' => 'tayang',
        'meta_title' => 'Judul Pendek untuk Google',
        'meta_description' => 'Keterangan khusus mesin pencari.',
    ], $this->kepala)->assertOk();

    $html = $this->get(route('blog.detail', $artikel->fresh()))->assertOk()->getContent();

    // meta_title dipakai apa adanya — tanpa embel "— Blog Orcha Journey".
    expect($html)->toContain('<title>Judul Pendek untuk Google</title>')
        ->toContain('Keterangan khusus mesin pencari.');
});

test('meta yang belum diisi jatuh ke judul dan ringkasan', function () {
    $artikel = artikelApi(['ringkasan' => 'Ringkasan pengunjung.']);

    expect($artikel->meta_title_tampil)->toBe($artikel->judul)
        ->and($artikel->meta_description_tampil)->toBe('Ringkasan pengunjung.');
});

test('formulir menerima meta MENTAH, bukan cadangannya', function () {
    // Kalau show mengirim nilai cadangan, kotak meta di lemon tampak sudah
    // terisi padahal admin belum pernah menyentuhnya.
    $artikel = artikelApi(['meta_title' => null]);

    $data = $this->getJson("/api/v1/artikel/{$artikel->id}", $this->kepala)->assertOk()->json('data');

    expect($data['meta_title'])->toBeNull()
        ->and($data['meta_title_tampil'])->toBe($artikel->judul);
});

/* --------------------------------------------------------------- Kategori */

test('kategori bawaan ikut pindah dari config ke tabel', function () {
    $daftar = $this->getJson('/api/v1/kategori-artikel', $this->kepala)->assertOk()->json('data');

    expect(collect($daftar)->pluck('slug'))
        ->toContain('panduan', 'destinasi', 'tips', 'kabar');
});

test('kategori baru bisa ditambah dan langsung bisa dipakai artikel', function () {
    $this->postJson('/api/v1/kategori-artikel', ['nama' => 'Kuliner Lokal'], $this->kepala)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'kuliner-lokal');

    // Validator artikel membaca daftar yang sama, jadi rubrik baru langsung sah.
    $this->postJson('/api/v1/artikel', [
        'judul' => 'Soto Terbaik di Jalur Bromo',
        'isi' => '<p>Isi.</p>',
        'status' => 'draf',
        'kategori' => 'kuliner-lokal',
    ], $this->kepala)->assertCreated();
});

test('nama kategori tidak boleh kembar', function () {
    $this->postJson('/api/v1/kategori-artikel', ['nama' => 'Kuliner'], $this->kepala)->assertCreated();
    $this->postJson('/api/v1/kategori-artikel', ['nama' => 'Kuliner'], $this->kepala)
        ->assertJsonValidationErrors('nama');
});

test('kategori yang masih dipakai artikel tidak bisa dihapus', function () {
    /*
     | Artikel menyimpan slug rubriknya sebagai teks, bukan relasi — menghapus
     | rubriknya tidak menimbulkan galat apa pun. Yang terjadi lebih buruk:
     | artikelnya kehilangan rubrik tanpa satu pun pesan.
     */
    $kategori = \App\Models\Blog\KategoriArtikel::where('slug', 'panduan')->firstOrFail();
    artikelApi(['kategori' => 'panduan']);

    $this->deleteJson("/api/v1/kategori-artikel/{$kategori->id}", [], $this->kepala)
        ->assertStatus(422);

    expect(\App\Models\Blog\KategoriArtikel::find($kategori->id))->not->toBeNull();
});

test('kategori yang tidak dipakai bisa dihapus', function () {
    $kategori = \App\Models\Blog\KategoriArtikel::where('slug', 'kabar')->firstOrFail();

    $this->deleteJson("/api/v1/kategori-artikel/{$kategori->id}", [], $this->kepala)->assertOk();

    expect(\App\Models\Blog\KategoriArtikel::find($kategori->id))->toBeNull();
});

test('tab kategori di halaman blog ikut kategori tabel, bukan config', function () {
    \App\Models\Blog\KategoriArtikel::create(['nama' => 'Kuliner Lokal', 'slug' => 'kuliner-lokal']);

    $this->get(route('blog'))->assertOk()->assertSee('Kuliner Lokal');
});

/* -------------------------------------------------- Gambar di dalam isi */

test('gambar yang ditempel ke isi artikel diselamatkan jadi berkas webp', function () {
    Storage::fake('public');

    // PNG dibuat langsung, bukan lewat UploadedFile::fake(): berkas
    // sementaranya dihapus begitu objeknya lepas, jadi sempat hilang sebelum
    // isinya terbaca.
    $gd = imagecreatetruecolor(40, 30);
    imagefill($gd, 0, 0, imagecolorallocate($gd, 20, 110, 165));
    ob_start();
    imagepng($gd);
    $png = base64_encode((string) ob_get_clean());
    imagedestroy($gd);

    $this->postJson('/api/v1/artikel', [
        'judul' => 'Artikel dengan Gambar Tempelan',
        'isi' => '<p>Sebelum.</p><img src="data:image/png;base64,'.$png.'"><p>Sesudah.</p>',
        'status' => 'draf',
    ], $this->kepala)->assertCreated();

    $isi = Artikel::firstOrFail()->isi;

    // Base64-nya HARUS hilang dari kolom isi: itu seluruh maksudnya.
    expect($isi)->not->toContain('base64,')
        ->toContain('/storage/artikel/isi/')
        ->toContain('.webp')
        // Gambar di tengah artikel selalu di bawah layar pertama.
        ->toContain('loading="lazy"');
});

test('isi tanpa gambar tempelan dibiarkan apa adanya', function () {
    $isi = '<h2>Judul bagian</h2><p>Teks biasa tanpa gambar.</p>';

    $this->postJson('/api/v1/artikel', [
        'judul' => 'Artikel Teks Saja',
        'isi' => $isi,
        'status' => 'draf',
    ], $this->kepala)->assertCreated();

    expect(Artikel::firstOrFail()->isi)->toBe($isi);
});

test('gambar tempelan yang rusak tidak menghilangkan tagnya', function () {
    // Lebih baik artikel memuat gambar yang gagal tampil daripada gambarnya
    // lenyap diam-diam tanpa admin tahu.
    $this->postJson('/api/v1/artikel', [
        'judul' => 'Tempelan Rusak',
        'isi' => '<p>Teks.</p><img src="data:image/png;base64,bukan-gambar-sungguhan">',
        'status' => 'draf',
    ], $this->kepala)->assertCreated();

    expect(Artikel::firstOrFail()->isi)->toContain('<img');
});
