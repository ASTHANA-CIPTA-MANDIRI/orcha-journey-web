<?php

use App\Models\Testimoni;

$halaman = [
    'testimoni',
    'faq',
    'syarat-ketentuan',
    'ketentuan-pembayaran',
    'kebijakan-pengembalian',
    'kebijakan-privasi',
];

test('halaman informasi bisa dibuka publik', function (string $nama) {
    $this->get(route($nama))->assertOk();
})->with($halaman);

test('halaman informasi punya tautan kembali ke beranda', function (string $nama) {
    $this->get(route($nama))->assertOk()->assertSee('Beranda');
})->with($halaman);

test('halaman testimoni menampilkan seluruh ulasan dengan paginasi', function () {
    foreach (range(1, 12) as $i) {
        Testimoni::create([
            'customer_name' => "Pelanggan $i",
            'rating' => ($i % 5) + 1,
            'testimonial' => "Ulasan nomor $i dari pelanggan.",
        ]);
    }

    $response = $this->get(route('testimoni'));

    $response->assertOk()
        ->assertSee('Pelanggan 12')      // 9 terbaru tampil di halaman pertama
        ->assertDontSee('Pelanggan 3')   // sisanya pindah ke halaman berikutnya
        ->assertSee('12 ulasan pelanggan');
});

test('landing page memakai slogan dan menautkan halaman testimoni', function () {
    Testimoni::create([
        'customer_name' => 'Pelanggan Uji',
        'rating' => 5,
        'testimonial' => 'Mantap.',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Teman Setia Perjalanan Anda', false)
        ->assertSee(route('testimoni'), false)
        ->assertSee('Lihat Semua');
});

test('ketentuan pembayaran memakai angka dari config', function () {
    config()->set('orcha.pembayaran.dp_persen', 35);

    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('35% dari total biaya');
});

test('tabel pengembalian dana mengikuti config', function () {
    config()->set('orcha.pengembalian.tangga', [
        ['batas' => 'Contoh batas uji', 'kembali' => '77% dari DP', 'potongan' => '23% dari DP'],
    ]);

    $this->get(route('kebijakan-pengembalian'))
        ->assertOk()
        ->assertSee('Contoh batas uji')
        ->assertSee('77% dari DP');
});

test('nomor rekening tidak ditampilkan selama config kosong', function () {
    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('jangan mentransfer ke rekening apa pun sebelum menerima konfirmasi resmi', false);
});
