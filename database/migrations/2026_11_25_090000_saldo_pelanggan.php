<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo pelanggan: uang yang sudah masuk tetapi tidak jadi terpakai.
 *
 * Lahir dari satu keadaan nyata: rombongan yang pindah paket ke harga yang
 * lebih murah. Uangnya sudah di rekening kantor, tripnya tetap jalan, dan
 * selisihnya menggantung. Pilihannya dua — dikembalikan, atau disimpan untuk
 * perjalanan berikutnya — dan yang kedua tidak punya tempat di mana pun
 * sebelum tabel ini ada.
 *
 * Tanpa tabel, "saldo" hanya bisa ditulis sebagai catatan bebas. Catatan
 * bebas tidak pernah ditagih balik oleh siapa pun: setengah tahun kemudian
 * pelanggan menyebut punya titipan, dan tidak ada satu pun angka yang bisa
 * membenarkan atau membantahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_saldo_pelanggan', function (Blueprint $tabel) {
            $tabel->id();

            /*
             | Pemiliknya dikenali lewat NOMOR WHATSAPP yang sudah dinormalkan
             | (NomorTelepon::angka), bukan lewat nama.
             |
             | Orcha tidak punya tabel pelanggan — pelanggannya disimpulkan
             | dari pesanan. Nama ditulis berbeda-beda oleh orang yang sama
             | ("Eva", "eva", "Bu Eva"), sedangkan nomornya satu. Nama tetap
             | disimpan, tetapi sebagai keterangan untuk dibaca manusia, bukan
             | sebagai kunci.
             */
            $tabel->string('whatsapp', 32)->index();
            $tabel->string('nama', 120)->nullable();

            $tabel->unsignedBigInteger('nominal');

            // Dari pesanan mana uang ini berasal. Tanpa ini, saldo yang
            // dipersoalkan tidak bisa ditelusuri kembali ke transaksinya.
            $tabel->string('asal_kode', 32)->index();
            $tabel->string('alasan', 191)->nullable();

            /*
             | Dipakai atau belum — dan oleh pesanan yang mana.
             |
             | Satu baris saldo tidak pernah dipakai separuh. Kalau yang
             | terpakai cuma sebagian, barisnya ditutup dan sisanya dibuat
             | sebagai baris baru: saldo yang nominalnya berubah-ubah
             | menghapus jejak berapa yang semula dititipkan.
             */
            $tabel->timestamp('terpakai_pada')->nullable();
            $tabel->string('terpakai_kode', 32)->nullable()->index();

            $tabel->string('dicatat_oleh', 120)->nullable();
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_saldo_pelanggan');
    }
};
