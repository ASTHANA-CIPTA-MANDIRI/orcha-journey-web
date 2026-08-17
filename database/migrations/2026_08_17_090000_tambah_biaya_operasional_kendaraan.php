<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BBM, tol, dan parkir: ditanggung penyewa atau sudah termasuk.
 *
 * Sampai sekarang halaman publik menyatakan ketiganya TIDAK termasuk — ditulis
 * tetap di daftar "belum termasuk", berlaku untuk semua unit. Padahal paket
 * all-in memang ditawarkan untuk sebagian unit, terutama HiAce dan bus yang
 * jalannya jauh, dan kesepakatannya hanya ada di percakapan WhatsApp.
 *
 * Akibatnya penyewa membaca "belum termasuk BBM" untuk unit yang sebenarnya
 * all-in, lalu bertanya ulang — atau lebih buruk, mengira sudah termasuk padahal
 * belum dan berselisih saat pembayaran.
 *
 * Ketiganya digabung dalam satu penanda, bukan tiga penanda terpisah, karena
 * dalam praktiknya ditawarkan sebagai satu paket: unit yang BBM-nya ditanggung
 * pemilik hampir pasti tolnya juga. Tiga sakelar untuk satu keputusan hanya
 * memperbanyak keadaan yang harus diuji tanpa menambah kemampuan.
 *
 * biaya_operasional nullable dan boleh 0. Nol berarti "termasuk tanpa tambahan
 * biaya" — sah, misalnya untuk unit yang tarifnya sudah dihitung all-in sejak
 * awal. Membedakan null dari 0 tidak diperlukan di sini: keduanya berarti tidak
 * ada tambahan yang ditagihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->boolean('termasuk_operasional')->default(false)->after('harga_sopir');
            $tabel->unsignedInteger('biaya_operasional')->nullable()->after('termasuk_operasional');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn(['termasuk_operasional', 'biaya_operasional']);
        });
    }
};
