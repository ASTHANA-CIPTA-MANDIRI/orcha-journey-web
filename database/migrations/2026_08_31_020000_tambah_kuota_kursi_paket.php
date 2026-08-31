<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berapa kursi yang tersedia pada satu paket.
 *
 * Selama ini paket hanya punya minimal_peserta — batas BAWAH, jumlah minimum
 * supaya trip jadi berangkat. Batas atasnya tidak pernah ada di mana pun,
 * sehingga:
 *
 *   1. Tidak ada yang mencegah pendaftaran melewati daya angkut armadanya.
 *      Yang menahannya cuma ingatan admin, dan ingatan itu bekerja paling
 *      buruk justru saat paling dibutuhkan — ketika pendaftaran ramai.
 *
 *   2. Pengunjung tidak pernah melihat "sisa 4 kursi". Pada bisnis open trip
 *      itu pendorong keputusan yang paling kuat, dan datanya sebenarnya sudah
 *      ada: jumlah peserta pada tiap pendaftaran yang belum batal.
 *
 * Nullable, dan null berarti "belum ditetapkan" — bukan nol.
 *
 * Bedanya penting: paket lama seluruhnya belum punya angka ini, dan
 * memperlakukan null sebagai nol akan menutup pendaftaran SEMUA paket yang
 * sudah tayang pada detik migrasi ini jalan. Paket tanpa kuota berperilaku
 * persis seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $tabel) {
            $tabel->unsignedSmallInteger('kuota')->nullable()->after('minimal_peserta');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $tabel) {
            $tabel->dropColumn('kuota');
        });
    }
};
