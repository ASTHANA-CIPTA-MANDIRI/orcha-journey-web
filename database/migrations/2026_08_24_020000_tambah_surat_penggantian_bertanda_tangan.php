<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat menyimpan surat pernyataan penggantian yang sudah ditandatangani.
 *
 * Sistem sudah bisa menerbitkan suratnya, tetapi berkas yang kembali —
 * bermaterai, bertanda tangan pemesan dan para pengganti — tidak punya tempat
 * pulang. Selama itu ia hanya ada di percakapan WhatsApp seorang admin, dan
 * hilang begitu ponselnya berganti.
 *
 * Satu berkas per pendaftaran, sejalan dengan suratnya yang juga satu per
 * pemesanan. Unggahan baru menggantikan yang lama: hasil pindaian buram atau
 * tanda tangan yang terlewat memang harus diulang, bukan ditumpuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->string('surat_penggantian')->nullable()->after('riwayat_penggantian');
            $tabel->timestamp('surat_penggantian_pada')->nullable()->after('surat_penggantian');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropColumn(['surat_penggantian', 'surat_penggantian_pada']);
        });
    }
};
