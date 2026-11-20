<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda versi RAB, untuk dua orang yang menyusun satu RAB bersamaan.
 *
 * Dilaporkan 20 Nov 2026: A dan B membuka RAB yang sama, A mengubah harga
 * jadi 1 juta, lalu angka di layar A balik sendiri ke 900 ribu.
 *
 * Itu bukan tampilan yang basi — itu DATA YANG HILANG. simpanItinerary()
 * menghapus seluruh jadwal lalu menulis ulang dari kiriman pengirim, jadi
 * siapa pun yang menyimpan terakhir menimpa pekerjaan yang lain dengan
 * salinan lama yang masih dipegangnya. Tanpa galat, tanpa tanda apa pun.
 *
 * Kolom ini membuat keadaan itu bisa DIKETAHUI: tiap penyimpanan membawa
 * versi yang jadi dasarnya, dan peladen menolak yang dasarnya sudah
 * ketinggalan. Menolak satu simpanan jauh lebih murah daripada kehilangan
 * satu jadwal.
 *
 * `versi_oleh` dan `versi_pada` bukan hiasan: pesan "ditolak" yang tidak
 * menyebut siapa dan kapan membuat orang menebak, dan yang paling sering
 * ditebak adalah "aplikasinya rusak".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            // Mulai dari 1, bukan 0: RAB lama pun punya versi yang sah, jadi
            // kiriman pertama dari layar lama tidak langsung ditolak.
            $t->unsignedBigInteger('versi')->default(1)->after('status');
            $t->string('versi_oleh', 150)->nullable()->after('versi');
            $t->timestamp('versi_pada')->nullable()->after('versi_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            $t->dropColumn(['versi', 'versi_oleh', 'versi_pada']);
        });
    }
};
