<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan yang DITETAPKAN admin, terpisah dari yang diusulkan sistem.
 *
 * Perkiraan dihitung dari tangga kebijakan, dan itu benar untuk kebanyakan
 * perkara. Tapi ada yang tidak diketahui sistem: tiket masuk yang sudah
 * dibayarkan ke pihak ketiga dan tidak bisa ditarik, kesepakatan menjadwal
 * ulang sebagian, atau kelonggaran yang diberikan karena alasannya musibah.
 *
 * Selama angka itu hanya hidup di kepala admin, yang tercatat di sistem
 * berbeda dengan yang benar-benar dikirim — dan setengah tahun kemudian tidak
 * ada yang bisa menjelaskan selisihnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pembatalan', function (Blueprint $tabel) {
            $tabel->unsignedBigInteger('potongan_ditetapkan')->nullable()->after('catatan_admin');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pembatalan', function (Blueprint $tabel) {
            $tabel->dropColumn('potongan_ditetapkan');
        });
    }
};
