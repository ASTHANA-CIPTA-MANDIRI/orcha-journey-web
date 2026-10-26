<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam selesai pada tiap kegiatan itinerary.
 *
 * Kertas kerja yang dipakai tim menulis waktunya sebagai RENTANG — "18.00 –
 * 05.00", "07.30 – 09.00" — berikut lamanya. Satu jam mulai saja tidak
 * menjawab pertanyaan yang sebenarnya diajukan pembaca jadwal: kegiatan ini
 * memakan waktu berapa lama, dan kapan rombongan bisa bergerak lagi.
 *
 * Lamanya TIDAK disimpan: ia selisih dua jam di baris yang sama. Menyimpan
 * angka yang bisa dihitung berarti suatu hari ada baris yang jam selesainya
 * diubah tetapi durasinya tidak — dan yang membacanya tidak punya cara tahu
 * mana dari dua angka itu yang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_itinerary', function (Blueprint $t) {
            $t->string('jam_selesai', 5)->nullable()->after('jam');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_itinerary', function (Blueprint $t) {
            $t->dropColumn('jam_selesai');
        });
    }
};
