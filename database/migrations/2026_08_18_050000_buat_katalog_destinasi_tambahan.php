<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama destinasi yang ditambahkan admin sendiri.
 *
 * Katalog bawaan berisi tempat yang paling sering diminta, tetapi Indonesia
 * jauh lebih luas dari daftar mana pun. Nama yang ditulis admin di pemilihnya
 * disimpan di sini supaya langsung bisa dipilih lagi lain kali — termasuk
 * sebelum destinasinya sendiri benar-benar tersimpan.
 *
 * Provinsinya ikut disimpan: gunanya katalog ini bukan sekadar melengkapi nama,
 * melainkan mengisi provinsi dan wilayah sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('katalog_destinasi_tambahan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 191)->unique();
            $tabel->string('provinsi', 100)->nullable();
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('katalog_destinasi_tambahan');
    }
};
