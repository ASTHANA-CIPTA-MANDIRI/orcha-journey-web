<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto berkas jaminan penyewa (KTP, SIM, atau apa pun yang dititipkan).
 *
 * Kolom `jaminan` yang sudah ada hanya berisi tulisan "KTP asli" — cukup untuk
 * mengingat, tidak cukup untuk membuktikan. Saat unit tidak kembali, yang
 * dibutuhkan adalah gambarnya: nama, alamat, dan nomor yang bisa dibaca.
 *
 * Disimpan sebagai jalur berkas, gambarnya sendiri diubah ke WebP seperti
 * bukti transfer — foto KTP dari kamera ponsel bisa 5 MB, dan tidak ada
 * gunanya menyimpan sebesar itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $table) {
            $table->string('berkas_jaminan')->nullable()->after('jaminan');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $table) {
            $table->dropColumn('berkas_jaminan');
        });
    }
};
