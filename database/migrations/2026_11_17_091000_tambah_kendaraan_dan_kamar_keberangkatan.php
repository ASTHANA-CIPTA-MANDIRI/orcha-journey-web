<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kendaraan dan kamar yang disiapkan untuk satu rombongan.
 *
 * Diletakkan di KEBERANGKATAN, bukan di pendaftaran. Satu bus mengangkut
 * peserta dari banyak pendaftaran sekaligus — disimpan per pendaftaran, dua
 * pemesan yang berbeda bisa sama-sama menempati "Bus 1 kursi 5" tanpa ada yang
 * menyadarinya sampai di parkiran.
 *
 * Bentuk `kendaraan`: [{"nama": "Bus 1", "tata": "2-2", "jumlah_kursi": 44}]
 * Bentuk `kamar`:     [{"nama": "201", "kapasitas": 2, "jenis": "twin"}]
 *
 * Keduanya JSON, bukan tabel sendiri: isinya tidak pernah dicari, diurut, atau
 * dihitung di luar rombongannya sendiri — selalu dibaca sepaket dengan
 * keberangkatan yang memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_paket_keberangkatan', function (Blueprint $table) {
            $table->json('kendaraan')->nullable()->after('catatan');
            $table->json('kamar')->nullable()->after('kendaraan');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_paket_keberangkatan', function (Blueprint $table) {
            $table->dropColumn(['kendaraan', 'kamar']);
        });
    }
};
