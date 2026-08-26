<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perincian estimasi biaya sewa ikut disimpan, bukan hanya totalnya.
 *
 * Halaman pemesanan sudah menampilkan asal angkanya baris demi baris — tarif
 * dikali lama sewa, sopir, BBM — tetapi yang tersimpan cuma satu bilangan.
 * Akibatnya berkas yang dikirim ke penyewa hanya sanggup menulis "Estimasi
 * biaya sewa Rp sekian", persis angka yang tadi ia lihat tanpa penjelasan.
 * Padahal justru pada tahap ini, sebelum uang ditransfer, pertanyaannya
 * "kok segitu?" — dan yang menjawab itu perinciannya.
 *
 * Tidak dihitung ulang dari tarif unit saat berkasnya dibuat: tarif bisa
 * berubah kapan saja, dan perincian yang jumlahnya tidak lagi sama dengan
 * total yang dipesan lebih buruk daripada tidak ada perincian sama sekali.
 * Maka disalin sekali saat pesanan dibuat, sebagaimana totalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->json('rincian_estimasi')->nullable()->after('estimasi_biaya');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->dropColumn('rincian_estimasi');
        });
    }
};
