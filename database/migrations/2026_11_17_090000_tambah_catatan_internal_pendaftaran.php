<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan internal admin pada sebuah pendaftaran.
 *
 * Kolom `catatan` yang sudah ada adalah milik PEMESAN — isinya ikut tercetak
 * di kwitansi dan terbaca pelanggan. Tidak ada tempat bagi admin menuliskan
 * "sudah ditelepon 2x, minta ditelepon sore" tanpa ikut terbaca orang luar,
 * padahal tab "Perlu ditelepon" justru menuntut catatan semacam itu: tanpanya,
 * admin kedua menelepon orang yang sama di hari yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->text('catatan_internal')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn('catatan_internal');
        });
    }
};
