<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daerah destinasi: kabupaten, kota, atau kawasan tempatnya berada.
 *
 * Selama ini alamat destinasi melompat dari namanya langsung ke provinsi —
 * "Kawah Ijen, Jawa Timur" — padahal yang dicari dan ditanyakan penyewa justru
 * daerahnya: berangkat dari mana, menginap di mana, berapa jauh dari kota
 * terdekat. Jawa Timur saja membentang 47 ribu km persegi, dan Ijen di ujung
 * timurnya sementara Bromo di tengah.
 *
 * Nullable: destinasi yang sudah tercatat belum punya keterangan ini, dan
 * memaksa angka atau nama ke sana hanya menghasilkan data karangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            $tabel->string('daerah', 100)->nullable()->after('provinsi');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            $tabel->dropColumn('daerah');
        });
    }
};
