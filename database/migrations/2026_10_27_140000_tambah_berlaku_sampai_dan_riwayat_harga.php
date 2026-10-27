<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua hal yang selama ini hanya hidup di kepala admin.
 *
 * `berlaku_sampai`: harga vendor punya masa berlaku, dan sampai sekarang
 * ditulis sebagai kalimat di kolom catatan — jadi tidak ada apa pun yang
 * bisa memperingatkan saat tanggalnya lewat. Harga tiket tahun lalu
 * terlihat persis sama meyakinkannya dengan harga kemarin.
 *
 * `tbl_master_harga_riwayat`: yang tersimpan selama ini hanya angka hari
 * ini. "Naik berapa sejak kapan, dan siapa yang mengubahnya" tidak bisa
 * dijawab, padahal itu pertanyaan pertama saat harga terasa janggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->date('berlaku_sampai')->nullable()->after('kapasitas');
        });

        Schema::create('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('master_harga_id')->constrained('tbl_master_harga')->cascadeOnDelete();
            $t->unsignedBigInteger('harga_lama');
            $t->unsignedBigInteger('harga_baru');
            // Siapa yang mengubah: disimpan sebagai NAMA, bukan id. Riwayat
            // harus tetap terbaca setelah akunnya dihapus atau berganti peran.
            $t->string('oleh', 120)->nullable();
            $t->string('catatan', 255)->nullable();
            $t->timestamps();

            $t->index(['master_harga_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_master_harga_riwayat');

        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->dropColumn('berlaku_sampai');
        });
    }
};
