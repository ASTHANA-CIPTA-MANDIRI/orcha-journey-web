<?php

use App\Models\PaketWisata\PromoRombonganTingkat;

/**
 * Migrasi pembetul ambang promo.
 *
 * Diuji langsung — bukan lewat migrasi yang sudah jalan di penyiapan uji,
 * karena di sana tabelnya sudah disemai dengan angka yang benar. Yang perlu
 * dibuktikan justru perilakunya pada data LAMA.
 */
function jalankanPembetulAmbang(): void
{
    $berkas = database_path('migrations/2026_09_03_090000_betulkan_ambang_promo_rombongan.php');

    (require $berkas)->up();
}

test('tingkat bawaan lama digeser ke jumlah peserta yang benar', function () {
    PromoRombonganTingkat::query()->delete();
    PromoRombonganTingkat::create(['min_peserta' => 5, 'potongan_persen' => 5]);
    PromoRombonganTingkat::create(['min_peserta' => 10, 'gratis_orang' => 1]);

    jalankanPembetulAmbang();

    expect(PromoRombonganTingkat::pluck('min_peserta')->sort()->values()->all())
        ->toBe([6, 11]);
});

test('kalimatnya ikut dirakit ulang, tidak tertinggal angka lama', function () {
    PromoRombonganTingkat::query()->delete();
    $t = PromoRombonganTingkat::create(['min_peserta' => 5, 'potongan_persen' => 5]);

    // Kalimat lama ditanam langsung ke basis data, melewati model — persis
    // seperti baris yang tersimpan sebelum perakitan otomatis ada.
    PromoRombonganTingkat::withoutEvents(
        fn () => $t->update(['label' => 'Ajak 5 orang — hemat 5%'])
    );

    jalankanPembetulAmbang();

    expect($t->fresh()->label)->toBe('Ajak 5 rekan — potongan 5% untuk pemesan');
});

test('angka yang sudah diubah admin TIDAK ditimpa', function () {
    /*
     | Admin yang menyetel tingkat 5 orang jadi 12% sudah memutuskan sesuatu.
     | Migrasi yang menggesernya jadi 6 orang mengubah keputusan itu diam-diam,
     | dan yang menyadarinya pelanggan yang tiba-tiba tidak lagi dapat promo.
     */
    PromoRombonganTingkat::query()->delete();
    PromoRombonganTingkat::create(['min_peserta' => 5, 'potongan_persen' => 12]);

    jalankanPembetulAmbang();

    expect(PromoRombonganTingkat::first()->min_peserta)->toBe(5);
});

test('ambang tujuan yang sudah terpakai tidak ditabrak', function () {
    /*
     | Tanpa penjagaan ini, menggeser 5 ke 6 menabrak kolom unik dan
     | menggagalkan SELURUH migrasi — di server, bukan di sini.
     */
    PromoRombonganTingkat::query()->delete();
    PromoRombonganTingkat::create(['min_peserta' => 5, 'potongan_persen' => 5]);
    PromoRombonganTingkat::create(['min_peserta' => 6, 'potongan_persen' => 7]);

    jalankanPembetulAmbang();

    expect(PromoRombonganTingkat::pluck('min_peserta')->sort()->values()->all())
        ->toBe([5, 6]);
});
