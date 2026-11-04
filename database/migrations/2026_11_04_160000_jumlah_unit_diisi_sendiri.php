<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah kamar/unit yang DITENTUKAN admin, bukan diturunkan dari peserta.
 *
 * Satuan berkapasitas menghitung unitnya sendiri: ceil(peserta ÷ kapasitas).
 * Itu benar selama seluruh rombongan memakai satu jenis unit — satu model bus,
 * satu tipe kamar.
 *
 * Begitu pelanggan minta campuran, tidak ada cara menuliskannya. Dua kamar
 * Family dan tiga kamar Standard di hotel yang sama menjadi DUA baris yang
 * masing-masing menginapkan SELURUH rombongan: 20 peserta jadi 5 kamar family
 * DAN 10 kamar standard sekaligus. Kolom `jumlah` tidak menolong — ia
 * mengalikan, bukan menggantikan, sehingga "2" terbaca sebagai dua kali lima
 * kamar. Rp 3.800.000 yang sebenarnya terhitung Rp 28.000.000, tanpa satu pun
 * tanda.
 *
 * Kolom ini menggantikan penurunan otomatis itu untuk baris yang mengisinya.
 * NULL berarti "hitung seperti biasa", jadi seluruh baris yang sudah ada tidak
 * berubah sedikit pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $table) {
            $table->unsignedSmallInteger('unit_manual')->nullable()->after('kapasitas');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $table) {
            $table->dropColumn('unit_manual');
        });
    }
};
