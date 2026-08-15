<?php

use App\Models\Pembatalan;
use App\Models\PendaftaranOpenTrip;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->pendaftaran = PendaftaranOpenTrip::create([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 3,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
});

test('halaman pembatalan bisa dibuka publik', function () {
    $this->get(route('pembatalan'))
        ->assertOk()
        ->assertSee('Pengajuan Pembatalan')
        ->assertSee('Rekening Pengembalian');
});

test('pengajuan pembatalan yang sah tersimpan', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('alasan', 'kondisi_kesehatan')
        ->set('penjelasan', 'Saya sakit dan disarankan tidak bepergian jauh.')
        ->set('jumlahDibatalkan', 2)
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('atasNama', 'Budi Santoso')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $pembatalan = Pembatalan::firstOrFail();

    expect($pembatalan->kode_pendaftaran)->toBe($this->pendaftaran->kode)
        ->and($pembatalan->alasan_label)->toBe('Kondisi kesehatan')
        ->and($pembatalan->jumlah_dibatalkan)->toBe(2)
        ->and($pembatalan->status)->toBe('diajukan')
        ->and($pembatalan->status_label)->toBe('Diajukan')
        ->and($pembatalan->pendaftaran->id)->toBe($this->pendaftaran->id);
});

test('pengajuan pembatalan menolak isian tidak lengkap', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', 'OT-0000-XXXX')
        ->set('nama', 'A')
        ->call('ajukan')
        ->assertHasErrors(['kode', 'nama', 'whatsapp', 'alasan', 'bank', 'nomorRekening', 'atasNama', 'setuju']);

    expect(Pembatalan::count())->toBe(0);
});

test('kode pendaftaran menampilkan data pemesanan di formulir pembatalan', function () {
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSee('Pemesanan ditemukan')
        ->assertSee('Budi Santoso')
        ->assertSee('Open Trip Banyuwangi');
});

test('halaman kebijakan pengembalian menautkan formulir pembatalan', function () {
    $this->get(route('kebijakan-pengembalian'))
        ->assertOk()
        ->assertSee(route('pembatalan'), false);
});
