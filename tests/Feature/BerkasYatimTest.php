<?php

use App\Models\Etalase\DestinationPopuler;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar yang tidak lagi dirujuk data mana pun.
 *
 * Menghapus destinasi, paket, atau artikel tidak selalu ikut membuang
 * gambarnya, jadi disk tumbuh terus tanpa ada yang membersihkan.
 */
test('bawaannya melaporkan, tidak menghapus', function () {
    /*
     | Salah menghapus di sini berarti menghilangkan foto yang masih dipakai
     | halaman publik — dan foto destinasi yang hilang tidak bisa dibuat ulang
     | dari mana pun. Melaporkan dulu lalu dibaca manusia jauh lebih murah
     | daripada memulihkan yang telanjur terbuang.
     */
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/utama/yatim.webp', 'x');

    $this->artisan('orcha:berkas-yatim')->assertSuccessful();

    Storage::disk('public')->assertExists('destinasi_populer/utama/yatim.webp');
});

test('menghapus hanya bila diminta dengan tegas', function () {
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/utama/yatim.webp', 'x');

    $this->artisan('orcha:berkas-yatim', ['--hapus' => true])->assertSuccessful();

    Storage::disk('public')->assertMissing('destinasi_populer/utama/yatim.webp');
});

test('berkas yang masih dirujuk tidak pernah disentuh', function () {
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/utama/dipakai.webp', 'x');

    DestinationPopuler::create([
        'destination_name' => 'Bromo', 'wilayah' => 'jawa',
        'main_photo' => '/storage/destinasi_populer/utama/dipakai.webp',
    ]);

    $this->artisan('orcha:berkas-yatim', ['--hapus' => true])->assertSuccessful();

    Storage::disk('public')->assertExists('destinasi_populer/utama/dipakai.webp');
});

test('rujukan dicocokkan lewat nama berkas, bukan jalur utuh', function () {
    /*
     | Jalur tersimpan dalam beberapa bentuk sepanjang umur sistem ini — ada
     | yang '/storage/...', ada yang URL penuh. Membandingkan jalur utuh
     | membuat berkas yang sebenarnya dipakai terlihat yatim, dan itu persis
     | kesalahan yang paling mahal di sini.
     */
    Storage::fake('public');
    Storage::disk('public')->put('destinasi_populer/utama/dipakai.webp', 'x');

    DestinationPopuler::create([
        'destination_name' => 'Bromo', 'wilayah' => 'jawa',
        // Bentuk URL penuh, bukan jalur relatif.
        'main_photo' => 'https://orchajourney.com/storage/destinasi_populer/utama/dipakai.webp',
    ]);

    $this->artisan('orcha:berkas-yatim', ['--hapus' => true])->assertSuccessful();

    Storage::disk('public')->assertExists('destinasi_populer/utama/dipakai.webp');
});

test('bukti transfer dan jaminan tidak pernah ikut diperiksa', function () {
    /*
     | Keduanya bukti milik pelanggan, dan rujukannya tersebar di beberapa
     | tabel sekaligus riwayat pembatalan. Salah menghapus di sana berarti
     | menghilangkan bukti transfer orang yang sudah membayar — sesuatu yang
     | tidak bisa diminta ulang berbulan-bulan kemudian.
     */
    Storage::fake('public');
    Storage::disk('public')->put('bukti-bayar/penting.webp', 'x');
    Storage::disk('public')->put('jaminan/penting.webp', 'x');

    $this->artisan('orcha:berkas-yatim', ['--hapus' => true])->assertSuccessful();

    Storage::disk('public')->assertExists('bukti-bayar/penting.webp');
    Storage::disk('public')->assertExists('jaminan/penting.webp');
});
