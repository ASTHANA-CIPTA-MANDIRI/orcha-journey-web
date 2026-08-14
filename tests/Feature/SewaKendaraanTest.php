<?php

use App\Models\Car;
use App\Models\PenyewaanKendaraan;
use App\Models\User;
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

/* ------------------------------ ADMIN ------------------------------ */

test('admin bisa membuka dan mengubah status pemesanan sewa', function () {
    $this->actingAs(User::factory()->create());
    $mobil = buatMobil();

    $sewa = PenyewaanKendaraan::create([
        'car_id' => $mobil->id,
        'nama_kendaraan' => $mobil->name,
        'nama' => 'Budi Santoso',
        'whatsapp' => '081234567890',
        'transmisi' => 'Manual',
        'satuan' => 'hari',
        'durasi' => 2,
        'tanggal_mulai' => now()->addWeek()->toDateString(),
        'jam_mulai' => '08:00',
        'dengan_sopir' => true,
        'estimasi_biaya' => 1000000,
    ]);

    $this->get('/admin/penyewaan')->assertOk()->assertSee('Budi Santoso');

    Volt::test('admin.penyewaan.index')
        ->call('buka', $sewa->id)
        ->set('statusBaru', 'dikonfirmasi')
        ->call('simpanStatus');

    expect($sewa->fresh()->status)->toBe('dikonfirmasi');
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
