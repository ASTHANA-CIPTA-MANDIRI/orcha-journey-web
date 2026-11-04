<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tiket untuk beberapa destinasi.
 *
 * Tiket terusan Dieng berlaku untuk Candi Arjuna DAN Kawah Sikidang, dibayar
 * sekali. Master harga hanya punya satu kolom `destinasi`, jadi tidak ada cara
 * memasukkannya dengan benar — dua-duanya salah, dan arah salahnya berlawanan:
 *
 * - Dibuat DUA baris master, satu per destinasi: keduanya tertarik ke RAB dan
 *   tiketnya ditagih dua kali.
 * - Dibuat SATU baris dengan destinasi Candi Arjuna: Kawah Sikidang dilaporkan
 *   "belum ada tiketnya", pil harinya cuma menyebut hari Candi Arjuna, dan —
 *   yang paling mahal — begitu Candi Arjuna digeser keluar jadwal, pembuang
 *   tiket yatim MENGHAPUS tiketnya, padahal Kawah Sikidang masih dikunjungi
 *   dan masih membutuhkannya. Barisnya hilang tanpa pemberitahuan; RAB-nya
 *   tetap terlihat wajar, cuma modalnya berkurang.
 *
 * Kolom ini menampung destinasi LAIN yang tercakup tiket yang sama. `destinasi`
 * tetap yang utama — ia yang ditampilkan, diurutkan, dan diindeks — supaya
 * seluruh kueri lama tetap berarti dan tidak ada data yang perlu dipindah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $table) {
            $table->json('destinasi_lain')->nullable()->after('destinasi');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga', function (Blueprint $table) {
            $table->dropColumn('destinasi_lain');
        });
    }
};
