<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian kendaraan yang diperiksa saat serah terima — jadi data, bukan config.
 *
 * Sebelumnya daftarnya dipatok di config/orcha.php: dua belas bagian, sama
 * untuk semua unit. Menambah satu bagian berarti mengubah berkas dan
 * men-deploy, dan pemilik armada yang mulai menyewakan bus tidak punya cara
 * menambahkan "pintu bagasi" atau "AC blower atas" sendiri.
 *
 * Tarifnya ikut di baris yang sama, bukan di tabel terpisah, karena keduanya
 * memang selalu ditulis bersamaan: bagian tanpa tarif membuat usulan denda
 * kerusakan diam-diam melewatinya — perhitungannya tetap jalan, angkanya
 * kurang, dan tidak ada yang memberi tahu.
 *
 * Yang TIDAK ikut jadi data: tingkat kondisinya (baik → lecet → rusak →
 * hilang). Itu skala berurutan, bukan daftar — urutannya dipakai membandingkan
 * keadaan sebelum dan sesudah pada SELURUH serah terima yang sudah tersimpan,
 * jadi menyisipkan tingkat baru di tengahnya menggeser hasil perhitungan data
 * lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bagian_pemeriksaan', function (Blueprint $tabel) {
            $tabel->id();

            /*
             | Kunci yang tersimpan di dalam kondisi unit dan lembar serah
             | terima. Tidak pernah berubah setelah dibuat — label boleh
             | diperbaiki ejaannya kapan saja, kuncinya tidak, karena ribuan
             | baris kondisi lama menunjuk ke sini.
             */
            $tabel->string('kunci', 60)->unique();
            $tabel->string('label', 120);

            /*
             | Jenis kendaraan yang memakai bagian ini. Bus tidak punya ban
             | serep sebagaimana mobil, dan mobil tidak punya pintu bagasi
             | samping — memaksa keduanya memakai satu daftar membuat separuh
             | ceklisnya diisi "Baik" tanpa pernah benar-benar diperiksa.
             */
            $tabel->json('jenis');

            // Perkiraan biaya perbaikan. Usulan, bukan tagihan: nota bengkel
            // yang sebenarnya selalu menang.
            $tabel->unsignedBigInteger('biaya_lecet')->default(0);
            $tabel->unsignedBigInteger('biaya_rusak')->default(0);
            $tabel->unsignedBigInteger('biaya_hilang')->default(0);

            $tabel->unsignedSmallInteger('urutan')->default(0);

            /*
             | Dinonaktifkan, bukan dihapus.
             |
             | Lembar serah terima setahun lalu masih menunjuk kuncinya, dan
             | menghapus barisnya membuat namanya hilang dari lembar itu —
             | tersisa kunci mentahnya. Yang tidak aktif berhenti muncul di
             | formulir baru, tetapi tetap bisa dibaca namanya.
             */
            $tabel->boolean('aktif')->default(true);

            $tabel->timestamps();

            $tabel->index(['aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bagian_pemeriksaan');
    }
};
