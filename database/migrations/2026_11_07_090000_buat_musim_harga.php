<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RENTANG tanggal yang punya tarifnya sendiri — Nataru, Lebaran, long weekend.
 *
 * Kenapa tidak cukup ditaruh di tbl_hari_libur: daftar itu tidak menyimpan
 * harga. Ia hanya berkata "perlakukan tanggal ini seperti akhir pekan", lalu
 * kolom harga_akhir_pekan yang dipakai. Memasukkan 20 Des–5 Jan ke sana
 * membuat Nataru dibayar dengan TARIF HARI SABTU — dan hotel yang Rp 550.000
 * di Sabtu bisa Rp 900.000 di Nataru. Selisih yang tidak berbunyi apa pun
 * sampai rombongannya berdiri di meja resepsionis.
 *
 * Tiga hal yang membuatnya tidak bisa menumpang kolom akhir pekan:
 *
 * 1. Angkanya tingkat KETIGA. Dipaksa masuk kolom akhir pekan, seluruh hari
 *    Sabtu biasa sepanjang tahun ikut jadi tarif Nataru.
 * 2. Rentang, bukan tanggal. Nataru 17 tanggal diketik satu per satu, tiap
 *    tahun — pekerjaan yang karena itu tidak dikerjakan.
 * 3. Nataru BUKAN Lebaran. Satu kolom "high season" memaksa salah satunya
 *    keliru, dan tidak ada apa pun di layar yang menyebut mana.
 *
 * Harganya sendiri TIDAK di sini, melainkan di tbl_master_harga_musim: satu
 * musim menyentuh banyak harga, dan tiap harga naik dengan angkanya sendiri.
 *
 * Tabel kosong bukan kesalahan: artinya hanya hari kerja & akhir pekan yang
 * dikenal, persis seperti sebelum migrasi ini — dan layar mengatakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_musim_harga', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);

            /*
             | Rentang TERTUTUP: kedua ujungnya ikut.
             |
             | Musim sehari — 1 Januari — ditulis mulai & sampai di tanggal
             | yang sama, bukan dengan rentang kosong.
             */
            $t->date('tanggal_mulai');
            $t->date('tanggal_sampai');

            /*
             | Dua musim boleh bertumpuk, dan urutannya harus bisa diputuskan.
             |
             | Cuti bersama di dalam rentang Nataru itu hal yang nyata. Tanpa
             | angka ini pemenangnya ditentukan urutan id — yaitu urutan
             | pengisian, yang tidak ada hubungannya dengan mana yang lebih
             | mahal. Lebih besar menang.
             */
            $t->unsignedSmallInteger('prioritas')->default(0);

            /*
             | Dimatikan tanpa kehilangan angkanya.
             |
             | Musim yang sudah lewat tidak dihapus: RAB lama memegang harga
             | beku, dan riwayatnya harus tetap bisa menyebut nama musimnya.
             | Menghapusnya membuat riwayat berkata "Rp 800.000 → Rp 900.000"
             | tanpa pernah bisa menyebut itu harga apa.
             */
            $t->boolean('aktif')->default(true);

            // Nama, bukan id: tetap terbaca sesudah akunnya hilang.
            $t->string('oleh', 120)->nullable();
            $t->timestamps();

            // Pertanyaan yang selalu ditanyakan: musim apa yang memuat
            // tanggal ini. Keduanya disaring bersama.
            $t->index(['tanggal_mulai', 'tanggal_sampai']);
            $t->index('aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_musim_harga');
    }
};
