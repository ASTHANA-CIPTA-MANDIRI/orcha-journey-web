<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Livewire\Volt\Volt;

/**
 * Perjalanan luar kota dihargai berbeda.
 *
 * Sopirnya menginap, unitnya menempuh jarak jauh, dan risikonya lain. Sampai
 * sekarang sistem hanya mengenal satu tarif, sehingga selisihnya disepakati
 * lewat percakapan dan tidak pernah tercatat.
 *
 * HANYA harian: perjalanan ke luar kota tidak selesai dalam dua belas jam.
 */
function unitLuarKota(array $ubah = []): Car
{
    return Car::create(array_merge([
        'name' => 'HiAce Commuter', 'brand' => 'Toyota', 'type' => 'hiace',
        'transmission' => 'Manual', 'transmisi_tersedia' => ['Manual'],
        'capacity' => 14, 'lepas_kunci' => false, 'termasuk_sopir' => true,
        'price_per_day' => 500000, 'harga_per_jam' => 60000, 'harga_12_jam' => 300000,
        'harga_luar_kota' => 800000, 'is_available' => true,
    ], $ubah));
}

function pesanLuarKota(Car $unit, array $ubah = [])
{
    $isi = array_merge([
        'unit' => $unit->uuid, 'transmisi' => 'Manual',
        'satuan' => 'hari', 'durasi' => 2,
        'tanggalMulai' => now()->addWeek()->toDateString(), 'jamMulai' => '07:00',
        'lokasiAntar' => 'Hotel Malioboro Yogyakarta', 'tujuan' => 'Bromo',
        'nama' => 'Budi Santoso', 'whatsapp' => '081234567890',
        'email' => 'budi@contoh.test', 'setuju' => true,
    ], $ubah);

    $uji = Volt::test('public.sewa-kendaraan.pemesanan');

    foreach ($isi as $medan => $nilai) {
        $uji->set($medan, $nilai);
    }

    return $uji->call('pesan');
}

test('tarif luar kota dipakai menggantikan tarif harian', function () {
    $unit = unitLuarKota();

    // 2 hari x 800.000, bukan 2 x 500.000.
    expect($unit->estimasiBiaya('hari', 2, false, true))->toBe(1_600_000)
        ->and($unit->estimasiBiaya('hari', 2, false, false))->toBe(1_000_000);
});

test('tarif luar kota yang kosong berarti sama dengan dalam kota', function () {
    $unit = unitLuarKota(['harga_luar_kota' => null]);

    // Bukan nol, dan bukan menolak pesanan — sebagian unit memang tidak
    // membedakan keduanya.
    expect($unit->estimasiBiaya('hari', 2, false, true))->toBe(1_000_000)
        ->and($unit->punya_tarif_luar_kota)->toBeFalse()
        ->and($unit->luar_kota_label)->toBe('Luar kota tarifnya sama');
});

test('tarif luar kota yang sama persis tidak diperlakukan sebagai berbeda', function () {
    $unit = unitLuarKota(['harga_luar_kota' => 500000]);

    // Menyebut "berbeda" untuk angka yang sama hanya menambah baris kosong.
    expect($unit->punya_tarif_luar_kota)->toBeFalse();
});

test('memilih luar kota memaksa satuan harian', function () {
    $unit = unitLuarKota();

    // Perjalanan ke luar kota tidak selesai dalam dua belas jam. Dipaksa di
    // muka, bukan dibiarkan lalu ditolak — penyewa tidak bisa menebak aturan
    // yang tidak terlihat.
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('satuan', 'jam')
        ->set('luarKota', true)
        ->assertSet('satuan', 'hari');
});

test('satuan jam dan 12 jam tidak ditawarkan untuk luar kota', function () {
    $unit = unitLuarKota();

    $uji = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('luarKota', true);

    expect(array_keys($uji->viewData('satuanTersedia')->all()))->toBe(['hari']);
});

test('satuan selain harian ditolak bila luar kota', function () {
    $unit = unitLuarKota();

    // Urutannya penting: luarKota diset DULU, baru satuannya diubah. Kalau
    // sebaliknya, updatedLuarKota() keburu memaksa satuannya ke hari dan yang
    // teruji hanya pemaksaan di layar — bukan pengaman di server, yang justru
    // satu-satunya yang berlaku bila permintaannya dirakit tangan.
    $uji = Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->set('luarKota', true)
        ->set('satuan', 'jam')
        ->set('transmisi', 'Manual')
        ->set('durasi', 2)
        ->set('tanggalMulai', now()->addWeek()->toDateString())
        ->set('jamMulai', '07:00')
        ->set('lokasiAntar', 'Hotel Malioboro Yogyakarta')
        ->set('tujuan', 'Bromo')
        ->set('nama', 'Budi Santoso')
        ->set('whatsapp', '081234567890')
        ->set('email', 'budi@contoh.test')
        ->set('setuju', true);

    $uji->call('pesan')->assertHasErrors('satuan');

    expect(PenyewaanKendaraan::count())->toBe(0);
});

