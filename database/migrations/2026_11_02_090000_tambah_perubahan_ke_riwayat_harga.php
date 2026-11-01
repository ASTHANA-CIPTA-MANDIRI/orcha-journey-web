<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perubahan SELAIN harga, di baris riwayat yang sama.
 *
 * Riwayat hanya mencatat angkanya. Nama yang diperbaiki, vendor yang
 * dipindah, destinasi yang diganti, baris yang dimatikan — semuanya terjadi
 * tanpa meninggalkan jejak di tempat orang mencarinya. Pertanyaan "siapa
 * yang mengubah ini, dan apa yang diubahnya" karena itu hanya bisa dijawab
 * untuk uang, padahal yang paling sering salah justru destinasinya: salah
 * satu huruf, dan tiketnya berhenti ikut tertarik ke RAB.
 *
 * Disimpan sebagai daftar {medan, dari, ke} — bukan kolom per medan, karena
 * medan master harga masih akan bertambah (harga akhir pekan baru saja
 * ditambahkan), dan tiap tambahan akan menuntut migrasi lagi.
 *
 * Nullable: baris riwayat lama memang tidak tahu apa pun selain harganya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->json('perubahan')->nullable()->after('akhir_pekan_baru');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->dropColumn('perubahan');
        });
    }
};
