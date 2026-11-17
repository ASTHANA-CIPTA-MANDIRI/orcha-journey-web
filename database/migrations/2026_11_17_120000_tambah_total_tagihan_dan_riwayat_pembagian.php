<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom, dua keperluan yang berbeda.
 *
 * total_tagihan — SALINAN omzet yang disimpan, supaya daftar pendaftaran bisa
 * diurut menurut sisa tagihan. Angkanya tetap dihitung PHP (satu-satunya
 * tempat aturannya hidup: PendaftaranOpenTrip::getOmzetAttribute), lalu
 * dituliskan ke sini pada tiap penyimpanan. Yang ditolak adalah menulis ulang
 * hitungan itu sebagai SQL hanya supaya bisa diurut — dua hitungan untuk satu
 * angka uang cepat atau lambat berbeda jawabannya, dan yang berbeda di sini
 * adalah tagihan pelanggan.
 *
 * pembagian_riwayat — potret pembagian kursi & kamar SEBELUM tiap perubahan.
 * Satu tekanan "bagi otomatis" dengan centang timpa menghapus satu jam
 * penyusunan tangan, dan hasilnya tetap terlihat rapi sehingga tidak ada yang
 * menyadarinya sampai ada yang protes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            /*
             | Nullable, bukan default nol.
             |
             | Null berarti "belum pernah dihitung" — keadaan nyata untuk baris
             | lama sampai perintah pendaftaran:hitung-tagihan dijalankan. Nol
             | berarti "tagihannya memang nol", dan itu keadaan yang berbeda
             | (paket tanpa harga, rombongan yang seluruhnya pendamping gratis).
             | Disamakan, daftar yang diurut sisa tagihan menaruh seluruh data
             | lama di puncak sebagai "tidak punya tagihan".
             */
            $table->unsignedBigInteger('total_tagihan')->nullable()->after('harga_modal');
        });

        Schema::table('tbl_paket_keberangkatan', function (Blueprint $table) {
            $table->json('pembagian_riwayat')->nullable()->after('kamar');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn('total_tagihan');
        });

        Schema::table('tbl_paket_keberangkatan', function (Blueprint $table) {
            $table->dropColumn('pembagian_riwayat');
        });
    }
};
