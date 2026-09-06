<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip bukti pembayaran yang pernah dipakai catatan ini.
 *
 * Bukti susulan dan bukti pengganti sama-sama menulis ke kolom 'bukti'. Tanpa
 * arsip, gambar yang lama hilang dari jangkauan begitu ditimpa — dan yang
 * hilang itu justru bukti sebuah transaksi.
 *
 * Mengganti bukti pada catatan uang adalah tindakan yang paling mungkin
 * dipersoalkan belakangan, dan yang mempersoalkannya akan bertanya "yang lama
 * mana?". Pertanyaan itu tidak bisa dijawab kalau jawabannya sudah ditimpa.
 *
 * Berisi daftar {jalur, diganti_pada, oleh}. Berkasnya sendiri TIDAK dihapus
 * dari cakram, dan aman di sana: orcha:berkas-yatim sengaja tidak pernah
 * menyentuh folder bukti-bayar sama sekali — lihat alasannya di perintah itu.
 * Jadi arsip ini tidak perlu didaftarkan ke mana pun supaya selamat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_konfirmasi_pembayaran', function (Blueprint $tabel) {
            $tabel->json('bukti_riwayat')->nullable()->after('bukti');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_konfirmasi_pembayaran', function (Blueprint $tabel) {
            $tabel->dropColumn('bukti_riwayat');
        });
    }
};
