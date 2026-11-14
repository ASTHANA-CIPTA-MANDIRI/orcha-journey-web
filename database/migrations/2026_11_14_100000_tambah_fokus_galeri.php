<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titik fokus foto galeri: bagian mana yang tetap terlihat saat dipotong.
 *
 * Beranda memajang setiap foto dalam kotak 4:3 dengan object-cover, jadi foto
 * tegak kehilangan atas dan bawahnya. Selama titiknya selalu di tengah, foto
 * rombongan yang orangnya berdiri di bagian bawah terpotong kepalanya — dan
 * admin tidak punya cara memperbaikinya selain memotong fotonya sendiri di
 * luar aplikasi lalu mengunggah ulang.
 *
 * Yang disimpan POSISINYA, bukan hasil potongannya. Berkas fotonya tidak
 * pernah diubah, jadi titiknya boleh digeser berkali-kali tanpa mutunya
 * menurun, dan foto yang sudah terlanjur diunggah ikut bisa diatur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_galeri', function (Blueprint $tabel) {
            // Persen, 0–100. Bawaannya 50/50 — persis perilaku lama, sehingga
            // seluruh foto yang sudah ada tidak bergeser sedikit pun saat
            // migrasi ini jalan.
            $tabel->unsignedTinyInteger('fokus_x')->default(50)->after('keterangan');
            $tabel->unsignedTinyInteger('fokus_y')->default(50)->after('fokus_x');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_galeri', function (Blueprint $tabel) {
            $tabel->dropColumn(['fokus_x', 'fokus_y']);
        });
    }
};
