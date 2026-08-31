<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa mengubah apa, kapan.
 *
 * Orcha tidak punya sesi login untuk admin — pengelolaannya lewat lemon, dan
 * yang sampai ke sini cuma nama admin di header X-Orcha-Admin. Sudah ada
 * ApiController::catat() yang menuliskannya ke berkas log, lengkap dengan
 * komentar yang menyebut "supaya jelas admin Phoenix mana yang melakukannya".
 *
 * Metode itu tidak pernah dipanggil dari mana pun. 49 endpoint yang mengubah
 * data, nol yang tercatat.
 *
 * Dan berkas log memang bukan tempatnya, walaupun dipanggil:
 *
 *   - Tidak bisa dibaca siapa pun tanpa akses SSH ke server, sedangkan yang
 *     bertanya "siapa yang mengubah nominal pengembalian ini?" adalah orang
 *     keuangan, bukan yang memegang kunci server.
 *   - Log berputar dan terhapus. Sengketa justru datang belakangan.
 *   - Tidak bisa disaring per pesanan, per admin, atau per rentang tanggal.
 *
 * Tabel ini menyimpannya sebagai data: bisa ditanyai, ikut dicadangkan
 * bersama basis data, dan bisa ditampilkan di layar admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_jejak_audit', function (Blueprint $tabel) {
            $tabel->id();

            /*
             | Nama admin apa adanya, BUKAN relasi ke tabel pengguna.
             |
             | Penggunanya memang tidak ada di basis data ini — mereka ada di
             | lemon. Menyimpan namanya sebagai teks juga berarti jejaknya tetap
             | terbaca setelah akun itu dihapus atau berganti nama, dan itu
             | justru sifat yang dituntut dari catatan audit: ia merekam keadaan
             | saat kejadian, bukan keadaan sekarang.
             */
            $tabel->string('admin')->index();

            // Mesin yang membaca: 'pembayaran.diterima', 'pembatalan.disetujui'.
            $tabel->string('aksi')->index();

            // Manusia yang membaca. Sudah dirakit saat dicatat, bukan disusun
            // ulang saat ditampilkan — kalimat yang dirakit belakangan akan
            // memakai data yang SEKARANG, bukan yang saat itu.
            $tabel->string('ringkasan', 500);

            /*
             | Kode pesanan yang terkena, bila ada.
             |
             | Inilah yang dipakai orang untuk mencari: pertanyaannya hampir
             | selalu "apa yang terjadi pada OT-3108-K7QMXV", bukan "apa saja
             | yang dilakukan admin Rina hari Selasa".
             */
            $tabel->string('kode')->nullable()->index();

            // Nilai sebelum dan sesudah, untuk perubahan yang berupa angka atau
            // status. Disimpan sebagai teks supaya satu kolom melayani nominal
            // maupun status tanpa tabel tambahan.
            $tabel->string('sebelum')->nullable();
            $tabel->string('sesudah')->nullable();

            $tabel->string('ip', 45)->nullable();

            $tabel->timestamps();

            // Halaman jejak selalu diurutkan terbaru dulu, sering disaring per
            // admin sekaligus.
            $tabel->index(['created_at', 'admin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_jejak_audit');
    }
};
