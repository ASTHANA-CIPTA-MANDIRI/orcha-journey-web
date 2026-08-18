<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BBM, tol, parkir, dan sopir dibedakan antara dalam kota dan luar kota.
 *
 * Sampai sekarang tiap unit hanya punya SATU aturan biaya, padahal keduanya
 * memang berbeda di lapangan: sewa dalam kota diserahkan apa adanya — BBM,
 * tol, dan sopir ditanggung penyewa — sementara perjalanan ke luar kota
 * ditawarkan sepaket bersama sopir dan bahan bakarnya, karena tidak ada penyewa
 * yang mau mengurus itu sendiri di jalan jauh.
 *
 * Dengan satu aturan, admin terpaksa memilih salah satu lalu menjelaskan
 * sisanya lewat percakapan. Yang tertulis di kartu unit dan di surat pemesanan
 * jadi tidak berlaku untuk separuh pesanan, dan selisihnya baru dipersoalkan
 * saat menagih — persis kesalahan yang dulu diperbaiki dengan memisahkan tarif
 * luar kota.
 *
 * Kolom yang sudah ada TETAP berarti dalam kota. Nilainya disalin apa adanya ke
 * kolom luar kota supaya tidak ada unit yang diam-diam berubah aturannya hari
 * ini: yang berubah kemampuannya, bukan datanya. Admin yang menyesuaikan
 * kemudian, unit demi unit.
 */
return new class extends Migration
{
    private const POS = ['bbm', 'tol', 'parkir'];

    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            foreach (self::POS as $pos) {
                $tabel->boolean("luar_termasuk_{$pos}")->default(false)->after("biaya_{$pos}");
                $tabel->unsignedInteger("luar_biaya_{$pos}")->nullable()->after("luar_termasuk_{$pos}");
            }

            $tabel->boolean('luar_termasuk_sopir')->default(false)->after('termasuk_sopir');
            $tabel->unsignedInteger('luar_harga_sopir')->nullable()->after('luar_termasuk_sopir');
        });

        // Disalin lewat DB::table, bukan Eloquent: aturan ini tidak boleh
        // menyentuh updated_at. Tanggal ubah yang bergeser serentak untuk
        // seluruh armada menghapus jejak kapan tiap unit benar-benar disunting.
        DB::table('cars')->update([
            'luar_termasuk_bbm' => DB::raw('termasuk_bbm'),
            'luar_biaya_bbm' => DB::raw('biaya_bbm'),
            'luar_termasuk_tol' => DB::raw('termasuk_tol'),
            'luar_biaya_tol' => DB::raw('biaya_tol'),
            'luar_termasuk_parkir' => DB::raw('termasuk_parkir'),
            'luar_biaya_parkir' => DB::raw('biaya_parkir'),
            'luar_termasuk_sopir' => DB::raw('termasuk_sopir'),
            'luar_harga_sopir' => DB::raw('harga_sopir'),
        ]);
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $kolom = ['luar_termasuk_sopir', 'luar_harga_sopir'];

            foreach (self::POS as $pos) {
                $kolom[] = "luar_termasuk_{$pos}";
                $kolom[] = "luar_biaya_{$pos}";
            }

            $tabel->dropColumn($kolom);
        });
    }
};
