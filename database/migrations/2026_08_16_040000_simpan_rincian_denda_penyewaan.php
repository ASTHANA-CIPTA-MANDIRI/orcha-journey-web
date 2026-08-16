<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian denda kerusakan yang sudah DITETAPKAN admin ikut disimpan.
 *
 * Selama ini rinciannya selalu dihitung ulang dari selisih kondisi awal dan
 * akhir. Begitu unit selesai diperiksa, keadaan barunya jadi patokan untuk
 * sewa berikutnya — dan sejak saat itu selisihnya nol, jadi rinciannya
 * lenyap dari layar meskipun dendanya sudah terlanjur ditagih.
 *
 * Yang hilang bukan angka totalnya, melainkan alasan di baliknya: bagian mana
 * yang rusak dan berapa masing-masing. Justru itu yang ditanyakan penyewa saat
 * menerima tagihan. Maka rincian yang sudah ditetapkan disimpan apa adanya,
 * bukan dihitung ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->json('rincian_denda')->nullable()->after('catatan_denda');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->dropColumn('rincian_denda');
        });
    }
};
