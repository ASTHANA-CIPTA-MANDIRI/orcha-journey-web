<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merek dan model kendaraan yang ditambahkan admin sendiri.
 *
 * Katalog bawaan ada di config/orcha.php dan tidak bisa disunting dari admin —
 * memang tidak seharusnya, itu daftar rujukan yang ikut versi kode. Tabel ini
 * menampung tambahan dari admin lewat pilihan "Tulis sendiri" di formulir
 * armada, supaya sekali ditulis langsung ikut terdaftar dan tidak perlu ditulis
 * ulang tiap kali ada unit sejenis.
 *
 * Sebelum ini, merek yang ditulis manual hanya bertahan kalau unitnya benar-
 * benar tersimpan — nama yang diketik lalu dibatalkan hilang begitu saja, dan
 * tidak ada cara menghapus entri yang salah tulis.
 *
 * model boleh NULL: barisnya berarti "merek ini ada", tanpa menyebut modelnya.
 * Merek dan model disimpan sebagai baris terpisah supaya keduanya bisa dihapus
 * sendiri-sendiri.
 *
 * Menghapus baris di sini TIDAK menghapus kendaraan apa pun. Unit yang sudah
 * memakai merek atau model itu tetap utuh, dan namanya tetap muncul di daftar
 * pilihan karena ikut dibaca dari armada — jadi menghapus entri katalog tidak
 * pernah membuat unit yang ada kehilangan mereknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('katalog_kendaraan_tambahan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('merek', 100);
            $tabel->string('model', 120)->nullable();
            $tabel->timestamps();

            // Satu entri hanya perlu ada sekali. Tanpa ini, menekan "Tulis
            // sendiri" dua kali dengan ejaan yang sama membuat daftar pilihan
            // memuat baris kembar yang tak terbedakan.
            $tabel->unique(['merek', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('katalog_kendaraan_tambahan');
    }
};
