<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Models\PaketWisata\SaranPaket;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

/* ------------------------ ETALASE: MENULIS ------------------------ */

test('paket wisata bisa ditambah lewat api', function () {
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Karimunjawa',
        'kategori' => 'open_trip',
        'durasi' => '3 Hari 2 Malam',
        'minimal_peserta' => 6,
        'harga' => 1250000,
        'harga_asli' => 1500000,
        'diskon_persen' => 17,
        'destinasi' => ['Pantai Ujung Gelam', 'Snorkeling Menjangan Kecil'],
        'fasilitas' => ['Kapal', 'Homestay'],
        'itinerary_teks' => "Day 1\n07.00 | Berangkat dari Jepara",
    ], kirim())->assertCreated();

    $balasan->assertJsonPath('data.nama', 'Open Trip Karimunjawa')
        ->assertJsonPath('data.minimal_peserta', 6)
        ->assertJsonPath('data.destinasi.0', 'Pantai Ujung Gelam');

    $paket = TravelPackage::firstOrFail();

    expect($paket->uuid)->toHaveLength(36)
        ->and($paket->itinerary[0]['agenda'][0]['kegiatan'])->toBe('Berangkat dari Jepara');
});

test('paket wisata bisa diubah dan gambarnya tersimpan', function () {
    Storage::fake('public');

    $paket = TravelPackage::create([
        'name' => 'Paket Lama', 'category' => 'open_trip', 'price' => 1000000, 'minimal_peserta' => 6,
    ]);

    $this->post("/api/v1/paket-wisata/{$paket->id}", [
        'nama' => 'Paket Baru',
        'kategori' => 'private_trip',
        'minimal_peserta' => 4,
        'harga' => 2000000,
        'gambar' => UploadedFile::fake()->image('sampul.jpg'),
    ], kirim())->assertOk();

    $paket->refresh();

    expect($paket->name)->toBe('Paket Baru')
        ->and($paket->category)->toBe('private_trip')
        ->and($paket->foto)->toStartWith('/storage/paket/');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $paket->foto));
});

test('paket yang sudah punya pendaftar tidak bisa dihapus', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000, 'minimal_peserta' => 6,
    ]);

    buatPendaftaran(['travel_package_id' => $paket->id]);

    $this->deleteJson("/api/v1/paket-wisata/{$paket->id}", [], kirim())
        ->assertStatus(422)
        ->assertJsonPath('pesan', 'Paket ini sudah punya pendaftar, jadi tidak bisa dihapus. Ubah saja isinya.');

    expect(TravelPackage::count())->toBe(1);
});

test('kendaraan bisa ditambah dengan tarif bertingkat dan dua transmisi', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Innova Zenix',
        'merek' => 'Toyota',
        'jenis' => 'mobil',
        'kapasitas' => 7,
        'transmisi_tersedia' => ['Manual', 'Matic'],
        'tarif_hari' => 700000,
        'tarif_jam' => 90000,
        'tarif_12jam' => 480000,
        'tarif_sopir' => 175000,
    ], kirim())->assertCreated()
        ->assertJsonPath('data.transmisi_label', 'Manual & Matic')
        ->assertJsonPath('data.tarif.jam', 90000);

    expect(Car::firstOrFail()->transmisi_tersedia)->toBe(['Manual', 'Matic']);
});

test('unit yang pernah disewa tidak bisa dihapus', function () {
    $mobil = Car::create([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'price_per_day' => 350000,
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'], 'capacity' => 7, 'is_available' => true,
    ]);

    PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'transmisi' => 'Manual', 'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => now()->addWeek()->toDateString(), 'jam_mulai' => '08:00',
        'dengan_sopir' => false, 'estimasi_biaya' => 350000,
    ]);

    $this->deleteJson("/api/v1/kendaraan/{$mobil->id}", [], kirim())->assertStatus(422);

    expect(Car::count())->toBe(1);
});

