<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wilayah yang ditambahkan admin sendiri.
 *
 * Delapan kelompok pulau sudah menutup seluruh Indonesia, tetapi pemasaran
 * kadang butuh pengelompokan lain — "Jawa Barat & sekitarnya", "Segitiga
 * Terumbu Karang" — dan menunggu rilis kode untuk itu terlalu mahal.
 *
 * Kuncinya disimpan tersendiri dari labelnya: label boleh diperbaiki
 * ejaannya kapan saja, sedangkan kunci sudah terlanjur tersimpan di kolom
 * wilayah tiap destinasi. Mengubah kunci berarti memutus keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_tambahan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('kunci', 40)->unique();
            $tabel->string('label', 60);
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_tambahan');
    }
};
