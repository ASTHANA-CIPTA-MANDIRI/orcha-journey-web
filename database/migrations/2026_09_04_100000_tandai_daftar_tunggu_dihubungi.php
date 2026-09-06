<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan seseorang di daftar tunggu benar-benar dihubungi orang.
 *
 * Kolom yang sudah ada, dikabari_pada, TIDAK berarti itu — dan bedanya
 * menentukan.
 *
 * dikabari_pada berarti "kursi terbuka dan orang ini yang dipilih sistem".
 * Untuk yang punya surel, surat memang terkirim. Untuk yang TANPA surel,
 * penandanya dipasang lalu tidak ada apa pun yang dikirim — sengaja, supaya
 * tim melihatnya di daftar dan menelepon sendiri.
 *
 * Akibatnya orang yang paling membutuhkan telepon justru tergambar hijau
 * "Dikabari 2 jam lalu" di layar admin. Layarnya mengatakan kebalikan dari
 * kenyataan, tepat untuk orang yang tidak bisa dijangkau siapa pun.
 *
 * Kolom ini yang menutup lubang itu: diisi saat admin menekan tombol WhatsApp
 * di barisnya, jadi tidak ada langkah tambahan yang bisa terlupa. Menekan
 * tombolnya memang tidak membuktikan percakapannya terjadi — tetapi ia
 * membuktikan seseorang sudah MENCOBA, dan itu yang membedakan antrean yang
 * sudah diurus dari yang belum disentuh sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_daftar_tunggu', function (Blueprint $tabel) {
            $tabel->timestamp('dihubungi_pada')->nullable()->after('dikabari_pada');

            // Siapa yang menghubungi. Antrean yang panjang diurus lebih dari
            // satu orang, dan "sudah dihubungi" tanpa nama membuat dua admin
            // sama-sama mengira yang lain yang mengerjakannya.
            $tabel->string('dihubungi_oleh', 120)->nullable()->after('dihubungi_pada');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_daftar_tunggu', function (Blueprint $tabel) {
            $tabel->dropColumn(['dihubungi_pada', 'dihubungi_oleh']);
        });
    }
};
