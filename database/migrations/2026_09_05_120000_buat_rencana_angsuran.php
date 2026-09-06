<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana angsuran untuk pelanggan yang meminta keringanan.
 *
 * Dua tabel, dan pembagiannya sengaja: satu rencana per pesanan, beberapa
 * termin per rencana. Termin disimpan sebagai baris tersendiri — bukan JSON —
 * karena pertanyaan yang paling sering diajukan justru melintasi pesanan:
 * "siapa yang terminnya sudah lewat jatuh tempo?". Itu satu kueri bila
 * barisnya nyata, dan pemindaian seluruh tabel bila ia tersembunyi di dalam
 * JSON.
 *
 * Yang TIDAK ada di sini: kolom "sudah dibayar" per termin.
 *
 * Status termin diturunkan dari jumlah kumulatif pembayaran, bukan disimpan.
 * Menyimpannya berarti mengalokasikan tiap pembayaran ke termin tertentu —
 * dan alokasi itu punya kasus khusus yang tidak habis-habis: pelanggan yang
 * membayar lebih, yang membayar kurang, yang membayar dua termin sekaligus,
 * yang dikembalikan sebagian. Tiap kasus adalah satu tempat baru untuk
 * desinkron dengan tagihan yang sebenarnya. Diturunkan, semuanya benar tanpa
 * satu pun cabang tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_angsuran', function (Blueprint $table) {
            $table->id();

            // Kode pendaftaran (OT-...) atau sewa (SK-...), sama seperti
            // tbl_konfirmasi_pembayaran.
            $table->string('kode', 30)->index();

            $table->unsignedTinyInteger('jumlah_termin');

            /*
             | Total tagihan SAAT RENCANA DIBUAT.
             |
             | Disimpan, bukan dihitung ulang. Harga pesanan bisa berubah
             | sesudahnya — peserta bertambah, promo disesuaikan — dan jadwal
             | yang angkanya bergeser sendiri sesudah disepakati adalah cara
             | tercepat kehilangan kepercayaan pelanggan yang sedang kesulitan.
             | Selisihnya ditangani sebagai sisa tagihan biasa, bukan dengan
             | menulis ulang jadwalnya diam-diam.
             */
            $table->unsignedBigInteger('total');

            $table->string('dibuat_oleh', 120)->nullable();
            $table->text('catatan')->nullable();

            // Rencana yang dibatalkan tidak dihapus: yang perlu dijawab saat
            // ada sengketa adalah "dulu dijanjikan apa", dan baris yang hilang
            // tidak menjawab apa pun.
            $table->timestamp('dibatalkan_pada')->nullable();

            $table->timestamps();
        });

        Schema::create('tbl_angsuran_termin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('angsuran_id')->constrained('tbl_angsuran')->cascadeOnDelete();

            $table->unsignedTinyInteger('urutan');
            $table->unsignedBigInteger('nominal');
            $table->date('jatuh_tempo')->index();

            $table->timestamps();

            $table->unique(['angsuran_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_angsuran_termin');
        Schema::dropIfExists('tbl_angsuran');
    }
};
