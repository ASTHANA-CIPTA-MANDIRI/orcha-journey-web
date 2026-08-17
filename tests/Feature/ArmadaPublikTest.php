<?php

use App\Models\SewaKendaraan\Car;
use Livewire\Volt\Volt;

/**
 * Penanda lepas kunci harus berlaku di halaman publik, bukan hanya di admin.
 *
 * Sebelumnya halaman pemesanan memakai aturannya sendiri — "hanya jenis mobil
 * yang boleh lepas kunci" — sehingga keputusan admin per unit tidak berpengaruh
 * apa pun. Dan aturan itu hanya ada di tampilan: permintaan yang dirakit tangan
 * bisa mengirim "lepas kunci" untuk bus.
 */
beforeEach(function () {
    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);
});

function unitPublik(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'mobil',
        'transmission' => 'Matic', 'transmisi_tersedia' => ['Matic'],
        'capacity' => 7, 'lepas_kunci' => true, 'price_per_day' => 400000,
        'harga_sopir' => 150000, 'is_available' => true,
    ], $ubah));
}

function isiPemesanan(Car $unit, string $sopir): array
{
    return [
        'unit' => $unit->uuid, 'transmisi' => $unit->transmisi_tersedia_list[0],
        'satuan' => 'hari', 'durasi' => 2,
        'tanggalMulai' => now()->addWeek()->toDateString(), 'jamMulai' => '08:00',
        'denganSopir' => $sopir,
        'lokasiAntar' => 'Bandara YIA', 'lokasiKembali' => 'Bandara YIA',
        'nama' => 'Budi Santoso', 'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test', 'setuju' => true,
    ];
}

test('bus tidak bisa dipesan lepas kunci walau permintaannya dirakit tangan', function () {
    $bus = unitPublik([
        'name' => 'Bus RK', 'brand' => 'Hino', 'type' => 'bus',
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 58, 'lepas_kunci' => false, 'price_per_day' => 4000000,
    ]);

    $uji = Volt::test('public.sewa-kendaraan.pemesanan');

    foreach (isiPemesanan($bus, 'tidak') as $medan => $nilai) {
        $uji->set($medan, $nilai);
    }

    // Menyembunyikan pilihannya di layar tidak cukup — dialog bukan pengaman.
    $uji->call('pesan')->assertHasErrors('denganSopir');

    expect(App\Models\SewaKendaraan\PenyewaanKendaraan::count())->toBe(0);
});

test('unit lepas kunci tetap bisa dipesan tanpa sopir', function () {
    $mobil = unitPublik();

    $uji = Volt::test('public.sewa-kendaraan.pemesanan');

    foreach (isiPemesanan($mobil, 'tidak') as $medan => $nilai) {
        $uji->set($medan, $nilai);
    }

    $uji->call('pesan')->assertHasNoErrors();

    expect(App\Models\SewaKendaraan\PenyewaanKendaraan::first()->dengan_sopir)->toBeFalse();
});

test('mobil yang admin tandai selalu dengan sopir ikut ditolak', function () {
    // Inti sinkronisasinya: keputusan per unit di admin, bukan aturan jenis yang
    // diulang di halaman publik.
    $mobil = unitPublik(['lepas_kunci' => false]);

    $uji = Volt::test('public.sewa-kendaraan.pemesanan');

    foreach (isiPemesanan($mobil, 'tidak') as $medan => $nilai) {
        $uji->set($medan, $nilai);
    }

    $uji->call('pesan')->assertHasErrors('denganSopir');
});

test('berpindah ke unit tanpa lepas kunci memaksa pilihan sopir', function () {
    unitPublik();
    $hiace = unitPublik([
        'name' => 'HiAce Commuter', 'type' => 'hiace', 'transmission' => 'Manual',
        'transmisi_tersedia' => ['Manual'], 'capacity' => 14, 'lepas_kunci' => false,
        'price_per_day' => 1200000,
    ]);

    // Tanpa ini, "lepas kunci" tertinggal terpilih pada unit yang tidak
    // melayaninya — dan perkiraan biayanya ikut salah.
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('denganSopir', 'tidak')
        ->set('unit', $hiace->uuid)
        ->assertSet('denganSopir', 'ya');
});