test('pilihan wilayah tersimpan dan estimasinya memakai tarif luar kota', function () {
    $unit = unitLuarKota();

    pesanLuarKota($unit, ['luarKota' => true])->assertHasNoErrors();

    $sewa = PenyewaanKendaraan::first();

    expect($sewa->luar_kota)->toBeTrue()
        ->and($sewa->estimasi_biaya)->toBe(1_600_000);
});

test('pesanan dalam kota memakai tarif biasa', function () {
    $unit = unitLuarKota();

    pesanLuarKota($unit)->assertHasNoErrors();

    expect(PenyewaanKendaraan::first())
        ->luar_kota->toBeFalse()
        ->estimasi_biaya->toBe(1_000_000);
});

test('kartu publik menyebut tarif luar kota hanya bila berbeda', function () {
    unitLuarKota();

    // Diperiksa pada teks yang terbaca, bukan pada penanda HTML-nya. Angkanya
    // ditebalkan sehingga kalimatnya terpotong <b> di tengah — assertSee akan
    // merah walaupun penyewa membaca kalimat yang persis sama. Yang dijanjikan
    // ke penyewa kalimatnya, bukan cara menuliskannya.
    $teks = preg_replace('/\s+/', ' ', strip_tags($this->get(route('sewa-kendaraan'))->assertOk()->getContent()));

    expect($teks)->toContain('Luar kota Rp 800.000/hari');
});

test('kartu publik tidak menyebut apa pun bila tarifnya sama', function () {
    unitLuarKota(['harga_luar_kota' => null]);

    // Menuliskan "luar kota tarifnya sama" di setiap kartu menambah baris tanpa
    // menambah keterangan.
    $this->get(route('sewa-kendaraan'))->assertOk()->assertDontSee('Luar kota');
});

test('tarif luar kota tersimpan lewat API dan terkirim di resource', function () {
    config()->set('orcha.api.kunci', 'kunci-uji');
    config()->set('orcha.api.ip_diizinkan', []);

    $kepala = ['X-Orcha-Key' => 'kunci-uji', 'X-Orcha-Admin' => 'a@b.test', 'Accept' => 'application/json'];

    $this->postJson('/api/v1/kendaraan', [
        'nama' => 'Bus RK', 'merek' => 'Hino', 'jenis' => 'bus', 'kapasitas' => 58,
        'lepas_kunci' => false, 'termasuk_sopir' => true, 'transmisi_tersedia' => ['Manual'],
        'tarif_hari' => 4000000, 'tarif_luar_kota' => 5500000,
    ], $kepala)->assertCreated();

    $baris = $this->getJson('/api/v1/kendaraan', $kepala)->assertOk()->json('data.0');

    expect(Car::first()->harga_luar_kota)->toBe(5500000)
        ->and($baris['tarif']['luar_kota'])->toBe(5500000)
        ->and($baris['punya_tarif_luar_kota'])->toBeTrue();
});

test('formulir menyebut batas wilayah supaya penyewa tidak menebak', function () {
    $unit = unitLuarKota();

    // Penyewa memilih wilayahnya sendiri. Tanpa batasan yang tertulis, penyewa
    // yang hendak ke Borobudur tidak punya cara tahu harus memilih yang mana —
    // dan selisih tarifnya baru dipersoalkan saat menagih.
    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->assertSee('Wilayah perjalanan')
        ->assertSee(config('orcha.wilayah_sewa.dalam_kota'))
        ->assertSee(config('orcha.wilayah_sewa.catatan'));
});

test('batas wilayah diambil dari config, bukan ditulis di berkas tampilan', function () {
    $unit = unitLuarKota();

    // Supaya aturannya bisa diubah tanpa menyentuh blade.
    config()->set('orcha.wilayah_sewa.dalam_kota', 'Dalam kota hanya Kota Yogyakarta.');

    Volt::test('public.sewa-kendaraan.pemesanan')
        ->set('unit', $unit->uuid)
        ->assertSee('Dalam kota hanya Kota Yogyakarta.');
});
