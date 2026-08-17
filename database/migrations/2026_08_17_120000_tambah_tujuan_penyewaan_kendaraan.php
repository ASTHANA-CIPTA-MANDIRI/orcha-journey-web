<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tujuan perjalanan untuk sewa bersopir.
 *
 * Formulir sewa menanyakan "lokasi pengantaran unit" dan "lokasi pengembalian
 * unit" — pertanyaan yang benar untuk lepas kunci, karena unitnya memang
 * diserahkan lalu diambil kembali.
 *
 * Untuk HiAce dan bus, unitnya TIDAK diserahkan ke penyewa. Sopir kami yang
 * menjemput lalu mengantar, sehingga yang perlu diketahui adalah titik
 * penjemputan dan tujuan perjalanannya. Menanyakan alamat serah unit pada
 * penyewa bus menghasilkan jawaban yang tidak dipakai siapa pun, sementara
 * tujuannya — yang menentukan lama jalan, BBM, dan kesiapan sopir — tidak pernah
 * tercatat sama sekali. Sampai sekarang tujuan itu hanya ada di kolom catatan,
 * kalau penyewanya kebetulan menuliskannya.
 *
 * Nullable karena sewa lepas kunci memang tidak punya tujuan yang dicatat:
 * penyewa membawa unitnya ke mana pun ia perlu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->string('tujuan', 191)->nullable()->after('lokasi_kembali');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_penyewaan_kendaraan', function (Blueprint $tabel) {
            $tabel->dropColumn('tujuan');
        });
    }
};
