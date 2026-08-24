<?php

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\KonfirmasiPembayaran;
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
        'nama' => 'Nusa Penida', 'wilayah' => 'bali', 'provinsi' => 'Bali',
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

/* --------------------- KONFIRMASI PEMBAYARAN --------------------- */

test('bukti pembayaran terbaca lewat api dan bisa diverifikasi', function () {
    $pendaftaran = buatPendaftaran();

    $bayar = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 500000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'bukti' => '/storage/bukti-bayar/x.webp',
    ]);

    $this->getJson('/api/v1/pembayaran', kirim())
        ->assertOk()
        ->assertJsonPath('data.0.kode', $pendaftaran->kode)
        ->assertJsonPath('data.0.nominal_formatted', 'Rp 500.000')
        ->assertJsonPath('data.0.status_label', 'Menunggu Dicek')
        // Pesanannya ikut terbawa supaya admin tidak perlu mencari sendiri
        ->assertJsonPath('data.0.pesanan.nama', 'Budi Santoso')
        ->assertJsonPath('data.0.pesanan.keterangan', 'Open Trip Banyuwangi');

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", [
        'status' => 'diterima',
        'catatan_admin' => 'Cocok dengan mutasi rekening.',
    ], kirim())
        ->assertOk()
        ->assertJsonPath('data.status_label', 'Diterima');

    expect($bayar->fresh()->catatan_admin)->toBe('Cocok dengan mutasi rekening.');
});

test('status pembayaran di luar daftar ditolak', function () {
    $bayar = KonfirmasiPembayaran::create([
        'kode' => 'OT-0000-XXXX', 'jenis' => 'dp', 'nominal' => 1000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'X',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", ['status' => 'ngawur'], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($bayar->fresh()->status)->toBe('menunggu');
});

test('dashboard menghitung bukti bayar yang menunggu', function () {
    KonfirmasiPembayaran::create([
        'kode' => 'OT-0000-XXXX', 'jenis' => 'dp', 'nominal' => 1000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'X',
    ]);

    $this->getJson('/api/v1/dashboard', kirim())
        ->assertOk()
        ->assertJsonPath('data.perlu_ditindak.pembayaran_menunggu', 1);
});

test('pendaftaran membawa daftar peserta dan kelengkapan kesehatannya', function () {
    $pendaftaran = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => ['Budi Santoso', 'Sari Dewi'],
    ]);

    $this->getJson("/api/v1/pendaftaran/{$pendaftaran->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.peserta.0.nama', 'Budi Santoso')
        ->assertJsonPath('data.peserta.1.nama', 'Sari Dewi')
        ->assertJsonPath('data.kesehatan_terisi', 0)
        ->assertJsonPath('data.kesehatan_lengkap', false)
        ->assertJsonPath('data.peserta_belum_isi', ['Budi Santoso', 'Sari Dewi']);
});

test('titik jemput tiap peserta ikut terbaca lewat api', function () {
    $pendaftaran = buatPendaftaran([
        'jumlah_peserta' => 3,
        'daftar_peserta' => [
            ['nama' => 'Budi Santoso', 'titik_jemput' => 'Surakarta'],
            ['nama' => 'Sari Dewi', 'titik_jemput' => 'Jogja'],
            ['nama' => 'Rina Wijaya', 'titik_jemput' => 'Surakarta'],
        ],
    ]);

    $this->getJson("/api/v1/pendaftaran/{$pendaftaran->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.peserta.0.titik_jemput', 'Surakarta')
        // Dikelompokkan supaya sopir tahu siapa menunggu di mana
        ->assertJsonPath('data.jemput_per_titik.Surakarta', ['Budi Santoso', 'Rina Wijaya'])
        ->assertJsonPath('data.jemput_per_titik.Jogja', ['Sari Dewi']);
});

/* ------------------- DETAIL PELANGGAN UNTUK LEMON -------------------
 *
 * Admin yang membuka satu pelanggan biasanya sedang menjawab satu
 * pertanyaan lewat WhatsApp: sudah bayar berapa, siapa saja yang ikut,
 * dan apakah ada pengajuan pembatalan. Ketiganya harus datang sekalian,
 * supaya lemon tidak menggambar halaman dari tiga panggilan terpisah.
 */

test('detail pendaftaran memuat tagihan, pembayaran, dan pembatalan', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);

    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id]);

    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode,
        'jenis' => 'dp',
        'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(),
        'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso',
        'status' => 'diterima',
    ]);

    Pembatalan::create([
        'kode_pendaftaran' => $pendaftaran->kode,
        'nama_pemohon' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'alasan' => 'kondisi_kesehatan',
        'jumlah_dibatalkan' => 1,
        'bank' => 'BCA',
        'nomor_rekening' => '1234567890',
        'atas_nama_rekening' => 'Budi Santoso',
    ]);

    $hasil = $this->getJson("/api/v1/pendaftaran/{$pendaftaran->id}", kirim())
        ->assertOk()
        ->json('data');

    // 2 × 1.430.000 = 2.860.000; DP 858.000 sudah masuk
    expect($hasil['tagihan']['total'])->toBe(2860000)
        ->and($hasil['tagihan']['sudah'])->toBe(858000)
        ->and($hasil['tagihan']['sisa'])->toBe(2002000)
        ->and($hasil['tagihan']['lunas'])->toBeFalse()
        ->and($hasil['pembayaran'])->toHaveCount(1)
        ->and($hasil['pembayaran'][0]['nominal_formatted'])->toBe('Rp 858.000')
        ->and($hasil['pembatalan']['jumlah_dibatalkan'])->toBe(1)
        ->and($hasil['pembatalan']['rekening'])->toContain('1234567890');

    // Riwayat kesehatan TIDAK ikut: jalurnya sendiri, dan tercatat
    expect($hasil)->not->toHaveKey('riwayat_kesehatan');
});

