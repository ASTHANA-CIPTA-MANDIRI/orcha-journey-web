<?php

/**
 * Halaman publik tidak boleh lagi menyuruh pelanggan mengirim bukti bayar.
 *
 * Pembayaran publik seluruhnya lewat gerbang. Unggah bukti transfer masih ada,
 * tetapi HANYA di sisi admin — untuk pelanggan yang kesulitan membayar daring,
 * biasanya rombongan private trip atau study tour, yang pembayarannya diurus
 * lewat percakapan lalu dicatatkan admin sendiri.
 *
 * Kalimat yang tertinggal dari jalur lama tidak merusak apa pun secara teknis,
 * dan justru itu bahayanya: tidak ada yang gagal, tidak ada yang merah, dan
 * satu-satunya yang menemukannya adalah pelanggan yang menuruti perintah lalu
 * mencari halaman unggah yang sudah tidak ada.
 *
 * Berkas ini menyisir halamannya seperti pengunjung — lewat HTTP, bukan lewat
 * pembacaan berkas — supaya yang diperiksa memang yang sampai ke layar.
 */
beforeEach(function () {
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');
});

dataset('halaman_publik', [
    '/',
    '/tentang-kami',
    '/faq',
    '/ketentuan-pembayaran',
    '/syarat-ketentuan',
    '/kebijakan-privasi',
    '/lacak-pesanan',
    '/konfirmasi-pembayaran',
]);

test('halaman publik tidak menyuruh mengirim bukti bayar', function (string $jalur) {
    $halaman = $this->get($jalur)->assertOk();

    foreach ([
        'Kirim Bukti Transfer',
        'Kirim Bukti Pembayaran',
        'unggah buktinya',
        'Unggah bukti',
        'Sudah transfer?',
        'formulir Konfirmasi Pembayaran',
    ] as $ajakan) {
        $halaman->assertDontSee($ajakan);
    }
})->with('halaman_publik');

test('gerbang mati mengembalikan ajakan bukti transfer', function () {
    /*
     | Bukan pengecualian yang lupa dibersihkan, melainkan jaring pengaman.
     |
     | Saat gerbangnya tidak bisa dihubungi, halaman pembayaran kembali
     | menawarkan jalur bukti — dan ajakan di halaman lain harus ikut berubah,
     | bukan tetap menjanjikan tombol bayar yang tidak berfungsi.
     */
    config()->set('doku.aktif', false);

    $this->get('/konfirmasi-pembayaran')
        ->assertOk()
        ->assertSee('Pembayaran online sedang tidak tersedia');
});

test('kalimat yang menyebut transfer sebagai CARA BAYAR tetap boleh', function () {
    /*
     | Transfer bank tetap salah satu cara membayar di halaman gerbang, dan
     | rekening tujuan pengembalian dana memang harus ditanyakan. Uji di atas
     | sengaja melarang AJAKAN mengirim bukti, bukan kata "transfer" — larangan
     | yang terlalu lebar akan memaksa orang berikutnya menulis kalimat yang
     | berputar-putar demi lolos.
     */
    $this->get('/ketentuan-pembayaran')->assertOk()->assertSee('QRIS');
    $this->get('/pembatalan')->assertOk()->assertSee('Nomor rekening');
});
