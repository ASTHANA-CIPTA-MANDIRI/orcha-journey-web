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

/* -------- BBM, TOL, PARKIR — TIGA POS TERPISAH -------- */

test('kartu publik menyebut pos mana termasuk dan mana ditanggung penyewa', function (array $pos, string $harapan) {
    unitPublik($pos);

    $this->get(route('sewa-kendaraan'))->assertOk()->assertSee($harapan);
})->with([
    'tidak ada yang termasuk' => [
        [], 'BBM, tol, dan parkir ditanggung penyewa',
    ],
    'ketiganya termasuk' => [
        ['termasuk_bbm' => true, 'biaya_bbm' => 200000,
            'termasuk_tol' => true, 'biaya_tol' => 100000,
            'termasuk_parkir' => true, 'biaya_parkir' => 50000],
        'BBM, tol, dan parkir termasuk (+Rp 350.000/hari)',
    ],
    // Inti pemisahannya: keadaan ini tidak bisa dinyatakan sama sekali dengan
    // satu penanda gabungan.
    'sebagian termasuk' => [
        ['termasuk_bbm' => true, 'biaya_bbm' => 200000,
            'termasuk_tol' => true, 'biaya_tol' => 100000],
        'BBM dan tol termasuk (+Rp 300.000/hari) · parkir ditanggung penyewa',
    ],
    'termasuk tanpa tambahan biaya' => [
        ['termasuk_bbm' => true],
        'BBM termasuk · tol dan parkir ditanggung penyewa',
    ],
]);

test('biaya tiap pos yang termasuk dijumlahkan ke perkiraan', function () {
    $unit = unitPublik([
        'price_per_day' => 500000, 'harga_sopir' => 150000,
        'termasuk_bbm' => true, 'biaya_bbm' => 200000,
        'termasuk_tol' => true, 'biaya_tol' => 100000,
    ]);

    // 2 hari: (500.000 + 150.000 + 200.000 + 100.000) x 2
    expect($unit->biaya_operasional_total)->toBe(300000)
        ->and($unit->estimasiBiaya('hari', 2, true))->toBe(1_900_000)
        ->and($unit->estimasiBiaya('hari', 2, false))->toBe(1_600_000);
});

test('biaya pada pos yang tidak termasuk tidak ikut ditagihkan', function () {
    // Angka yang tertinggal di sana bukan tagihan — pos itu ditanggung penyewa.
    $unit = unitPublik([
        'price_per_day' => 500000,
        'termasuk_bbm' => false, 'biaya_bbm' => 999999,
    ]);

    expect($unit->biaya_operasional_total)->toBe(0)
        ->and($unit->estimasiBiaya('hari', 1, false))->toBe(500000);
});

test('sewa per jam menghitung biaya pos satu hari kerja', function () {
    $unit = unitPublik([
        'harga_per_jam' => 60000, 'price_per_day' => 500000,
        'termasuk_bbm' => true, 'biaya_bbm' => 200000,
    ]);

    // Mengikuti perlakuan sopir: BBM dan tol tidak dihitung per jam di praktiknya.
    expect($unit->estimasiBiaya('jam', 5, false))->toBe(300000 + 200000);
});

test('nominal pos tidak tersimpan bila posnya ditanggung penyewa', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Avanza', 'merek' => 'Toyota', 'jenis' => 'mobil',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Matic'], 'tarif_hari' => 400000,
        'termasuk_bbm' => true, 'biaya_bbm' => 200000,
        'termasuk_tol' => false, 'biaya_tol' => 100000,
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertCreated();

    $unit = Car::first();

    expect($unit->biaya_bbm)->toBe(200000)
        ->and($unit->termasuk_tol)->toBeFalse()
        ->and($unit->biaya_tol)->toBeNull();
});

