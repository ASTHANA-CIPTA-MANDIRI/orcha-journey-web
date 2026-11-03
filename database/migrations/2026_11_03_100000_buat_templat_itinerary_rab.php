<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal perjalanan yang disimpan untuk dipakai lagi.
 *
 * Mengambil jadwal dari paket wisata sudah bisa sejak lama, tetapi jalurnya
 * SATU ARAH: jadwal bagus yang baru saja disusun di RAB tidak bisa disimpan
 * ke mana pun, jadi tiap "Jogja 3H2M" berikutnya disusun dari nol lagi.
 *
 * Tabelnya sendiri, BUKAN menulis balik ke tbl_travel_packages: paket wisata
 * adalah katalog yang dibaca pengunjung situs. Menyimpan jadwal kerja ke sana
 * berarti satu klik admin di layar RAB mengubah apa yang dilihat publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_rab_templat', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 150);
            $tabel->string('provinsi', 80)->nullable();
            $tabel->unsignedSmallInteger('jumlah_hari')->default(1);
            $tabel->unsignedSmallInteger('jumlah_kegiatan')->default(0);
            $tabel->json('itinerary');
            $tabel->string('oleh', 120)->nullable();
            $tabel->timestamps();

            // Disaring per provinsi di panel "ambil jadwal", dan diurut nama.
            $tabel->index(['provinsi', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rab_templat');
    }
};
