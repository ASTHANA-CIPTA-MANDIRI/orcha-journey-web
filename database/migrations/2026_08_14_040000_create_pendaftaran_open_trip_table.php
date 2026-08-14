<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pendaftaran open trip beserta riwayat kesehatan pesertanya.
     *
     * Riwayat kesehatan disimpan di tabel terpisah karena satu pendaftaran bisa
     * berisi beberapa peserta, dan isinya data sensitif yang hanya boleh dilihat
     * dari panel admin.
     */
    public function up(): void
    {
        Schema::create('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->foreignId('travel_package_id')->nullable();
            $table->string('nama_paket', 191)->nullable();
            $table->string('nama', 120);
            $table->string('whatsapp', 30);
            $table->string('email', 150)->nullable();
            $table->unsignedSmallInteger('jumlah_peserta')->default(1);
            $table->date('tanggal_berangkat')->nullable();
            $table->string('titik_jemput', 191)->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('baru');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('tbl_riwayat_kesehatan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pendaftaran', 20)->index();
            $table->string('nama_peserta', 120);
            $table->unsignedTinyInteger('usia')->nullable();
            $table->string('golongan_darah', 5)->nullable();
            $table->text('riwayat_penyakit')->nullable();
            $table->text('alergi')->nullable();
            $table->text('obat_rutin')->nullable();
            $table->string('pantangan_kegiatan', 191)->nullable();
            $table->string('kontak_darurat_nama', 120);
            $table->string('kontak_darurat_hp', 30);
            $table->string('kontak_darurat_hubungan', 60)->nullable();
            $table->boolean('setuju_data_kesehatan')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_riwayat_kesehatan');
        Schema::dropIfExists('tbl_pendaftaran_open_trip');
    }
};
