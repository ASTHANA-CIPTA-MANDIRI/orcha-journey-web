<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guru pendamping yang ikut berangkat tanpa dibayar.
 *
 * Skema baku study tour: sekolah membawa satu atau dua guru pendamping yang
 * tidak ditagih. Selama ini satu-satunya cara menyatakannya adalah menurunkan
 * jumlah peserta — dan itu MERUSAK hal lain yang bergantung pada angka
 * tersebut: gurunya hilang dari manifes tour leader, tidak terhitung di kuota
 * kursi bus, dan tidak diminta mengisi riwayat kesehatan. Padahal ia
 * benar-benar berangkat, benar-benar menempati kursi, dan justru dialah yang
 * paling perlu punya kontak darurat tercatat.
 *
 * Dua angka yang memang berbeda, dan sekarang disimpan terpisah:
 *
 *   jumlah_peserta      — berapa orang BERANGKAT (kursi, manifes, kesehatan)
 *   pendamping_gratis   — berapa di antaranya TIDAK DIBAYAR (tagihan)
 *
 * Dibekukan seperti harga dan potongan promo: kesepakatan "dua guru gratis"
 * dibuat pada hari itu, dan tidak boleh berubah karena seseorang menyunting
 * aturan umum setahun kemudian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->unsignedSmallInteger('pendamping_gratis')->default(0)->after('jumlah_peserta');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropColumn('pendamping_gratis');
        });
    }
};
