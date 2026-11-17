<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat TAWARAN kursi kepada orang yang mengantre.
 *
 * Sebelumnya antrean hanya menyimpan "sudah dihubungi atau belum". Begitu satu
 * pendaftaran dibatalkan dan kursinya menganggur, admin mengabari orang di
 * antrean lewat WhatsApp — dan sesudah itu tidak ada satu pun catatan tentang
 * apa yang baru saja dijanjikan: kursi rombongan mana, kepada siapa, dan
 * sampai kapan ditahan.
 *
 * Akibatnya dua: dua admin bisa menawarkan kursi yang sama kepada dua orang,
 * dan kalimat "kursinya kami tahan dulu sampai nanti sore" tidak ditegakkan
 * apa pun — tidak ada yang tahu kapan sorenya lewat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_daftar_tunggu', function (Blueprint $table) {
            /*
             | Rombongan yang ditawarkan — bukan paketnya.
             |
             | Antreannya sendiri tetap milik PAKET: orang mendaftar antre
             | sebelum tahu tanggal mana yang akan kosong. Yang berpasangan
             | dengan satu tanggal adalah tawarannya, bukan antreannya.
             */
            $table->unsignedBigInteger('keberangkatan_id')->nullable()->after('travel_package_id');
            $table->timestamp('ditawarkan_pada')->nullable()->after('dikabari_pada');

            /*
             | Sampai kapan kursinya ditahan.
             |
             | Tidak menahan apa pun di tabel kuota — penahanan sungguhan
             | menuntut kursi dikurangi lalu dikembalikan, dan rantai itu putus
             | setiap kali ada yang lupa. Yang disimpan di sini JANJI-nya, dan
             | layar menandai yang sudah lewat supaya admin tahu kursinya boleh
             | ditawarkan ke orang berikutnya.
             */
            $table->timestamp('ditahan_sampai')->nullable()->after('ditawarkan_pada');

            $table->index('keberangkatan_id', 'tunggu_keberangkatan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_daftar_tunggu', function (Blueprint $table) {
            $table->dropIndex('tunggu_keberangkatan_idx');
            $table->dropColumn(['keberangkatan_id', 'ditawarkan_pada', 'ditahan_sampai']);
        });
    }
};
