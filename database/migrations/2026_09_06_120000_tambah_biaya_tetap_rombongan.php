<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya yang TIDAK ikut bertambah saat pesertanya bertambah.
     *
     * Modal selama ini hanya berbentuk per orang, dan itu benar untuk open
     * trip: kursinya dijual satuan, dan operator sudah membagi biaya busnya ke
     * perkiraan jumlah peserta jauh sebelum harganya dipasang.
     *
     * Private trip dan study tour tidak begitu. Carter bus, guide, sopir, dan
     * tol hampir sama mahalnya untuk tiga orang maupun tiga puluh — dan
     * memaksanya masuk ke kolom "modal per orang" berarti admin harus membagi
     * sendiri di kepalanya tiap kali. Selama ia ingat, hasilnya benar. Yang
     * terjadi sebenarnya adalah ia memakai ulang angka dari rombongan
     * sebelumnya, dan rombongan bertiga lalu dilaporkan untung besar padahal
     * satu busnya saja sudah lebih mahal daripada seluruh omzetnya.
     *
     * Disimpan di PENDAFTARAN, bukan di paket. Biaya tetapnya berubah menurut
     * jarak, jumlah hari, dan musim — satu paket private trip yang sama bisa
     * berangkat dengan biaya carter yang berbeda dua kali lipat. Angka di
     * paket akan selalu salah untuk sebagian besar rombongan yang memakainya.
     *
     * NOL, bukan null, dan bedanya disengaja: modal per orang yang kosong
     * berarti "belum diketahui" dan membuat laporan menolak menghitung, tetapi
     * biaya tetap yang kosong berarti "memang tidak ada" — dan itu keadaan
     * normal seluruh open trip. Kalau ia juga null, setiap open trip yang
     * sudah berjalan mendadak jadi tidak bisa dihitung.
     */
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->unsignedBigInteger('biaya_tetap')->default(0)->after('harga_modal');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn('biaya_tetap');
        });
    }
};
