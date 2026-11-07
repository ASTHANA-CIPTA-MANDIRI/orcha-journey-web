<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berapa MALAM (atau hari) baris ini dipakai — bukan selalu sepanjang trip.
 *
 * Satuan yang dikali durasi memakai angka milik PERJALANAN: kamar_malam
 * dikali jumlah_malam, unit_hari dikali jumlah_hari. Itu benar selama satu
 * baris menutup seluruh perjalanan.
 *
 * Begitu rombongannya PINDAH menginap, tidak ada cara menuliskannya.
 * Perjalanan 3 malam dengan Homestay 1 malam lalu Sans Hotel 2 malam
 * menagih KEDUANYA 3 malam — enam malam dibayar untuk tiga malam menginap,
 * dan tidak ada satu pun angka yang terlihat salah.
 *
 * Kolom `jumlah` tidak menolong, sama seperti saat `unit_manual` dibuat: ia
 * pengali buta. "2×" di kartu hotel tidak menyebut dua malam, dan saat
 * perjalanannya bertambah jadi 4D3N ia diam sementara kamar_malam ikut —
 * penawaran yang kurang satu malam tanpa satu pun tanda.
 *
 * Jalan pintas yang sempat dipakai — mengubah satuannya jadi `unit` (sekali)
 * — lebih buruk lagi: kartunya jadi berbohong tentang satuannya sendiri, dan
 * hubungannya ke panjang perjalanan putus sama sekali.
 *
 * SATU kolom untuk malam dan hari, bukan dua: satuannya yang menentukan
 * artinya, dan dua kolom yang hanya salah satunya pernah terisi adalah dua
 * tempat untuk mencari jawaban yang sama.
 *
 * NULL berarti "ikut panjang perjalanan", jadi seluruh baris yang sudah ada
 * tidak berubah sedikit pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $table) {
            $table->unsignedSmallInteger('malam_manual')->nullable()->after('unit_manual');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $table) {
            $table->dropColumn('malam_manual');
        });
    }
};