test('detail pendaftaran tanpa pembayaran tetap utuh bentuknya', function () {
    $pendaftaran = buatPendaftaran();

    $hasil = $this->getJson("/api/v1/pendaftaran/{$pendaftaran->id}", kirim())
        ->assertOk()
        ->json('data');

    expect($hasil['pembayaran'])->toBe([])
        ->and($hasil['pembatalan'])->toBeNull()
        // Paket belum berharga → tagihannya tidak dikarang
        ->and($hasil['tagihan'])->toBe([]);
});

/* ------------- TINGKAT PERHATIAN & STATUS SEMI OTOMATIS ------------- */

function buatKesehatan(string $kode, array $ubah = []): RiwayatKesehatan
{
    return RiwayatKesehatan::create(array_merge([
        'kode_pendaftaran' => $kode,
        'nama_peserta' => 'Budi Santoso',
        'usia' => 30,
        'jenis_kelamin' => 'Laki-laki',
        'kemampuan_renang' => 'lancar',
        'kontak_darurat_nama' => 'Siti',
        'kontak_darurat_hp' => '081234567890',
        'setuju_data_kesehatan' => true,
    ], $ubah));
}

test('peserta tanpa keluhan apa pun ditandai aman', function () {
    expect(buatKesehatan('OT-X')->tingkat_perhatian)->toBe('aman');
});

test('penyakit yang bisa kambuh menuntut perhatian tinggi', function () {
    $riwayat = buatKesehatan('OT-X', ['kondisi_khusus' => ['jantung', 'maag']]);

    expect($riwayat->tingkat_perhatian)->toBe('tinggi')
        ->and($riwayat->alasan_perhatian)->toContain('Gangguan jantung')
        // Maag tetap dicatat, tapi bukan yang menuntut kesiapan khusus
        ->and($riwayat->alasan_catatan)->toContain('Maag / GERD');
});

test('obat rutin dan alergi juga menuntut perhatian tinggi', function () {
    expect(buatKesehatan('OT-X', ['obat_rutin' => 'Salbutamol'])->tingkat_perhatian)->toBe('tinggi')
        ->and(buatKesehatan('OT-Y', ['alergi' => 'Udang'])->tingkat_perhatian)->toBe('tinggi');
});

test('pantangan makanan saja tidak membuat peserta merah', function () {
    // Inti pemisahannya: kalau semua ditandai merah, penandanya berhenti berarti
    $riwayat = buatKesehatan('OT-X', ['pantangan_makanan' => 'Tidak suka pedas']);

    expect($riwayat->tingkat_perhatian)->toBe('sedang')
        ->and($riwayat->alasan_perhatian)->toBe([])
        ->and($riwayat->alasan_catatan)->toContain('Pantangan makanan: Tidak suka pedas');
});

test('tidak bisa berenang dicatat, bukan diabaikan', function () {
    expect(buatKesehatan('OT-X', ['kemampuan_renang' => 'tidak_bisa'])->alasan_catatan)
        ->toContain('Tidak bisa berenang');
});

test('menyetujui pembayaran ikut memajukan status pendaftaran', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id]);   // 2 peserta = 2.860.000

    $dp = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$dp->id}/status", ['status' => 'diterima'], kirim())
        ->assertOk()
        ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'DP Masuk'));

    expect($pendaftaran->fresh()->status)->toBe('dp_masuk');

    // Pelunasan membuatnya lunas
    $lunas = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'pelunasan', 'nominal' => 2002000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$lunas->id}/status", ['status' => 'diterima'], kirim())->assertOk();

    expect($pendaftaran->fresh()->status)->toBe('lunas');
});

test('bukti yang ditolak menarik kembali status pendaftarannya', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id, 'status' => 'dp_masuk']);

    $bayar = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi Santoso', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", ['status' => 'ditolak'], kirim())->assertOk();

    // Tidak ada uang yang sah masuk, jadi statusnya tidak dimajukan sendiri
    expect($pendaftaran->fresh()->status)->toBe('dp_masuk');
});

test('pesanan yang sudah dibatalkan tidak dihidupkan lagi oleh pembayaran', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id, 'status' => 'batal']);

    $bayar = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Budi', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", ['status' => 'diterima'], kirim())->assertOk();

    // Pembatalan adalah keputusan manusia; uang yang masuk sesudahnya tidak
    // boleh diam-diam menghidupkannya lagi
    expect($pendaftaran->fresh()->status)->toBe('batal');
});

test('serah terima memperbarui kondisi unit dan menutup sewanya', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'dikembalikan_pada' => '2026-09-11 08:15',
        'kondisi_awal' => ['bodi_depan' => 'baik', 'kaca' => 'baik'],
        'kondisi_akhir' => ['bodi_depan' => 'lecet', 'kaca' => 'baik'],
    ], kirim())->assertOk();

    // Unit membawa keadaannya sendiri ke sewa berikutnya
    expect($mobil->fresh()->kondisi_terkini)->toBe(['bodi_depan' => 'lecet', 'kaca' => 'baik'])
        ->and($mobil->fresh()->kondisi_diperiksa_pada)->not->toBeNull()
        // Status yang harus diingat sendiri adalah status yang paling sering tertinggal
        ->and($sewa->fresh()->status)->toBe('selesai');
});

