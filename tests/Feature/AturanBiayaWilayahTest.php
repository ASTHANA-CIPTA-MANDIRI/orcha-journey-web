<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

/**
 * BBM, tol, parkir, dan sopir dibedakan antara dalam kota dan luar kota.
 *
 * Sewa dalam kota diserahkan apa adanya — penyewa mengisi bensin dan membayar
 * tolnya sendiri — sementara perjalanan ke luar kota ditawarkan sepaket bersama
 * sopir dan bahan bakarnya. Dengan satu aturan untuk keduanya, yang tertulis di
 * kartu unit dan di surat pemesanan tidak berlaku untuk separuh pesanan.
 */
function unitDuaWilayah(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'HiAce Commuter', 'brand' => 'Toyota', 'type' => 'hiace',
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 14, 'lepas_kunci' => false,
        'price_per_day' => 1000000, 'harga_luar_kota' => 1500000,
        'is_available' => true,

        // Dalam kota: semuanya ditanggung penyewa, sopir ditambahkan.
        'termasuk_sopir' => false, 'harga_sopir' => 200000,
        'termasuk_bbm' => false, 'termasuk_tol' => false, 'termasuk_parkir' => false,

        // Luar kota: sepaket bersama sopir dan BBM.
        'luar_termasuk_sopir' => true, 'luar_harga_sopir' => null,
        'luar_termasuk_bbm' => true, 'luar_biaya_bbm' => 300000,
        'luar_termasuk_tol' => false, 'luar_termasuk_parkir' => false,
    ], $ubah));
}

/* -------- MODEL -------- */

test('aturan biaya dibaca menurut wilayahnya', function () {
    $unit = unitDuaWilayah();

    expect($unit->rincianOperasional(false)['bbm']['termasuk'])->toBeFalse()
        ->and($unit->rincianOperasional(true)['bbm']['termasuk'])->toBeTrue()
        ->and($unit->biayaOperasionalTotal(false))->toBe(0)
        ->and($unit->biayaOperasionalTotal(true))->toBe(300000)
        ->and($unit->termasukSopir(false))->toBeFalse()
        ->and($unit->termasukSopir(true))->toBeTrue();
});

test('aksesor lama tetap berarti dalam kota', function () {
    $unit = unitDuaWilayah();

    // Halaman lain masih memanggil aksesor tanpa wilayah. Kalau maknanya
    // bergeser, semua yang membacanya ikut salah tanpa satu pun berkas berubah.
    expect($unit->rincian_operasional)->toBe($unit->rincianOperasional(false))
        ->and($unit->operasional_label)->toBe($unit->operasionalLabel(false))
        ->and($unit->sopir_label)->toBe($unit->sopirLabel(false));
});

test('perkiraan luar kota memakai sopir dan pos luar kotanya', function () {
    $unit = unitDuaWilayah();

    // Dalam kota: tarif 1.000.000 + sopir 200.000, tidak ada pos yang termasuk.
    expect($unit->estimasiBiaya('hari', 2, true, false))->toBe(2_400_000);

    // Luar kota: tarif 1.500.000 × 2, sopir sudah termasuk (tidak ditambah),
    // BBM 300.000 × 2 hari.
    expect($unit->estimasiBiaya('hari', 2, true, true))->toBe(3_600_000);

    $rincian = collect($unit->rincianEstimasi('hari', 2, true, true));

    expect($rincian->pluck('label')->all())->toBe(['Tarif luar kota', 'BBM'])
        ->and($rincian->firstWhere('label', 'BBM')['jumlah'])->toBe(600000);
});

test('unit yang aturannya sama di dua wilayah tidak ditandai berbeda', function () {
    $sama = unitDuaWilayah([
        'luar_termasuk_sopir' => false, 'luar_harga_sopir' => 200000,
        'luar_termasuk_bbm' => false, 'luar_biaya_bbm' => null,
    ]);

    expect($sama->beda_aturan_luar_kota)->toBeFalse()
        ->and(unitDuaWilayah()->beda_aturan_luar_kota)->toBeTrue();
});

