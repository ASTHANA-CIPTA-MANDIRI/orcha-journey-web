<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kenapa harga baris ini berbeda dari master.
 *
 * Harga di RAB dibekukan dan boleh disunting, jadi tim yang menemukan hotel
 * lebih murah tinggal menimpanya. Yang tidak tersimpan selama ini adalah
 * SEBABNYA — hasil nego vendor, low season, atau kamar triple — sehingga
 * beberapa bulan kemudian tidak ada yang bisa membedakan harga temuan dari
 * salah ketik, dan tidak ada yang berani memakainya lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->string('catatan', 200)->nullable()->after('urutan');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->dropColumn('catatan');
        });
    }
};
