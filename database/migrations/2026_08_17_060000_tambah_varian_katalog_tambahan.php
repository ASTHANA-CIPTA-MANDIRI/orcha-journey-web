<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipe/varian yang ditambahkan admin sendiri.
 *
 * Merek dan nama unit yang ditulis manual sudah langsung terdaftar, tetapi tipe
 * tidak: ia hanya mengisi isian, dan baru ikut terbaca sebagai pilihan SETELAH
 * unitnya tersimpan — ditambah simpanan rujukan sepuluh menit yang membuatnya
 * belum muncul juga. Tipe yang diketik lalu dibatalkan hilang begitu saja, dan
 * tipe yang salah tulis tidak bisa dihapus.
 *
 * Satu baris kini bisa berarti tiga tingkat:
 *   (Esemka, null,      null)      — mereknya ada
 *   (Esemka, Bima 1.3,  null)      — modelnya ada
 *   (Toyota, Avanza,    Veloz Q)   — tipenya ada untuk model itu
 *
 * Sehingga ketiganya bisa dihapus sendiri-sendiri.
 *
 * Batasan uniknya diperluas ke tiga kolom. Perlu dicatat: MySQL memperbolehkan
 * beberapa baris NULL pada indeks unik, jadi indeks ini bukan satu-satunya
 * pengaman — pemeriksaan "sudah ada" di controller yang menjadi pengaman
 * utamanya, dan indeks ini jaring keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('katalog_kendaraan_tambahan', function (Blueprint $tabel) {
            $tabel->string('varian', 60)->nullable()->after('model');
            $tabel->dropUnique(['merek', 'model']);
            $tabel->unique(['merek', 'model', 'varian']);
        });
    }

    public function down(): void
    {
        Schema::table('katalog_kendaraan_tambahan', function (Blueprint $tabel) {
            $tabel->dropUnique(['merek', 'model', 'varian']);
            $tabel->dropColumn('varian');
            $tabel->unique(['merek', 'model']);
        });
    }
};
