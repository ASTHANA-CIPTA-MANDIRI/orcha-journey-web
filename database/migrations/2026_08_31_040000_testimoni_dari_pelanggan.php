<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Testimoni yang ditulis pelanggannya sendiri.
 *
 * Selama ini testimoni hanya bisa dimasukkan admin lewat /admin/testimoni.
 * Dua akibatnya:
 *
 *   1. Jumlahnya tidak pernah tumbuh sendiri. Tiap cerita harus diminta,
 *      disalin, lalu diketik ulang oleh admin — pekerjaan yang selalu kalah
 *      prioritas dibanding pesanan yang sedang berjalan.
 *
 *   2. Bobotnya ringan di mata calon pembeli. Testimoni yang jelas dikurasi
 *      penjual dibaca sebagai bahan pemasaran, bukan kesaksian.
 *
 * KODE PESANAN DIPAKAI SEBAGAI SYARAT, bukan sekadar formulir terbuka.
 * Penulisnya harus membuktikan ia memang pernah memesan — kode ditambah empat
 * digit terakhir nomornya, penjagaan yang sama dengan halaman lacak pesanan.
 * Itu sekaligus menyelesaikan dua hal: spam tidak punya jalan masuk, dan
 * testimoninya boleh ditandai terverifikasi secara jujur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_testimoni', function (Blueprint $tabel) {
            /*
             | Bawaannya 'menunggu' untuk baris BARU.
             |
             | Baris lama diisi 'tayang' di bawah: semuanya dimasukkan admin
             | sendiri dan sudah tampil di halaman publik selama ini.
             | Membiarkannya ikut 'menunggu' akan mengosongkan halaman testimoni
             | pada detik migrasi ini jalan.
             */
            $tabel->string('status')->default('menunggu')->after('avatar');

            // Kode pesanan penulisnya. Nullable karena baris lama tidak
            // punya — testimoninya nyata, hanya asalnya bukan formulir ini.
            $tabel->string('kode_pesanan')->nullable()->after('status');

            $tabel->index('status');
        });

        DB::table('tbl_testimoni')->update(['status' => 'tayang']);
    }

    public function down(): void
    {
        Schema::table('tbl_testimoni', function (Blueprint $tabel) {
            $tabel->dropIndex(['status']);
            $tabel->dropColumn(['status', 'kode_pesanan']);
        });
    }
};
