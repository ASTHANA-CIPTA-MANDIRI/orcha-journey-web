<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

function buatMobil(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza Uji',
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
    ], $ubah));
}

/* ---------------------------- TARIF ---------------------------- */

test('tarif per jam, 12 jam, dan per hari disimpan terpisah', function () {
    $mobil = buatMobil();

    expect($mobil->tarif('jam'))->toBe(55000)
        ->and($mobil->tarif('12jam'))->toBe(280000)
        ->and($mobil->tarif('hari'))->toBe(350000);
});

test('estimasi biaya menghitung durasi dan biaya sopir', function () {
    $mobil = buatMobil();

    // 6 jam × 55.000 + sopir 1 hari 150.000
    expect($mobil->estimasiBiaya('jam', 6, true))->toBe(480000)
        // 3 hari × 350.000 + sopir 3 hari
        ->and($mobil->estimasiBiaya('hari', 3, true))->toBe(1500000)
        // tanpa sopir
        ->and($mobil->estimasiBiaya('hari', 2, false))->toBe(700000);
});

test('satuan yang tidak dijual mengembalikan null', function () {
    $bus = buatMobil(['name' => 'Big Bus Uji', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    expect($bus->tarif('jam'))->toBeNull()
        ->and($bus->estimasiBiaya('jam', 5))->toBeNull();
});

/* ------------------------- TAMPILAN DAFTAR ------------------------- */

test('kartu daftar menampilkan semua transmisi yang tersedia dan tarifnya', function () {
    buatMobil();

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('Manual &amp; Matic', false)   // bukan cuma "Manual"
        ->assertSee('Per jam')
        ->assertSee('Rp 55.000')
        ->assertSee('Per hari (24 jam)')
        ->assertSee('Rp 350.000');
});

test('unit satu transmisi hanya menampilkan transmisi itu', function () {
    buatMobil(['name' => 'HiAce Uji', 'type' => 'hiace', 'transmisi_tersedia' => ['Manual']]);

    $this->get(route('sewa-kendaraan', 'hiace'))
        ->assertOk()
        ->assertSee('HiAce Uji')
        ->assertDontSee('Manual &amp; Matic', false);
});

/* ------------------------ FORMULIR PEMESANAN ------------------------ */

test('formulir sewa bisa dibuka dan memakai uuid unit', function () {
    $mobil = buatMobil();

    $this->get(route('sewa-kendaraan.pesan'))->assertOk();

    $this->get(route('sewa-kendaraan.pesan', ['unit' => $mobil->uuid]))
        ->assertOk()
        ->assertSee('Avanza Uji');

    expect($mobil->uuid)->toHaveLength(36);
});

test('pemesanan sewa tersimpan lengkap dengan estimasi biaya', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'jam')
        ->set('durasi', 6)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::firstOrFail();

    expect($sewa->kode)->toStartWith('SK-')
        ->and($sewa->nama_kendaraan)->toBe('Avanza Uji')
        ->and($sewa->transmisi)->toBe('Matic')
        ->and($sewa->satuan)->toBe('jam')
        ->and($sewa->durasi)->toBe(6)
        ->and($sewa->dengan_sopir)->toBeTrue()
        ->and($sewa->estimasi_biaya)->toBe(480000)
        ->and($sewa->status)->toBe('baru');
});

