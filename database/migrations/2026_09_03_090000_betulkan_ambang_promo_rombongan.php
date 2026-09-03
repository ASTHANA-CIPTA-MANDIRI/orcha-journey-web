<?php

use App\Models\PaketWisata\PromoRombonganTingkat;
use Illuminate\Database\Migrations\Migration;

/**
 * Membetulkan ambang promo yang terlanjur tersimpan salah satu angka.
 *
 * min_peserta membandingkan JUMLAH PESERTA satu pendaftaran — pemesannya ikut
 * terhitung. Yang diucapkan orang jumlah REKAN: "ajak 5 dapat diskon" berarti
 * enam orang berangkat, dan "ajak 10, yang ke-11 gratis" berarti sebelas.
 *
 * Tingkat bawaan disemai dengan 5 dan 10, dibaca sebagai jumlah rekan padahal
 * dibandingkan sebagai jumlah peserta. Akibatnya rombongan berlima — yang si
 * pemesannya baru mengajak empat rekan — sudah mendapat potongan, dan
 * rombongan sepuluh sudah mendapat satu kursi gratis. Bukan salah hitung:
 * potongannya benar-benar diberikan, satu tingkat lebih murah daripada yang
 * dimaksudkan.
 *
 * YANG DISENTUH HANYA BARIS YANG MASIH PERSIS SEPERTI DISEMAI. Admin yang
 * sudah mengubah angkanya sendiri sudah memutuskan sesuatu, dan keputusan itu
 * tidak boleh ditimpa migrasi. Labelnya dirakit ulang untuk SEMUA baris —
 * kalimatnya kini menyebut jumlah rekan, dan label lama akan terus berbunyi
 * "Ajak 6 orang" pada tingkat yang minta enam peserta.
 */
return new class extends Migration
{
    /** min_peserta lama => baru, hanya untuk baris yang belum disentuh admin. */
    private const GESER = [
        5 => ['ke' => 6, 'potongan_persen' => 5, 'gratis_orang' => 0],
        10 => ['ke' => 11, 'potongan_persen' => 0, 'gratis_orang' => 1],
    ];

    public function up(): void
    {
        foreach (self::GESER as $lama => $ke) {
            $baris = PromoRombonganTingkat::query()
                ->where('min_peserta', $lama)
                ->where('potongan_persen', $ke['potongan_persen'])
                ->where('gratis_orang', $ke['gratis_orang'])
                ->first();

            if (! $baris) {
                continue;
            }

            // Ambang tujuannya sudah dipakai tingkat lain — menggesernya akan
            // menabrak kolom unik dan menggagalkan seluruh migrasi. Baris ini
            // dilewati; yang sudah ada di sana lebih layak dipertahankan.
            $bentrok = PromoRombonganTingkat::query()
                ->where('min_peserta', $ke['ke'])
                ->exists();

            if ($bentrok) {
                continue;
            }

            $baris->update(['min_peserta' => $ke['ke']]);
        }

        // Menyimpan ulang memicu perakitan label dan ajakan di model, sehingga
        // kalimatnya selalu sesuai angka yang berlaku — satu-satunya tempat
        // kalimat itu dibentuk.
        PromoRombonganTingkat::query()->get()->each->save();
    }

    /**
     * Sengaja tidak mengembalikan ambangnya.
     *
     * Angka lamanya salah baca, bukan pilihan bisnis. Memutar balik berarti
     * memberi potongan satu tingkat lebih murah lagi kepada pelanggan yang
     * mendaftar sesudahnya — kerugian yang tidak akan disadari siapa pun.
     */
    public function down(): void {}
};
