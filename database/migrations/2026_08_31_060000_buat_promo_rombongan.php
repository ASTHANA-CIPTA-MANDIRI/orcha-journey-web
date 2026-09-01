<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat promo rombongan yang bisa diubah admin sendiri.
 *
 * Sebelumnya tetap di config('orcha.promo_rombongan'). Masalahnya sama dengan
 * kategori artikel dulu: admin tidak punya akses ke berkas config, jadi tiap
 * kali ingin mengubah "ajak 5 dapat 5%" jadi 7%, pekerjaannya berhenti sampai
 * ada yang menyunting kode dan menaikkannya ke server.
 *
 * Padahal justru angka inilah yang paling sering diutak-atik — ia mengikuti
 * musim liburan, sisa kursi, dan tawaran pesaing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_promo_rombongan', function (Blueprint $tabel) {
            $tabel->id();

            // Jumlah peserta minimal supaya tingkat ini berlaku.
            $tabel->unsignedSmallInteger('min_peserta')->unique();

            /*
             | Dua bentuk keuntungan, dan keduanya boleh kosong salah satunya.
             |
             | Bedanya disengaja: "gratis 1 dari 10" secara hitungan sama dengan
             | potongan 10%, tetapi DISEBUT sebagai gratis satu orang — dan
             | kalimat itu yang diceritakan ulang orang ke temannya.
             */
            $tabel->unsignedTinyInteger('potongan_persen')->default(0);
            $tabel->unsignedTinyInteger('gratis_orang')->default(0);

            $tabel->string('label');
            $tabel->string('ajakan')->nullable();

            // Dimatikan sementara tanpa kehilangan angkanya — promo musiman
            // sering dihidupkan lagi tahun berikutnya dengan angka yang sama.
            $tabel->boolean('aktif')->default(true);

            $tabel->timestamps();
        });

        /*
         | Tingkat yang selama ini di config dipindahkan apa adanya.
         |
         | Tanpa ini, promo yang sedang berjalan mati begitu migrasinya
         | dijalankan — dan yang menyadarinya pelanggan, bukan kita.
         */
        $sekarang = now();

        $baris = collect(config('orcha.promo_rombongan', []))
            ->map(fn ($t) => [
                'min_peserta' => (int) ($t['min'] ?? 0),
                'potongan_persen' => (int) ($t['potongan_persen'] ?? 0),
                'gratis_orang' => (int) ($t['gratis_orang'] ?? 0),
                'label' => $t['label'] ?? '',
                'ajakan' => $t['ajakan'] ?? null,
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])
            ->filter(fn ($t) => $t['min_peserta'] > 0)
            ->values()
            ->all();

        if ($baris !== []) {
            DB::table('tbl_promo_rombongan')->insertOrIgnore($baris);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_promo_rombongan');
    }
};
