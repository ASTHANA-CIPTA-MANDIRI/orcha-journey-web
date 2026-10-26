<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor: dari siapa sebuah harga dibeli, dan ke nomor mana meneleponnya.
     *
     * Sebelum ini kontak vendor cuma muat di kolom catatan master harga —
     * teks bebas, tidak bisa dicari, dan TIDAK ikut tersalin ke RAB. Admin
     * yang sedang menyusun penawaran dan perlu menelepon vendor busnya harus
     * meninggalkan layar itu, membuka master harga, dan mencari barisnya.
     *
     * Dibuat sebagai tabel sendiri, bukan dua kolom di master harga, karena
     * satu vendor biasanya memasok beberapa harga sekaligus — Hiace, Elf,
     * dan bus besar dari pemilik armada yang sama. Dengan kolom, nomor yang
     * berganti harus diubah di setiap barisnya, dan yang terlewat satu pun
     * membuat nomor lama bertahan tanpa ada yang tahu mana yang benar.
     *
     * vendor_id di tbl_rab_biaya SENGAJA tidak membekukan nama & nomornya.
     * Harga memang dibekukan — penawaran yang angkanya berubah sendiri
     * adalah sengketa yang menunggu terjadi — tetapi nomor telepon justru
     * sebaliknya: yang dibutuhkan selalu nomor yang berlaku HARI INI, bukan
     * nomor yang berlaku saat penawaran dibuat.
     */
    public function up(): void
    {
        Schema::create('tbl_vendor', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);
            // Nomor, nama orangnya, atau keduanya — ditulis apa adanya.
            // Memaksa format nomor hanya membuat "0812xxx (Pak Budi)" ditolak
            // oleh aplikasi padahal itu justru yang berguna.
            $t->string('kontak', 120)->nullable();
            $t->string('jenis', 30)->nullable();
            $t->string('catatan', 300)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index('aktif');
        });

        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->foreignId('vendor_id')->nullable()->after('kategori')
                ->constrained('tbl_vendor')->nullOnDelete();
        });

        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->foreignId('vendor_id')->nullable()->after('master_harga_id')
                ->constrained('tbl_vendor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->dropForeign(['vendor_id']);
            $t->dropColumn('vendor_id');
        });

        Schema::table('tbl_master_harga', function (Blueprint $t) {
            $t->dropForeign(['vendor_id']);
            $t->dropColumn('vendor_id');
        });

        Schema::dropIfExists('tbl_vendor');
    }
};
