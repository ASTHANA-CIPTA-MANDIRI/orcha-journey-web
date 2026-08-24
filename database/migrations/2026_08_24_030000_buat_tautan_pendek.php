<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan pendek untuk berkas yang dibagikan ke pelanggan lewat WhatsApp.
 *
 * Alamat bertanda tangan Laravel benar, tetapi panjangnya lebih dari 200
 * karakter — di gelembung percakapan ia patah ke banyak baris dan menutupi
 * kalimat di sekitarnya, dan pelanggan yang melihat deretan begitu cenderung
 * curiga alih-alih mengetuk.
 *
 * Kodenya disimpan, bukan dihitung dari isinya: kode yang bisa dihitung ulang
 * berarti bisa ditebak, dan berkas ini memuat nama, nomor telepon, dan rincian
 * biaya seseorang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_tautan_pendek', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('kode', 16)->unique();

            // Jenis berkasnya, bukan alamat tujuan yang disimpan mentah:
            // menyimpan URL berarti tautan lama menunjuk rute lama selamanya.
            $tabel->string('jenis', 32);
            $tabel->foreignId('pendaftaran_id');

            $tabel->timestamp('kedaluwarsa_pada')->nullable();
            $tabel->timestamps();

            // Satu tautan per berkas per pendaftaran: dipakai ulang, bukan
            // dibuat baru tiap kali halaman detail dibuka.
            $tabel->unique(['jenis', 'pendaftaran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_tautan_pendek');
    }
};
