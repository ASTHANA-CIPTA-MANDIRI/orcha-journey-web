<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peminat trip yang kursinya sedang penuh.
 *
 * Halaman paket yang penuh mengarahkan orang ke WhatsApp, dan jawabannya tidak
 * tersimpan di mana pun: begitu percakapannya berakhir, peminat itu hilang.
 *
 * Padahal merekalah yang paling mungkin langsung mengambil kursi yang dilepas
 * otomatis — kursi yang sekarang memang rutin dilepas tiap jam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_daftar_tunggu', function (Blueprint $tabel) {
            $tabel->id();

            $tabel->foreignId('travel_package_id')->constrained('tbl_travel_package')->cascadeOnDelete();

            $tabel->string('nama', 120);
            $tabel->string('whatsapp', 30);
            $tabel->string('email', 150)->nullable();
            $tabel->unsignedSmallInteger('jumlah_peserta')->default(1);

            /*
             | Kapan ia dikabari kursinya terbuka. Kosong berarti belum pernah.
             |
             | Disimpan sebagai waktu, bukan penanda benar/salah: yang perlu
             | diketahui admin bukan cuma "sudah dikabari" melainkan KAPAN —
             | orang yang dikabari tiga minggu lalu dan tidak menjawab berbeda
             | dari yang dikabari pagi tadi.
             */
            $tabel->timestamp('dikabari_pada')->nullable();

            $tabel->timestamps();

            /*
             | Satu nomor, satu antrean per paket.
             |
             | Orang yang bertanya dua kali tidak boleh menempati dua tempat di
             | antrean — dan tanpa ini, ia juga akan menerima dua kabar saat
             | kursinya terbuka.
             */
            $tabel->unique(['travel_package_id', 'whatsapp']);

            // Dipakai mencari siapa yang perlu dikabari: per paket, yang belum.
            $tabel->index(['travel_package_id', 'dikabari_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_daftar_tunggu');
    }
};
