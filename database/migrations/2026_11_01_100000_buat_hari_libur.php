<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal merah yang harus diperlakukan seperti akhir pekan.
 *
 * Sabtu & Minggu bisa dihitung sendiri dari tanggalnya; 17 Agustus tidak.
 * Tanpa daftar ini sistem akan diam-diam memakai harga hari kerja di tanggal
 * merah — selisih yang baru ketahuan saat tagihan vendor datang.
 *
 * Diisi admin sekali setahun. Tabel kosong bukan kesalahan: artinya hanya
 * Sabtu & Minggu yang dihitung akhir pekan, dan layar mengatakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_hari_libur', function (Blueprint $t) {
            $t->id();
            // Satu tanggal sekali saja — dua baris untuk tanggal yang sama
            // tidak menambah arti apa pun.
            $t->date('tanggal')->unique();
            $t->string('nama', 120);
            // Nama, bukan id: tetap terbaca sesudah akunnya hilang.
            $t->string('oleh', 120)->nullable();
            $t->timestamps();

            $t->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_hari_libur');
    }
};
