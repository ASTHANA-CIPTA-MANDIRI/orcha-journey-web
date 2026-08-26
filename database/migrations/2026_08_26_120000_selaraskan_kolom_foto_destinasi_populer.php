<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Menambah destinasi di produksi selalu gagal dengan kode 500:
 |
 |     Unknown column 'main_photo' in 'INSERT INTO'
 |
 | Migrasi pembuat tabelnya sudah mendefinisikan main_photo dan others_photo
 | sejak awal, dan `migrate:status` menyatakan TIDAK ADA yang tertunda. Tetapi
 | tabel di produksi memuat satu kolom `foto` dan tidak memuat keduanya —
 | tabelnya tidak pernah benar-benar dibuat oleh migrasi itu, melainkan masuk
 | lewat impor SQL manual dengan bentuk yang lebih lama. Baris migrasinya
 | tercatat sudah jalan, jadi tidak ada satu pun perkakas yang mengeluh.
 |
 | Ditulis defensif — memeriksa dulu apa yang ada — karena justru itu bedanya
 | tiap lingkungan: di mesin pengembang kolomnya sudah benar dan migrasi ini
 | tidak boleh melakukan apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            if (! Schema::hasColumn('tbl_destinasi_populer', 'main_photo')) {
                $tabel->string('main_photo')->nullable()->after('deskripsi');
            }

            if (! Schema::hasColumn('tbl_destinasi_populer', 'others_photo')) {
                $tabel->json('others_photo')->nullable()->after('main_photo');
            }
        });

        /*
         | Isi kolom lama diselamatkan, bukan ditinggal.
         |
         | Di produksi tabelnya kebetulan kosong, tetapi migrasi ini juga akan
         | jalan di salinan lain yang mungkin sudah berisi destinasi. Foto yang
         | hilang diam-diam saat pindah kolom tidak menimbulkan galat apa pun —
         | kartunya sekadar tampil tanpa gambar, dan itu baru ketahuan kalau
         | ada yang membuka halamannya.
         */
        if (Schema::hasColumn('tbl_destinasi_populer', 'foto')) {
            DB::table('tbl_destinasi_populer')
                ->whereNull('main_photo')
                ->whereNotNull('foto')
                ->update(['main_photo' => DB::raw('`foto`')]);
        }
    }

    public function down(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            foreach (['others_photo', 'main_photo'] as $kolom) {
                if (Schema::hasColumn('tbl_destinasi_populer', $kolom)) {
                    $tabel->dropColumn($kolom);
                }
            }
        });
    }
};
