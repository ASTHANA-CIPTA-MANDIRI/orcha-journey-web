<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\TagihanPesanan;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * Nominal di formulir bukti pembayaran diisikan sistem.
 *
 * Sebelumnya pelanggan mengetiknya dari ingatan. Salah ketik satu digit
 * membuat pembayaran tidak cocok dengan mutasi rekening, dan pekerjaan
 * mencocokkannya berakhir di WhatsApp admin.
 */
beforeEach(function () {
    Mail::fake();

    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    $this->pendaftaran = PendaftaranOpenTrip::create([
        'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name,
        'nama' => 'Siti Aminah',
        'whatsapp' => '081298765432',
        'jumlah_peserta' => 2,
    ]);
});

function catatBayar(string $kode, int $nominal, string $status = 'menunggu'): KonfirmasiPembayaran
{
    return KonfirmasiPembayaran::create([
        'kode' => $kode,
        'jenis' => 'dp',
        'nominal' => $nominal,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti Aminah',
        'status' => $status,
    ]);
}

test('belum ada pembayaran: yang ditawarkan uang mukanya', function () {
    // 2 × Rp 1.430.000 = Rp 2.860.000, DP 30% = Rp 858.000
    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['total_teks'])->toBe('Rp 2.860.000')
        ->and($tagihan['sudah'])->toBe(0)
        ->and($tagihan['jenis_disarankan'])->toBe('dp')
        ->and($tagihan['nominal_disarankan'])->toBe(858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('jenis', 'dp')
        ->assertSet('nominal', '858000')
        ->assertSet('nominalTeks', '858.000');
});

test('dp sudah masuk: yang ditawarkan sisanya', function () {
    catatBayar($this->pendaftaran->kode, 858000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('jenis', 'pelunasan')
        // 2.860.000 − 858.000
        ->assertSet('nominal', '2002000')
        ->assertSet('nominalTeks', '2.002.000');
});

test('bukti yang masih menunggu dicek tetap dihitung', function () {
    // Pelanggan yang baru mengirim bukti DP satu jam lalu memang sedang
    // menunggu; menawarkan DP yang sama untuk kedua kalinya menyesatkan.
    catatBayar($this->pendaftaran->kode, 858000, 'menunggu');

    expect(TagihanPesanan::untuk($this->pendaftaran)['jenis_disarankan'])->toBe('pelunasan');
});

test('bukti yang ditolak tidak ikut mengurangi tagihan', function () {
    catatBayar($this->pendaftaran->kode, 858000, 'ditolak');

    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['sudah'])->toBe(0)
        ->and($tagihan['jenis_disarankan'])->toBe('dp');
});

test('pembayaran sebagian tetap menawarkan sisa sebenarnya', function () {
    // Pelanggan mentransfer bulat Rp 800.000, bukan Rp 858.000
    catatBayar($this->pendaftaran->kode, 800000);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('nominal', '2060000');
});

test('angka yang sudah diketik pelanggan tidak ditimpa', function () {
    // Transfer nyata sering tidak bulat — isian yang berubah sendiri
    // setelah diketik membuat orang berhenti mempercayai formulirnya.
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->set('nominalTeks', '900000')
        ->set('jenis', 'pelunasan')
        ->assertSet('nominal', '900000');
});

test('ganti jenis pembayaran mengubah angka yang ditawarkan', function () {
    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('nominal', '858000')
        ->set('jenis', 'pelunasan')
        // Belum ada yang masuk, jadi pelunasannya sebesar seluruh tagihan
        ->assertSet('nominal', '2860000');
});

test('sudah lunas: sistem berhenti menawarkan angka', function () {
    catatBayar($this->pendaftaran->kode, 2860000, 'diterima');

    $tagihan = TagihanPesanan::untuk($this->pendaftaran);

    expect($tagihan['lunas'])->toBeTrue()
        ->and($tagihan['nominal_disarankan'])->toBe(0)
        ->and(TagihanPesanan::nominalUntukJenis($tagihan, 'pelunasan'))->toBeNull();
});

test('kode yang tidak dikenal tidak mengarang angka', function () {
    expect(TagihanPesanan::untuk(null))->toBe([]);

    Volt::test('public.open-trip.konfirmasi-pembayaran')
        ->set('kode', 'OT-9999-ZZZZ')
        ->assertSet('nominal', '')
        ->assertSet('nominalTeks', '');
});

test('paket tanpa harga tidak mengarang angka', function () {
    $this->pendaftaran->paket->update(['price' => 0]);

    expect(TagihanPesanan::untuk($this->pendaftaran->fresh()))->toBe([]);
});

test('halaman menampilkan posisi tagihannya', function () {
    catatBayar($this->pendaftaran->kode, 858000);

    $this->get(route('konfirmasi-pembayaran', ['kode' => $this->pendaftaran->kode]))
        ->assertOk()
        ->assertSee('Total tagihan')
        ->assertSee('Rp 2.860.000')
        ->assertSee('Sudah dilaporkan')
        ->assertSee('Rp 858.000')
        ->assertSee('Rp 2.002.000')
        ->assertSee('Terisi otomatis dari tagihan Anda');
});
