<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga yang DISEPAKATI, bila berbeda dari harga yang dihitung sistem.
 *
 * Sebelum ini harga jual sepenuhnya diturunkan dari modal + margin. Artinya
 * satu-satunya cara memenuhi tawaran pelanggan adalah mengutak-atik margin
 * sampai angkanya kebetulan jatuh pas — hitungan mundur yang dikerjakan di
 * kepala, dan karena pembulatannya ke atas, angka persis sering memang tidak
 * bisa dicapai.
 *
 * Dengan kolom ini arahnya dibalik: admin mengetik angka yang diminta
 * pelanggan, dan MARGIN-lah yang jadi hasil. Nullable, jadi RAB yang tidak
 * dinegosiasikan berjalan persis seperti sebelumnya.
 *
 * Harga aslinya sengaja TIDAK ikut disimpan: ia selalu bisa dihitung ulang
 * dari modal + margin yang masih utuh di kolomnya masing-masing, dan dua
 * kolom yang menjawab hal yang sama akan berselisih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            $t->unsignedBigInteger('harga_nego')->nullable()->after('pembulatan');
            // Alasannya ikut disimpan: potongan tanpa sebab tidak bisa
            // dipertanggungjawabkan saat laporannya dibaca bulan depan,
            // dan "kenapa yang ini lebih murah" adalah pertanyaan pertama.
            $t->string('alasan_nego', 200)->nullable()->after('harga_nego');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab', function (Blueprint $t) {
            $t->dropColumn(['harga_nego', 'alasan_nego']);
        });
    }
};
