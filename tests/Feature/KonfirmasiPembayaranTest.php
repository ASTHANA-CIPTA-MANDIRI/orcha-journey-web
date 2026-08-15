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
        ->set('nominal', '500000')
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
        ->assertSee('Pesanan ditemukan')
        ->assertSee('Budi Santoso')
        ->assertSee('Open Trip Banyuwangi');
});

test('kode yang tidak dikenal tetap boleh dikirim, dengan peringatan', function () {
    $halaman = Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', 'OT-0000-XXXX');

    $halaman->assertSee('belum kami temukan');

    // Uang sudah terlanjur pindah — buktinya harus tetap masuk untuk dicek
    $halaman->set('nominal', '500000')
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
        ->set('nominal', '500000')
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
