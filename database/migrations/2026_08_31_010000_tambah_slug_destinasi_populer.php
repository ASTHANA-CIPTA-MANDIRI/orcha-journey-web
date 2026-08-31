<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Alamat sendiri untuk tiap destinasi.
 *
 * Selama ini nama destinasi hanya muncul sebagai teks di tengah satu halaman
 * daftar yang panjang. Tidak ada alamat yang bisa diberikan ke mesin pencari,
 * tidak ada judul halaman yang memuat namanya, dan tidak ada barisnya di peta
 * situs — sehingga "Raja Ampat" tidak pernah bisa menjadi hasil pencariannya
 * sendiri, padahal keterangan dan fotonya sudah lama tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            /*
             | Nullable dan BELUM unik pada langkah ini.
             |
             | Kolom unik yang ditambahkan ke tabel berisi akan menolak seluruh
             | barisnya sekaligus, karena semuanya bernilai null pada saat
             | kolomnya lahir. Keunikannya dipasang setelah terisi.
             */
            $tabel->string('slug')->nullable()->after('destination_name');
        });

        /*
         | Slug diisi satu per satu, bukan lewat satu perintah SQL.
         |
         | Dua destinasi boleh bernama sama — "Pantai Indrayanti" di dua
         | kabupaten, misalnya — dan Str::slug menghasilkan teks yang sama untuk
         | keduanya. Yang kedua diberi akhiran angka, persis seperti slug
         | artikel, supaya kunci uniknya tidak menolak barisnya.
         */
        $terpakai = [];

        foreach (DB::table('tbl_destinasi_populer')->select('id', 'destination_name')->get() as $baris) {
            $dasar = Str::slug($baris->destination_name) ?: 'destinasi';
            $slug = $dasar;
            $n = 2;

            while (isset($terpakai[$slug])) {
                $slug = "$dasar-$n";
                $n++;
            }

            $terpakai[$slug] = true;

            DB::table('tbl_destinasi_populer')->where('id', $baris->id)->update(['slug' => $slug]);
        }

        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            $tabel->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_destinasi_populer', function (Blueprint $tabel) {
            $tabel->dropUnique(['slug']);
            $tabel->dropColumn('slug');
        });
    }
};