test('destinasi, testimoni, dan partner bisa ditambah lalu dihapus', function () {
    $this->postJson('/api/v1/destinasi', [
        'nama' => 'Nusa Penida', 'wilayah' => 'bali_nusa', 'provinsi' => 'Bali',
    ], kirim())->assertCreated();

    $this->postJson('/api/v1/testimoni', [
        'nama' => 'Sari', 'rating' => 5, 'isi' => 'Tripnya menyenangkan.',
    ], kirim())->assertCreated();

    $this->postJson('/api/v1/partner', ['nama' => 'Homestay Ijen'], kirim())->assertCreated();

    expect(DestinationPopuler::count())->toBe(1)
        ->and(Testimoni::count())->toBe(1)
        ->and(Partner::count())->toBe(1);

    $this->deleteJson('/api/v1/destinasi/'.DestinationPopuler::first()->id, [], kirim())->assertOk();
    $this->deleteJson('/api/v1/testimoni/'.Testimoni::first()->id, [], kirim())->assertOk();
    $this->deleteJson('/api/v1/partner/'.Partner::first()->id, [], kirim())->assertOk();

    expect(DestinationPopuler::count())->toBe(0)
        ->and(Testimoni::count())->toBe(0)
        ->and(Partner::count())->toBe(0);
});

test('isian yang salah ditolak dengan pesan berbahasa indonesia', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => '', 'jenis' => 'pesawat', 'transmisi_tersedia' => [],
    ], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nama', 'merek', 'jenis', 'kapasitas', 'transmisi_tersedia', 'tarif_hari']);

    expect(Car::count())->toBe(0);
});

test('menulis pun tetap butuh kunci', function () {
    $this->postJson('/api/v1/partner', ['nama' => 'Tanpa Kunci'])->assertStatus(401);

    expect(Partner::count())->toBe(0);
});

/* --------------------- SARAN ISIAN PAKET --------------------- */

/**
 * Migrasi sudah mengisi daftar saran dari config fasilitas dan destinasi yang
 * ada. Uji di bawah ini menguji perilakunya, jadi mulainya dari daftar kosong
 * supaya hitungannya jelas.
 */
function saranKosong(): void
{
    SaranPaket::query()->delete();
}

test('daftar saran terbagi per jenis dan urut nama', function () {
    saranKosong();

    SaranPaket::create(['jenis' => 'destinasi', 'nama' => 'Kawah Ijen']);
    SaranPaket::create(['jenis' => 'destinasi', 'nama' => 'Baluran']);
    SaranPaket::create(['jenis' => 'fasilitas', 'nama' => 'Homestay']);

    $this->getJson('/api/v1/saran', kirim())
        ->assertOk()
        ->assertJsonPath('data.destinasi.0.nama', 'Baluran')
        ->assertJsonPath('data.destinasi.1.nama', 'Kawah Ijen')
        ->assertJsonPath('data.fasilitas.0.nama', 'Homestay')
        ->assertJsonCount(2, 'data.destinasi');
});

test('saran baru tersimpan dan tidak berganda', function () {
    saranKosong();

    $this->postJson('/api/v1/saran', ['jenis' => 'fasilitas', 'nama' => 'Kaos peserta'], kirim())
        ->assertCreated();

    // Nama yang sama tidak menggandakan baris
    $this->postJson('/api/v1/saran', ['jenis' => 'fasilitas', 'nama' => 'Kaos peserta'], kirim())
        ->assertOk();

    expect(SaranPaket::jenis('fasilitas')->count())->toBe(1);
});

test('jenis saran di luar daftar ditolak', function () {
    saranKosong();

    $this->postJson('/api/v1/saran', ['jenis' => 'ngawur', 'nama' => 'X'], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors('jenis');

    expect(SaranPaket::count())->toBe(0);
});

test('menghapus saran tidak mengubah paket yang sudah tersimpan', function () {
    saranKosong();

    $saran = SaranPaket::create(['jenis' => 'destinasi', 'nama' => 'Kawah Ijen']);

    $paket = TravelPackage::create([
        'name' => 'Open Trip Ijen', 'category' => 'open_trip', 'price' => 500000,
        'minimal_peserta' => 6, 'destination_list' => ['Kawah Ijen'],
    ]);

    $this->deleteJson("/api/v1/saran/{$saran->id}", [], kirim())->assertOk();

    expect(SaranPaket::count())->toBe(0)
        ->and($paket->fresh()->destination_list)->toBe(['Kawah Ijen']);
});

test('isian paket baru otomatis masuk daftar saran', function () {
    saranKosong();

    $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Baluran',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 900000,
        'destinasi' => ['Taman Nasional Baluran', 'Pantai Bama'],
        'fasilitas' => ['Kaos peserta'],
    ], kirim())->assertCreated();

    expect(SaranPaket::jenis('destinasi')->orderBy('nama')->pluck('nama')->all())
        ->toBe(['Pantai Bama', 'Taman Nasional Baluran'])
        ->and(SaranPaket::jenis('fasilitas')->pluck('nama')->all())
        ->toBe(['Kaos peserta']);

    // Menyimpan paket kedua dengan isi yang sama tidak menggandakan saran
    $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Baluran Edisi 2',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 950000,
        'destinasi' => ['Pantai Bama'],
    ], kirim())->assertCreated();

    expect(SaranPaket::jenis('destinasi')->count())->toBe(2);
});