test('kondisi saat unit diserahkan tidak tertimpa oleh pemeriksaan berikutnya', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'kondisi_awal' => ['bodi_depan' => 'baik'],
        'kondisi_akhir' => ['bodi_depan' => 'lecet'],
        'denda_kerusakan' => 250000,
    ], kirim())->assertOk();

    // Lembarnya dibuka lagi. Kolom "saat diserahkan" kini terisi kondisi
    // terkini unit — yang sudah termasuk lecet yang baru saja dicatat.
    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'kondisi_awal' => ['bodi_depan' => 'lecet'],
        'kondisi_akhir' => ['bodi_depan' => 'lecet'],
    ], kirim())->assertOk();

    // Kalau ini tertimpa, tidak ada lagi bukti bahwa unitnya tadinya mulus,
    // dan usulan dendanya lenyap dari layar padahal sudah ditagihkan.
    expect($sewa->fresh()->kondisi_awal)->toBe(['bodi_depan' => 'baik'])
        ->and($sewa->fresh()->denda_kerusakan_usulan)->toBeGreaterThan(0);
});

test('rincian denda yang sudah ditetapkan tersimpan dan ikut di nota', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'denda_kerusakan' => 450000,
        'rincian_denda' => [
            ['bagian' => 'Bodi depan & bemper', 'dari' => 'Baik', 'jadi' => 'Lecet', 'biaya' => 250000],
            ['bagian' => 'Bodi samping kiri', 'dari' => 'Baik', 'jadi' => 'Lecet', 'biaya' => 200000],
        ],
    ], kirim())->assertOk();

    expect($sewa->fresh()->rincian_denda)->toHaveCount(2);

    // Nota harus tetap bisa menyebut bagian mana yang ditagih, walau
    // perbandingan kondisinya sudah tidak menyisakan selisih apa pun.
    $nota = App\Http\Controllers\Api\SewaKendaraan\PenyewaanController::notaSewa($sewa->fresh());
    $baris = collect($nota['baris'])->firstWhere('label', 'Denda kerusakan');

    expect($baris['keterangan'])->toContain('Bodi depan & bemper')
        ->and($baris['keterangan'])->toContain('Bodi samping kiri');
});

test('berkas jaminan penyewa tersimpan dari medan bernama gambar', function () {
    Storage::fake('public');

    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Sisi admin selalu melampirkan berkasnya dengan nama medan "gambar",
    // sama seperti unggahan foto paket dan armada. Endpoint ini sempat
    // mencari nama lain, dan tiap unggahan ditolak "wajib diisi" padahal
    // berkasnya terkirim — jadi nama medannya ikut diuji.
    $this->post("/api/v1/penyewaan/{$sewa->id}/berkas-jaminan", [
        'gambar' => UploadedFile::fake()->image('ktp.jpg'),
    ], kirim())->assertOk();

    expect($sewa->fresh()->berkas_jaminan)->toStartWith('/storage/jaminan/');
});

test('pesanan sewa yang dibatalkan tidak ikut ditutup jadi selesai', function () {
    $mobil = Car::create([
        'name' => 'Avanza Uji', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'capacity' => 7, 'price_per_day' => 500000,
        'is_available' => true, 'transmisi_tersedia' => ['Matic'],
    ]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1, 'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'batal',
    ]);

    $this->patchJson("/api/v1/penyewaan/{$sewa->id}/serah-terima", [
        'dikembalikan_pada' => '2026-09-11 08:15',
    ], kirim())->assertOk();

    expect($sewa->fresh()->status)->toBe('batal');
});

test('status pembayaran yang diubah mengabari pelanggan', function () {
    Illuminate\Support\Facades\Mail::fake();
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');

    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id, 'email' => 'siti@contoh.test']);

    $bayar = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", ['status' => 'diterima'], kirim())->assertOk();

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        $isi = $surat->render();

        return $surat->hasTo('siti@contoh.test')
            && $surat->judul === 'Pembayaran Anda Sudah Diterima'
            // Sisa yang perlu dilunasi ikut disebut, bukan cuma "diterima"
            && str_contains($isi, 'Rp 2.002.000');
    });
});

test('bukti yang ditolak mengabari pelanggan dengan alasannya', function () {
    Illuminate\Support\Facades\Mail::fake();
    config()->set('orcha.email_pemberitahuan', 'halo@orchajourney.com');

    $pendaftaran = buatPendaftaran(['email' => 'siti@contoh.test']);

    $bayar = KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 500000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'menunggu',
    ]);

    $this->patchJson("/api/v1/pembayaran/{$bayar->id}/status", [
        'status' => 'ditolak', 'catatan_admin' => 'Nominal tidak cocok dengan mutasi.',
    ], kirim())->assertOk();

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PemberitahuanFormulir::class, function ($surat) {
        if (! $surat->untukPelanggan) {
            return false;
        }

        return $surat->judul === 'Bukti Pembayaran Perlu Diperiksa Ulang'
            // Alasannya disebut, dan uang yang sudah berpindah tidak diabaikan
            && str_contains($surat->langkah ?? $surat->catatan, 'Nominal tidak cocok');
    });
});

test('status pesanan hanya maju oleh pembayaran yang sudah diterima', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip', 'price' => 1430000,
        'tanggal_berangkat' => now()->addMonth()->toDateString(),
    ]);
    $pendaftaran = buatPendaftaran(['travel_package_id' => $paket->id]);

    // Bukti yang baru dikirim dan belum dicek TIDAK memajukan status —
    // kalau tidak, siapa pun bisa memajukan statusnya sendiri dengan
    // mengunggah gambar.
    KonfirmasiPembayaran::create([
        'kode' => $pendaftaran->kode, 'jenis' => 'dp', 'nominal' => 858000,
        'tanggal_transfer' => now()->toDateString(), 'bank_pengirim' => 'BCA',
        'atas_nama_pengirim' => 'Siti', 'status' => 'menunggu',
    ]);

    expect(App\Support\StatusPendaftaran::selaraskan($pendaftaran))->toBeNull()
        ->and($pendaftaran->fresh()->status)->toBe('baru');
});

