<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daerah yang ditambahkan admin sendiri.
 *
 * Katalog bawaan berisi daerah wisata yang paling sering diminta, tetapi
 * Indonesia punya lebih dari lima ratus kabupaten dan kota — daftar mana pun
 * akan tertinggal. Yang ditulis admin di pemilihnya disimpan di sini supaya
 * langsung bisa dipilih lagi lain kali.
 *
 * Provinsinya ikut disimpan: daerah dipilih SESUDAH provinsi, dan daftar yang
 * tidak tahu provinsinya tidak bisa disaring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daerah_tambahan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 100);
            $tabel->string('provinsi', 100);
            $tabel->timestamps();
            $tabel->unique(['nama', 'provinsi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daerah_tambahan');
    }
};
