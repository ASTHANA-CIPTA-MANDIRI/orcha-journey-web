<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan promo yang benar-benar diberikan pada satu pendaftaran.
 *
 * Tanpa kolom ini, laporan keuntungan menghitung omzet dari harga penuh
 * (jual_satuan x peserta) sementara pelanggan ditagih setelah potongan.
 * Terukur pada rombongan sepuluh orang bertingkat "gratis 1": ditagih
 * Rp 12.870.000, tercatat Rp 14.300.000 — dan keuntungannya dilaporkan lima
 * puluh persen lebih besar daripada yang sebenarnya.
 *
 * DIBEKUKAN, bukan dihitung ulang, mengikuti harga_jual dan harga_modal yang
 * sudah lebih dulu begitu. Alasannya sama: tingkat promo berubah sepanjang
 * tahun, dan laporan bulan lalu tidak boleh ikut berubah tiap admin menyunting
 * angka promo hari ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->unsignedInteger('potongan_promo')->default(0)->after('harga_modal');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropColumn('potongan_promo');
        });
    }
};