/* ------------------- SARINGAN PAKET DI PENDAFTARAN ------------------- */

test('pendaftaran bisa disaring per paket', function () {
    $banyuwangi = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip',
        'price' => 1430000, 'minimal_peserta' => 6,
    ]);
    $dieng = TravelPackage::create([
        'name' => 'Private Trip Dieng', 'category' => 'private_trip',
        'price' => 2500000, 'minimal_peserta' => 4,
    ]);

    buatPendaftaran(['travel_package_id' => $banyuwangi->id, 'nama' => 'Peserta Banyuwangi']);
    buatPendaftaran(['travel_package_id' => $banyuwangi->id, 'nama' => 'Peserta Banyuwangi Dua']);
    buatPendaftaran(['travel_package_id' => $dieng->id, 'nama' => 'Peserta Dieng']);

    $this->getJson("/api/v1/pendaftaran?paket_id={$banyuwangi->id}", kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissing(['nama' => 'Peserta Dieng']);

    // Tanpa saringan, ketiganya tetap keluar.
    $this->getJson('/api/v1/pendaftaran', kirim())
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

test('rujukan membawa daftar paket untuk pemilih saringan', function () {
    TravelPackage::create([
        'name' => 'Study Tour Bali', 'category' => 'study_tour',
        'price' => 1600000, 'minimal_peserta' => 10,
        'tanggal_berangkat' => '2026-09-22',
    ]);

    $this->getJson('/api/v1/rujukan', kirim())
        ->assertOk()
        ->assertJsonPath('data.paket_wisata.0.nama', 'Study Tour Bali')
        ->assertJsonPath('data.paket_wisata.0.kategori', 'study_tour')
        ->assertJsonPath('data.paket_wisata.0.tanggal_berangkat', '2026-09-22');
});

/* ------------------ MELENGKAPI DAFTAR PESERTA ------------------ */

test('admin bisa melengkapi nama peserta yang belum didata', function () {
    $daftar = buatPendaftaran(['jumlah_peserta' => 3, 'titik_jemput' => 'Jogja']);

    expect($daftar->peserta)->toBe([]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [
            ['nama' => ' Siti Aminah ', 'titik_jemput' => 'Surakarta'],
            ['nama' => 'Budi Santoso', 'titik_jemput' => ''],
            ['nama' => 'Rina Wijaya'],
        ],
    ], kirim())
        ->assertOk()
        ->assertJsonPath('pesan', 'Daftar peserta tersimpan.')
        ->assertJsonPath('data.peserta.0.nama', 'Siti Aminah')
        ->assertJsonPath('data.peserta.0.titik_jemput', 'Surakarta')
        // Titik jemput kosong jatuh ke titik rombongan, bukan dibiarkan hampa.
        ->assertJsonPath('data.peserta.1.titik_jemput', 'Jogja');
});

test('jumlah peserta tidak ikut berubah, selisihnya dilaporkan', function () {
    $daftar = buatPendaftaran(['jumlah_peserta' => 5]);

    $balasan = $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [['nama' => 'Siti Aminah'], ['nama' => 'Budi Santoso']],
    ], kirim())->assertOk();

    // Angka itu yang mengalikan harga jadi tagihan — tidak boleh berubah
    // diam-diam hanya karena nama yang masuk baru dua.
    expect($daftar->fresh()->jumlah_peserta)->toBe(5)
        ->and($balasan->json('pesan'))->toContain('2 nama untuk 5 peserta');
});

test('daftar peserta boleh dikosongkan lagi', function () {
    $daftar = buatPendaftaran(['daftar_peserta' => [['nama' => 'Siti Aminah']]]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", ['peserta' => []], kirim())
        ->assertOk();

    expect($daftar->fresh()->daftar_peserta)->toBeNull();
});

test('nama peserta kosong ditolak', function () {
    $daftar = buatPendaftaran();

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [['nama' => '', 'titik_jemput' => 'Jogja']],
    ], kirim())
        ->assertStatus(422)
        ->assertJsonValidationErrors('peserta.0.nama');
});

test('melengkapi peserta tetap dijaga kunci api', function () {
    $daftar = buatPendaftaran();

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", ['peserta' => []])
        ->assertStatus(401);
});

test('detail pendaftaran membawa titik jemput paket untuk daftar pilihan', function () {
    $paket = TravelPackage::create([
        'name' => 'Open Trip Banyuwangi', 'category' => 'open_trip',
        'price' => 1430000, 'minimal_peserta' => 6,
        'titik_jemput' => 'Jogja, Klaten, Surakarta',
    ]);

    $daftar = buatPendaftaran(['travel_package_id' => $paket->id]);

    $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.paket.titik_jemput', ['Jogja', 'Klaten', 'Surakarta']);
});

/* ------------------- PENGGANTIAN PESERTA ------------------- */

test('penggantian tercatat saat admin menyatakannya', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => [
            ['nama' => 'Suparjiman', 'titik_jemput' => 'Klaten'],
            ['nama' => 'Haha', 'titik_jemput' => 'Jogja'],
        ],
    ]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [
            ['nama' => 'Suparjiman', 'titik_jemput' => 'Klaten'],
            ['nama' => 'Wiam', 'titik_jemput' => 'Jogja', 'gantikan' => 'Haha'],
        ],
    ], kirim())->assertOk();

    $jejak = $daftar->fresh()->riwayat_penggantian;

    expect($jejak)->toHaveCount(1)
        ->and($jejak[0]['dari'])->toBe('Haha')
        ->and($jejak[0]['ke'])->toBe('Wiam')
        ->and($jejak[0]['oleh'])->toBe('admin@phoenix.test');
});

