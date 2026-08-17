<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif sudah termasuk sopir atau belum.
 *
 * harga_sopir selama ini SELALU ditambahkan di atas tarif. Untuk HiAce dan bus
 * yang tarifnya memang sudah dihitung bersama sopirnya — "2.500.000 per hari,
 * sudah termasuk sopir" — satu-satunya cara menyatakannya adalah mengosongkan
 * harga_sopir. Dan itu bermakna ganda: bisa berarti "sudah termasuk", bisa
 * berarti "belum diisi".
 *
 * Akibatnya kartu unit tidak menyebut sopir sama sekali untuk unit semacam itu.
 * Pelanggan melihat 2.500.000 tanpa tahu sopirnya sudah dibayar, lalu bertanya
 * ulang — pertanyaan yang seharusnya sudah dijawab halaman itu.
 *
 * Unit yang sudah ada disesuaikan: yang SELALU dengan sopir tetapi harga
 * sopirnya kosong memang berarti tarifnya sudah termasuk sopir. Itu satu-satunya
 * tafsir yang masuk akal untuk keadaan tersebut, dan membiarkannya bernilai
 * bawaan false membuat data lamanya menyatakan hal yang tidak benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->boolean('termasuk_sopir')->default(false)->after('harga_sopir');
        });

        DB::table('cars')
            ->where('lepas_kunci', false)
            ->whereNull('harga_sopir')
            ->update(['termasuk_sopir' => true]);
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $tabel) {
            $tabel->dropColumn('termasuk_sopir');
        });
    }
};
