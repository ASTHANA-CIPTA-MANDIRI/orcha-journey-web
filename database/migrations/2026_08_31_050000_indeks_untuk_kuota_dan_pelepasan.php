<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk dua kueri yang ditambahkan bersama kuota kursi.
 *
 * Perhitungan sisa kursi menyaring travel_package_id + status; pelepasan kursi
 * menyaring status + created_at. Tabel ini hanya punya indeks pada `kode` dan
 * `status`, jadi keduanya memindai jauh lebih banyak baris daripada yang
 * dibutuhkan.
 *
 * Belum terasa pada ratusan baris — dan justru itu yang membuatnya mudah
 * terlewat: keluhannya baru muncul saat datanya sudah menumpuk, di saat
 * sebabnya paling sulit dilacak balik ke perubahan yang menyebabkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            /*
             | Gabungan, bukan dua indeks terpisah.
             |
             | Kedua kueri menyaring travel_package_id DAN status sekaligus;
             | indeks gabungan melayani keduanya dalam satu penelusuran,
             | sedangkan dua indeks terpisah memaksa basis data memilih salah
             | satu lalu menyaring sisanya baris per baris.
             |
             | Urutannya travel_package_id dulu karena itu yang paling
             | membeda-bedakan: satu paket menyaring dari seluruh tabel menjadi
             | puluhan baris, sedangkan status hanya membelah lima kelompok.
             */
            $tabel->index(['travel_package_id', 'status'], 'idx_pendaftaran_paket_status');

            // Dipakai pelepasan kursi: status yang belum bayar, dan lebih tua
            // dari batas waktunya.
            $tabel->index(['status', 'created_at'], 'idx_pendaftaran_status_dibuat');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropIndex('idx_pendaftaran_paket_status');
            $tabel->dropIndex('idx_pendaftaran_status_dibuat');
        });
    }
};