test('pemesanan menolak transmisi yang tidak tersedia pada unit', function () {
    $mobil = buatMobil(['transmisi_tersedia' => ['Manual']]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 1)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasErrors(['transmisi']);

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('pemesanan menolak satuan yang tidak dijual unit itu', function () {
    $bus = buatMobil(['name' => 'Bus Uji', 'type' => 'bus', 'harga_per_jam' => null, 'transmisi_tersedia' => ['Manual']]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $bus->uuid)
        ->set('transmisi', 'Manual')
        ->set('satuan', 'jam')
        ->set('durasi', 5)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasErrors(['satuan']);

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('pemesanan menolak isian yang tidak lengkap', function () {
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('nama', 'A')
        ->set('tanggalMulai', now()->subWeek()->toDateString())
        ->call('pesan')
        ->assertHasErrors(['unit', 'transmisi', 'tanggalMulai', 'nama', 'whatsapp', 'setuju']);
});

/* --------------------- PENATAAN BERKAS PER FITUR --------------------- */

test('berkas blade dikelompokkan per fitur', function () {
    $wajibAda = [
        'resources/views/livewire/public/sewa-kendaraan/index.blade.php',
        'resources/views/livewire/public/sewa-kendaraan/pemesanan.blade.php',
        'resources/views/livewire/public/paket-wisata/index.blade.php',
        'resources/views/livewire/public/paket-wisata/detail.blade.php',
        'resources/views/livewire/public/open-trip/pendaftaran.blade.php',
        'resources/views/livewire/public/informasi/faq.blade.php',
        'resources/views/livewire/admin/sewa-kendaraan/index.blade.php',
        'resources/views/livewire/admin/penyewaan/index.blade.php',
        'resources/views/components/sewa-kendaraan/kartu.blade.php',
        'resources/views/components/paket-wisata/kartu.blade.php',
    ];

    foreach ($wajibAda as $berkas) {
        expect(file_exists(base_path($berkas)))->toBeTrue("$berkas tidak ditemukan");
    }

    // Tidak ada lagi berkas publik yang menggantung di luar folder fitur
    $menggantung = glob(base_path('resources/views/livewire/public/*.blade.php'));
    expect($menggantung)->toBeEmpty();
});

/* ---------------- PENGEMBALIAN, DENDA & PEMERIKSAAN FISIK ---------------- */

test('tenggat pengembalian dihitung dan disimpan saat memesan', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'jam')
        ->set('durasi', 6)
        ->set('tanggalMulai', '2026-09-10')
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('lokasiAntar', 'Bandara YIA')
        ->set('lokasiKembali', 'Kantor Orcha')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::firstOrFail();

    // Sewa 6 jam mulai 07.00 → kembali 13.00 di hari yang sama
    expect($sewa->tanggal_selesai->toDateString())->toBe('2026-09-10')
        ->and(substr((string) $sewa->jam_selesai, 0, 5))->toBe('13:00')
        ->and($sewa->lokasi_kembali)->toBe('Kantor Orcha')
        ->and($sewa->email)->toBe('budi@contoh.test');
});

test('email dan kedua lokasi wajib diisi', function () {
    $mobil = buatMobil();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('transmisi', 'Matic')
        ->set('satuan', 'hari')
        ->set('durasi', 1)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('denganSopir', 'ya')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasErrors(['email', 'lokasiAntar', 'lokasiKembali']);
});

test('durasi harian menghasilkan tenggat pada jam yang sama', function () {
    $selesai = PenyewaanKendaraan::hitungSelesai('2026-09-10', '08:00', 'hari', 3);

    expect($selesai->format('Y-m-d H:i'))->toBe('2026-09-13 08:00');
});

test('terlambat dalam tenggang tidak didenda', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-11 08:20',   // telat 20 menit
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Macet di jalan bukan hal yang pantas didendakan
    expect($sewa->terlambat_menit)->toBe(20)
        ->and($sewa->terlambat)->toBeFalse()
        ->and($sewa->denda_keterlambatan_usulan)->toBe(0);
});

test('denda keterlambatan dihitung per jam dari tarif harian', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-11 11:00',   // telat 3 jam
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Lewat tenggang 30 menit → 2,5 jam dibulatkan 3 jam × 10% × 500.000
    expect($sewa->denda_keterlambatan_usulan)->toBe(150000);
});

test('denda keterlambatan dibatasi tarif sehari per hari telat', function () {
    $mobil = buatMobil(['price_per_day' => 500000]);

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'tanggal_selesai' => '2026-09-11', 'jam_selesai' => '08:00',
        'dikembalikan_pada' => '2026-09-12 08:00',   // telat sehari penuh
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
    ]);

    // Tanpa batas, 24 jam × 10% = 240% tarif harian untuk telat sehari
    expect($sewa->denda_keterlambatan_usulan)->toBe(500000);
});

test('hanya kerusakan baru yang ditagihkan ke penyewa', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'berjalan',
        // Bodi kanan SUDAH lecet sebelum unit diserahkan
        'kondisi_awal' => ['bodi_kanan' => 'lecet', 'kaca' => 'baik', 'ban' => 'baik'],
        'kondisi_akhir' => ['bodi_kanan' => 'lecet', 'kaca' => 'rusak', 'ban' => 'baik'],
    ]);

    $baru = $sewa->kerusakan_baru;

    // Lecet lama tidak ikut terhitung; hanya kaca yang memburuk
    expect($baru)->toHaveCount(1)
        ->and($baru[0]['bagian'])->toBe('Kaca & spion')
        ->and($baru[0]['dari'])->toBe('Baik')
        ->and($baru[0]['jadi'])->toBe('Rusak');
});

test('total tagihan menjumlahkan sewa dengan seluruh denda', function () {
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id, 'nama_kendaraan' => $mobil->name, 'nama' => 'Budi',
        'whatsapp' => '0812', 'email' => 'a@b.test', 'transmisi' => 'Matic',
        'satuan' => 'hari', 'durasi' => 1,
        'tanggal_mulai' => '2026-09-10', 'jam_mulai' => '08:00',
        'estimasi_biaya' => 500000, 'status' => 'selesai',
        'denda_keterlambatan' => 150000, 'denda_kerusakan' => 300000, 'denda_lain' => 50000,
    ]);

    expect($sewa->total_denda)->toBe(500000)
        ->and($sewa->total_tagihan)->toBe(1000000);
});
