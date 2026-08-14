<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Melengkapi formulir kesehatan peserta dan menambah tabel pengajuan
     * pembatalan perjalanan.
     */
    public function up(): void
    {
        Schema::table('tbl_riwayat_kesehatan', function (Blueprint $table) {
            $table->string('jenis_kelamin', 10)->nullable()->after('usia');
            $table->unsignedSmallInteger('tinggi_badan')->nullable()->after('jenis_kelamin');
            $table->unsignedSmallInteger('berat_badan')->nullable()->after('tinggi_badan');
            $table->json('kondisi_khusus')->nullable()->after('riwayat_penyakit');
            $table->text('riwayat_operasi')->nullable()->after('kondisi_khusus');
            $table->text('pantangan_makanan')->nullable()->after('alergi');
            $table->string('kemampuan_renang', 30)->nullable()->after('pantangan_kegiatan');
            $table->string('asuransi', 120)->nullable()->after('kemampuan_renang');
            $table->text('catatan_tambahan')->nullable()->after('asuransi');
        });

        Schema::create('tbl_pembatalan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pendaftaran', 20)->index();
            $table->string('nama_pemohon', 120);
            $table->string('whatsapp', 30);
            $table->string('email', 150)->nullable();
            $table->string('alasan', 40);
            $table->text('penjelasan')->nullable();
            $table->unsignedSmallInteger('jumlah_dibatalkan')->default(1);
            $table->string('bank', 60)->nullable();
            $table->string('nomor_rekening', 40)->nullable();
            $table->string('atas_nama_rekening', 120)->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pembatalan');

        Schema::table('tbl_riwayat_kesehatan', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_kelamin',
                'tinggi_badan',
                'berat_badan',
                'kondisi_khusus',
                'riwayat_operasi',
                'pantangan_makanan',
                'kemampuan_renang',
                'asuransi',
                'catatan_tambahan',
            ]);
        });
    }
};
