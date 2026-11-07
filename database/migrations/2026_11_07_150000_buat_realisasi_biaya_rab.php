<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berapa biaya ini SEBENARNYA ditagih vendor, sesudah trip jalan.
 *
 * RAB adalah perkiraan. Yang dibayar belum tentu sama — vendor memberi
 * diskon, cashback, atau justru menagih lebih. Tanpa angka ini, laporan
 * untung sesudah trip hanya bisa membandingkan RAB dengan uang keluar, dan
 * DUA HAL YANG BERLAWANAN jadi terlihat sama persis:
 *
 *   RAB 8jt, tagihan 7jt, dibayar 7jt  -> hemat 1jt   (untung bertambah)
 *   RAB 8jt, tagihan 8jt, dibayar 7jt  -> kurang 1jt  (masih utang)
 *
 * Keduanya "RAB dikurangi yang dibayar = 1jt". Hotel yang lupa dilunasi akan
 * tampil sebagai cashback satu juta, dan laporannya menjanjikan untung yang
 * uangnya justru masih harus keluar.
 *
 * Karena itu tagihan dicatat TERPISAH dari pembayaran, dan tandanya (+ sisa
 * / − kurang) dihitung, bukan diketik orang.
 *
 * Satu baris per baris biaya RAB: itu yang membuat "sisa dari destinasi mana"
 * bisa dijawab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_rab_realisasi', function (Blueprint $t) {
            $t->id();

            /*
             | Baris biaya yang direkap. Satu baris biaya satu rekap —
             | dua rekap untuk baris yang sama membuat "tagihan hotel ini
             | berapa" punya dua jawaban.
             |
             | Baris biaya dihapus, rekapnya ikut: ia tidak punya arti
             | sendirian. Penghapusan baris biaya sesudah trip jalan memang
             | tidak seharusnya terjadi, dan kalau terjadi, rekap yang
             | menggantung tanpa induk lebih menyesatkan daripada tidak ada.
             */
            $t->foreignId('rab_biaya_id')->unique()
                ->constrained('tbl_rab_biaya')->cascadeOnDelete();

            /*
             | Yang BENAR-BENAR ditagih vendor. Kosong berarti belum direkap
             | sama sekali — dan itu beda dari nol.
             |
             | Nol adalah tagihan yang sah: destinasi yang ternyata gratis,
             | atau biaya yang batal dipakai. Memakai nol sebagai "belum
             | diisi" membuat baris yang benar-benar gratis hilang dari
             | daftar tunggakan rekap, lalu tidak pernah diperiksa.
             */
            $t->unsignedBigInteger('tagihan')->nullable();

            /*
             | Siapa yang mengurus pembayaran ini. NAMA, bukan id: tetap
             | terbaca sesudah akunnya hilang — alasan yang sama dengan
             | kolom `oleh` di hari libur dan musim harga.
             */
            $t->string('pic', 120)->nullable();

            $t->string('catatan', 500)->nullable();
            $t->string('oleh', 120)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rab_realisasi');
    }
};