test('kartu publik menyebut penumpang, tipe, tahun, dan cc', function () {
    unitPublik([
        'name' => 'HiAce Commuter', 'varian' => 'Standar', 'tahun' => 2023, 'cc' => 2500,
        'type' => 'hiace', 'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 14, 'lepas_kunci' => false, 'price_per_day' => 1200000,
    ]);

    $this->get(route('sewa-kendaraan'))
        ->assertOk()
        ->assertSee('14 penumpang')
        ->assertSee('(15 kursi)')
        ->assertSee('Selalu dengan sopir')
        ->assertSee('Standar')
        ->assertSee('2023')
        ->assertSee('2.500 cc');
});

test('unit lepas kunci tidak dilencanai dan tidak mengulang angka kursi', function () {
    unitPublik(['capacity' => 7]);

    $halaman = $this->get(route('sewa-kendaraan'))->assertOk();

    $halaman->assertSee('7 penumpang')
        ->assertDontSee('(7 kursi)')
        ->assertDontSee('Selalu dengan sopir');
});

test('unit lama tanpa tahun dan cc tetap terbaca wajar di publik', function () {
    unitPublik(['tahun' => null, 'cc' => null, 'varian' => null]);

    $this->get(route('sewa-kendaraan'))->assertOk()->assertSee('Toyota');
});

/* -------- BBM, TOL, PARKIR -------- */

test('kartu publik menyebut keadaan BBM tol parkir apa adanya', function (bool $termasuk, ?int $biaya, string $harapan) {
    unitPublik(['termasuk_operasional' => $termasuk, 'biaya_operasional' => $biaya]);

    $this->get(route('sewa-kendaraan'))->assertOk()->assertSee($harapan);
})->with([
    [false, null, 'BBM, tol, dan parkir ditanggung penyewa'],
    [true, 250000, 'BBM, tol, dan parkir termasuk (+Rp 250.000/hari)'],
    // Nol berarti termasuk tanpa tambahan — sah untuk unit yang tarifnya sudah
    // dihitung all-in sejak awal.
    [true, null, 'BBM, tol, dan parkir sudah termasuk harga sewa'],
]);

test('biaya all-in ikut dihitung di perkiraan biaya', function () {
    $unit = unitPublik([
        'price_per_day' => 500000, 'harga_sopir' => 150000,
        'termasuk_operasional' => true, 'biaya_operasional' => 250000,
    ]);

    // Perkiraan yang melewatkannya menampilkan angka lebih rendah daripada yang
    // ditagihkan, dan selisihnya baru ketahuan saat pembayaran.
    expect($unit->estimasiBiaya('hari', 2, true))->toBe(1_800_000)
        ->and($unit->estimasiBiaya('hari', 2, false))->toBe(1_500_000);
});

test('unit tanpa paket all-in tidak menambah apa pun ke perkiraan', function () {
    $unit = unitPublik([
        'price_per_day' => 500000, 'harga_sopir' => 150000,
        'termasuk_operasional' => false, 'biaya_operasional' => null,
    ]);

    expect($unit->estimasiBiaya('hari', 2, true))->toBe(1_300_000);
});

test('sewa per jam menghitung biaya all-in satu hari kerja', function () {
    $unit = unitPublik([
        'harga_per_jam' => 60000, 'price_per_day' => 500000,
        'termasuk_operasional' => true, 'biaya_operasional' => 200000,
    ]);

    // Mengikuti perlakuan sopir: satuan jam tetap satu hari kerja, karena BBM
    // dan tol tidak dihitung per jam di praktiknya.
    expect($unit->estimasiBiaya('jam', 5, false))->toBe(300000 + 200000);
});

test('nominal all-in tidak tersimpan bila paketnya tidak termasuk', function () {
    // Angka siluman pada unit non-all-in akan ikut terpakai begitu penandanya
    // dinyalakan lagi, dan pemiliknya tidak ingat pernah mengisinya.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Avanza', 'merek' => 'Toyota', 'jenis' => 'mobil',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Matic'], 'tarif_hari' => 400000,
        'termasuk_operasional' => false, 'biaya_operasional' => 250000,
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertCreated();

    expect(Car::first()->biaya_operasional)->toBeNull();
});

test('halaman daftar mengakui sebagian unit all-in', function () {
    unitPublik();

    // Tanpa catatan ini, daftar "Belum termasuk" menyatakan hal yang
    // bertentangan dengan kartu unitnya sendiri.
    $this->get(route('sewa-kendaraan'))->assertOk()
        ->assertSee('Sebagian unit ditawarkan all-in');
});
