<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus katalog destinasi yang BISA DIKEMBALIKAN.
 *
 * Menghapus nama dari katalog sebelumnya permanen: barisnya hilang dari
 * tabel dan tidak ada jalan pulang selain mengetiknya ulang berikut
 * provinsi & daerahnya — yang justru tidak diingat siapa pun, karena dulu
 * diisikan sendiri oleh pencari lokasi.
 *
 * Dengan `deleted_at` barisnya tetap ada, cuma tidak ikut terbaca. Menambah
 * nama yang sama lewat jalan biasa memulihkannya utuh, lengkap dengan
 * provinsi & daerah lamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('katalog_destinasi_tambahan', function (Blueprint $tabel) {
            $tabel->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('katalog_destinasi_tambahan', function (Blueprint $tabel) {
            $tabel->dropSoftDeletes();
        });
    }
};
