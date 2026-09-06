<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\Rujukan\KodeRujukan;

/**
 * Imbalan rujukan baru jadi hak pemilik kode SETELAH pendaftarannya lunas.
 *
 * Sebelum ini imbalannya terhitung terutang sejak orangnya mengisi formulir.
 * Akibatnya laporan komisi memuat uang yang belum pernah masuk: yang mendaftar
 * lalu tidak pernah membayar, dan yang membatalkan, keduanya tetap menambah
 * tagihan. Yang menagihnya kemudian pemilik kode — dengan angka yang kita
 * sendiri yang menampilkan.
 *
 * Uang muka pun belum cukup. DP bisa hangus, pesanannya bisa batal, dan
 * kursinya bisa dilepas karena pelunasannya tidak pernah datang. Membayar
 * komisi atas pesanan yang kemudian batal berarti kehilangan dua kali: trip
 * yang tidak jadi, dan komisi yang tidak bisa ditagih balik.
 */
function kepalaImbalan(): array
{
    config()->set('orcha.api.kunci', 'kunci-uji-imbalan');

    return ['X-Orcha-Key' => 'kunci-uji-imbalan', 'Accept' => 'application/json'];
}

function kodeImbalan(): KodeRujukan
{
    return KodeRujukan::create(['nama' => 'Budi', 'whatsapp' => '081234567890']);
}

function pakaiKodeLunas(KodeRujukan $kode, string $status): PendaftaranOpenTrip
{
    $paket = TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip',
        'price' => 1000000, 'status' => 'terbit',
    ]);

    return PendaftaranOpenTrip::create([
        'nama' => 'Sari', 'whatsapp' => '0813'.rand(10000000, 99999999),
        'jumlah_peserta' => 1, 'travel_package_id' => $paket->id,
        'nama_paket' => $paket->name, 'kode_rujukan' => $kode->kode,
        'status' => $status,
    ])->fresh();
}

/* ------------------------ HITUNGANNYA ------------------------ */

test('yang baru mendaftar belum menambah komisi', function () {
    $kode = kodeImbalan();
    pakaiKodeLunas($kode, 'baru');

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaImbalan())
        ->assertOk()
        ->json('data.0');

    expect($baris['imbalan_belum_dibayar'])->toBe(0)
        ->and($baris['imbalan_total'])->toBe(0)
        // Disebutkan terpisah, bukan disembunyikan.
        ->and($baris['imbalan_menunggu'])->toBeGreaterThan(0);
});

test('yang baru bayar DP juga belum menambah komisi', function () {
    /*
     | Inti aturannya. DP bisa hangus, pesanannya bisa batal, dan kursinya bisa
     | dilepas karena pelunasannya tidak pernah datang.
     */
    $kode = kodeImbalan();
    pakaiKodeLunas($kode, 'dp_masuk');

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaImbalan())->json('data.0');

    expect($baris['imbalan_belum_dibayar'])->toBe(0)
        ->and($baris['imbalan_menunggu'])->toBeGreaterThan(0);
});

test('yang sudah lunas menambah komisi', function () {
    $kode = kodeImbalan();
    $daftar = pakaiKodeLunas($kode, 'lunas');

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaImbalan())->json('data.0');

    expect($baris['imbalan_belum_dibayar'])->toBe((int) $daftar->imbalan_rujukan)
        ->and($baris['imbalan_total'])->toBe((int) $daftar->imbalan_rujukan)
        // Sudah jadi hak, jadi tidak lagi "menunggu".
        ->and($baris['imbalan_menunggu'])->toBe(0);
});

test('yang batal tidak menambah komisi maupun menunggu', function () {
    /*
     | Pesanan yang batal tidak akan pernah jadi hak siapa pun. Menghitungnya
     | sebagai "menunggu lunas" membuat angka itu menumpuk sepanjang tahun
     | tanpa pernah bisa berkurang.
     */
    $kode = kodeImbalan();
    pakaiKodeLunas($kode, 'batal');

    $baris = $this->getJson('/api/v1/kode-rujukan', kepalaImbalan())->json('data.0');

    expect($baris['imbalan_belum_dibayar'])->toBe(0)
        ->and($baris['imbalan_menunggu'])->toBe(0);
});