/* -------- KARTU PUBLIK -------- */

test('kartu memberi label wilayah pada aturan yang berbeda', function () {
    unitDuaWilayah();

    // Tanpa label, penyewa tidak punya cara tahu bahwa keterangan pertama
    // ternyata hanya berlaku dalam kota — ia membaca keduanya sebagai satu
    // daftar yang saling bertentangan.
    $teks = preg_replace('/\s+/', ' ', strip_tags(
        $this->get(route('sewa-kendaraan'))->assertOk()->getContent()
    ));

    expect($teks)->toContain('Dalam kota — semuanya ditanggung penyewa · sopir +Rp 200.000/hari')
        ->and($teks)->toContain('Luar kota — BBM dan sopir termasuk');
});

test('kartu tidak memberi label bila kedua wilayah sama', function () {
    unitDuaWilayah([
        'luar_termasuk_sopir' => false, 'luar_harga_sopir' => 200000,
        'luar_termasuk_bbm' => false, 'luar_biaya_bbm' => null,
    ]);

    // Menuliskan "dalam kota" dan "luar kota" untuk hal yang tidak berbeda
    // menyuruh penyewa mencari perbedaan yang tidak ada.
    $this->get(route('sewa-kendaraan'))->assertOk()
        ->assertDontSee('Dalam kota —')
        ->assertDontSee('Luar kota —')
        // Keterangannya tetap ada, hanya tanpa label wilayah.
        ->assertSee('Sopir +Rp 200.000/hari');
});

/* -------- HALAMAN PEMESANAN -------- */

test('keterangan di formulir ikut wilayah yang dipilih', function () {
    $unit = unitDuaWilayah();

    $uji = Volt::test('public.sewa-kendaraan.pemesanan')->set('unit', $unit->uuid);

    $uji->assertSee('Sopir +Rp 200.000/hari');

    $uji->set('luarKota', true)
        ->assertSee('Harga sudah termasuk sopir')
        ->assertDontSee('Sopir +Rp 200.000/hari');
});

/* -------- SURAT PEMESANAN -------- */

test('surat menyebut aturan wilayah pesanannya', function () {
    $unit = unitDuaWilayah();

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('transmisi', 'Manual')
        ->set('satuan', 'hari')
        ->set('durasi', 2)
        ->set('luarKota', true)
        ->set('denganSopir', 'ya')
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('lokasiAntar', 'Hotel Malioboro Yogyakarta')
        ->set('tujuan', 'Bromo')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('setuju', true)
        ->call('pesan')
        ->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::first();

    // Surat yang menyalin aturan dalam kota untuk pesanan luar kota menjanjikan
    // hal yang tidak berlaku — dan itu baru dipersoalkan saat menagih.
    expect($sewa->luar_kota)->toBeTrue()
        ->and($sewa->estimasi_biaya)->toBe(3_600_000);
});

/* -------- API -------- */

function kirimUnit(array $isi): \Illuminate\Testing\TestResponse
{
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    return test()->postJson('/api/v1/kendaraan', array_merge([
        'nama' => 'Avanza', 'merek' => 'Toyota', 'jenis' => 'mobil',
        'kapasitas' => 7, 'transmisi_tersedia' => ['Matic'], 'tarif_hari' => 400000,
    ], $isi), [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
        'Accept' => 'application/json',
    ]);
}

test('aturan luar kota tersimpan lewat api', function () {
    kirimUnit([
        'termasuk_bbm' => false,
        'luar_termasuk_bbm' => true, 'luar_biaya_bbm' => 250000,
        'luar_termasuk_sopir' => true,
    ])->assertCreated();

    $unit = Car::first();

    expect($unit->termasukSopir(false))->toBeFalse()
        ->and($unit->termasukSopir(true))->toBeTrue()
        ->and($unit->rincianOperasional(true)['bbm'])->toMatchArray(['termasuk' => true, 'biaya' => 250000]);
});

