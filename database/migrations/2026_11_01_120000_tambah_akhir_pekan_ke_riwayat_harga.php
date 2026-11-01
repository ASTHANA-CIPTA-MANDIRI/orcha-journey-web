<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat harga akhir pekan.
 *
 * Tanpa kolom ini, menaikkan harga akhir pekan dari Rp 31.000 ke Rp 36.000
 * tidak meninggalkan jejak apa pun — padahal menaikkan harga hari kerjanya
 * tercatat. Jejak yang ada untuk separuh angka dan hilang untuk separuhnya
 * lebih menyesatkan daripada tidak ada jejak.
 *
 * Nullable: baris riwayat lama memang tidak tahu harga akhir pekannya, dan
 * layar membedakan "tidak berubah" dari "tidak diketahui".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->unsignedBigInteger('akhir_pekan_lama')->nullable()->after('harga_baru');
            $t->unsignedBigInteger('akhir_pekan_baru')->nullable()->after('akhir_pekan_lama');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->dropColumn(['akhir_pekan_lama', 'akhir_pekan_baru']);
        });
    }
};
