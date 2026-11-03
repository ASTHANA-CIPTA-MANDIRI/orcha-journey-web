<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi penawaran menyimpan RINCIANNYA, bukan cuma angka jadinya.
 *
 * Sebelum ini yang tersimpan hanya harga total, harga per orang, dan untung.
 * Angka-angka itu cukup untuk menjawab "dulu saya menawarkan berapa?",
 * tetapi TIDAK cukup untuk mengembalikan penawarannya: baris biayanya,
 * marginnya, dan pembulatannya tidak pernah ikut disimpan.
 *
 * Akibatnya tim yang menurunkan harga lalu menyesal harus mengetik ulang
 * seluruh angkanya dari tangkapan layar. Kolom ini menyimpan potret utuh
 * saat penawaran dikirim, supaya ia bisa dipasang kembali.
 *
 * Versi yang terlanjur tercatat SEBELUM migrasi ini tidak bisa dipulihkan —
 * rinciannya memang tidak pernah ada. Itu dinyatakan apa adanya di layar,
 * bukan disembunyikan di balik tombol yang gagal diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_versi', function (Blueprint $tabel) {
            $tabel->json('rincian')->nullable()->after('jumlah_baris');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_versi', function (Blueprint $tabel) {
            $tabel->dropColumn('rincian');
        });
    }
};
