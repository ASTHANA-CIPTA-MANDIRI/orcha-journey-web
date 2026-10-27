<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu perintah massal, berikut keadaan SEBELUMNYA.
 *
 * Menaikkan 10% ke tiga puluh baris lalu sadar salah pilih tidak bisa
 * dikembalikan dengan menurunkan 9,09% — pembulatan ke ratusan membuat
 * angkanya tidak persis kembali. Jadi yang disimpan bukan perintahnya,
 * melainkan nilai lama tiap baris yang disentuhnya.
 *
 * Yang dihapus tidak bisa dikembalikan dari sini; barisnya sudah tidak ada.
 * Itu dikatakan apa adanya di layar, bukan dijanjikan diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_master_harga_batch', function (Blueprint $t) {
            $t->id();
            $t->string('aksi', 20);
            $t->unsignedInteger('jumlah')->default(0);
            // [{id, harga?, aktif?, vendor_id?, berlaku_sampai?}, ...]
            $t->json('sebelum');
            $t->string('oleh', 120)->nullable();
            $t->timestamp('dibatalkan_pada')->nullable();
            $t->timestamps();
        });

        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->foreignId('batch_id')->nullable()->after('master_harga_id')
                ->constrained('tbl_master_harga_batch')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_master_harga_riwayat', function (Blueprint $t) {
            $t->dropConstrainedForeignId('batch_id');
        });

        Schema::dropIfExists('tbl_master_harga_batch');
    }
};
