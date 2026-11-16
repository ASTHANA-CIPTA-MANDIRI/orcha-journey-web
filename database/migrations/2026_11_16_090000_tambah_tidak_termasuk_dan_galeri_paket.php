<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua hal yang selama ini hanya hidup di kepala admin.
 *
 * `tidak_termasuk` — lawan dari fasilitas. Paket selalu menyebut apa yang
 * DIDAPAT dan tidak pernah menyebut apa yang TIDAK, sehingga tiket masuk,
 * pengeluaran pribadi, dan asuransi jadi bahan ribut berulang dengan
 * pelanggan yang merasa sudah membayar semuanya. Disimpan terpisah, bukan
 * ditempel di catatan promo: ia perlu tampil sebagai daftarnya sendiri di
 * halaman paket, bersebelahan dengan fasilitas.
 *
 * `foto_galeri` — paket cuma punya SATU foto sampul. Satu foto tidak cukup
 * untuk menjual trip tiga hari, dan Galeri Perjalanan yang sudah ada adalah
 * galeri perusahaan, bukan galeri paket ini.
 *
 * Keduanya json dan nullable: paket lama tidak perlu disentuh, dan null
 * berarti "belum diisi" — berbeda dari senarai kosong yang berarti "memang
 * tidak ada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $t) {
            $t->json('tidak_termasuk')->nullable()->after('fasilitas');
            $t->json('foto_galeri')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $t) {
            $t->dropColumn(['tidak_termasuk', 'foto_galeri']);
        });
    }
};
