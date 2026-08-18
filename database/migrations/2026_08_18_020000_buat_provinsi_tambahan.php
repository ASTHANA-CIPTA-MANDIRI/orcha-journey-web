<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provinsi yang ditambahkan admin sendiri.
 *
 * Daftar 38 provinsi ada di config, dan itu cukup untuk hari ini. Tetapi
 * provinsi bisa dimekarkan — 2022 saja bertambah empat sekaligus — dan admin
 * tidak boleh menunggu rilis kode hanya untuk mencatat destinasi di provinsi
 * baru. Yang ditulis di sini melengkapi daftar bawaan, tidak menggantikannya.
 *
 * Wilayahnya ikut disimpan: tanpa itu provinsi tambahan tidak masuk penyaring
 * mana pun di halaman publik, dan destinasinya menghilang dari daftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinsi_tambahan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 100)->unique();
            $tabel->string('wilayah', 30);
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinsi_tambahan');
    }
};
