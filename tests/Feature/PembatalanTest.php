<?php

use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
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

test('sewa kendaraan juga bisa diajukan pembatalannya', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => 'Avanza Uji', 'nama' => 'Rina Wijaya',
        'whatsapp' => '081298765432', 'email' => 'rina@contoh.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 2, 'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'jam_mulai' => '08:00', 'estimasi_biaya' => 1000000, 'status' => 'dp_masuk',
    ]);

    // Dulu kode SK- selalu ditolak "tidak ditemukan" karena hanya tabel
    // pendaftaran open trip yang diperiksa — padahal kodenya benar.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $sewa->kode)
        ->assertSee('Pesanan sewa ditemukan')
        ->assertSee('Avanza Uji')
        // Satu unit tidak bisa dibatalkan sebagian, jadi isiannya tidak ada
        ->assertDontSee('Jumlah peserta yang dibatalkan')
        ->set('alasan', 'kondisi_kesehatan')
        ->set('bank', 'BCA')
        ->set('nomorRekening', '1234567890')
        ->set('setuju', true)
        ->call('ajukan')
        ->assertHasNoErrors()
        ->assertSet('terkirim', true);

    $pembatalan = Pembatalan::firstOrFail();

    expect($pembatalan->kode_pendaftaran)->toBe($sewa->kode)
        ->and($pembatalan->pesanan()->id)->toBe($sewa->id)
        ->and($pembatalan->jumlah_dibatalkan)->toBe(1);
});

test('isian pemohon terisi sendiri dari pesanan yang ditemukan', function () {
    // Orang yang sedang membatalkan biasanya terburu-buru; mengetik ulang
    // nama dan nomor yang sudah ada di pesanannya hanya menambah salah ketik,
    // dan nomor yang salah berarti perhitungan pengembalian tidak sampai.
    Volt::test('public.open-trip.pembatalan')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('nama', 'Budi Santoso')
        ->assertSet('whatsapp', '0812-3456-7890')
        ->assertSet('atasNama', 'Budi Santoso')
        // Jumlah peserta ikut terisi sesuai pesanannya, bukan selalu 1
        ->assertSet('jumlahDibatalkan', 3);
});

test('kode dari tautan email langsung mengisi data pemohon', function () {
    // Orang yang datang lewat tautan justru yang paling tidak perlu mengetik
    // apa pun; kodenya sudah dibawa tautannya.
    // Diperiksa lewat permintaan sungguhan, karena kodenya datang dari query
    // string — bukan dari parameter mount.
    //
    // Yang dilihat bukan tulisan di layar (nama pemesan juga muncul di kartu
    // ringkasan, jadi tidak membuktikan apa-apa), melainkan keadaan awal
    // komponen yang tertanam di halaman: isian atas nama rekening hanya
    // terisi bila pengisian otomatisnya benar-benar jalan.
    $this->get(route('pembatalan', ['kode' => $this->pendaftaran->kode]))
        ->assertOk()
        ->assertSee('Pemesanan ditemukan')
        ->assertSee('&quot;atasNama&quot;:&quot;Budi Santoso&quot;', false)
        ->assertSee('&quot;jumlahDibatalkan&quot;:3', false);
});

test('isian yang sudah diketik pemohon tidak ditimpa isian otomatis', function () {
    // Rekening pengembalian kadang atas nama orang lain, dan nomornya bisa
    // sudah berganti. Yang sudah diketik manusia menang.
    Volt::test('public.open-trip.pembatalan')
        ->set('nama', 'Siti Aminah')
        ->set('atasNama', 'Siti Aminah')
        ->set('kode', $this->pendaftaran->kode)
        ->assertSet('nama', 'Siti Aminah')
        ->assertSet('atasNama', 'Siti Aminah');
});

test('halaman kebijakan pengembalian menautkan formulir pembatalan', function () {
    $this->get(route('kebijakan-pengembalian'))
        ->assertOk()
        ->assertSee(route('pembatalan'), false);
});
