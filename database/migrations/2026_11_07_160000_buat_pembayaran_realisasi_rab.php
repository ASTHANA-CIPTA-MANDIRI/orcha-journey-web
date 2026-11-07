<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiap kali uang keluar untuk sebuah biaya: DP, pelunasan, cicilan.
 *
 * BARIS ANAK, bukan dua kolom dp & pelunasan — dan alasannya sudah dua kali
 * terbukti di aplikasi ini (harga akhir pekan, lalu tarif musim): dua kolom
 * tidak bisa menampung yang ketiga.
 *
 * Di sini bahkan tidak perlu menunggu kasus ketiga. Tiket Baluran dibayar
 * SEKALI tanpa DP sama sekali, sementara hotel dibayar dua kali. "DP" dan
 * "pelunasan" ternyata bukan dua kolom yang selalu ada, melainkan LABEL dari
 * satu hal yang sama: sebuah pembayaran.
 *
 * Buktinya menumpang jalur yang sudah aman: GambarWebp::simpan(...,
 * 'bukti-bayar') menaruhnya di cakram `rahasia` — hanya bisa dibuka lewat
 * BerkasRahasiaController yang memeriksa login — dan perintah
 * orcha:berkas-yatim memang sengaja tidak pernah menyentuh folder itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_rab_realisasi_bayar', function (Blueprint $t) {
            $t->id();

            $t->foreignId('realisasi_id')->constrained('tbl_rab_realisasi')->cascadeOnDelete();

            // 'dp' | 'pelunasan' | 'cicilan' | 'refund'. String, bukan enum:
            // jenis baru tidak boleh butuh ALTER TABLE di produksi.
            $t->string('jenis', 20)->default('pelunasan');

            /*
             | Nominalnya BERTANDA lewat jenis, bukan lewat angka negatif.
             |
             | Refund — cashback yang benar-benar dikembalikan tunai — adalah
             | uang yang MASUK, dan menuliskannya sebagai nominal negatif
             | membuat setiap penjumlahan di seluruh laporan harus ingat
             | memeriksa tandanya. Satu tempat yang lupa, dan totalnya salah
             | tanpa berbunyi.
             */
            $t->unsignedBigInteger('nominal');

            $t->date('tanggal')->nullable();

            // Jalur berkas di cakram rahasia. Kosong = bukti belum diunggah,
            // dan itu DISEBUT di layar: pembayaran tanpa bukti adalah baris
            // yang tidak bisa dipertanggungjawabkan saat ditanya.
            $t->string('bukti', 255)->nullable();

            /*
             | Bukti yang pernah dipakai baris ini.
             |
             | Mengganti bukti pada catatan uang adalah tindakan yang paling
             | mungkin dipersoalkan belakangan, dan yang mempersoalkannya akan
             | bertanya "yang lama mana?". Pertanyaan itu tidak bisa dijawab
             | kalau jawabannya sudah ditimpa. Polanya sama persis dengan
             | bukti_riwayat di konfirmasi pembayaran peserta.
             */
            $t->json('bukti_riwayat')->nullable();

            // Siapa yang membayar — nama, bukan id.
            $t->string('oleh', 120)->nullable();
            $t->string('catatan', 300)->nullable();
            $t->timestamps();

            $t->index(['realisasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rab_realisasi_bayar');
    }
};
