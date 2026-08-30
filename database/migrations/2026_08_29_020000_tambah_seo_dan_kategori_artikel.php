<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan blog Orcha dengan blog Phoenix: meta SEO sendiri, dan kategori
 * yang bisa ditambah admin — bukan daftar tetap di config.
 *
 * Kategori pindah dari config ke tabel karena admin tidak punya akses ke berkas
 * config: setiap kali ia butuh rubrik baru, pekerjaannya berhenti sampai ada
 * yang menyunting kode dan menaikkannya ke server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_artikel', function (Blueprint $tabel) {
            /*
             | Judul dan keterangan khusus untuk mesin pencari, terpisah dari
             | judul dan ringkasan yang dibaca pengunjung.
             |
             | Perlu terpisah karena keduanya punya batas panjang sendiri:
             | Google memenggal judul di sekitar 60 huruf dan keterangan di
             | sekitar 155. Judul artikel yang bagus untuk dibaca manusia sering
             | lebih panjang dari itu, dan memaksanya jadi satu berarti salah
             | satu dari keduanya selalu dikorbankan.
             */
            $tabel->string('meta_title')->nullable()->after('penulis');
            $tabel->string('meta_description', 300)->nullable()->after('meta_title');
        });

        Schema::create('tbl_kategori_artikel', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama')->unique();

            /*
             | Slug-lah yang disimpan di kolom kategori milik artikel, BUKAN
             | namanya seperti di blog Phoenix.
             |
             | Sebabnya Orcha punya penyaring beralamat: /blog?kategori=panduan.
             | Kalau yang tersimpan namanya, mengganti "Panduan Perjalanan" jadi
             | "Panduan" akan mematikan seluruh tautan kategori yang sudah
             | beredar sekaligus melepas semua artikel dari rubriknya.
             */
            $tabel->string('slug')->unique();
            $tabel->timestamps();
        });

        /*
         | Empat kategori yang selama ini ada di config dipindahkan apa adanya —
         | kuncinya jadi slug, labelnya jadi nama.
         |
         | Tanpa ini artikel yang sudah tersimpan kehilangan rubriknya dan
         | tautan /blog?kategori=panduan yang sudah beredar menjawab daftar
         | kosong. Config-nya sendiri dibiarkan sebagai cadangan bila tabelnya
         | kosong; lihat KategoriArtikel::daftar().
         */
        $sekarang = now();

        $baris = collect(config('orcha.kategori_artikel', []))
            ->map(fn ($nama, $slug) => [
                'nama' => $nama,
                'slug' => $slug,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])
            ->values()
            ->all();

        if ($baris !== []) {
            DB::table('tbl_kategori_artikel')->insertOrIgnore($baris);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_kategori_artikel');

        Schema::table('tbl_artikel', function (Blueprint $tabel) {
            $tabel->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
