<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Support\Facades\Mail;

/**
 * Mengisi riwayat kesehatan untuk rombongan.
 *
 * Satu pendaftaran bisa berisi enam orang yang riwayat kesehatannya berbeda
 * semua, dan yang mendaftar biasanya cuma satu orang. Yang diuji di sini
 * adalah hal yang membuat rombongan besar bisa jalan sendiri: tiap peserta
 * punya tautannya sendiri, dan ketua rombongan bisa melihat siapa yang
 * belum mengisi — bukan sekadar berapa yang belum.
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
        'jumlah_peserta' => 3,
        'daftar_peserta' => [
            ['nama' => 'Siti Aminah', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Surakarta'],
            ['nama' => 'Rini Lestari', 'titik_jemput' => 'Klaten'],
        ],
    ]);
});

test('halaman menyebut siapa yang belum mengisi, bukan cuma berapa', function () {
    RiwayatKesehatan::create([
        'kode_pendaftaran' => $this->pendaftaran->kode,
        'nama_peserta' => 'Siti Aminah',
        'usia' => 30,
        'jenis_kelamin' => 'Perempuan',
        'kemampuan_renang' => 'tidak_bisa',
        'kontak_darurat_nama' => 'Budi',
        'kontak_darurat_hp' => '081298765432',
        'setuju_data_kesehatan' => true,
    ]);

    $halaman = $this->get(route('riwayat-kesehatan', ['kode' => $this->pendaftaran->kode]));

    $halaman->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('Sudah diisi')
        // Nama yang belum mengisi tetap disebut namanya
        ->assertSee('Budi Santoso')
        ->assertSee('Rini Lestari')
        ->assertSee('1 dari 3');
});

test('tiap peserta punya tautannya sendiri untuk dibagikan', function () {
    $halaman = $this->get(route('riwayat-kesehatan', ['kode' => $this->pendaftaran->kode]));

    // Tautan pribadi: kode dan namanya sudah terbawa. Ampersandnya tampil
    // sebagai &amp; karena Blade meloloskannya — itu memang benar di HTML.
    $halaman->assertSee('/riwayat-kesehatan?kode='.$this->pendaftaran->kode, escape: false)
        ->assertSee('peserta=Budi%20Santoso', escape: false);

    // Dibagikan lewat WhatsApp tanpa nomor tujuan — penerimanya dipilih saat itu
    $halaman->assertSee('api.whatsapp.com/send?text=', escape: false)
        ->assertSee('Kirim tautan');
});

test('yang membuka tautannya langsung terisi namanya', function () {
    $this->get(route('riwayat-kesehatan', [
        'kode' => $this->pendaftaran->kode,
        // Ejaan seadanya dari tautan yang disalin ulang
        'peserta' => 'budi santoso',
    ]))
        ->assertOk()
        // Ejaannya disamakan dengan yang terdaftar, kalau tidak penanda
        // "sudah diisi" tidak akan pernah cocok
        ->assertSee('Budi Santoso');
});

test('peserta di luar daftar tetap bisa mengisi', function () {
    // Rombongan sering bertambah orang setelah pendaftarannya masuk
    $this->get(route('riwayat-kesehatan', [
        'kode' => $this->pendaftaran->kode,
        'peserta' => 'Joko Susilo',
    ]))
        ->assertOk()
        ->assertSee('Joko Susilo')
        ->assertSee('Kembali memilih dari daftar peserta');
});

test('penanda selesai ikut menghitung nama yang sudah masuk', function () {
    foreach (['Siti Aminah', 'Budi Santoso', 'Rini Lestari'] as $nama) {
        RiwayatKesehatan::create([
            'kode_pendaftaran' => $this->pendaftaran->kode,
            'nama_peserta' => $nama,
            'usia' => 30,
            'jenis_kelamin' => 'Perempuan',
            'kemampuan_renang' => 'tidak_bisa',
            'kontak_darurat_nama' => 'Budi',
            'kontak_darurat_hp' => '081298765432',
            'setuju_data_kesehatan' => true,
        ]);
    }

    expect($this->pendaftaran->fresh()->kesehatan_lengkap)->toBeTrue()
        ->and($this->pendaftaran->fresh()->peserta_belum_isi)->toBe([]);

    $this->get(route('riwayat-kesehatan', ['kode' => $this->pendaftaran->kode]))
        ->assertSee('Semua peserta sudah mengisi');
});
