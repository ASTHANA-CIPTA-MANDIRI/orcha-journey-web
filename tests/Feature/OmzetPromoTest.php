<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\PromoRombonganTingkat;
use App\Models\PaketWisata\TravelPackage;
use App\Support\RincianBiaya;

/**
 * Laporan keuntungan harus menghitung uang yang BENAR-BENAR masuk.
 *
 * Omzet dulu dihitung dari harga penuh (jual_satuan x peserta) sementara
 * pelanggan ditagih setelah potongan promo. Terukur pada rombongan sepuluh
 * orang bertingkat "gratis 1": ditagih Rp 12.870.000, tercatat Rp 14.300.000,
 * dan keuntungannya dilaporkan lima puluh persen lebih besar daripada
 * kenyataannya.
 *
 * Yang berbahaya bukan selisihnya sendiri, melainkan bahwa angka itu dipakai
 * memutuskan paket mana yang layak dijalankan lagi.
 */
function paketBerpromo(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Uji', 'category' => 'open_trip',
        'price' => 1430000, 'harga_modal' => 1000000,
        'status' => 'terbit', 'promo_rombongan' => true,
    ]);
}

function daftarkanOrang(TravelPackage $paket, int $orang): PendaftaranOpenTrip
{
    return PendaftaranOpenTrip::create([
        'nama' => 'Pemesan', 'whatsapp' => '0812', 'jumlah_peserta' => $orang,
        'travel_package_id' => $paket->id, 'nama_paket' => $paket->name,
    ])->fresh();
}

test('omzet sama persis dengan yang ditagih ke pelanggan', function () {
    $paket = paketBerpromo();

    foreach ([3, 6, 11, 13] as $orang) {
        $daftar = daftarkanOrang($paket, $orang);
        $ditagih = RincianBiaya::untuk($paket, $orang)['total'];

        expect((float) $daftar->omzet)->toBe($ditagih, "Selisih pada rombongan $orang orang");
    }
});

test('keuntungan dihitung dari uang masuk, bukan dari margin per orang', function () {
    /*
     | Keduanya sama selama tidak ada promo. Begitu ada, margin per orang tidak
     | tahu apa-apa soal potongan yang diberikan — dan keuntungan yang
     | dilaporkan jadi lebih besar daripada uang yang benar-benar masuk.
     */
    $daftar = daftarkanOrang(paketBerpromo(), 11);

    // 10 kursi dibayar x 1.430.000 = 14.300.000, modal 11 x 1.000.000
    expect($daftar->keuntungan)->toBe(14300000 - 11000000);
});

test('potongan promonya dibekukan saat mendaftar', function () {
    /*
     | Tingkat promo berubah sepanjang tahun. Tanpa dibekukan, laporan bulan
     | lalu ikut berubah tiap admin menyunting angka promo hari ini — dan yang
     | membacanya tidak punya cara tahu kenapa angkanya bergeser.
     |
     | Pola yang sama sudah dipakai harga_jual dan harga_modal.
     */
    $paket = paketBerpromo();
    $daftar = daftarkanOrang($paket, 11);

    $omzetSemula = $daftar->omzet;

    // Promonya diperbesar SETELAH orang itu mendaftar.
    PromoRombonganTingkat::where('min_peserta', 11)->update(['gratis_orang' => 5]);

    expect($daftar->fresh()->omzet)->toBe($omzetSemula);
});

test('paket tanpa promo tidak terpengaruh sama sekali', function () {
    $biasa = TravelPackage::create([
        'name' => 'Tanpa Promo', 'category' => 'open_trip',
        'price' => 1430000, 'harga_modal' => 1000000, 'status' => 'terbit',
    ]);

    $daftar = daftarkanOrang($biasa, 10);

    expect($daftar->potongan_promo)->toBe(0)
        ->and($daftar->omzet)->toBe(10 * 1430000)
        ->and($daftar->keuntungan)->toBe(10 * 430000);
});

test('paket tanpa harga modal tidak mengarang keuntungan', function () {
    // Keuntungan yang dikarang lebih buruk daripada keuntungan yang kosong:
    // yang kosong terlihat dan bisa dilengkapi.
    $tanpaModal = TravelPackage::create([
        'name' => 'Belum Ada Modal', 'category' => 'open_trip',
        'price' => 1430000, 'status' => 'terbit',
    ]);

    expect(daftarkanOrang($tanpaModal, 5)->keuntungan)->toBeNull();
});
