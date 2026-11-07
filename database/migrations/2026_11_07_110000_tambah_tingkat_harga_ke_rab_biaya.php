<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TINGKAT harga mana yang dibekukan baris ini — bukan lagi ya/tidak.
 *
 * pakai_akhir_pekan adalah boolean, dan boolean sudah cukup selama hanya ada
 * dua tingkat. Dengan musim ia jadi berbahaya, dan bahayanya persis yang
 * dicegah komentar migrasi pembuatnya dulu:
 *
 *   "Pakai harga master" dan "Simpan ke master" harus menyentuh SISI YANG
 *   SAMA. Tanpa penanda ini, "Simpan ke master" menulis harga akhir pekan ke
 *   kolom hari kerja, dan seluruh RAB berikutnya memakai harga yang salah.
 *
 * Baris bertarif Nataru yang penandanya cuma true akan dibaca sebagai baris
 * akhir pekan — lalu "Simpan ke master" menulis Rp 900.000 ke kolom harga
 * akhir pekan, dan setiap hari Sabtu biasa sepanjang tahun ikut jadi
 * Rp 900.000. Kerugian yang menyebar dari satu kali menekan tombol, dan tidak
 * akan pernah dicurigai karena angkanya memang pernah benar.
 *
 * DUA kolom, bukan satu teks "musim:7": musim_id yang dikubur di dalam string
 * tidak bisa di-join, tidak bisa dijaga kunci asing, dan akan menunjuk musim
 * yang sudah tidak ada tanpa satu pun galat.
 *
 * pakai_akhir_pekan TIDAK dibuang. Ia tetap ditulis sebagai cermin —
 * lemon membacanya dari API, dan membuangnya di migrasi yang sama berarti
 * dua repo harus naik ke server pada detik yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->string('tingkat_harga', 20)->default('hari_kerja')->after('pakai_akhir_pekan');

            /*
             | Musim yang dihapus menyisakan barisnya utuh — harganya sudah
             | dibekukan, dan penawaran lama tidak boleh berubah. Yang hilang
             | hanya kemampuan menyebut nama musimnya, dan itu sebabnya musim
             | lewat dimatikan alih-alih dihapus.
             */
            $t->foreignId('musim_id')->nullable()->after('tingkat_harga')
                ->constrained('tbl_musim_harga')->nullOnDelete();
        });

        /*
         | Seluruh baris lama diterjemahkan dari penanda lamanya.
         |
         | Dibiarkan di nilai bawaan 'hari_kerja', baris bertarif akhir pekan
         | yang sudah ada akan ditimpa harga hari kerja pada "Pakai harga
         | master" berikutnya — penawaran yang turun diam-diam tanpa ada yang
         | memintanya.
         */
        DB::table('tbl_rab_biaya')->where('pakai_akhir_pekan', true)
            ->update(['tingkat_harga' => 'akhir_pekan']);
    }

    public function down(): void
    {
        Schema::table('tbl_rab_biaya', function (Blueprint $t) {
            $t->dropConstrainedForeignId('musim_id');
            $t->dropColumn('tingkat_harga');
        });
    }
};
