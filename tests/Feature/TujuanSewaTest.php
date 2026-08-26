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

test('perincian estimasi ikut terkirim ke admin lemon', function () {
    $unit = unitSewa();
    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    // Admin yang ditanya "kok segitu?" saat menagih tidak punya jawabannya
    // kalau yang sampai ke lemon cuma satu bilangan.
    $baris = $this->getJson('/api/v1/penyewaan', [
        'X-Orcha-Key' => 'kunci-rahasia-untuk-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0');

    expect($baris['rincian_estimasi'])->not->toBeEmpty()
        ->and($baris['rincian_estimasi'][0])->toHaveKeys(['label', 'keterangan', 'jumlah'])
        ->and(array_sum(array_column($baris['rincian_estimasi'], 'jumlah')))
        ->toBe($baris['estimasi_biaya']);
});

test('apa saja yang termasuk dibaca menurut wilayah pesanannya', function () {
    // Unit yang DALAM kota diserahkan apa adanya, tetapi untuk luar kota
    // ditawarkan sepaket bersama BBM. Aturan yang salah wilayah menjanjikan hal
    // yang tidak berlaku bagi penyewa yang memegang suratnya.
    $unit = unitSewa([
        'termasuk_bbm' => false,
        'luar_termasuk_bbm' => true,
        'luar_biaya_bbm' => 300000,
        'tarif_luar_kota' => 1500000,
    ]);

    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo', 'luarKota' => true]))->assertHasNoErrors();

    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $baris = $this->getJson('/api/v1/penyewaan', [
        'X-Orcha-Key' => 'kunci-rahasia-untuk-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0');

    $bbm = collect($baris['kendaraan']['termasuk'])->firstWhere('label', 'BBM');

    expect($bbm['termasuk'])->toBeTrue()
        ->and($bbm['catatan'])->toContain('300.000');
});

test('sewa lepas kunci tidak menyebut sopir di daftar yang termasuk', function () {
    $unit = unitSewa(['lepas_kunci' => true, 'termasuk_sopir' => false, 'harga_sopir' => 200000]);

    kirimSewa(isiSewa($unit, ['denganSopir' => 'tidak', 'lokasiKembali' => 'Kantor Orcha']))
        ->assertHasNoErrors();

    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $baris = $this->getJson('/api/v1/penyewaan', [
        'X-Orcha-Key' => 'kunci-rahasia-untuk-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0');

    // Pada lepas kunci sopir bukan pos yang "tidak termasuk" melainkan pos yang
    // tidak ada: unitnya memang disetir penyewa sendiri.
    expect(collect($baris['kendaraan']['termasuk'])->pluck('label'))
        ->not->toContain('Sopir');
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

/* -------- SUREL SELARAS DENGAN PUBLIK & ADMIN -------- */

test('rincian surat menyebut sebutan lengkap unit, bukan nama model saja', function () {
    $unit = unitSewa(['varian' => 'Standar', 'tahun' => 2023, 'cc' => 2500]);

    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    // Surat yang hanya menulis "HiAce Commuter" tidak menyebut merek, tipe,
    // tahun, maupun cc — padahal itu yang dipakai penyewa memastikan unit yang
    // datang memang benar.
    expect($unit->fresh()->sebutan_lengkap)
        ->toBe('Toyota HiAce Commuter Standar 2023 · 2.500 cc');
});

test('keterangan unit ikut terkirim ke admin lewat resource', function () {
    config()->set('orcha.api.kunci', 'kunci-rahasia-untuk-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $unit = unitSewa([
        'varian' => 'Standar', 'tahun' => 2023, 'cc' => 2500,
        'termasuk_bbm' => true, 'biaya_bbm' => 200000,
    ]);
    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    $baris = $this->getJson('/api/v1/penyewaan', [
        'X-Orcha-Key' => 'kunci-rahasia-untuk-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test', 'Accept' => 'application/json',
    ])->assertOk()->json('data.0.kendaraan');

    // Admin harus membaca hal yang sama dengan yang tertulis di surat penyewa.
    expect($baris['sebutan'])->toBe('Toyota HiAce Commuter Standar 2023 · 2.500 cc')
        ->and($baris['kapasitas'])->toBe(14)
        ->and($baris['kursi_total'])->toBe(15)
        ->and($baris['sopir_label'])->toBe('Harga sudah termasuk sopir')
        ->and($baris['operasional_label'])->toContain('BBM termasuk');
});

test('nama kendaraan pada penyewaan tetap jejak, tidak ikut berubah', function () {
    $unit = unitSewa();
    kirimSewa(isiSewa($unit, ['tujuan' => 'Bromo']))->assertHasNoErrors();

    $unit->update(['name' => 'HiAce Premio']);

    // Unit boleh berganti nama; catatan penyewaan lama tidak ikut berubah,
    // karena yang disewa saat itu memang unit dengan nama yang lama.
    expect(PenyewaanKendaraan::first()->nama_kendaraan)->toBe('HiAce Commuter');
});
