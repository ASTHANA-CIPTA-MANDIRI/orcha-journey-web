<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RAB & itinerary private trip, berikut master harga yang mengisinya.
     *
     * Sebelum ini, penawaran private trip dihitung di luar aplikasi: tiket
     * masuk dicari ulang, tarif bus ditanyakan ulang, lalu angkanya diketik
     * ke pendaftaran sebagai "modal per orang" hasil hitungan kepala. Tiap
     * penawaran mengulang pekerjaan yang sama, dan hasilnya tidak bisa
     * dibandingkan antarpenawaran karena tidak ada yang tercatat.
     *
     * MASTER HARGA dirawat admin. Satu baris = satu hal yang bisa dibayar,
     * berikut SATUAN hitungnya — dan satuan itulah yang membuat RAB bisa
     * dihitung sendiri: tiket dikali peserta, bus dikali hari dan jumlah
     * unit, hotel dikali malam dan jumlah kamar.
     *
     * Baris biaya RAB MEMBEKUKAN harganya saat ditambahkan. Harga tiket yang
     * naik bulan depan tidak boleh diam-diam mengubah penawaran yang sudah
     * dikirim ke pelanggan hari ini — penawaran yang angkanya berubah sendiri
     * sesudah disetujui adalah sengketa yang menunggu terjadi.
     */
    public function up(): void
    {
        Schema::create('tbl_master_harga', function (Blueprint $t) {
            $t->id();
            $t->string('kategori', 30);
            $t->string('nama', 150);
            // Kosong = berlaku di mana saja (asuransi, P3K, dokumentasi).
            $t->string('provinsi', 80)->nullable();
            $t->string('daerah', 80)->nullable();
            // Nama destinasi katalog. Terisi = tiket masuk destinasi itu, dan
            // ia ikut tertarik sendiri saat destinasinya dipilih di itinerary.
            $t->string('destinasi', 150)->nullable();
            $t->string('satuan', 20);
            $t->unsignedBigInteger('harga');
            // Kursi per unit kendaraan / orang per kamar. Dipakai satuan
            // unit_hari dan kamar_malam untuk menghitung berapa unit.
            $t->unsignedSmallInteger('kapasitas')->nullable();
            // Ikut ditambahkan ke setiap RAB di provinsinya tanpa dipilih:
            // makan, parkir, asuransi — hal yang SELALU ada.
            $t->boolean('otomatis')->default(false);
            $t->boolean('aktif')->default(true);
            $t->string('catatan', 500)->nullable();
            $t->timestamps();

            $t->index(['provinsi', 'aktif']);
            $t->index('destinasi');
        });

        Schema::create('tbl_rab', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('judul', 150);
            $t->string('nama_pelanggan', 120);
            $t->string('whatsapp', 32)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('provinsi', 80);
            $t->string('daerah', 80)->nullable();
            $t->date('tanggal_mulai')->nullable();
            $t->unsignedSmallInteger('jumlah_hari')->default(1);
            $t->unsignedSmallInteger('jumlah_malam')->default(0);
            $t->unsignedSmallInteger('jumlah_peserta')->default(1);
            // persen | per_orang | total — lihat HitungRab::margin().
            $t->string('margin_jenis', 20)->default('persen');
            $t->unsignedBigInteger('margin_nilai')->default(0);
            $t->unsignedInteger('pembulatan')->default(1000);
            $t->text('catatan')->nullable();
            $t->text('catatan_penawaran')->nullable();
            $t->string('status', 20)->default('draf');
            $t->date('berlaku_sampai')->nullable();
            $t->string('kode_pendaftaran', 30)->nullable();
            $t->string('dibuat_oleh', 150)->nullable();
            $t->timestamps();

            $t->index('status');
        });

        Schema::create('tbl_rab_itinerary', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rab_id')->constrained('tbl_rab')->cascadeOnDelete();
            $t->unsignedSmallInteger('hari_ke');
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->string('jam', 5)->nullable();
            $t->string('nama', 150);
            $t->string('keterangan', 500)->nullable();
            $t->string('destinasi', 150)->nullable();
            $t->timestamps();

            $t->index(['rab_id', 'hari_ke', 'urutan']);
        });

        Schema::create('tbl_rab_biaya', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rab_id')->constrained('tbl_rab')->cascadeOnDelete();
            // Rujukan saja; harganya DIBEKUKAN di baris ini. Master yang
            // dihapus tidak menghapus biaya penawaran yang sudah dibuat.
            $t->foreignId('master_harga_id')->nullable()
                ->constrained('tbl_master_harga')->nullOnDelete();
            $t->string('kategori', 30);
            $t->string('nama', 150);
            $t->string('satuan', 20);
            $t->unsignedBigInteger('harga_satuan');
            $t->unsignedSmallInteger('kapasitas')->nullable();
            // Pengali tambahan: dua pemandu, tiga kali parkir.
            $t->unsignedSmallInteger('jumlah')->default(1);
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rab_biaya');
        Schema::dropIfExists('tbl_rab_itinerary');
        Schema::dropIfExists('tbl_rab');
        Schema::dropIfExists('tbl_master_harga');
    }
};