test('pembetulan salah ketik tidak dianggap penggantian', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 1,
        'daftar_peserta' => [['nama' => 'Suparjimen']],
    ]);

    // Menebak dari selisih daftar tidak bisa membedakan keduanya: sama-sama satu
    // nama keluar, satu nama masuk. Yang membedakan niat admin, dan niat itu
    // dinyatakan lewat 'gantikan' — bukan ditebak.
    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta",
        ['peserta' => [['nama' => 'Suparjiman']]], kirim())->assertOk();

    expect($daftar->fresh()->riwayat_penggantian)->toBeNull()
        ->and($daftar->fresh()->peserta[0]['nama'])->toBe('Suparjiman');
});

test('penggantian beruntun menumpuk, tidak menimpa yang sebelumnya', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 1,
        'daftar_peserta' => [['nama' => 'Haha']],
    ]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta",
        ['peserta' => [['nama' => 'Wiam', 'gantikan' => 'Haha']]], kirim())->assertOk();

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta",
        ['peserta' => [['nama' => 'Rina', 'gantikan' => 'Wiam']]], kirim())->assertOk();

    expect(collect($daftar->fresh()->riwayat_penggantian)
        ->map(fn ($satu) => $satu['dari'].' → '.$satu['ke'])->all())
        ->toBe(['Haha → Wiam', 'Wiam → Rina']);
});

test('titik jemput yang ikut berpindah tercatat di riwayatnya', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 1,
        'daftar_peserta' => [['nama' => 'Haha', 'titik_jemput' => 'Jogja']],
    ]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [
            ['nama' => 'Wiam', 'titik_jemput' => 'Klaten',
                'gantikan' => 'Haha', 'gantikan_titik' => 'Jogja'],
        ],
    ], kirim())->assertOk();

    $jejak = $daftar->fresh()->riwayat_penggantian;

    expect($jejak[0]['dari_titik'])->toBe('Jogja')
        ->and($jejak[0]['ke_titik'])->toBe('Klaten');
});

test('titik jemput tetap dicatat walau pengganti naik di titik yang sama', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 1,
        'daftar_peserta' => [['nama' => 'Haha', 'titik_jemput' => 'Jogja']],
    ]);

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [
            ['nama' => 'Wiam', 'titik_jemput' => 'Jogja',
                'gantikan' => 'Haha', 'gantikan_titik' => 'Jogja'],
        ],
    ], kirim())->assertOk();

    // Arsip yang diam saat titiknya tidak berubah menyisakan pertanyaan yang
    // tidak bisa dijawab lagi kemudian: memang tetap, atau tidak sempat
    // dicatat? Dicatat selalu, jadi tidak perlu ditafsirkan.
    $jejak = $daftar->fresh()->riwayat_penggantian;

    expect($jejak[0]['dari_titik'])->toBe('Jogja')
        ->and($jejak[0]['ke_titik'])->toBe('Jogja');
});

test('surat penggantian memuat seluruh penggantian pendaftarannya', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => [['nama' => 'Wildan'], ['nama' => 'Wiam']],
        'riwayat_penggantian' => [
            ['dari' => 'Suparjiman', 'ke' => 'Wildan', 'dari_titik' => 'Jogja', 'ke_titik' => 'Surakarta'],
            ['dari' => 'Haha', 'ke' => 'Wiam', 'dari_titik' => 'Surakarta', 'ke_titik' => 'Klaten'],
        ],
    ]);

    /*
     | Satu surat untuk satu pemesanan, bukan satu per penggantian.
     |
     | Pihak yang menyatakan sama, pendaftaran yang dirujuk sama, kebijakan yang
     | mendasarinya sama — yang berbeda cuma barisnya. Surat per penggantian
     | membuat pemesan menandatangani dua berkas bermaterai untuk satu
     | pemesanan.
     */
    $this->get("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian", kirim())
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $isi = view('pdf.surat-penggantian', [
        'pendaftaran' => $daftar,
        'riwayat' => $daftar->riwayat_penggantian,
    ])->render();

    expect($isi)
        ->toContain('Suparjiman')->toContain('Wildan')
        ->toContain('Haha')->toContain('Wiam')
        // Jumlahnya disebut di pasalnya, bukan hanya tersirat dari banyak baris.
        ->toContain('sebanyak 2 penggantian,')
        // Tanda tangan para pengganti pindah ke tabelnya sendiri: tidak ada
        // satu nama yang pantas ditulis di kolom tunggal.
        ->toContain('Persetujuan Peserta Pengganti');
});

test('surat tidak membocorkan surel admin, dan kopnya tercetak tiap halaman', function () {
    $daftar = buatPendaftaran();

    $isi = view('pdf.surat-penggantian', [
        'pendaftaran' => $daftar,
        'riwayat' => [[
            'dari' => 'Haha', 'ke' => 'Wiam',
            'pada' => '2026-08-24T18:17:00+07:00',
            'oleh' => 'pt.asthanaciptamandiri@gmail.com',
        ]],
    ])->render();

    // Berkas ini keluar ke pemesan. Alamat surel staf bukan miliknya untuk
    // dipegang — yang perlu ia tahu cuma bahwa pencatatnya pihak Orcha. Nama
    // admin sebenarnya tetap tersimpan di riwayat dan terbaca di layar admin.
    expect($isi)
        ->not->toContain('pt.asthanaciptamandiri@gmail.com')
        ->toContain('oleh Admin Orcha Journey')
        /*
         | Kop dipasang mengambang, bukan sekadar tabel di puncak badan.
         |
         | Yang mengambang ikut tercetak di setiap halaman; yang tidak hanya
         | muncul sekali, dan halaman kedua surat resmi yang polos tanpa kop
         | terbaca seperti lembar lepas yang tercecer dari berkas lain.
         */
        ->toContain('kepala-luar')
        ->toContain('position: fixed');
});

