<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekaman penawaran pada saat ia DIKIRIM ke pelanggan.
 *
 * Mengubah harga sesudah penawaran dikirim tidak meninggalkan jejak apa
 * pun, jadi "versi mana yang dia setujui" tidak bisa dijawab — padahal RAB
 * justru satu-satunya hal di sistem ini yang keluar ke orang lain.
 *
 * Yang disimpan ANGKA-ANGKANYA, bukan seluruh barisnya: yang ditanyakan
 * saat beda pendapat selalu "waktu itu berapa", dan menyalin puluhan baris
 * tiap kali tombol kirim ditekan membuat tabelnya tumbuh tanpa dibaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_rab_versi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rab_id')->constrained('tbl_rab')->cascadeOnDelete();
            $t->unsignedInteger('urutan')->default(1);
            $t->unsignedBigInteger('harga_total');
            $t->unsignedBigInteger('harga_per_orang');
            $t->bigInteger('untung');
            $t->decimal('persen_untung', 6, 2)->nullable();
            $t->unsignedInteger('jumlah_peserta');
            $t->unsignedInteger('jumlah_baris')->default(0);
            // Nama, bukan id: versi harus tetap terbaca sesudah akunnya hilang.
            $t->string('oleh', 120)->nullable();
            $t->timestamps();

            $t->index(['rab_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rab_versi');
    }
};
