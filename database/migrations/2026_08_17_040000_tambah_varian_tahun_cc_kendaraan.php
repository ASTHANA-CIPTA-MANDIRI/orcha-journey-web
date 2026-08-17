<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipe, tahun, dan isi silinder unit.
 *
 * Selama ini keterangan unit hanya "Avanza" — padahal penyewa menanyakan hal
 * yang lebih rinci: tipe E atau G, tahun berapa, mesinnya berapa cc. Jawabannya
 * ada di kepala pemilik, bukan di sistem.
 *
 * Ketiganya kolom TERSENDIRI, bukan disatukan ke dalam nama unit menjadi "Agya
 * tipe E tahun 2025 1200cc". Nama yang dijejali begitu tidak bisa disaring,
 * tidak bisa diurutkan menurut tahun, dan mengulang kesalahan yang sama seperti
 * merek yang dulu diketik bebas: satu unit tercatat dengan beberapa ejaan.
 * Menggabungkannya untuk ditampilkan mudah; memisahkannya kembali sesudah
 * tercampur tidak.
 *
 * Ketiganya nullable. Unit lama tidak tahu tahun dan cc-nya, dan memaksa
 * angka ke sana hanya menghasilkan data karangan yang terlihat seperti fakta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            // Tipe/varian: "E", "G", "GR Sport", "Commuter". Pendek, jadi 60
            // cukup — ini bukan tempat menuliskan keterangan panjang.
            $tabel->string('varian', 60)->nullable()->after('name');

            // Tahun perakitan. smallInteger cukup sampai tahun 32767.
            $tabel->smallInteger('tahun')->nullable()->after('varian');

            // Isi silinder dalam cc. Mobil terkecil di pasar Indonesia ~1000cc,
            // bus besar ~7700cc; integer biasa jauh lebih dari cukup.
            $tabel->unsignedSmallInteger('cc')->nullable()->after('tahun');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn(['varian', 'tahun', 'cc']);
        });
    }
};
