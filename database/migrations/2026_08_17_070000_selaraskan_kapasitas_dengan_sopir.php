<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyesuaikan kapasitas unit yang selalu dengan sopir.
 *
 * Arti kolom capacity berubah: dahulu kursi TOTAL termasuk kursi sopir, sekarang
 * kursi PENUMPANG — angka yang dipakai menjawab "muat berapa orang?" dan yang
 * tertulis di penawaran.
 *
 * Baris yang sudah ada masih memakai arti lama. Tanpa penyesuaian ini, HiAce
 * yang tercatat 15 akan terbaca sebagai 15 penumpang + 1 sopir = 16 kursi, dan
 * halaman publik menjanjikan satu kursi yang tidak ada. Justru kesalahan yang
 * seluruh perubahan ini ada untuk mencegah.
 *
 * Hanya unit yang selalu dengan sopir yang disesuaikan. Unit lepas kunci tidak
 * pernah kehilangan kursi untuk sopir, jadi angkanya sudah benar apa adanya.
 *
 * Dijalankan sekali; Laravel mencatat migrasi yang sudah jalan, jadi tidak ada
 * risiko dikurangi dua kali. Kapasitas 1 tidak diturunkan menjadi 0 — angka itu
 * tidak berarti apa-apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cars')
            ->where('lepas_kunci', false)
            ->where('capacity', '>', 1)
            ->decrement('capacity');
    }

    public function down(): void
    {
        DB::table('cars')
            ->where('lepas_kunci', false)
            ->increment('capacity');
    }
};
