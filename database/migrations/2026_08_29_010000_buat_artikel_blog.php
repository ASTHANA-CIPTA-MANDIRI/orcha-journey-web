<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikel blog.
 *
 * Halaman paket menjawab orang yang SUDAH tahu ia mau berangkat. Yang mencari
 * "bawa apa saja ke Bromo" atau "open trip itu apa" belum sampai ke sana, dan
 * selama ini tidak ada satu pun halaman Orcha yang menjawabnya — mereka
 * membaca jawabannya di situs lain, lalu memesan di situs lain itu juga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_artikel', function (Blueprint $tabel) {
            $tabel->id();

            $tabel->string('judul');

            /*
             | Slug, bukan UUID.
             |
             | Halaman publik lain memakai UUID supaya jumlah datanya tidak bisa
             | ditebak dari alamat. Untuk artikel alasan itu tidak berlaku —
             | jumlah tulisan memang bukan rahasia — sementara ongkosnya nyata:
             | mesin pencari dan orang yang menempelkan tautan sama-sama membaca
             | alamatnya, dan /blog/bawa-apa-ke-bromo mengalahkan
             | /blog/9f8c1e2a-... di dua-duanya.
             */
            $tabel->string('slug')->unique();

            /*
             | Ringkasan dipisah dari isi, tidak dipotong otomatis.
             |
             | Potongan 150 huruf pertama hampir selalu berhenti di tengah
             | kalimat pembuka yang belum menyatakan apa-apa. Ini juga yang
             | dipakai sebagai keterangan di hasil pencarian dan pratinjau
             | tautan WhatsApp, jadi ia perlu berdiri sendiri sebagai kalimat.
             */
            $tabel->text('ringkasan')->nullable();

            $tabel->longText('isi');

            $tabel->string('sampul')->nullable();

            // Kunci dari config('orcha.kategori_artikel'). Disimpan sebagai
            // teks, bukan relasi: daftarnya pendek dan jarang berubah, dan
            // tabel kedua hanya untuk empat baris menambah sambungan tanpa
            // menambah kemampuan.
            $tabel->string('kategori')->nullable();

            $tabel->string('penulis')->nullable();

            /*
             | Dua kolom untuk satu pertanyaan "sudah tayang?", dan keduanya
             | perlu:
             |
             |   status      — keputusan penulis (draf / tayang)
             |   terbit_pada — kapan ia boleh mulai terlihat
             |
             | Tanpa terbit_pada, artikel yang disiapkan untuk Senin pagi harus
             | ditayangkan manual Senin pagi. Tanpa status, satu-satunya cara
             | menyembunyikan tulisan yang belum matang adalah memberinya
             | tanggal di masa depan yang jauh — dan itu terbaca sebagai
             | jadwal, bukan sebagai draf.
             */
            $tabel->string('status')->default('draf');
            $tabel->timestamp('terbit_pada')->nullable();

            $tabel->unsignedInteger('dilihat')->default(0);

            $tabel->timestamps();

            // Penyaring yang dipakai halaman daftar: status + tanggal terbit,
            // lalu kategori.
            $tabel->index(['status', 'terbit_pada']);
            $tabel->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_artikel');
    }
};
