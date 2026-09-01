<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda apakah satu paket ikut promo rombongan.
 *
 * Tingkat promonya seragam untuk seluruh perusahaan, tetapi TIDAK setiap trip
 * ikut: sebagian sudah tipis marginnya, sebagian sedang musim ramai dan tidak
 * perlu didorong.
 *
 * BAWAANNYA MATI, dan itu disengaja.
 *
 * Kalau bawaannya hidup, seluruh paket yang sudah ada mendadak memberi
 * potongan rombongan begitu migrasi ini jalan — tanpa satu pun keputusan
 * diambil, dan yang menyadarinya belakangan adalah laporan keuntungan. Mati
 * lebih dulu berarti promonya tidak melakukan apa-apa sampai ada yang sengaja
 * menyalakannya per paket; itu keadaan yang terlihat dan bisa diperbaiki,
 * bukan uang yang sudah telanjur keluar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $tabel) {
            $tabel->boolean('promo_rombongan')->default(false)->after('catatan_promo');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $tabel) {
            $tabel->dropColumn('promo_rombongan');
        });
    }
};
