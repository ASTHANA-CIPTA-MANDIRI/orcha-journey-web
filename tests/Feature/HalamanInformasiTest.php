<?php

use App\Models\Etalase\Testimoni;

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
            // Bawaan basis datanya 'menunggu' — itu untuk yang dikirim
            // pelanggan lewat formulir publik, yang harus disetujui dulu.
            'status' => 'tayang',
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

test('halaman pembayaran menyebut nama penerima yang sah, tanpa nomor rekening', function () {
    $halaman = $this->get(route('ketentuan-pembayaran'))->assertOk();

    // Nama penerima adalah patokan yang bisa dicek pelanggan di mesin bank
    $halaman->assertSee(config('orcha.pembayaran.atas_nama'))
        ->assertSee('bukan kami', false);

    // Nomornya sengaja tidak dipajang supaya tidak disalin penipu
    expect(config('orcha.pembayaran.rekening'))->toBeEmpty();
});

test('nama penerima yang sah muncul di semua halaman yang menyinggung pembayaran', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee(config('orcha.pembayaran.atas_nama'));
})->with([
    fn () => route('ketentuan-pembayaran'),
    fn () => route('faq'),
    fn () => route('kebijakan-pengembalian'),
    fn () => route('pembatalan'),
    fn () => route('sewa-kendaraan.pesan'),
    fn () => route('pendaftaran-open-trip'),
]);

/* ------------------- METODE & BUKTI PEMBAYARAN ------------------- */

test('hanya transfer bank yang ditawarkan', function () {
    $halaman = $this->get(route('ketentuan-pembayaran'))->assertOk();

    $halaman->assertSee('Transfer bank')
        // QRIS dan tunai sempat tercantum padahal tidak dilayani
        ->assertDontSee('QRIS')
        ->assertDontSee('Tunai di kantor');

    expect(config('orcha.pembayaran.metode'))->toBe(['Transfer bank']);
});

test('bukti pembayaran diarahkan ke formulir, bukan percakapan', function () {
    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('formulir Konfirmasi Pembayaran')
        ->assertSee(route('konfirmasi-pembayaran'), false)
        // Bukan lagi "kirim bukti ke nomor WhatsApp"
        ->assertDontSee('Kirimkan bukti transfer ke nomor WhatsApp');

    $this->get(route('faq'))
        ->assertOk()
        ->assertSee(route('konfirmasi-pembayaran'), false);
});