test('tautan konfirmasi pembayaran membawa kodenya, tidak dipendekkan', function () {
    $daftar = buatPendaftaran();

    $tautan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()->json('data.konfirmasi_pembayaran_tautan');

    /*
     | Sengaja terbaca, bukan /t/xxx seperti berkas: yang diminta di sini bukan
     | mengunduh melainkan mengunggah bukti transfer, dan orang yang diminta
     | menyerahkan bukti pembayaran pantas melihat ke mana ia dibawa sebelum
     | mengetuk.
     */
    expect($tautan)->toContain('/konfirmasi-pembayaran')
        ->toContain('kode='.$daftar->kode)
        ->not->toContain('/t/');
});

test('peserta yang belum mengisi dapat tautan pribadinya sendiri', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => [['nama' => 'Rina Wijaya'], ['nama' => 'Ahmad Fauzi']],
    ]);

    $tautan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()->json('data.peserta_belum_isi_tautan');

    // Kode dan namanya sudah terbawa, sehingga yang membukanya tinggal mengisi
    // kondisinya sendiri — tidak perlu mengetik ulang kode yang mudah salah.
    expect($tautan)->toHaveKey('Rina Wijaya')
        ->and($tautan['Rina Wijaya'])
        ->toContain('riwayat-kesehatan')
        ->toContain('kode='.$daftar->kode)
        ->toContain('peserta=Rina');
});

/* ------------------------------- GALERI ------------------------------- */

test('foto galeri disimpan sebagai webp, aslinya tidak ikut tersimpan', function () {
    Storage::fake('public');

    // PNG hasil unggahan admin biasanya beberapa MB. Menyimpan aslinya
    // berdampingan dengan WebP-nya berarti membayar dua kali untuk gambar yang
    // sama, dan yang penuh lebih dulu adalah kuota hosting.
    $this->post('/api/v1/galeri', [
        'gambar' => UploadedFile::fake()->image('rombongan.png', 800, 600),
    ], kirim())->assertStatus(201);

    $berkas = Storage::disk('public')->allFiles('galeri');

    expect($berkas)->toHaveCount(1)
        ->and($berkas[0])->toEndWith('.webp');
});

test('galeri hanya menampilkan yang ditandai tampil, mengikuti urutannya', function () {
    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/c.webp', 'urutan' => 3]);
    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/a.webp', 'urutan' => 1]);
    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/x.webp', 'urutan' => 2, 'tampil' => false]);

    expect(App\Models\Etalase\Galeri::tayang()->pluck('foto')->all())
        ->toBe(['/storage/galeri/a.webp', '/storage/galeri/c.webp']);
});

test('foto baru masuk ke belakang barisan, tidak menyerobot urutan yang sudah disusun', function () {
    Storage::fake('public');

    App\Models\Etalase\Galeri::create(['foto' => '/storage/galeri/a.webp', 'urutan' => 5]);

    $this->post('/api/v1/galeri', ['gambar' => UploadedFile::fake()->image('b.jpg')], kirim())
        ->assertStatus(201);

    // Urutan yang sudah disusun admin tidak boleh berubah sendiri hanya karena
    // ada unggahan baru.
    expect(App\Models\Etalase\Galeri::latest('id')->first()->urutan)->toBe(6);
});

test('menghapus foto galeri ikut membuang berkasnya', function () {
    Storage::fake('public');

    $this->post('/api/v1/galeri', ['gambar' => UploadedFile::fake()->image('a.jpg')], kirim())
        ->assertStatus(201);

    $galeri = App\Models\Etalase\Galeri::first();
    $jalur = str_replace('/storage/', '', $galeri->foto);

    Storage::disk('public')->assertExists($jalur);

    $this->delete("/api/v1/galeri/{$galeri->id}", [], kirim())->assertOk();

    // Berkas yatim yang tidak dirujuk apa pun cuma memakan ruang.
    Storage::disk('public')->assertMissing($jalur);
});

