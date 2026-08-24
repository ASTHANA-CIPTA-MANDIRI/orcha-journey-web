<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galeri momen perjalanan pelanggan.
 *
 * Sebelumnya bagian "Galeri" di beranda meminjam foto destinasi, dan bila
 * jumlahnya kurang dari enam ia diam-diam jatuh ke foto bawaan bawaan repo.
 * Akibatnya dua hal yang berbeda maksudnya jadi satu: foto destinasi menjual
 * TEMPAT, sedangkan galeri menunjukkan ORANG yang sudah berangkat — dan admin
 * tidak punya cara menaruh foto rombongan tanpa mengarang destinasi baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_galeri', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('foto');

            // Keterangan boleh kosong: yang wajib cuma fotonya. Admin yang
            // diminta mengarang judul untuk dua puluh foto rombongan akan
            // berhenti mengunggah.
            $tabel->string('keterangan')->nullable();

            // Urutan tampil, bukan tanggal unggah: yang terbaru belum tentu
            // yang paling pantas dipajang paling depan.
            $tabel->unsignedInteger('urutan')->default(0);
            $tabel->boolean('tampil')->default(true);

            $tabel->timestamps();

            $tabel->index(['tampil', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_galeri');
    }
};
