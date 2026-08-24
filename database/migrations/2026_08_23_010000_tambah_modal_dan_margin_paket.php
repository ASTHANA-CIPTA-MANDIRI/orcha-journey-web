<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modal paket (biaya internal per orang) dan jejaknya di tiap pendaftaran.
     *
     * Selama ini paket hanya menyimpan harga jual dan harga coret, jadi
     * pertanyaan "sebenarnya untung berapa" hanya bisa dijawab dengan membuka
     * catatan di luar aplikasi. Modal disimpan PER ORANG, sama satuannya
     * dengan harga jual, supaya marginnya cukup selisih keduanya — paket
     * 1.400.000 yang dijual 1.430.000 berarti 30.000 per peserta.
     *
     * Angkanya boleh kosong: private trip yang masih dihitung manual memang
     * belum punya modal saat paketnya dibuat. Kosong berarti "belum diketahui",
     * bukan nol — laporan menyebutnya belum lengkap alih-alih mengaku untung
     * penuh.
     *
     * Di pendaftaran, kedua angka itu DIBEKUKAN saat kode pendaftaran dibuat.
     * Modal paket berubah sepanjang tahun mengikuti harga hotel dan sewa bus;
     * kalau laporan selalu membaca angka paket hari ini, keuntungan bulan lalu
     * ikut berubah tiap kali admin merevisi modal, dan laporan yang bisa
     * berubah sendiri tidak bisa dipakai membuat keputusan.
     *
     * Pendaftaran yang sudah ada sengaja tidak diisi di sini: modalnya memang
     * belum pernah tercatat, dan mengarang angkanya lebih buruk daripada
     * mengakui kosong. Selama kosong, laporan meminjam angka paketnya —
     * lihat PendaftaranOpenTrip::getModalSatuanAttribute().
     */
    public function up(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->unsignedBigInteger('harga_modal')->nullable()->after('original_price');
        });

        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->unsignedBigInteger('harga_jual')->nullable()->after('jumlah_peserta');
            $table->unsignedBigInteger('harga_modal')->nullable()->after('harga_jual');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_travel_package', function (Blueprint $table) {
            $table->dropColumn('harga_modal');
        });

        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn(['harga_jual', 'harga_modal']);
        });
    }
};
