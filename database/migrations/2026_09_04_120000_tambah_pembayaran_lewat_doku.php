<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran publik pindah ke gerbang pembayaran DOKU.
 *
 * Dua perubahan, dan yang pertama menjelaskan kenapa yang kedua perlu tabel
 * tersendiri alih-alih menumpang yang sudah ada.
 *
 * 1. tbl_konfirmasi_pembayaran mendapat kolom `kanal`.
 *
 *    Isinya sekarang bercampur: bukti transfer yang diunggah pelanggan,
 *    catatan manual admin, dan mulai sekarang pembayaran yang masuk sendiri
 *    lewat DOKU. Ketiganya punya tingkat kepastian yang jauh berbeda —
 *    yang dari DOKU sudah pasti uangnya ada, yang dari unggahan baru
 *    klaim — dan yang membaca daftarnya setahun kemudian harus bisa
 *    membedakan tanpa menebak dari isi catatan admin.
 *
 * 2. Percobaan pembayaran DOKU ditampung tbl_pembayaran_doku, TERPISAH.
 *
 *    Godaannya adalah menuliskan tiap halaman DOKU yang dibuka sebagai baris
 *    "menunggu" di tbl_konfirmasi_pembayaran. Itu akan merusak tagihan.
 *    TagihanPesanan menghitung apa pun yang belum ditolak sebagai "sudah
 *    dilaporkan", jadi satu pelanggan yang membuka halaman bayar tiga kali
 *    lalu meninggalkannya akan terlihat sudah membayar tiga kali lipat, dan
 *    sisa tagihannya jatuh ke nol tanpa satu rupiah pun masuk.
 *
 *    Halaman bayar yang dibuka bukan pembayaran. Ia baru menjadi pembayaran
 *    saat notifikasi DOKU tiba — dan pada saat itulah barisnya lahir di
 *    tbl_konfirmasi_pembayaran, langsung berstatus diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_konfirmasi_pembayaran', function (Blueprint $table) {
            // transfer = bukti unggahan / catatan admin, doku = lewat gerbang.
            // Bawaannya 'transfer' supaya seluruh baris lama terbaca benar
            // tanpa perlu satu pun perintah pembaruan data.
            $table->string('kanal', 20)->default('transfer')->after('jenis');
        });

        Schema::create('tbl_pembayaran_doku', function (Blueprint $table) {
            $table->id();

            /*
             | Nomor tagihan yang dikirim ke DOKU, dan kunci yang dipakai
             | notifikasinya untuk menunjuk balik ke sini.
             |
             | Unik karena satu pesanan boleh mencoba membayar berkali-kali —
             | halaman kedaluwarsa, pelanggan berganti pikiran soal channel,
             | jaringan putus di tengah. Tiap percobaan adalah barisnya sendiri.
             */
            $table->string('invoice', 40)->unique();

            $table->string('kode', 30)->index();
            $table->string('jenis', 20)->default('dp');

            // Dipisah supaya bisa dijelaskan ke pelanggan dan dicocokkan admin:
            // "Rp 750.000 + kode unik 913". Nominal adalah yang benar-benar
            // ditagihkan DOKU.
            $table->unsignedBigInteger('nominal_pokok');
            $table->unsignedSmallInteger('kode_unik')->default(0);
            $table->unsignedBigInteger('nominal');

            // menunggu | berhasil | gagal | kedaluwarsa
            $table->string('status', 20)->default('menunggu')->index();

            $table->string('token_id')->nullable();
            $table->text('url')->nullable();

            // Channel yang akhirnya dipakai (VIRTUAL_ACCOUNT_BCA, QRIS, ...).
            // Baru diketahui saat notifikasinya tiba.
            $table->string('channel', 60)->nullable();

            $table->timestamp('dibayar_pada')->nullable();
            $table->timestamp('kedaluwarsa_pada')->nullable();

            /*
             | Isi notifikasi disimpan apa adanya.
             |
             | Saat ada sengketa "uang saya sudah masuk tapi statusnya belum
             | berubah", yang menjawabnya bukan ingatan siapa pun melainkan
             | pesan asli dari DOKU berikut nomor referensinya. Kolom ini yang
             | menyimpannya.
             */
            $table->json('notifikasi')->nullable();

            // Baris di tbl_konfirmasi_pembayaran yang lahir dari pembayaran ini.
            $table->unsignedBigInteger('konfirmasi_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pembayaran_doku');

        Schema::table('tbl_konfirmasi_pembayaran', function (Blueprint $table) {
            $table->dropColumn('kanal');
        });
    }
};
