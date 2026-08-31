<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('public');

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'daftar_peserta' => ['Budi Santoso', 'Sari Dewi'],
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
});

test('halaman konfirmasi pembayaran bisa dibuka publik', function () {
    $this->get(route('konfirmasi-pembayaran'))
        ->assertOk()
        ->assertSee('Konfirmasi Pembayaran')
        // Patokan anti-penipuan ikut tampil di halaman ini
        ->assertSee(config('orcha.pembayaran.atas_nama'));
});

test('bukti transfer tersimpan sebagai webp', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('jenis', 'dp')
        ->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Budi Santoso')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg', 800, 1200))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $bayar = KonfirmasiPembayaran::firstOrFail();

    expect($bayar->kode)->toBe($this->pendaftaran->kode)
        ->and($bayar->nominal)->toBe(500000)
        ->and($bayar->status)->toBe('menunggu')
        ->and($bayar->jenis_label)->toBe('Uang Muka (DP)')
        ->and($bayar->nominal_formatted)->toBe('Rp 500.000')
        ->and($bayar->bukti)->toEndWith('.webp');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $bayar->bukti));
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

test('kode yang tidak dikenal tetap boleh dikirim, dengan peringatan', function () {
    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', 'OT-0000-XXXX');

    $halaman->assertSee('belum kami temukan');

    // Uang sudah terlanjur pindah — buktinya harus tetap masuk untuk dicek
    $halaman->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Budi Santoso')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasNoErrors();

    expect(KonfirmasiPembayaran::count())->toBe(1);
});

test('isian yang tidak lengkap ditolak', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', '')
        ->set('tanggalTransfer', now()->addWeek()->toDateString())
        ->call('kirim')
        ->assertHasErrors(['kode', 'nominal', 'tanggalTransfer', 'bankPengirim', 'atasNamaPengirim', 'bukti', 'setuju']);

    expect(KonfirmasiPembayaran::count())->toBe(0);
});

test('tanpa bukti transfer tidak bisa dikirim', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Budi Santoso')
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasErrors(['bukti']);

    expect(KonfirmasiPembayaran::count())->toBe(0);
});

test('kode diseragamkan jadi huruf besar', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', strtolower($this->pendaftaran->kode))
        ->assertSet('kode', $this->pendaftaran->kode);
});

test('nominal transfer tampil bertitik dan tersimpan sebagai angka', function () {
    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('nominalTeks', '500000');

    expect($halaman->get('nominalTeks'))->toBe('500.000')
        ->and($halaman->get('nominal'))->toBe('500000');

    // Ketikan berantakan tetap terbaca
    $halaman->set('nominalTeks', 'Rp 1.430.000,-');
    expect($halaman->get('nominalTeks'))->toBe('1.430.000')
        ->and($halaman->get('nominal'))->toBe('1430000');

    // Dikosongkan: kembali kosong, bukan "0" yang harus dihapus dulu
    $halaman->set('nominalTeks', '');
    expect($halaman->get('nominalTeks'))->toBe('')
        ->and($halaman->get('nominal'))->toBe('');

    $halaman->set('kode', $this->pendaftaran->kode)
        ->set('nominalTeks', '500000')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Budi Santoso')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasNoErrors();

    // Titik pemisah tidak pernah ikut tersimpan
    expect(KonfirmasiPembayaran::firstOrFail()->nominal)->toBe(500000);
});

test('nominal kosong ditolak validasi', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('nominalTeks', '')
        ->set('tanggalTransfer', now()->toDateString())
        ->set('bankPengirim', 'BCA')
        ->set('atasNamaPengirim', 'Budi Santoso')
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->set('setuju', true)
        ->call('kirim')
        ->assertHasErrors(['nominal']);
});
