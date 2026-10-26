<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis perjalanan dan dasar perhitungan harganya.
 *
 * Private trip dan open trip bukan dua nama untuk hal yang sama: pada private
 * trip jumlah orangnya SUDAH PASTI di kesepakatan, jadi kursi kosong bukan
 * risiko kantor. Pada open trip harga diumumkan lebih dulu dan jumlah orangnya
 * baru ketahuan belakangan — risikonya pindah ke kantor, dan yang menanggung
 * bukan biaya per orang melainkan biaya tetap yang dibayar sama saja entah
 * yang datang lima atau empat belas.
 *
 * Karena itu harga open trip boleh dihitung dari jumlah PALING RENDAH yang
 * masih mau diberangkatkan, bukan dari target. Yang di atas itu jadi untung,
 * bukan penyelamat.
 *
 * Bawaannya 'private' supaya seluruh RAB yang sudah ada tetap dihitung persis
 * seperti sebelumnya — perubahan ini tidak boleh menggeser satu rupiah pun
 * pada penawaran yang sudah dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            $t->string('jenis', 20)->default('private')->after('provinsi');
            $t->unsignedSmallInteger('peserta_minimum')->nullable()->after('jumlah_peserta');
            $t->string('harga_dasar', 20)->default('peserta')->after('peserta_minimum');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            $t->dropColumn(['jenis', 'peserta_minimum', 'harga_dasar']);
        });
    }
};
