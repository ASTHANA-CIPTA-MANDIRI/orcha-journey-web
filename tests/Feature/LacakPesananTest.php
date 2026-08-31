<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use Livewire\Volt\Volt;

/**
 * "Pesanan saya sekarang bagaimana?"
 *
 * Pelanggan hanya menerima surat dua kali — saat mengirim formulir dan saat
 * pembayarannya diperiksa. Di antara keduanya ia buta, sehingga tiap
 * pertanyaan itu berubah jadi percakapan WhatsApp yang harus dijawab manusia
 * satu per satu, padahal jawabannya sudah tersimpan seluruhnya.
 */
beforeEach(function () {
    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
        'status' => 'dp_masuk',
    ]);
});

test('halaman lacak pesanan bisa dibuka publik', function () {
    $this->get(route('lacak-pesanan'))
        ->assertOk()
        ->assertSee('Lacak Pesanan')
        ->assertSee('Kode pesanan');
});

test('kode dan nomor yang cocok menampilkan status pesanan', function () {
    Volt::test('public.open-trip.lacak-pesanan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->call('lihat')
        ->assertSee('DP Masuk')
        ->assertSee('Budi Santoso')
        ->assertSee('Open Trip Banyuwangi')
        // Statusnya diterjemahkan jadi kalimat, bukan cuma lencana. "DP Masuk"
        // tidak memberi tahu apa yang harus dikerjakan berikutnya.
        ->assertSee('kursi Anda terkunci');
});

test('halaman lacak menampilkan bukti yang sudah dikirim beserta statusnya', function () {
    KonfirmasiPembayaran::create([
        'kode' => $this->pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 500000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'ditolak',
        'catatan_admin' => 'Nominalnya kurang Rp 50.000 dari uang muka.',
    ]);

    /*
     | Alasan penolakan ikut ditampilkan. Tanpa itu pelanggan cuma tahu
     | "ditolak" tanpa tahu harus berbuat apa — dan satu-satunya jalan
     | mengetahuinya adalah bertanya lewat WhatsApp, yang berarti alasan yang
     | sudah ditulis admin harus ditulis ulang oleh manusia lain.
     */
    Volt::test('public.open-trip.lacak-pesanan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->call('lihat')
        ->assertSee('Rp 500.000')
        ->assertSee('Nominalnya kurang Rp 50.000 dari uang muka.');
});

test('halaman lacak menyebut peserta yang belum mengisi formulir kesehatan', function () {
    // Kolomnya daftar_peserta; `peserta` adalah aksesor yang membacanya.
    $this->pendaftaran->update([
        'daftar_peserta' => [['nama' => 'Budi Santoso'], ['nama' => 'Siti Aminah']],
    ]);

    /*
     | Namanya disebut satu per satu, bukan cuma "1 dari 2 sudah". Angka saja
     | memaksa ketua rombongan menanyai semua anggotanya lagi untuk tahu siapa
     | yang belum.
     */
    Volt::test('public.open-trip.lacak-pesanan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '7890')
        ->call('lihat')
        ->assertSee('Siti Aminah')
        ->assertSee('Belum mengisi');
});

test('nomor yang salah tidak membuka apa pun', function () {
    /*
     | Halaman ini menampilkan lebih banyak daripada halaman mana pun — status,
     | tagihan, riwayat pembayaran, nama peserta — jadi justru di sini
     | penjagaannya paling penting.
     */
    Volt::test('public.open-trip.lacak-pesanan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('empatDigit', '0000')
        ->call('lihat')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('Open Trip Banyuwangi')
        ->assertSee('tidak cocok');
});

test('tautan berparameter kode saja tidak membuka pesanan', function () {
    // Tautan ikut tersalin ke mana-mana; yang memegangnya belum tentu
    // pemesannya.
    $this->get(route('lacak-pesanan', ['kode' => $this->pendaftaran->kode]))
        ->assertOk()
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('DP Masuk');
});
