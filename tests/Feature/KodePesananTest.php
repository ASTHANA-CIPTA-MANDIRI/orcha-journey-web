<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\KodePesanan;

/**
 * Kode pesanan adalah setengah dari kunci menuju data pelanggan — separuh
 * lainnya empat digit nomor WhatsApp. Kalau kodenya gampang ditebak, penjaga
 * yang tersisa cuma empat angka.
 */
test('kode acak tidak memakai huruf yang tertukar saat dibacakan', function () {
    /*
     | Kode ini dibacakan lewat telepon dan WhatsApp, dan disalin dari
     | tangkapan layar. O/0, I/1, dan S/5 adalah pasangan yang paling sering
     | tertukar — tiap satu kali tertukar berarti satu pelanggan menelepon
     | karena kodenya "tidak dikenali".
     */
    $gabung = collect(range(1, 200))->map(fn () => KodePesanan::acak())->implode('');

    expect($gabung)->not->toMatch('/[OIS015]/');
});

test('kode acak memakai seluruh abjadnya, bukan huruf besar hasil pelipatan', function () {
    /*
     | Bentuk lamanya Str::upper(Str::random(4)): 62 karakter dilipat jadi 36,
     | dan tidak merata — tiap huruf dua kali lebih sering muncul daripada tiap
     | angka. Yang sekarang mengambil langsung dari abjadnya.
     |
     | Diperiksa lewat keragaman: 200 kode × 6 huruf pasti menyentuh sebagian
     | besar dari 30 karakter yang tersedia bila pengambilannya memang merata.
     */
    $huruf = collect(range(1, 200))
        ->map(fn () => KodePesanan::acak())
        ->implode('');

    expect(count(array_unique(str_split($huruf))))->toBeGreaterThanOrEqual(25);
});

test('kode pesanan cukup panjang untuk tidak habis ditebak', function () {
    $kode = KodePesanan::untuk('OT');

    // OT-3108-K7QMXV
    expect($kode)->toMatch('/^OT-\d{4}-[A-Z0-9]{6}$/');
});

test('pendaftaran baru memakai pembangkit kode yang baru', function () {
    $daftar = PendaftaranOpenTrip::create([
        'nama' => 'Budi', 'whatsapp' => '081234567890', 'jumlah_peserta' => 1,
    ]);

    // Enam huruf, bukan empat seperti kode lama.
    expect($daftar->kode)->toMatch('/^OT-\d{4}-[A-Z0-9]{6}$/');
});