test('permintaan yang tidak menyebut luar kota mewarisi aturan dalam kota', function () {
    // Pemanggil lama tidak mengenal pemisahan ini. Mengosongkan aturan luar
    // kotanya berarti diam-diam mengubah unitnya jadi "semua ditanggung
    // penyewa di luar kota" — perubahan harga yang tidak pernah diminta.
    kirimUnit([
        'termasuk_bbm' => true, 'biaya_bbm' => 150000,
        'tarif_sopir' => 175000,
    ])->assertCreated();

    $unit = Car::first();

    expect($unit->rincianOperasional(true))->toBe($unit->rincianOperasional(false))
        ->and($unit->hargaSopir(true))->toBe(175000);
});

test('unit selalu bersopir wajib menyebut sopir luar kotanya juga', function () {
    // Tanpa ini, halaman publik menampilkan unit yang pasti bersopir untuk
    // perjalanan luar kota tanpa keterangan biaya sopirnya sama sekali.
    kirimUnit([
        'jenis' => 'hiace', 'kapasitas' => 14, 'lepas_kunci' => false,
        'tarif_sopir' => 200000,
        'luar_termasuk_bbm' => true, 'luar_biaya_bbm' => 300000,
    ])->assertStatus(422)->assertJsonValidationErrors('luar_termasuk_sopir');
});

test('resource mengirim aturan kedua wilayah', function () {
    unitDuaWilayah();

    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $baris = $this->getJson('/api/v1/kendaraan', [
        'X-Orcha-Key' => 'kunci-uji',
        'X-Orcha-Admin' => 'admin@phoenix.test',
    ])->assertOk()->json('data.0');

    expect($baris['luar_kota'])->toMatchArray([
        'termasuk_sopir' => true,
        'biaya_operasional_total' => 300000,
    ])->and($baris['beda_aturan_luar_kota'])->toBeTrue()
        ->and($baris['operasional']['bbm']['termasuk'])->toBeFalse();
});

/* -------- YANG DIHITUNG vs YANG DIBAYAR SENDIRI -------- */

test('formulir memilah pos yang dihitung dan yang dibayar penyewa', function () {
    $unit = unitDuaWilayah();

    // Sebelumnya dua kalimat kecil yang harus diurai sendiri oleh penyewa untuk
    // menjawab satu pertanyaan yang paling penting baginya: selain angka ini,
    // apa lagi yang harus saya siapkan?
    $uji = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('denganSopir', 'ya');

    $dalamKota = preg_replace('/\s+/', ' ', strip_tags($uji->html()));

    expect($dalamKota)->toContain('Sudah dihitung')
        ->and($dalamKota)->toContain('Dibayar sendiri')
        // Dalam kota: semuanya ditanggung penyewa, sopir ditambahkan.
        ->and($dalamKota)->toContain('Sopir +Rp 200.000/hari')
        ->and($dalamKota)->toContain('Tiket masuk lokasi wisata');

    $luarKota = preg_replace('/\s+/', ' ', strip_tags($uji->set('luarKota', true)->html()));

    // Luar kota: BBM ikut dihitung beserta nominalnya, sopir sudah termasuk.
    expect($luarKota)->toContain('BBM +Rp 300.000/hari')
        ->and($luarKota)->toContain('Harga sudah termasuk sopir');
});

test('sopir tidak disebut pada sewa lepas kunci', function () {
    $unit = unitDuaWilayah(['lepas_kunci' => true]);

    // Menyebut sopir pada pesanan yang memang tanpa sopir tidak menjawab
    // pertanyaan siapa pun.
    $teks = preg_replace('/\s+/', ' ', strip_tags(
        Volt::test('public.sewa-kendaraan.pemesanan')
            ->set('unit', $unit->uuid)
            ->set('denganSopir', 'tidak')
            ->html()
    ));

    expect($teks)->not->toContain('Sopir +Rp 200.000/hari');
});
