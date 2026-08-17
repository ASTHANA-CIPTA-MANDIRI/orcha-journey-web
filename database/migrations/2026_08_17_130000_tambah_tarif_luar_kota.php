<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif harian untuk perjalanan luar kota.
 *
 * Perjalanan luar kota dihargai berbeda — sopirnya menginap, unitnya menempuh
 * jarak jauh, dan risikonya lain. Sampai sekarang sistem hanya mengenal satu
 * tarif, sehingga selisih itu disepakati lewat percakapan WhatsApp dan tidak
 * pernah tercatat di mana pun.
 *
 * HANYA tarif harian. Sewa luar kota memang tidak dijual per jam atau paket 12
 * jam — perjalanan ke Bromo tidak selesai dalam dua belas jam. Menyediakan
 * ketiganya berarti membuat dua isian yang tidak akan pernah diisi.
 *
 * Nullable, dan kosong berarti tarifnya sama dengan dalam kota. Itu keadaan
 * yang sah: sebagian unit memang tidak membedakan keduanya.
 *
 * Kolom penyewaannya menyimpan pilihan penyewa, bukan disimpulkan dari tujuan.
 * Menebak "luar kota" dari tulisan bebas seperti "Borobudur" berarti menagih
 * lebih berdasarkan tebakan, dan tebakan yang salah tentang harga adalah
 * kesalahan yang paling cepat merusak kepercayaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->unsignedInteger('harga_luar_kota')->nullable()->after('price_per_day');
        });

        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->boolean('luar_kota')->default(false)->after('tujuan');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn('harga_luar_kota');
        });

        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->dropColumn('luar_kota');
        });
    }
};
