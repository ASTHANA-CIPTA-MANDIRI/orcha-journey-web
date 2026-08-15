<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status dan jadwal penayangan paket.
 *
 * Penayangan sengaja dihitung dari tanggal saat data diambil, bukan dijadwal
 * lewat cron. Alasannya: cron bisa mati diam-diam, dan kalau itu terjadi paket
 * yang seharusnya sudah tayang jadi tidak muncul tanpa ada yang sadar. Dengan
 * dihitung, hasilnya selalu benar meski tidak ada penjadwal sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            // draf | terbit | arsip. Yang sudah ada dianggap terbit supaya
            // website tidak mendadak kosong setelah migrasi.
            $table->string('status', 20)->default('terbit')->after('category');

            // Mulai tayang. Kosong = langsung tayang begitu berstatus terbit.
            $table->dateTime('tayang_mulai')->nullable()->after('status');

            // Berhenti tayang. Kosong = tidak dibatasi waktu.
            $table->dateTime('tayang_sampai')->nullable()->after('tayang_mulai');

            // Trip bertanggal berhenti tayang sendiri setelah rombongan pulang.
            // Bisa dimatikan untuk paket yang tanggalnya cuma contoh.
            $table->boolean('berakhir_otomatis')->default(true)->after('tayang_sampai');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->dropColumn(['status', 'tayang_mulai', 'tayang_sampai', 'berakhir_otomatis']);
        });
    }
};
