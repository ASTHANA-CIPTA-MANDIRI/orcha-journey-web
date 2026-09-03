<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode rujukan: alumni trip yang membawa pendaftaran baru.
 *
 * Bedanya dengan promo rombongan tegas, dan bukan sekadar bentuk lain dari
 * potongan yang sama. Promo rombongan berlaku dalam SATU pendaftaran — ramai
 * orang berangkat bersama di tanggal yang sama. Kode rujukan berlaku LINTAS
 * pendaftaran: orang yang sudah pulang mengajak temannya ikut trip berikutnya,
 * di tanggal yang sama sekali berbeda.
 *
 * Yang kedua inilah yang membuat alumni terus menjual tanpa diminta. Selama
 * ini setiap peserta yang pulang senang adalah tenaga penjual yang tidak
 * pernah kita berikan alat apa pun — ia merekomendasikan lewat mulut, dan
 * tidak ada satu pun cara mengetahui bahwa pendaftaran baru itu datang
 * darinya.
 *
 * Dua tabel, bukan satu:
 *
 *   tbl_kode_rujukan  — kodenya dan pemiliknya
 *   kolom di pendaftaran — kode yang dipakai, dan angka yang DIBEKUKAN
 *
 * Angkanya dibekukan dengan alasan yang sama seperti harga dan potongan promo:
 * imbalan rujukan berubah sepanjang tahun, dan tanpa dibekukan, komisi yang
 * belum dibayarkan ikut berubah setiap kali seseorang menyunting angkanya
 * hari ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_kode_rujukan', function (Blueprint $tabel) {
            $tabel->id();

            $tabel->string('kode', 32)->unique();

            // Pemiliknya. Nomor WhatsApp yang wajib — itu satu-satunya jalur
            // yang pasti sampai, dan lewat sanalah komisinya dibicarakan.
            $tabel->string('nama');
            $tabel->string('whatsapp', 32);
            $tabel->string('email')->nullable();

            /*
             | Pendaftaran yang membuatnya jadi alumni.
             |
             | Boleh kosong: sebagian kode dibuat untuk mitra atau reseller yang
             | belum pernah ikut trip sama sekali. Tetapi bila ada, ia menjawab
             | pertanyaan yang paling sering muncul saat komisi dibayarkan —
             | "ini siapa, ya?"
             */
            $tabel->string('kode_pendaftaran_asal')->nullable();

            // Dimatikan sementara tanpa kehilangan riwayatnya. Kode yang
            // dihapus memutus jejak pendaftaran yang sudah memakainya.
            $tabel->boolean('aktif')->default(true);

            $tabel->text('catatan')->nullable();

            $tabel->timestamps();

            $tabel->index('whatsapp');
        });

        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->string('kode_rujukan', 32)->nullable()->after('potongan_promo');

            /*
             | Keduanya DIBEKUKAN saat mendaftar, seperti harga dan promo.
             |
             | potongan_rujukan — yang dipotong dari tagihan pendaftar
             | imbalan_rujukan  — yang menjadi hak pemilik kodenya
             |
             | Keduanya disimpan terpisah karena besarnya memang boleh berbeda:
             | pendaftar mendapat potongan yang terasa, pemilik kode mendapat
             | imbalan yang sepadan dengan usahanya, dan tidak ada alasan
             | keduanya harus sama.
             */
            $tabel->unsignedInteger('potongan_rujukan')->default(0)->after('kode_rujukan');
            $tabel->unsignedInteger('imbalan_rujukan')->default(0)->after('potongan_rujukan');

            /*
             | Kapan imbalannya dibayarkan; kosong berarti masih utang.
             |
             | Tanpa kolom ini, satu-satunya cara mengetahui komisi mana yang
             | sudah dibayar adalah mengingatnya — dan yang menagih nanti orang
             | yang merasa haknya belum diberikan, sambil kita tidak punya cara
             | membuktikan sebaliknya.
             */
            $tabel->timestamp('imbalan_dibayar_pada')->nullable()->after('imbalan_rujukan');

            $tabel->index('kode_rujukan');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendaftaran_open_trip', function (Blueprint $tabel) {
            $tabel->dropIndex(['kode_rujukan']);
            $tabel->dropColumn(['kode_rujukan', 'potongan_rujukan', 'imbalan_rujukan', 'imbalan_dibayar_pada']);
        });

        Schema::dropIfExists('tbl_kode_rujukan');
    }
};
