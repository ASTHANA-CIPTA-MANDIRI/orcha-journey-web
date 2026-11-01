<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga mana yang dipakai baris ini, dan mengapa.
 *
 * DUA kolom, bukan satu, karena dua pembaca yang berbeda:
 *
 * - harga_sebab dibaca orang: "harga akhir pekan (Sabtu, 3 Okt)". Angka yang
 *   Rp 10.000 lebih mahal daripada yang tertulis di master akan dikira salah
 *   ketik, lalu dibetulkan admin ke angka yang justru keliru.
 * - pakai_akhir_pekan dibaca kode: "Pakai harga master" dan "Simpan ke
 *   master" harus menyentuh SISI YANG SAMA. Tanpa penanda ini, "Simpan ke
 *   master" menulis harga akhir pekan ke kolom hari kerja, dan seluruh RAB
 *   berikutnya memakai harga yang salah. Menebaknya dengan membaca teks
 *   harga_sebab adalah ketergantungan pada kalimat yang suatu hari diubah.
 *
 * Keduanya kosong/false untuk seluruh baris lama — perilakunya tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->string('harga_sebab', 120)->nullable()->after('harga_satuan');
            $t->boolean('pakai_akhir_pekan')->default(false)->after('harga_sebab');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->dropColumn(['harga_sebab', 'pakai_akhir_pekan']);
        });
    }
};