test('kwitansi punya tautan pendek yang bisa dibagikan ke pelanggan', function () {
    $daftar = buatPendaftaran();

    $tautan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()->json('data.kwitansi_tautan');

    /*
     | Pendek, bukan alamat bertanda tangan Laravel yang lebih dari 200
     | karakter. Di gelembung WhatsApp yang panjang patah ke banyak baris dan
     | lebih tampak seperti tautan sampah daripada berkas resmi.
     |
     | Dan bukan alamat unduh milik admin, yang menuntut X-Orcha-Key dan
     | karenanya cuma menghasilkan penolakan di tangan pelanggan.
     */
    expect($tautan)->toContain('/t/')
        ->and(strlen($tautan))->toBeLessThan(60);

    $this->get($tautan)->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('surat pernyataan punya tautan pendek untuk dikirim ke pemesan', function () {
    $daftar = buatPendaftaran([
        'riwayat_penggantian' => [['dari' => 'Haha', 'ke' => 'Wiam']],
    ]);

    $tautan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()->json('data.surat_penggantian_kosong_tautan');

    expect($tautan)->toContain('/t/')
        ->and(strlen($tautan))->toBeLessThan(60);

    // Berkas kosong untuk dicetak dan ditandatangani — langkah pertamanya,
    // bukan salinan arsip yang baru ada sesudah pemesan mengirimkannya balik.
    $balasan = $this->get($tautan)->assertOk();

    expect(substr($balasan->streamedContent(), 0, 5))->toBe('%PDF-');
});

test('tanpa penggantian, tautan suratnya tidak ditawarkan sama sekali', function () {
    $daftar = buatPendaftaran();

    // Suratnya memang tidak bisa diterbitkan, dan menawarkan tautan yang pasti
    // berujung galat lebih buruk daripada tidak menawarkan apa pun.
    $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.surat_penggantian_kosong_tautan', null);
});

test('kode tautan yang tidak dikenal ditolak, bukan menampilkan berkas orang lain', function () {
    $this->get('/t/kodengawur1')->assertStatus(404);
});

test('tautan pendek dipakai ulang, bukan dibuat baru tiap halaman dibuka', function () {
    $daftar = buatPendaftaran();

    $pertama = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->json('data.kwitansi_tautan');
    $kedua = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->json('data.kwitansi_tautan');

    // Kalau tidak, satu pendaftaran menumpuk puluhan baris dan tautan yang
    // telanjur dikirim ke pelanggan berdampingan dengan yang belum.
    expect($kedua)->toBe($pertama)
        ->and(App\Models\Umum\TautanPendek::count())->toBe(1);
});

test('tautan pendek yang kedaluwarsa ditolak, bukan diam-diam melayani', function () {
    $daftar = buatPendaftaran();

    $tautan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->json('data.kwitansi_tautan');

    App\Models\Umum\TautanPendek::query()->update(['kedaluwarsa_pada' => now()->subDay()]);

    $this->get($tautan)->assertStatus(404);
});

test('kode tautan tidak bisa ditebak dari nomor pendaftarannya', function () {
    $daftar = buatPendaftaran();

    $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())->assertOk();

    // Berkasnya memuat nama, nomor telepon, dan rincian biaya seseorang. Kode
    // yang bisa dihitung ulang dari nomor pendaftaran berarti bisa ditebak.
    $kode = App\Models\Umum\TautanPendek::first()->kode;

    expect($kode)->not->toContain((string) $daftar->id)
        ->and(strlen($kode))->toBe(10);

    $this->get('/t/'.$daftar->id)->assertStatus(404);
});

test('surat bertanda tangan bisa diunggah, dan yang lama digantikan', function () {
    Storage::fake('public');

    $daftar = buatPendaftaran([
        'riwayat_penggantian' => [['dari' => 'Haha', 'ke' => 'Wiam']],
    ]);

    $this->post("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd", [
        'surat' => UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf'),
    ], kirim())->assertOk();

    $lama = $daftar->fresh()->surat_penggantian;

    expect($lama)->toStartWith('/storage/surat-penggantian/')
        ->and($daftar->fresh()->surat_penggantian_pada)->not->toBeNull();

    Storage::disk('public')->assertExists(str_replace('/storage/', '', $lama));

    // Hasil pindaian buram atau tanda tangan yang terlewat memang harus
    // diulang, bukan ditumpuk — dan berkas lamanya ikut dibuang, bukan
    // ditinggal yatim memakan ruang.
    $this->post("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd", [
        'surat' => UploadedFile::fake()->image('ulang.jpg'),
    ], kirim())->assertOk();

    $baru = $daftar->fresh()->surat_penggantian;

    expect($baru)->not->toBe($lama);
    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $lama));
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $baru));
});

test('surat bertanda tangan bisa dicabut bila salah unggah', function () {
    Storage::fake('public');

    $daftar = buatPendaftaran();

    $this->post("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd",
        ['surat' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')],
        kirim())->assertOk();

    $jalur = $daftar->fresh()->surat_penggantian;

    $this->delete("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd", [], kirim())
        ->assertOk();

    expect($daftar->fresh()->surat_penggantian)->toBeNull()
        ->and($daftar->fresh()->surat_penggantian_pada)->toBeNull();

    Storage::disk('public')->assertMissing(str_replace('/storage/', '', $jalur));
});

test('berkas selain pindaian dan foto ditolak', function () {
    Storage::fake('public');

    $daftar = buatPendaftaran();

    // Diterima apa adanya PDF maupun foto — memaksa satu bentuk berarti menolak
    // cara paling lazim berkasnya sampai ke admin. Tapi bukan sembarang berkas.
    $this->postJson("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd",
        ['surat' => UploadedFile::fake()->create('virus.exe', 10)], kirim())
        ->assertStatus(422);
});

test('alamat surat bertanda tangan ikut di balasan pendaftaran', function () {
    Storage::fake('public');

    $daftar = buatPendaftaran();

    $this->post("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian-ttd",
        ['surat' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')],
        kirim())->assertOk();

    // Alamat penuh, supaya lemon bisa langsung menautkannya tanpa menebak
    // host berkasnya.
    $this->getJson("/api/v1/pendaftaran/{$daftar->id}", kirim())
        ->assertOk()
        ->assertJsonPath('data.surat_penggantian', url($daftar->fresh()->surat_penggantian));
});

test('surat penggantian ditolak bila belum ada penggantian sama sekali', function () {
    $daftar = buatPendaftaran();

    $this->getJson("/api/v1/pendaftaran/{$daftar->id}/surat-penggantian", kirim())
        ->assertStatus(422);
});

test('riwayat kesehatan peserta yang diganti ditandai, bukan dihapus', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => [['nama' => 'Suparjiman'], ['nama' => 'Haha']],
    ]);

    foreach (['Suparjiman', 'Haha'] as $nama) {
        RiwayatKesehatan::create([
            'kode_pendaftaran' => $daftar->kode, 'nama_peserta' => $nama,
            'kontak_darurat_nama' => 'Budi', 'kontak_darurat_hp' => '0812',
            'setuju_data_kesehatan' => true,
        ]);
    }

    $this->patchJson("/api/v1/pendaftaran/{$daftar->id}/peserta", [
        'peserta' => [['nama' => 'Suparjiman'], ['nama' => 'Wiam']],
    ], kirim())->assertOk();

    $balasan = $this->getJson("/api/v1/pendaftaran/{$daftar->id}/riwayat-kesehatan", kirim())
        ->assertOk();

    // Datanya tetap ada — menghapus data kesehatan orang hanya karena namanya
    // dicoret dari satu daftar bukan keputusan yang diambil diam-diam.
    expect($balasan->json('data'))->toHaveCount(2);

    $perNama = collect($balasan->json('data'))->keyBy('nama_peserta');

    expect($perNama['Suparjiman']['peserta_aktif'])->toBeTrue()
        ->and($perNama['Haha']['peserta_aktif'])->toBeFalse();
});

