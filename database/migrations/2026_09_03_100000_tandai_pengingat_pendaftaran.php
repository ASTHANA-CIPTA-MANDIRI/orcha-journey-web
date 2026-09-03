<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kapan tiap pengingat sudah dikirim.
 *
 * Tanpa penanda, satu-satunya cara menghindari kiriman ganda adalah menghitung
 * ulang tanggalnya dan berharap cron dijalankan tepat sekali sehari. Cron
 * gagal lalu diulang, jam server bergeser, atau seseorang menjalankan
 * perintahnya manual untuk memeriksa — dan pelanggan menerima surat yang sama
 * tiga kali. Yang paling merusak justru pengingat: surat yang menagih hal yang
 * sudah dibayar membuat orang berhenti membaca surat kita sama sekali.
 *
 * Disimpan sebagai WAKTU, bukan boolean. Saat ada yang bertanya "kenapa saya
 * tidak dikabari?", yang menjawabnya tanggal — bukan sekadar ya/tidak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->timestamp('pengingat_pelunasan_pada')->nullable()->after('status');
            $tabel->timestamp('briefing_pada')->nullable()->after('pengingat_pelunasan_pada');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropColumn(['pengingat_pelunasan_pada', 'briefing_pada']);
        });
    }
};