test('perincian pos terkirim di resource', function () {
    unitPublik(['termasuk_bbm' => true, 'biaya_bbm' => 200000]);

    $baris = $this->getJson('/api/v1/kendaraan', [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0');

    expect($baris['operasional']['bbm'])->toMatchArray(['label' => 'BBM', 'termasuk' => true, 'biaya' => 200000])
        ->and($baris['operasional']['tol']['termasuk'])->toBeFalse()
        ->and($baris['biaya_operasional_total'])->toBe(200000);
});

test('halaman daftar mengakui sebagian unit all-in', function () {
    unitPublik();

    // Tanpa catatan ini, daftar "Belum termasuk" menyatakan hal yang
    // bertentangan dengan kartu unitnya sendiri.
    $this->get(route('sewa-kendaraan'))->assertOk()
        ->assertSee('Sebagian unit ditawarkan all-in');
});

/* -------- TARIF SUDAH TERMASUK SOPIR -------- */

test('keterangan sopir menyebut tiga keadaan yang berbeda', function (array $ubah, string $harapan) {
    unitPublik($ubah);

    // Sebelumnya dua di antaranya dinyatakan dengan cara yang sama: harga_sopir
    // kosong bisa berarti "sudah termasuk" atau "belum diisi".
    $this->get(route('sewa-kendaraan'))->assertOk()->assertSee($harapan);
})->with([
    'sudah termasuk' => [['termasuk_sopir' => true, 'harga_sopir' => null], 'Harga sudah termasuk sopir'],
    'tambahan' => [['termasuk_sopir' => false, 'harga_sopir' => 150000], 'Sopir +Rp 150.000/hari'],
    'tidak tersedia' => [['termasuk_sopir' => false, 'harga_sopir' => null], 'Tanpa sopir'],
]);

test('tarif yang sudah termasuk sopir tidak ditagih dua kali', function () {
    $unit = unitPublik([
        'price_per_day' => 2500000, 'termasuk_sopir' => true, 'harga_sopir' => null,
        'lepas_kunci' => false,
    ]);

    // 2 hari x 2.500.000, tanpa tambahan sopir walau penyewaannya pasti bersopir.
    expect($unit->estimasiBiaya('hari', 2, true))->toBe(5_000_000)
        ->and($unit->sopir_label)->toBe('Harga sudah termasuk sopir');
});

test('tarif sopir yang tertinggal tidak ditagihkan bila sudah termasuk', function () {
    // Angka siluman: ikut ditagihkan begitu penandanya dimatikan, dan pemiliknya
    // tidak ingat pernah mengisinya.
    $unit = unitPublik([
        'price_per_day' => 2500000, 'termasuk_sopir' => true, 'harga_sopir' => 150000,
    ]);

    expect($unit->estimasiBiaya('hari', 1, true))->toBe(2_500_000);
});

test('tarif sopir tidak tersimpan bila sudah termasuk', function () {
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'HiAce Commuter', 'merek' => 'Toyota', 'jenis' => 'hiace',
        'kapasitas' => 14, 'lepas_kunci' => false, 'transmisi_tersedia' => ['Manual'],
        'tarif_hari' => 2500000, 'termasuk_sopir' => true, 'tarif_sopir' => 150000,
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertCreated();

    expect(Car::first()->harga_sopir)->toBeNull()
        ->and(Car::first()->termasuk_sopir)->toBeTrue();
});

test('unit selalu dengan sopir wajib menyebut biaya sopirnya', function () {
    // Tanpa keduanya, halaman publik menampilkan unit yang pasti bersopir tanpa
    // keterangan biaya sopirnya sama sekali.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Bus RK', 'merek' => 'Hino', 'jenis' => 'bus',
        'kapasitas' => 58, 'lepas_kunci' => false, 'transmisi_tersedia' => ['Manual'],
        'tarif_hari' => 4000000,
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertStatus(422)->assertJsonValidationErrors('termasuk_sopir');
});

test('unit lepas kunci boleh tanpa keterangan sopir', function () {
    // Mobil yang memang hanya disewakan lepas kunci tidak perlu menyebut sopir.
    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Avanza', 'merek' => 'Toyota', 'jenis' => 'mobil',
        'kapasitas' => 7, 'lepas_kunci' => true, 'transmisi_tersedia' => ['Matic'],
        'tarif_hari' => 400000,
    ], [
        'X-Orcha-Key' => config('orcha.api.kunci'),
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertCreated();
});

/* -------- TATA LETAK KARTU: TOMBOL SEBARIS -------- */

test('blok tarif menyerap sisa ruang supaya tombol tiap kartu sebaris', function () {
    // Kartu dalam satu baris grid selalu setinggi kartu tertinggi, tetapi isinya
    // tidak sama panjang: ada unit dengan tiga satuan tarif, ada yang hanya
    // harian. Tanpa satu elemen yang menyerap sisa ruang, tombol "Pesan Unit Ini"
    // berhenti di ketinggian yang berbeda-beda dan deretannya terlihat berantakan
    // — persis yang dikeluhkan. Sisa ruang diserap my-auto pada baris
    // spesifikasi, jadi habis SEBELUM kotak tarif dan semua yang sesudahnya
    // menempel ke dasar kartu. my-auto, bukan mt-auto: sisanya dibagi ke atas
    // dan ke bawah supaya tidak menumpuk jadi satu celah di atas harga.
    $kartu = file_get_contents(base_path('resources/views/components/sewa-kendaraan/kartu.blade.php'));

    expect($kartu)->toContain('my-auto')
        ->and($kartu)->toContain('h-full');

    // Urutannya yang menentukan: penyerap sisa ruang dulu, lalu kotak tarif,
    // lalu tombol sebagai elemen terakhir. Menyisipkan apa pun sesudah tombol
    // membatalkan penjajaran itu.
    $posisiPenyerap = strpos($kartu, 'my-auto');
    $posisiTarif = strpos($kartu, 'rounded-2xl bg-orcha-foam');
    $posisiTombol = strpos($kartu, 'Pesan Unit Ini');

    expect($posisiTarif)->toBeGreaterThan($posisiPenyerap)
        ->and($posisiTombol)->toBeGreaterThan($posisiTarif);
});

test('transmisi tidak ditulis dua kali di satu kartu', function () {
    unitPublik(['transmisi_tersedia' => ['Manual']]);

    // Dulu transmisi muncul sebagai label di atas foto DAN di baris spesifikasi.
    // Keterangan yang sama dua kali membuat kartunya padat tanpa menambah apa pun.
    $teks = preg_replace('/\s+/', ' ', strip_tags($this->get(route('sewa-kendaraan'))->assertOk()->getContent()));

    expect(substr_count($teks, 'Manual'))->toBe(1);
});
