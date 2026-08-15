<?php

use App\Models\Car;
use App\Models\Pembatalan;
use App\Models\PendaftaranOpenTrip;
use App\Models\PenyewaanKendaraan;
use App\Models\PesanKontak;
use App\Models\RiwayatKesehatan;
use App\Models\TravelPackage;

const KUNCI_UJI = 'kunci-rahasia-untuk-uji';

beforeEach(function () {
    config()->set('orcha.api.kunci', KUNCI_UJI);
    config()->set('orcha.api.ip_diizinkan', []);
});

function kirim(array $tambahan = []): array
{
    return array_merge([
        'X-Orcha-Key' => KUNCI_UJI,
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ], $tambahan);
}

function buatPendaftaran(array $ubah = []): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create(array_merge([
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'jumlah_peserta' => 2,
        'nama_paket' => 'Open Trip Banyuwangi',
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ], $ubah));
}

/* ----------------------------- PENJAGAAN ----------------------------- */

test('tanpa kunci ditolak', function () {
    $this->getJson('/api/v1/dashboard')->assertStatus(401);
});

test('kunci salah ditolak', function () {
    $this->getJson('/api/v1/dashboard', ['X-Orcha-Key' => 'salah'])->assertStatus(401);
});

test('kunci benar diterima', function () {
    $this->getJson('/api/v1/ping', kirim())
        ->assertOk()
        ->assertJsonPath('data.aplikasi', 'Orcha Journey')
        ->assertJsonPath('data.admin_pemanggil', 'admin@phoenix.test');
});

test('kunci belum disiapkan menolak semua permintaan', function () {
    config()->set('orcha.api.kunci', null);

    $this->getJson('/api/v1/dashboard', kirim())->assertStatus(503);
});

test('ip di luar daftar ditolak walau kuncinya benar', function () {
    config()->set('orcha.api.ip_diizinkan', ['203.0.113.7']);

    $this->getJson('/api/v1/dashboard', kirim())->assertStatus(403);
});

test('kunci juga bisa dikirim sebagai bearer token', function () {
    $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.KUNCI_UJI])->assertOk();
});

/* ----------------------------- DASHBOARD ----------------------------- */

test('dashboard mengirim kartu, rincian, dan yang perlu ditindak', function () {
    buatPendaftaran();
    PesanKontak::create([
        'nama' => 'Sari',
        'whatsapp' => '08123456789',
        'keperluan' => 'open_trip',
        'pesan' => 'Masih ada slot?',
    ]);

    $balasan = $this->getJson('/api/v1/dashboard', kirim())->assertOk();

    $balasan->assertJsonPath('data.perlu_ditindak.pendaftaran_baru', 1)
        ->assertJsonPath('data.perlu_ditindak.pesan_belum_dibaca', 1)
        ->assertJsonStructure([
            'data' => [
                'kartu' => [['kunci', 'label', 'nilai', 'ikon', 'tautan']],
                'paket_per_kategori',
                'kendaraan_per_jenis',
                'pendaftaran_terbaru',
                'penyewaan_terbaru',
                'perlu_ditindak',
            ],
            'meta' => ['diperbarui_pada'],
        ]);
});

