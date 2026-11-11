<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipe/jenis barang yang dijual baris harga ini.
 *
 * HiAce, Elf, Jeep, Twin, Deluxe — kata yang selama ini DIKETIK ke dalam
 * nama barisnya ("HiAce Jogja - Banyuwangi") dan karena itu tidak pernah
 * bisa dipakai apa pun selain mata: tidak bisa disaring, tidak bisa
 * dibandingkan, dan hilang begitu namanya diubah.
 *
 * Boleh dikosongkan. Sebagian besar baris memang tidak punya tipe —
 * retribusi pantai bukan "jenis" apa-apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $tabel) {
            $tabel->string('tipe', 40)->nullable()->after('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $tabel) {
            $tabel->dropColumn('tipe');
        });
    }
};
