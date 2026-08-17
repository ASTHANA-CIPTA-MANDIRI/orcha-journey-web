<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

/**
 * Sewa bersopir bertanya tujuan dan titik penjemputan, bukan alamat serah unit.
 *
 * Untuk HiAce dan bus, unitnya tidak diserahkan ke penyewa. Menanyakan "lokasi
 * pengantaran unit" menghasilkan jawaban yang tidak dipakai siapa pun, sementara
 * tujuannya — yang menentukan lama jalan, BBM, dan kesiapan sopir — tidak pernah
 * tercatat sama sekali.
 */
function unitSewa(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'HiAce Commuter', 'brand' => 'Toyota', 'type' => 'hiace',
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 14, 'lepas_kunci' => false, 'termasuk_sopir' => true,
        'price_per_day' => 1200000, 'is_available' => true,
    ], $ubah));
}

function isiSewa(Car $unit, array $ubah = []): array
{
    return array_merge([
        'unit' => $unit->uuid, 'transmisi' => $unit->transmisi_tersedia_list[0],
        'satuan' => 'hari', 'durasi' => 2,
        'tanggalMulai' => now()->addWeek()->toDateString(), 'jamMulai' => '07:00',
        'lokasiAntar' => 'Hotel Malioboro Yogyakarta',
        'nama' => 'Budi Santoso', 'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test', 'setuju' => true,
    ], $ubah);
}

function kirimSewa(array $isi)
{
    $uji = Volt::test('public.sewa-kendaraan.pemesanan');

    foreach ($isi as $medan => $nilai) {
        $uji->set($medan, $nilai);
    }

    return $uji->call('pesan');
}

test('formulir menanyakan tujuan untuk sewa bersopir', function () {
    $unit = unitSewa();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        // Unit yang selalu dengan sopir memaksa modanya, jadi isiannya berganti.
        ->assertSet('denganSopir', 'ya')
        ->assertSee('Titik penjemputan')
        ->assertSee('Tujuan perjalanan')
        ->assertDontSee('Lokasi pengembalian unit');
});

test('formulir tetap menanyakan antar dan ambil untuk lepas kunci', function () {
    $mobil = unitSewa([
        'name' => 'Avanza', 'type' => 'mobil', 'transmission' => 'Matic',
        'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'lepas_kunci' => true, 'termasuk_sopir' => false, 'harga_sopir' => 150000,
    ]);

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $mobil->uuid)
        ->set('denganSopir', 'tidak')
        ->assertSee('Lokasi pengantaran unit')
        ->assertSee('Lokasi pengembalian unit')
        ->assertDontSee('Tujuan perjalanan');
});

test('tujuan wajib diisi pada sewa bersopir', function () {
    $unit = unitSewa();

    kirimSewa(isiSewa($unit))->assertHasErrors('tujuan');

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('tujuan tersimpan dan lokasi pengembalian mengikuti titik penjemputan', function () {
    $unit = unitSewa();

    kirimSewa(isiSewa($unit, ['tujuan' => 'Borobudur — Dieng']))->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::first();

    // lokasi_kembali diisi dari titik penjemputan karena isiannya memang tidak
    // ditanyakan pada moda ini — celahnya diisi, bukan menimpa yang diberikan.
    expect($sewa->tujuan)->toBe('Borobudur — Dieng')
        ->and($sewa->lokasi_antar)->toBe('Hotel Malioboro Yogyakarta')
        ->and($sewa->lokasi_kembali)->toBe('Hotel Malioboro Yogyakarta');
});

test('lokasi pengembalian wajib pada lepas kunci, tujuan tidak', function () {
    $mobil = unitSewa([
        'name' => 'Avanza', 'type' => 'mobil', 'transmission' => 'Matic',
        'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'lepas_kunci' => true, 'termasuk_sopir' => false, 'harga_sopir' => 150000,
    ]);

    kirimSewa(isiSewa($mobil, ['denganSopir' => 'tidak']))
        ->assertHasErrors('lokasiKembali')
        ->assertHasNoErrors('tujuan');
});

test('sewa lepas kunci tidak menyimpan tujuan', function () {
    $mobil = unitSewa([
        'name' => 'Avanza', 'type' => 'mobil', 'transmission' => 'Matic',
        'transmisi_tersedia' => ['Matic'], 'capacity' => 7,
        'lepas_kunci' => true, 'termasuk_sopir' => false, 'harga_sopir' => 150000,
    ]);

    kirimSewa(isiSewa($mobil, [
        'denganSopir' => 'tidak',
        'lokasiKembali' => 'Bandara YIA',
        // Sengaja diisi: nilainya tidak boleh ikut tersimpan pada moda ini,
        // karena sewa lepas kunci memang tidak punya tujuan yang dicatat.
        'tujuan' => 'Bromo',
    ]))->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::first();

    expect($sewa->tujuan)->toBeNull()
        ->and($sewa->lokasi_kembali)->toBe('Bandara YIA');
});

test('pesan galat menyebut nama medan sesuai modanya', function () {
    $unit = unitSewa();

    // "lokasi pengantaran unit" pada sewa bus membingungkan: tidak ada unit yang
    // diantar ke siapa pun.
    $hasil = kirimSewa(isiSewa($unit, ['lokasiAntar' => '', 'tujuan' => 'Bromo']));

    expect($hasil->errors()->first('lokasiAntar'))->toContain('titik penjemputan');
});

test('rincian pesanan bersopir menyebut penjemputan dan tujuan', function () {
    $unit = unitSewa();

    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::first();

    // Kwitansi sewa bus yang menulis "Lokasi pengantaran unit" membingungkan
    // penyewa maupun sopir.
    expect($sewa->dengan_sopir)->toBeTrue()
        ->and($sewa->tujuan)->toBe('Bromo');
});

test('tujuan terkirim lewat resource ke admin lemon', function () {
    $unit = unitSewa();
    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $baris = $this->getJson('/api/v1/penyewaan', [
        'X-Orcha-Key' => 'kunci-rahasia-untuk-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0');

    expect($baris['tujuan'])->toBe('Bromo');
});

test('lokasi pengembalian yang benar-benar diberikan tidak ditimpa', function () {
    $unit = unitSewa();

    // Isiannya tidak ditanyakan pada sewa bersopir, tetapi bila ada nilai yang
    // sengaja ditulis — misalnya turun di bandara sepulang perjalanan —
    // membuangnya berarti menghilangkan keterangan yang dimaksudkan.
    kirimSewa(isiSewa($unit, [
        'tujuan' => 'Bromo', 'lokasiKembali' => 'Bandara YIA',
    ]))->assertHasNoErrors();

    expect(PenyewaanKendaraan::first()->lokasi_kembali)->toBe('Bandara YIA');
});
