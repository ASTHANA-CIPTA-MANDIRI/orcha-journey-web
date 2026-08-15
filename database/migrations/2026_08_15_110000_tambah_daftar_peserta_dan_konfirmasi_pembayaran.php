<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua hal yang saling menyambung.
 *
 * 1. Nama tiap peserta dicatat sejak mendaftar. Sebelumnya hanya jumlahnya
 *    yang tersimpan, sehingga identitas peserta lain baru diketahui setelah
 *    mereka mengisi formulir kesehatan — padahal admin perlu tahu siapa saja
 *    yang ikut jauh sebelum itu.
 *
 * 2. Konfirmasi pembayaran punya tempatnya sendiri. Bukti transfer yang hanya
 *    dikirim lewat WhatsApp tenggelam di antara percakapan; satu open trip
 *    berisi enam peserta yang masing-masing membayar dua kali, dan pada H-5
 *    admin harus bisa menjawab "siapa yang belum lunas" dalam hitungan menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->json('daftar_peserta')->nullable()->after('jumlah_peserta');
        });

        Schema::create('tbl_konfirmasi_pembayaran', function (Blueprint $table) {
            $table->id();

            // Kode pendaftaran open trip (OT-...) atau kode sewa (SK-...).
            $table->string('kode', 30)->index();
            $table->string('jenis', 20)->default('dp'); // dp | pelunasan | sewa | lainnya

            $table->unsignedBigInteger('nominal');
            $table->date('tanggal_transfer');
            $table->string('bank_pengirim', 60);
            $table->string('atas_nama_pengirim', 120);

            // Jalur berkas bukti transfer (disimpan sebagai WebP).
            $table->string('bukti')->nullable();
            $table->text('catatan')->nullable();

            $table->string('status', 20)->default('menunggu'); // menunggu | diterima | ditolak
            $table->text('catatan_admin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_konfirmasi_pembayaran');

        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $table) {
            $table->dropColumn('daftar_peserta');
        });
    }
};
