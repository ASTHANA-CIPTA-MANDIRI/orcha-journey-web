<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boleh atau tidaknya unit disewa lepas kunci.
 *
 * HiAce, bus, dan unit besar lainnya tidak dilepas tanpa sopir — mengemudikannya
 * butuh SIM dan kebiasaan yang tidak dimiliki penyewa umum, dan risikonya tidak
 * sebanding. Sampai sekarang aturan itu hanya ada di kepala pemilik: sistemnya
 * tidak tahu, sehingga tidak ada yang mencegah unit besar ditawarkan lepas
 * kunci.
 *
 * Kolom ini juga menentukan hitungan kursi penumpang. Unit yang selalu dengan
 * sopir kehilangan satu kursi untuk sopirnya: HiAce 15 kursi berarti 14
 * penumpang, bukan 15. Selisih satu itu yang membuat rombongan lima belas orang
 * dijanjikan muat lalu ternyata tidak — kesalahan yang baru diketahui di hari
 * keberangkatan.
 *
 * Unit yang sudah ada disesuaikan sekaligus: yang berjenis hiace dan bus
 * ditandai tidak boleh lepas kunci. Membiarkan semuanya bernilai bawaan true
 * berarti data lamanya menyatakan hal yang justru tidak benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->boolean('lepas_kunci')->default(true)->after('transmisi_tersedia');
        });

        DB::table('cars')->whereIn('type', ['hiace', 'bus'])->update(['lepas_kunci' => false]);
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn('lepas_kunci');
        });
    }
};
