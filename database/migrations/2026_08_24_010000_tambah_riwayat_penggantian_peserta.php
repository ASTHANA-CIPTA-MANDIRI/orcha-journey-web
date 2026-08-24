<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak perubahan nama peserta.
     *
     * Peserta yang berhalangan boleh digantikan tanpa biaya sepanjang jumlahnya
     * tetap — begitu bunyi Kebijakan Pengembalian pasal 6. Yang belum ada:
     * catatan tentang penggantian itu sendiri. Daftar peserta ditimpa nama baru,
     * dan nama lamanya lenyap tanpa jejak, padahal dialah yang membayar atau
     * yang riwayat kesehatannya sudah masuk.
     *
     * Disimpan sebagai larik: [{dari, ke, pada, oleh}]. Nama lama TIDAK dihapus
     * dari mana pun — riwayat kesehatannya pun dibiarkan utuh, hanya ditandai
     * sebagai milik peserta yang sudah diganti.
     */
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->json('riwayat_penggantian')->nullable()->after('daftar_peserta');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn('riwayat_penggantian');
        });
    }
};
