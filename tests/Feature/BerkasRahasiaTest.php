<?php

use App\Support\BerkasRahasia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Bukti transfer dan berkas jaminan sewa.
 *
 * Keduanya disimpan di disk `public` — di-symlink ke public/storage — sehingga
 * dapat diambil siapa pun yang memegang alamatnya, tanpa login. Isinya
 * tangkapan layar mutasi bank berikut nama pemilik rekening, nama bank,
 * nominal, dan sebagian nomor rekening.
 *
 * Satu-satunya pengaman adalah nama berkas berupa UUID: tidak bisa ditebak,
 * tetapi juga tidak pernah kedaluwarsa. Sekali alamatnya bocor — diteruskan di
 * grup, terbawa header referrer, tertangkap perayap — ia terbuka selamanya.
 */
test('alamat tanpa tanda tangan ditolak', function () {
    Storage::fake('rahasia');
    Storage::disk('rahasia')->put('bukti-bayar/contoh.webp', 'isi');

    $this->get('/berkas-rahasia/bukti-bayar/contoh.webp')->assertForbidden();
});

test('alamat bertanda tangan bisa dibuka', function () {
    Storage::fake('rahasia');
    Storage::disk('rahasia')->put('bukti-bayar/contoh.webp', 'isi');

    $this->get(BerkasRahasia::tautan('/storage/bukti-bayar/contoh.webp'))->assertOk();
});

test('tanda tangan yang sudah kedaluwarsa ditolak', function () {
    Storage::fake('rahasia');
    Storage::disk('rahasia')->put('bukti-bayar/contoh.webp', 'isi');

    $alamat = BerkasRahasia::tautan('/storage/bukti-bayar/contoh.webp');

    // Lewat masa berlakunya: alamat yang bocor lewat riwayat peramban atau
    // tangkapan layar tidak berguna lagi keesokan harinya.
    $this->travel(BerkasRahasia::JAM_BERLAKU + 1)->hours();

    $this->get($alamat)->assertForbidden();
});

test('tanda tangan sah tidak bisa dipakai membaca berkas di luar folder rahasia', function () {
    /*
     | Middleware 'signed' hanya menjamin tanda tangannya sah — bukan bahwa
     | jalur di dalamnya pantas dibuka. Tanpa pemeriksaan folder, satu alamat
     | sah bisa diubah jadi jalan membaca berkas mana pun di disk itu.
     */
    Storage::fake('rahasia');
    Storage::disk('rahasia')->put('rahasia-lain/kunci.txt', 'jangan dibaca');

    $alamat = URL::temporarySignedRoute(
        'berkas.rahasia',
        now()->addHour(),
        ['jalur' => 'rahasia-lain/kunci.txt'],
    );

    $this->get($alamat)->assertNotFound();
});

test('jalur yang mencoba keluar dari folder ditolak', function () {
    Storage::fake('rahasia');

    $alamat = URL::temporarySignedRoute(
        'berkas.rahasia',
        now()->addHour(),
        ['jalur' => 'bukti-bayar/../../.env'],
    );

    $this->get($alamat)->assertNotFound();
});

test('gambar biasa tidak ikut ditandatangani', function () {
    /*
     | Foto destinasi dan sampul artikel memang untuk dilihat umum.
     | Menandatanganinya hanya membuat gambar beranda kedaluwarsa tiap dua belas
     | jam — dan halaman publik tidak punya siapa pun yang bisa memperbaruinya.
     */
    expect(BerkasRahasia::tautan('/storage/destinasi/pantai.webp'))
        ->toBe('/storage/destinasi/pantai.webp');
});

test('folder rahasia dikenali, folder lain tidak', function () {
    expect(BerkasRahasia::rahasia('/storage/bukti-bayar/x.webp'))->toBeTrue()
        ->and(BerkasRahasia::rahasia('/storage/jaminan/x.webp'))->toBeTrue()
        ->and(BerkasRahasia::rahasia('/storage/galeri/x.webp'))->toBeFalse()
        ->and(BerkasRahasia::rahasia(null))->toBeFalse();
});

test('perintah amankan-berkas memindahkan lalu membuang salinan publiknya', function () {
    Storage::fake('public');
    Storage::fake('rahasia');

    Storage::disk('public')->put('bukti-bayar/lama.webp', 'bukti transfer');
    Storage::disk('public')->put('galeri/foto.webp', 'foto biasa');

    $this->artisan('orcha:amankan-berkas')->assertSuccessful();

    expect(Storage::disk('rahasia')->exists('bukti-bayar/lama.webp'))->toBeTrue()
        ->and(Storage::disk('public')->exists('bukti-bayar/lama.webp'))->toBeFalse()
        // Gambar biasa tidak ikut dipindahkan.
        ->and(Storage::disk('public')->exists('galeri/foto.webp'))->toBeTrue();
});

test('perintah amankan-berkas aman dijalankan berkali-kali', function () {
    Storage::fake('public');
    Storage::fake('rahasia');

    Storage::disk('public')->put('bukti-bayar/lama.webp', 'bukti');

    $this->artisan('orcha:amankan-berkas')->assertSuccessful();
    $this->artisan('orcha:amankan-berkas')->assertSuccessful();

    expect(Storage::disk('rahasia')->get('bukti-bayar/lama.webp'))->toBe('bukti');
});
