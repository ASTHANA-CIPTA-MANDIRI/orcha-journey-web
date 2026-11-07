<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga satu baris master pada satu musim.
 *
 * BARIS ANAK, bukan kolom ketiga di tbl_master_harga — dan bukan baris master
 * kembar.
 *
 * Kolom ketiga tidak cukup: ia hanya bisa memegang satu angka "high season",
 * sementara Nataru dan Lebaran di hotel yang sama hampir selalu berbeda.
 *
 * Baris master kembar justru berbahaya, dan alasannya sudah tertulis di
 * migrasi harga akhir pekan: dua baris master untuk destinasi yang sama
 * ditolak penjaga harga kembar, dan kalaupun dipaksa, "Tarik biaya" menarik
 * KEDUANYA — tiketnya terhitung dua kali.
 *
 * Yang diisi HANYA baris yang benar-benar naik. Tiket Candi Arjuna tetap
 * Rp 10.000 pada 25 Desember, jadi ia tidak punya baris di sini sama sekali,
 * dan ketiadaan barisnya berarti "pakai aturan biasa" — bukan "belum diisi".
 *
 * Bedanya penting, dan karena itu layar HARUS menyebut baris yang jatuh di
 * musim tanpa punya harga musim. Jatuh balik ke tarif hari kerja tanpa bilang
 * apa-apa adalah selisih yang baru terbaca di kasir hotel — alasan yang sama
 * dengan HargaHari::tidakCocok() dan campuran() yang menolak memilih diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_master_harga_musim', function (Blueprint $t) {
            $t->id();

            /*
             | Harga master hilang, harga musimnya ikut hilang: ia tidak punya
             | arti sendirian. RAB yang sudah menariknya TIDAK terpengaruh —
             | harga di baris biaya sudah dibekukan.
             */
            $t->foreignId('master_harga_id')->constrained('tbl_master_harga')->cascadeOnDelete();

            /*
             | Musim yang dihapus membawa harganya. Karena itu musim yang
             | sudah lewat DIMATIKAN, bukan dihapus — lihat kolom aktif di
             | tbl_musim_harga.
             */
            $t->foreignId('musim_id')->constrained('tbl_musim_harga')->cascadeOnDelete();

            /*
             | Nol adalah harga yang SAH dan tidak sama dengan "tidak diisi".
             |
             | Ada tempat yang memang digratiskan di musim tertentu. Yang
             | berarti "pakai aturan biasa" adalah TIDAK ADANYA baris ini,
             | bukan angka nol di dalamnya — karena itu kolomnya wajib isi.
             */
            $t->unsignedBigInteger('harga');

            $t->timestamps();

            // Satu harga, satu musim, satu angka. Dua baris untuk pasangan
            // yang sama tidak menambah arti apa pun, dan membuat "harga
            // Nataru hotel ini berapa" punya dua jawaban.
            $t->unique(['master_harga_id', 'musim_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_master_harga_musim');
    }
};
