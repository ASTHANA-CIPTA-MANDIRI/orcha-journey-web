<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan perbaikan unit.
 *
 * Kondisi unit sebelumnya hanya bisa ditulis saat serah terima — saat penyewa
 * mengembalikan mobilnya. Setelah pemilik membawanya ke bengkel dan kacanya
 * diganti, tidak ada tempat untuk menyatakan bahwa unit itu sudah baik lagi:
 * unitnya terus terbaca "rusak" sampai ada penyewa berikutnya yang
 * mengembalikannya.
 *
 * Kolom ini menyimpan keterangan pemeriksaan mandiri itu — "kaca diganti 17
 * Agustus di bengkel Slamet". Enam bulan kemudian, ketika ada yang bertanya
 * kenapa unit ini pernah ditandai rusak lalu tiba-tiba baik, jawabannya ada di
 * sini dan bukan di ingatan orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->string('kondisi_catatan', 500)->nullable()->after('kondisi_diperiksa_pada');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn('kondisi_catatan');
        });
    }
};
