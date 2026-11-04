<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan dari RAB ke paket wisata yang dibuat darinya.
 *
 * Open trip disusun di RAB — harga, itinerary, destinasi, fasilitas semuanya
 * sudah ada di sana — lalu diketik ULANG di borang paket wisata supaya bisa
 * didaftari publik. Dua sumber untuk angka yang sama, dan yang satu pasti
 * tertinggal begitu RAB-nya disunting.
 *
 * Kolom ini yang membuat keduanya tetap satu: paketnya dirakit dari RAB, dan
 * ikut diperbarui setiap RAB-nya berubah.
 *
 * Tanpa foreign key ke tbl_travel_package: paket yang dihapus admin tidak
 * boleh ikut menghapus RAB-nya, dan tautan yang menggantung cukup diperlakukan
 * sebagai "belum punya paket".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab', function (Blueprint $table) {
            $table->unsignedBigInteger('travel_package_id')->nullable()->after('kode_pendaftaran');
            $table->index('travel_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab', function (Blueprint $table) {
            $table->dropIndex(['travel_package_id']);
            $table->dropColumn('travel_package_id');
        });
    }
};