test('surat penggantian peserta terbit sebagai pdf', function () {
    $daftar = buatPendaftaran([
        'jumlah_peserta' => 2,
        'daftar_peserta' => [['nama' => 'Suparjiman'], ['nama' => 'Wiam']],
        'riwayat_penggantian' => [['dari' => 'Haha', 'ke' => 'Wiam']],
    ]);

    $balasan = $this->get(
        "/api/v1/pendaftaran/{$daftar->id}/surat-penggantian",
        kirim()
    )->assertOk();

    expect($balasan->headers->get('content-disposition'))
        ->toContain('SURAT-PENGGANTIAN-PESERTA-'.strtoupper($daftar->kode).'.pdf');

    // PDF menandai dirinya sendiri di lima huruf pertama.
    expect(substr($balasan->streamedContent(), 0, 5))->toBe('%PDF-');
});

test('isi surat penggantian menyebut kedua nama dan kode pendaftarannya', function () {
    $daftar = buatPendaftaran(['nama' => 'Suparjiman', 'nama_paket' => 'Open Trip Banyuwangi']);

    /*
     | Isinya diperiksa dari HTML templatnya, bukan dari PDF jadinya.
     |
     | Dompdf memampatkan aliran teks di dalam PDF, jadi mencari kata di
     | berkas jadinya tidak menemukan apa-apa walau katanya benar-benar
     | tercetak. Yang perlu dijaga uji ini justru isi suratnya — bahwa
     | pasal-pasalnya ada dan namanya benar — dan itu seluruhnya ditentukan
     | templat. Bahwa PDF-nya sendiri terbentuk dijaga uji di atas.
     */
    $isi = view('pdf.surat-penggantian', [
        'pendaftaran' => $daftar,
        'riwayat' => [['dari' => 'Haha', 'ke' => 'Wiam']],
    ])->render();

    expect($isi)
        ->toContain('Surat Pernyataan')
        ->toContain('Penggantian Peserta')
        ->toContain('Haha')
        ->toContain('Wiam')
        ->toContain($daftar->kode)
        ->toContain('Suparjiman')
        // Nomor suratnya bergaya persuratan supaya bisa diarsipkan pemesan
        // institusi bersama surat-surat lain.
        ->toContain('SPP/'.$daftar->kode.'/')
        // Dasar aturannya ikut disebut supaya pemesan tidak perlu mencari sendiri.
        ->toContain('tidak dikenakan biaya tambahan sepanjang jumlah peserta')
        // Nasib data lama dinyatakan terang.
        ->toContain('tetap tersimpan sebagai arsip')
        // Enam pasal, bukan daftar butir: berkas ini ditandatangani.
        ->toContain('Pasal 6')
        ->toContain('Materai')
        // Panah U+2192 tidak ada di Helvetica bawaan PDF; dompdf mencetaknya
        // sebagai tanda tanya, dan "Jogja ? Surakarta" di surat bermaterai
        // terbaca seperti data yang rusak.
        ->not->toContain('&rarr;')
        // Kop dan kaki sebentuk kwitansi.
        ->toContain('ORCHA <span>JOURNEY</span>')
        ->toContain('Nomor Berkas');
});

test('surat penggantian tidak mengaku sah tanpa tanda tangan', function () {
    $daftar = buatPendaftaran();

    // Kwitansi memang sah tanpa tanda tangan basah, dan kalimat itu ada di
    // kakinya. Surat ini kebalikannya: ia justru menunggu tanda tangan para
    // pihak, jadi kalimat yang sama akan bertentangan dengan blok materai di
    // atasnya.
    $isi = view('pdf.surat-penggantian', [
        'pendaftaran' => $daftar,
        'riwayat' => [['dari' => 'Haha', 'ke' => 'Wiam']],
    ])->render();

    expect($isi)
        ->not->toContain('sah tanpa tanda tangan basah')
        ->toContain('berlaku setelah ditandatangani para pihak');
});

test('titik jemput yang tidak berpindah disebut apa adanya di surat', function () {
    $daftar = buatPendaftaran();

    $isi = view('pdf.surat-penggantian', [
        'pendaftaran' => $daftar,
        'riwayat' => [['dari' => 'Haha', 'ke' => 'Wiam',
            'dari_titik' => 'Jogja', 'ke_titik' => 'Jogja']],
    ])->render();

    // Menulis "Jogja dari Jogja ke Jogja" memaksa pembacanya membandingkan
    // sendiri, lalu ragu apakah itu salah cetak.
    expect($isi)->toContain('(tetap)');
});
