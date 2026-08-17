<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan BBM, tol, dan parkir menjadi tiga penanda dan tiga biaya.
 *
 * Sebelumnya ketiganya digabung dalam satu penanda dengan alasan "dalam
 * praktiknya ditawarkan sebagai satu paket". Ternyata tidak: ada unit yang
 * BBM-nya ditanggung pemilik tetapi tolnya tidak, dan parkir hampir selalu
 * urusan tersendiri. Satu penanda memaksa ketiganya diputuskan bersama, sehingga
 * keadaan yang sebenarnya tidak bisa dinyatakan sama sekali.
 *
 * Migrasi 090000 yang menambahkan kolom gabungan sengaja TIDAK diubah. Ia sudah
 * masuk riwayat dan bisa jadi sudah jalan di tempat lain; menyuntingnya berarti
 * dua basis data mengaku menjalankan migrasi yang sama padahal isinya berbeda.
 * Yang benar adalah migrasi lanjutan yang memindahkan datanya lalu membuang
 * kolom lamanya.
 *
 * Pemindahan datanya: penanda gabungan diberlakukan ke ketiganya, dan seluruh
 * nominal lama dimasukkan ke biaya_bbm. Satu angka tidak bisa dipecah tiga tanpa
 * mengarang, dan yang paling penting dipertahankan adalah TOTALNYA — karena
 * itulah yang muncul di perkiraan harga. Pemilik bisa merincinya ulang kapan
 * saja dari formulir armada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            foreach (['bbm', 'tol', 'parkir'] as $pos) {
                $tabel->boolean("termasuk_{$pos}")->default(false)->after('biaya_operasional');
                $tabel->unsignedInteger("biaya_{$pos}")->nullable()->after("termasuk_{$pos}");
            }
        });

        DB::table('cars')->where('termasuk_operasional', true)->update([
            'termasuk_bbm' => true,
            'termasuk_tol' => true,
            'termasuk_parkir' => true,
            'biaya_bbm' => DB::raw('biaya_operasional'),
        ]);

        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn(['termasuk_operasional', 'biaya_operasional']);
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->boolean('termasuk_operasional')->default(false)->after('harga_sopir');
            $tabel->unsignedInteger('biaya_operasional')->nullable()->after('termasuk_operasional');
        });

        // Kembali ke satu penanda: termasuk bila ADA yang termasuk, dan biayanya
        // dijumlahkan. Bukan pembalikan yang setara — perinciannya memang hilang
        // saat digabungkan.
        DB::table('cars')
            ->where(fn ($q) => $q->where('termasuk_bbm', true)
                ->orWhere('termasuk_tol', true)
                ->orWhere('termasuk_parkir', true))
            ->update([
                'termasuk_operasional' => true,
                'biaya_operasional' => DB::raw(
                    'coalesce(biaya_bbm, 0) + coalesce(biaya_tol, 0) + coalesce(biaya_parkir, 0)'
                ),
            ]);

        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn([
                'termasuk_bbm', 'biaya_bbm',
                'termasuk_tol', 'biaya_tol',
                'termasuk_parkir', 'biaya_parkir',
            ]);
        });
    }
};
