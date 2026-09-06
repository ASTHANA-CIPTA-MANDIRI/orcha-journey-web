<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Livewire\Volt\Volt;

/**
 * Halaman pembayaran pelanggan.
 *
 * Berkas ini dulu menguji formulir unggah bukti transfer. Formulir itu SUDAH
 * DICABUT dari sisi publik sejak pembayaran pindah ke gerbang DOKU — unggah
 * bukti kini hanya ada di sisi admin, dan uji jalur itu berpindah ke
 * PembayaranManualTest serta PembayaranDokuTest.
 *
 * Yang tersisa di sini adalah bagian yang tidak ikut berubah, dan justru itu
 * yang paling perlu dijaga: DUA KUNCI yang membuka sebuah pesanan. Kode saja
 * tidak pernah cukup, karena kotak yang terbuka menyebut nama pemesan, trip
 * yang diikutinya, dan sisa utangnya.
 */
beforeEach(function () {
    config()->set('doku.aktif', true);
    config()->set('doku.client_id', 'BRN-0227-UJI');
    config()->set('doku.secret_key', 'SK-UJI-RAHASIA');

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'daftar_peserta' => ['Budi Santoso', 'Sari Dewi'],
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
});

test('halaman pembayaran bisa dibuka publik', function () {
    $this->get(route('konfirmasi-pembayaran'))
        ->assertOk()
        ->assertSee('Bayar Pesanan')
        // Patokan anti-penipuan ikut tampil di halaman ini
        ->assertSee(config('orcha.pembayaran.atas_nama'));
});

test('kode pesanan menampilkan ringkasan pesanannya', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        // Kunci kedua: empat digit terakhir nomor pemesan.
        ->set('empatDigit', '7890')
        ->assertSee('Pesanan ditemukan')
        ->assertSee('Budi Santoso')
        ->assertSee('Open Trip Banyuwangi');
});

test('kode yang benar dengan nomor yang salah tidak membuka ringkasan', function () {
    /*
     | Halaman ini menampilkan nama pemesan, nama trip, DAN mengisikan nominal
     | tagihannya begitu kodenya dikenali. Tanpa kunci kedua, kode yang ditebak
     | dengan beruntung memberi tahu siapa orangnya, ia ikut trip apa, dan
     | berapa sisa utangnya.
     */
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '0000')
        ->assertDontSee('Pesanan ditemukan')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('Open Trip Banyuwangi')
        // Nominal tagihannya pun tidak ikut terisi.
        ->assertSet('nominal', '');
});

test('pencarian kode yang gagal berulang kali ikut dibatasi', function () {
    /*
     | Pembatas yang sudah ada hanya menjaga PENGIRIMAN formulir. Kode diketik
     | dengan wire:model.live, dan tiap ketikan memanggil pencarian tanpa
     | pernah menyentuh pembatas mana pun — justru jalur itulah yang dipakai
     | untuk menebak kode satu per satu.
     */
    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran');

    for ($i = 0; $i < 16; $i++) {
        $halaman->set('kode', 'OT-1508-XX'.str_pad((string) $i, 2, '0', STR_PAD_LEFT))
            ->set('empatDigit', '0000');
    }

    // Batas tercapai: kode yang BENAR pun tidak lagi dibuka, sampai jamnya
    // berganti. Yang dilindungi bukan satu pesanan, melainkan seluruh daftar.
    $halaman->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->assertDontSee('Budi Santoso');
});

test('kode diseragamkan jadi huruf besar', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', strtolower($this->pendaftaran->kode))
        ->assertSet('kode', $this->pendaftaran->kode);
});

test('kode yang tidak dikenal tidak membuka apa pun', function () {
    /*
     | Dulu kode asing tetap boleh dipakai mengirim bukti: uangnya mungkin
     | sudah terlanjur pindah, dan menolaknya mentah lebih buruk daripada
     | menerimanya untuk diperiksa.
     |
     | Perhitungan itu berubah sejak pembayaran lewat gerbang. Di sini tidak
     | ada uang yang bisa terlanjur pindah tanpa kode yang benar — halaman
     | bayarnya dibuat DARI pesanannya. Yang tersisa hanyalah pesan yang
     | menuntun orang memeriksa ketikannya.
     */
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', 'OT-0000-XXXX')
        ->set('empatDigit', '7890')
        ->assertSee('belum kami temukan')
        ->assertDontSee('Pilih Pembayaran');
});