test('menu dan rujukan bisa dipakai menggambar sidebar dan dropdown', function () {
    $this->getJson('/api/v1/menu', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.jalur', 'dashboard');

    $this->getJson('/api/v1/rujukan', kirim())
        ->assertOk()
        ->assertJsonPath('data.status_penyewaan.baru', 'Baru')
        ->assertJsonPath('data.pembayaran.pelunasan_hari_sebelum', 5);
});

/* ---------------------------- PENDAFTARAN ---------------------------- */

test('daftar pendaftaran bisa dicari, disaring, dan berhalaman', function () {
    buatPendaftaran(['nama' => 'Budi Santoso']);
    buatPendaftaran(['nama' => 'Citra Dewi', 'status' => 'lunas']);

    $this->getJson('/api/v1/pendaftaran', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['kode', 'nama', 'status_label']], 'meta' => ['halaman', 'total']]);

    $this->getJson('/api/v1/pendaftaran?cari=Citra', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nama', 'Citra Dewi');

    $this->getJson('/api/v1/pendaftaran?status=lunas', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('status pendaftaran bisa diubah lewat api', function () {
    $pendaftaran = buatPendaftaran();

    $this->patchJson("/api/v1/pendaftaran/{$pendaftaran->id}/status", ['status' => 'dp_masuk'], kirim())
        ->assertOk()
        ->assertJsonPath('data.status', 'dp_masuk')
        ->assertJsonPath('data.status_label', 'DP Masuk');

    expect($pendaftaran->fresh()->status)->toBe('dp_masuk');
});

test('status pendaftaran di luar daftar ditolak', function () {
    $pendaftaran = buatPendaftaran();

    $this->patchJson("/api/v1/pendaftaran/{$pendaftaran->id}/status", ['status' => 'ngawur'], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($pendaftaran->fresh()->status)->toBe('baru');
});

test('riwayat kesehatan hanya keluar lewat jalur khususnya', function () {
    $pendaftaran = buatPendaftaran();

    RiwayatKesehatan::create([
        'kode_pendaftaran' => $pendaftaran->kode,
        'nama_peserta' => 'Budi Santoso',
        'usia' => 28,
        'jenis_kelamin' => 'Laki-laki',
        'riwayat_penyakit' => 'Asma ringan',
        'kontak_darurat_nama' => 'Sari',
        'kontak_darurat_hp' => '08987654321',
        'kontak_darurat_hubungan' => 'Istri',
        'setuju_data_kesehatan' => true,
    ]);

    // Tidak ikut terbawa daftar biasa
    $this->getJson('/api/v1/pendaftaran', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.jumlah_riwayat_kesehatan', 1)
        ->assertDontSee('Asma ringan');

    // Baru keluar kalau diminta khusus
    $this->getJson("/api/v1/pendaftaran/{$pendaftaran->id}/riwayat-kesehatan", kirim())
        ->assertOk()
        ->assertJsonPath('data.0.riwayat_penyakit', 'Asma ringan')
        ->assertJsonPath('data.0.kontak_darurat.hp', '08987654321');
});

/* ----------------------------- PENYEWAAN ----------------------------- */

test('penyewaan bisa dilihat dan diubah statusnya', function () {
    $mobil = Car::create([
        'name' => 'All New Avanza',
        'brand' => 'Toyota',
        'type' => 'mobil',
        'price_per_day' => 350000,
        'harga_per_jam' => 55000,
        'harga_12_jam' => 280000,
        'harga_sopir' => 150000,
        'transmission' => 'Manual',
        'transmisi_tersedia' => ['Manual', 'Matic'],
        'capacity' => 7,
        'is_available' => true,
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id,
        'nama_kendaraan' => $mobil->name,
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'transmisi' => 'Matic',
        'satuan' => 'jam',
        'durasi' => 6,
        'tanggal_mulai' => now()->addWeek()->toDateString(),
        'jam_mulai' => '07:00',
        'dengan_sopir' => true,
        'estimasi_biaya' => 480000,
    ]);

    $this->getJson('/api/v1/penyewaan', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.kode', $sewa->kode)
        ->assertJsonPath('data.0.durasi_label', '6 jam')
        ->assertJsonPath('data.0.estimasi_biaya', 480000);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/status", ['status' => 'dikonfirmasi'], kirim())
        ->assertOk()
        ->assertJsonPath('data.status', 'dikonfirmasi');

    expect($sewa->fresh()->status)->toBe('dikonfirmasi');
});

/* ----------------------------- PEMBATALAN ---------------------------- */

test('pembatalan bisa dilihat lengkap dengan catatan admin', function () {
    $pendaftaran = buatPendaftaran();

    $pembatalan = Pembatalan::create([
        'kode_pendaftaran' => $pendaftaran->kode,
        'nama_pemohon' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'alasan' => 'kendala_biaya',
        'jumlah_dibatalkan' => 1,
        'bank' => 'BCA',
        'nomor_rekening' => '1234567890',
        'atas_nama_rekening' => 'Budi Santoso',
    ]);

    $this->getJson("/api/v1/pembatalan/{$pembatalan->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.rekening.bank', 'BCA')
        ->assertJsonPath('data.pendaftaran.nama_paket', 'Open Trip Banyuwangi');

    $this->patchJson("/api/v1/pembatalan/{$pembatalan->id}/status", [
        'status' => 'dana_dikirim',
        'catatan_admin' => 'Potongan 50%, sudah ditransfer.',
    ], kirim())
        ->assertOk()
        ->assertJsonPath('data.status_label', 'Dana Dikirim');

    expect($pembatalan->fresh()->catatan_admin)->toBe('Potongan 50%, sudah ditransfer.');
});

/* ------------------------------- PESAN ------------------------------- */

test('pesan bisa disaring yang belum dibaca lalu ditandai', function () {
    $pesan = PesanKontak::create([
        'nama' => 'Sari',
        'whatsapp' => '08123456789',
        'keperluan' => 'sewa_kendaraan',
        'pesan' => 'HiAce untuk 12 orang ada?',
    ]);

    $this->getJson('/api/v1/pesan?belum_dibaca=1', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.keperluan_label', 'Sewa Kendaraan');

    $this->patchJson("/api/v1/pesan/{$pesan->id}/dibaca", [], kirim())
        ->assertOk()
        ->assertJsonPath('data.sudah_dibaca', true);

    $this->getJson('/api/v1/pesan?belum_dibaca=1', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

/* ------------------------------ ETALASE ------------------------------ */

test('paket wisata dan armada bisa dibaca beserta tarif bertingkat', function () {
    TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'duration' => '3 Hari 2 Malam',
        'price' => 1430000,
        'minimal_peserta' => 6,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    Car::create([
        'name' => 'All New Avanza',
        'brand' => 'Toyota',
        'type' => 'mobil',
        'price_per_day' => 350000,
        'harga_per_jam' => 55000,
        'harga_12_jam' => 280000,
        'harga_sopir' => 150000,
        'transmission' => 'Manual',
        'transmisi_tersedia' => ['Manual', 'Matic'],
        'capacity' => 7,
        'is_available' => true,
    ]);

    $this->getJson('/api/v1/paket-wisata', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.nama', 'Open Trip Banyuwangi')
        ->assertJsonPath('data.0.minimal_peserta', 6)
        ->assertJsonPath('data.0.jumlah_pendaftar', 0);

    $this->getJson('/api/v1/kendaraan', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.transmisi_label', 'Manual & Matic')
        ->assertJsonPath('data.0.tarif.jam', 55000)
        ->assertJsonPath('data.0.tarif.hari', 350000);
});

test('jumlah baris per halaman dibatasi', function () {
    foreach (range(1, 5) as $urutan) {
        buatPendaftaran(['nama' => "Peserta {$urutan}"]);
    }

    $this->getJson('/api/v1/pendaftaran?per_halaman=2', kirim())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.halaman_terakhir', 3);

    // Permintaan berlebihan dipangkas ke batas atas, tidak menarik seluruh tabel
    $this->getJson('/api/v1/pendaftaran?per_halaman=9999', kirim())
        ->assertOk()
        ->assertJsonPath('meta.per_halaman', config('orcha.api.per_halaman_maks'));
});
