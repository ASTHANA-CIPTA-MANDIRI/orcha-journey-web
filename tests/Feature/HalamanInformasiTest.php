<?php

use App\Models\Etalase\Testimoni;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\LepaskanKursiTertahan;

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

    /*
     | Nama penerima tetap disebut meski pembayarannya lewat gerbang.
     |
     | Ia patokan yang bisa dicek pelanggan sendiri, dan justru itu yang
     | dipakai membedakan kami dari penipu yang mengirim nomor rekening
     | pribadi mengatasnamakan kami.
     */
    $halaman->assertSee(config('orcha.pembayaran.atas_nama'))
        ->assertSee('rekening pribadi atas nama perorangan');

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

test('metode yang disebut adalah yang benar-benar dilayani', function () {
    /*
     | QRIS dan tunai dulu dicabut dari halaman ini karena tercantum padahal
     | tidak dilayani — cara bayar yang dijanjikan di situs lalu ditolak saat
     | pemesanan bikin pelanggan ragu.
     |
     | QRIS kembali disebut sekarang bukan karena aturannya melonggar,
     | melainkan karena gerbang pembayaran memang melayaninya. Yang tetap
     | dijaga: tunai tidak pernah dijanjikan, dan channel-nya tidak didaftar
     | satu per satu — daftar yang basi diam-diam adalah janji yang sama.
     */
    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('Virtual Account')
        ->assertSee('QRIS')
        ->assertDontSee('Tunai di kantor');
});

test('gerbang mati mengembalikan keterangan transfer bank', function () {
    config()->set('doku.aktif', false);

    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('Transfer bank')
        ->assertDontSee('QRIS');

    expect(config('orcha.pembayaran.metode'))->toBe(['Transfer bank']);
});

test('pembayaran diarahkan ke halaman bayar, bukan percakapan', function () {
    /*
     | Yang berubah tujuannya, bukan alasannya. Dulu yang dijauhkan adalah
     | bukti transfer yang dikirim lewat WhatsApp lalu tenggelam di antara
     | pesan lain. Sekarang tidak ada bukti untuk dikirim sama sekali — tetapi
     | halaman ini tetap tidak boleh menyuruh orang mengurus pembayarannya
     | lewat percakapan.
     */
    $this->get(route('ketentuan-pembayaran'))
        ->assertOk()
        ->assertSee('halaman pembayaran')
        ->assertSee(route('konfirmasi-pembayaran'), false)
        ->assertDontSee('Kirimkan bukti transfer ke nomor WhatsApp')
        // Tidak lagi menjanjikan nomor rekening yang dikirim admin
        ->assertDontSee('Nomor rekeningnya dikirim');

    $this->get(route('faq'))
        ->assertOk()
        ->assertSee(route('konfirmasi-pembayaran'), false);
});

test('ketentuan pembayaran menyebut pembatalan otomatis apa adanya', function () {
    /*
     | Kalimat lamanya cuma menulis "kursinya dilepas kembali untuk pemesan
     | lain". Yang benar-benar terjadi lebih dari itu: LepaskanKursiTertahan
     | mengubah status pemesanan menjadi 'batal'.
     |
     | Pelanggan yang kemudian membuka Lacak Pesanan membaca "Batal" — kata
     | yang halaman ketentuan tidak pernah mempersiapkannya. Selisih sekecil
     | itu berakhir sebagai pesan WhatsApp yang menanyakan apakah uangnya
     | hangus.
     */
    $isi = $this->get(route('ketentuan-pembayaran'))->assertOk()->getContent();

    expect($isi)->toContain('menjadi Batal')
        // Penjagaannya ikut disebut, dan itu bagian yang paling menenangkan:
        // yang sudah membayar tidak pernah ikut dilepas.
        ->toContain('tidak pernah ikut dilepas');
});

test('yang sudah mengirim bukti tidak pernah ikut dilepas', function () {
    /*
     | Janji di halaman ketentuan dijaga oleh perilaku, bukan hanya oleh
     | kalimat. Kalau penjagaan di LepaskanKursiTertahan hilang, halaman itu
     | berubah jadi janji yang tidak ditepati kepada orang yang justru sedang
     | berusaha membayar.
     */
    $lama = PendaftaranOpenTrip::create([
        'nama' => 'Budi', 'whatsapp' => '081234567890', 'jumlah_peserta' => 1,
        'nama_paket' => 'Open Trip Bromo', 'harga_jual' => 500000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(), 'status' => 'baru',
    ]);
    $lama->forceFill(['created_at' => now()->subDays(5)])->save();

    KonfirmasiPembayaran::create([
        'kode' => $lama->kode, 'jenis' => 'dp', 'nominal' => 150000,
        'tanggal_transfer' => now()->subDays(4)->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'ditolak',
    ]);

    LepaskanKursiTertahan::jalankan(false);

    // Buktinya DITOLAK pun tidak membuatnya dilepas: jawabannya memperbaiki
    // bukti itu, bukan kehilangan kursinya diam-diam.
    expect($lama->fresh()->status)->toBe('baru');
});
