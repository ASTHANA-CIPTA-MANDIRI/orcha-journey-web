<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga yang berlaku pada akhir pekan & hari libur.
 *
 * KOLOM kedua, bukan baris kedua. Dua baris master untuk destinasi yang sama
 * tidak bisa dipakai: penjaga harga kembar menolaknya, dan kalaupun dipaksa,
 * "Tarik biaya" menarik KEDUANYA — tiket Baluran jadi terhitung Rp 52.000
 * per orang alih-alih 21.000 atau 31.000.
 *
 * Nullable, dan kosong berarti harganya sama setiap hari. Seluruh baris yang
 * sudah ada karena itu berperilaku persis seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->unsignedBigInteger('harga_akhir_pekan')->nullable()->after('harga');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->dropColumn('harga_akhir_pekan');
        });
    }
};
