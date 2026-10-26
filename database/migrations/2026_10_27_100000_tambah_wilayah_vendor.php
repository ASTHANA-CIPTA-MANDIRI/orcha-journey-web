<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor melayani wilayah mana.
     *
     * Master harga sudah terikat provinsi; vendornya belum. Akibatnya saat
     * mengisi harga untuk trip Bali, kotak pilih vendor tetap menampilkan
     * seluruh vendor termasuk yang hanya melayani Jogja. Dengan lima vendor
     * itu tidak terasa; dengan empat puluh, yang benar tenggelam di antara
     * yang tidak mungkin dipakai.
     *
     * Boleh kosong, dan kosong berarti BERLAKU DI MANA SAJA — bukan "belum
     * diisi". Asuransi dan vendor dokumentasi memang tidak terikat tempat,
     * dan memaksa mereka memilih provinsi hanya melahirkan isian asal.
     */
    public function up(): void
    {
        Schema::table('tbl_vendor', function (Blueprint $t) {
            $t->string('provinsi', 80)->nullable()->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_vendor', function (Blueprint $t) {
            $t->dropColumn('provinsi');
        });
    }
};