/* ------------------- STATUS & JADWAL TAYANG LEWAT API ------------------- */

test('status dan jadwal tayang ikut terbaca dan tersimpan lewat api', function () {
    $balasan = $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Terjadwal',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1000000,
        'status' => 'terbit',
        'tayang_mulai' => now()->addWeek()->format('Y-m-d H:i'),
    ], kirim())->assertCreated();

    $balasan->assertJsonPath('data.status', 'terbit')
        ->assertJsonPath('data.status_tayang', 'terjadwal')
        ->assertJsonPath('data.status_tayang_label', 'Terjadwal')
        ->assertJsonPath('data.sedang_tayang', false);

    expect(TravelPackage::firstOrFail()->tayang_mulai)->not->toBeNull();
});

test('paket baru tanpa status dianggap terbit', function () {
    $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Biasa',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1000000,
    ], kirim())->assertCreated()->assertJsonPath('data.status_tayang', 'tayang');
});

test('berhenti tayang sebelum mulai tayang ditolak', function () {
    $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Ngawur',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1000000,
        'tayang_mulai' => now()->addWeek()->format('Y-m-d H:i'),
        'tayang_sampai' => now()->format('Y-m-d H:i'),
    ], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors('tayang_sampai');

    expect(TravelPackage::count())->toBe(0);
});

test('rujukan membawa pilihan status paket', function () {
    $this->getJson('/api/v1/rujukan', kirim())
        ->assertOk()
        ->assertJsonPath('data.status_paket.terbit', 'Terbit')
        ->assertJsonPath('data.status_tayang.terjadwal', 'Terjadwal');
});

/* ------------------------- GAMBAR JADI WEBP ------------------------- */

test('gambar jpg yang diunggah tersimpan sebagai webp', function () {
    Storage::fake('public');

    $this->postJson('/api/v1/partner', [
        'nama' => 'Homestay Ijen',
        'gambar' => UploadedFile::fake()->image('logo.jpg', 600, 400),
    ], kirim())->assertCreated();

    $jalur = Partner::firstOrFail()->foto;

    expect($jalur)->toEndWith('.webp');

    $isi = Storage::disk('public')->get(str_replace('/storage/', '', $jalur));

    // Tanda pengenal berkas WebP: "RIFF....WEBP"
    expect(substr($isi, 0, 4))->toBe('RIFF')
        ->and(substr($isi, 8, 4))->toBe('WEBP');
});

test('gambar png ikut jadi webp', function () {
    Storage::fake('public');

    $this->postJson('/api/v1/testimoni', [
        'nama' => 'Sari',
        'rating' => 5,
        'isi' => 'Menyenangkan.',
        'gambar' => UploadedFile::fake()->image('avatar.png', 300, 300),
    ], kirim())->assertCreated();

    expect(Testimoni::firstOrFail()->avatar)->toEndWith('.webp');
});

test('gambar raksasa dikecilkan supaya halaman tetap ringan', function () {
    Storage::fake('public');

    $this->postJson('/api/v1/paket-wisata', [
        'nama' => 'Open Trip Foto Besar',
        'kategori' => 'open_trip',
        'minimal_peserta' => 6,
        'harga' => 1000000,
        'gambar' => UploadedFile::fake()->image('sampul.jpg', 4000, 2000),
    ], kirim())->assertCreated();

    $jalur = TravelPackage::firstOrFail()->foto;
    $isi = Storage::disk('public')->get(str_replace('/storage/', '', $jalur));

    [$lebar, $tinggi] = getimagesizefromstring($isi);

    expect($lebar)->toBe(1920)
        ->and($tinggi)->toBe(960);
});
