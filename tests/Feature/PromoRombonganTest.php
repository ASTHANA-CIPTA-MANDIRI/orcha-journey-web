<?php

use App\Models\PaketWisata\TravelPackage;
use App\Support\PromoRombongan;
use App\Support\RincianBiaya;

/**
 * Potongan menurut jumlah peserta.
 *
 * Berlaku DI ATAS harga yang sudah berlaku — termasuk harga early bird. Paket
 * yang sudah turun dari 1.700.000 ke 1.430.000 dihitung promo rombongannya
 * dari 1.430.000.
 */
function paketPromo(): TravelPackage
{
    return TravelPackage::create([
        'name' => 'Open Trip Banyuwangi',
        'category' => 'open_trip',
        'original_price' => 1700000,
        'price' => 1430000,          // harga early bird
        'status' => 'terbit',
    ]);
}

test('di bawah tingkat pertama tidak ada potongan', function () {
    $b = RincianBiaya::untuk(paketPromo(), 4);

    expect($b['total'])->toBe((float) (4 * 1430000))
        ->and($b['promo_label'])->toBeNull();
});

test('lima orang mendapat potongan persen dari harga early bird', function () {
    /*
     | Dihitung dari 1.430.000, BUKAN dari 1.700.000. Promo rombongan menumpang
     | di atas harga yang sudah berlaku — kalau dihitung dari harga normal,
     | pelanggan early bird justru dirugikan karena promonya jadi lebih kecil.
     */
    $b = RincianBiaya::untuk(paketPromo(), 5);

    $sebelum = 5 * 1430000;

    expect($b['total_sebelum_promo'])->toBe((float) $sebelum)
        ->and($b['total'])->toBe((float) round($sebelum * 0.95))
        ->and($b['promo_label'])->toContain('5 orang');
});

test('sepuluh orang membayar sembilan', function () {
    /*
     | "Gratis 1 dari 10" secara hitungan sama dengan potongan 10%, tetapi
     | disebut sebagai gratis satu orang — itu yang dipahami dan diceritakan
     | ulang orang ke temannya.
     */
    $b = RincianBiaya::untuk(paketPromo(), 10);

    expect($b['promo_gratis_orang'])->toBe(1)
        ->and($b['promo_orang_dibayar'])->toBe(9)
        ->and($b['total'])->toBe((float) (9 * 1430000))
        ->and($b['promo_potongan'])->toBe(1430000);
});

test('tingkat tidak bertumpuk — dua belas orang memakai tingkat sepuluh saja', function () {
    /*
     | Bertumpuk terdengar lebih murah hati, tetapi angkanya jadi sulit
     | dijelaskan di WhatsApp dan lebih sulit lagi diperiksa saat ada yang
     | protes.
     */
    $b = RincianBiaya::untuk(paketPromo(), 12);

    // 12 orang, 1 gratis, 11 dibayar — tanpa potongan persen tingkat 5.
    expect($b['promo_orang_dibayar'])->toBe(11)
        ->and($b['total'])->toBe((float) (11 * 1430000));
});

test('ajakan menyebut berapa orang lagi yang kurang', function () {
    /*
     | Ini yang mengubah promo dari keterangan jadi dorongan: orang yang sedang
     | mengisi 4 peserta perlu tahu bahwa satu orang lagi mengubah harganya.
     */
    expect(PromoRombongan::ajakanBerikutnya(4))->toContain('1 orang lagi')
        ->and(PromoRombongan::ajakanBerikutnya(7))->toContain('3 orang lagi')
        // Sudah di tingkat tertinggi: tidak ada yang perlu diajak lagi.
        ->and(PromoRombongan::ajakanBerikutnya(15))->toBeNull();
});

test('uang muka ikut dihitung dari total setelah promo', function () {
    // Kalau DP dihitung dari total sebelum promo, pelanggan diminta
    // mentransfer lebih banyak daripada haknya — dan itu ketahuan justru saat
    // pelunasan, ketika sisanya jadi minus.
    $b = RincianBiaya::untuk(paketPromo(), 10);

    expect($b['dp'])->toBe(round($b['total'] * $b['dp_persen'] / 100));
});

test('paket tanpa harga tidak mengarang promo', function () {
    $tanpaHarga = TravelPackage::create([
        'name' => 'Private Trip', 'category' => 'private_trip', 'status' => 'terbit',
    ]);

    expect(RincianBiaya::untuk($tanpaHarga, 10))->toBe([]);
});

test('promo tampil hidup di formulir saat peserta ditambah', function () {
    /*
     | Promo yang cuma diumumkan di halaman paket hanya menguntungkan rombongan
     | yang kebetulan sudah ramai. Yang dituju justru yang masih empat orang —
     | dan ia baru bergerak kalau tahu satu orang lagi mengubah harganya.
     */
    $paket = paketPromo();

    $halaman = Livewire\Volt\Volt::test('public.open-trip.pendaftaran')
        ->set('paketId', $paket->uuid);

    // Empat orang: belum dapat, tetapi diberi tahu kurang berapa.
    $halaman->set('jumlahPeserta', 4)
        ->assertSee('1 orang lagi')
        ->assertDontSee('gratis');

    // Sepuluh orang: bentuknya disebut apa adanya, bukan sebagai persen.
    $halaman->set('jumlahPeserta', 10)
        ->assertSee('gratis')
        ->assertSee('dibayar 9 orang');
});
