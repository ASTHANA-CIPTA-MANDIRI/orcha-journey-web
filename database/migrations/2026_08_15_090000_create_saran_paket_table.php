<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar saran isian paket: destinasi yang dikunjungi dan fasilitas.
 *
 * Sengaja dipisah dari `tbl_destinasi_populer`. Destinasi populer adalah
 * etalase publik — ada foto, deskripsi, dan tampil di website. Sementara
 * "Meeting point Jogja" atau "Tiket masuk wisata" hanya berguna sebagai
 * pilihan cepat saat menyusun paket, dan tidak layak tampil di halaman
 * destinasi. Karena itu keduanya tidak dicampur.
 *
 * Isinya bertambah sendiri: begitu admin mengetik destinasi atau fasilitas
 * baru di formulir paket, namanya ikut tersimpan di sini supaya paket
 * berikutnya tinggal mengklik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_saran_paket', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20)->index();   // destinasi | fasilitas
            $table->string('nama', 191);
            $table->timestamps();

            $table->unique(['jenis', 'nama']);
        });

        $sekarang = now();

        // Fasilitas yang selama ini ada di config dipindah jadi data, supaya
        // bisa ditambah dan dihapus admin tanpa menyunting berkas.
        $baris = collect(config('orcha.fasilitas_umum', []))
            ->map(fn ($nama) => ['jenis' => 'fasilitas', 'nama' => $nama, 'created_at' => $sekarang, 'updated_at' => $sekarang]);

        // Destinasi populer yang sudah terdaftar langsung jadi saran, jadi
        // daftarnya tidak kosong sejak awal.
        $baris = $baris->merge(
            DB::table('tbl_destinasi_populer')
                ->pluck('destination_name')
                ->filter()
                ->unique()
                ->map(fn ($nama) => ['jenis' => 'destinasi', 'nama' => $nama, 'created_at' => $sekarang, 'updated_at' => $sekarang])
        );

        if ($baris->isNotEmpty()) {
            DB::table('tbl_saran_paket')->insert($baris->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_saran_paket');
    }
};