test('komisi ikut naik saat statusnya berubah jadi lunas', function () {
    // Tidak ada langkah tambahan yang harus diingat admin: begitu pelunasannya
    // tercatat, komisinya muncul sendiri.
    $kode = kodeImbalan();
    $daftar = pakaiKodeLunas($kode, 'dp_masuk');

    expect($this->getJson('/api/v1/kode-rujukan', kepalaImbalan())->json('data.0.imbalan_belum_dibayar'))
        ->toBe(0);

    $daftar->update(['status' => 'lunas']);

    expect($this->getJson('/api/v1/kode-rujukan', kepalaImbalan())->json('data.0.imbalan_belum_dibayar'))
        ->toBe((int) $daftar->imbalan_rujukan);
});

/* ------------------------ PEMBAYARANNYA ------------------------ */

test('imbalan yang belum lunas DITOLAK dibayarkan', function () {
    /*
     | Ditahan di server, bukan cuma disembunyikan tombolnya di layar: yang
     | dibayarkan uang, dan uang yang sudah berpindah tidak bisa ditarik
     | kembali. Layar bisa saja tertinggal keadaannya — dibuka sebelum
     | statusnya berubah, lalu tombolnya ditekan semenit kemudian.
     */
    $daftar = pakaiKodeLunas(kodeImbalan(), 'dp_masuk');

    $this->postJson("/api/v1/kode-rujukan/bayar/{$daftar->id}", [], kepalaImbalan())
        ->assertStatus(422);

    expect($daftar->fresh()->imbalan_dibayar_pada)->toBeNull();
});

test('pesannya menyebut status yang sedang berlaku', function () {
    // "Tidak bisa dibayarkan" tanpa sebab membuat admin menekan tombolnya lagi.
    $daftar = pakaiKodeLunas(kodeImbalan(), 'baru');

    $this->postJson("/api/v1/kode-rujukan/bayar/{$daftar->id}", [], kepalaImbalan())
        ->assertStatus(422)
        ->assertSee('lunas', false);
});

test('imbalan yang sudah lunas boleh dibayarkan', function () {
    $daftar = pakaiKodeLunas(kodeImbalan(), 'lunas');

    $this->postJson("/api/v1/kode-rujukan/bayar/{$daftar->id}", [], kepalaImbalan())
        ->assertOk();

    expect($daftar->fresh()->imbalan_dibayar_pada)->not->toBeNull();
});

test('yang batal juga ditolak, bukan cuma yang belum bayar', function () {
    $daftar = pakaiKodeLunas(kodeImbalan(), 'batal');

    $this->postJson("/api/v1/kode-rujukan/bayar/{$daftar->id}", [], kepalaImbalan())
        ->assertStatus(422);
});

/* ------------------------ RINCIANNYA ------------------------ */

test('rincian pemakaian menyebut mana yang sudah jadi hak', function () {
    /*
     | Dikirim sebagai keputusan, bukan dibiarkan lemon menyimpulkannya sendiri
     | dari status. Aturannya ada di satu tempat — kalau tidak, layar dan
     | server bisa berbeda pendapat tentang komisi yang sama.
     */
    $kode = kodeImbalan();
    pakaiKodeLunas($kode, 'lunas');
    pakaiKodeLunas($kode, 'dp_masuk');

    $rincian = collect(
        $this->getJson("/api/v1/kode-rujukan/{$kode->id}/pemakaian", kepalaImbalan())
            ->assertOk()
            ->json('data')
    )->keyBy('status');

    expect($rincian['lunas']['berhak'])->toBeTrue()
        ->and($rincian['dp_masuk']['berhak'])->toBeFalse();
});
