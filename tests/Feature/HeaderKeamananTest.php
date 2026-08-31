<?php

/**
 * Sebelum ini tidak ada satu pun header keamanan — pada situs yang
 * menampilkan formulir pembayaran dan menyimpan data medis peserta.
 */
test('halaman publik mengirim header keamanan', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('hsts tidak dikirim lewat sambungan biasa', function () {
    /*
     | Lewat http, HSTS diabaikan peramban — dan di lingkungan pengembangan
     | yang berjalan di http, memasangnya justru bisa mengunci localhost ke
     | https di peramban yang sama sampai disetel ulang manual.
     */
    $this->get(route('home'))->assertOk()->assertHeaderMissing('Strict-Transport-Security');
});

test('hsts dikirim lewat https', function () {
    $this->get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
