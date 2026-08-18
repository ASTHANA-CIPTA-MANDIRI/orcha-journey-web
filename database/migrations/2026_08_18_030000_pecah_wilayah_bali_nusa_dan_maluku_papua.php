<?php

use App\Models\Etalase\DestinationPopuler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wilayah dipecah mengikuti kelompok pulau yang sebenarnya.
 *
 * Sebelumnya enam: "Bali & Nusa Tenggara" dan "Maluku & Papua" masing-masing
 * menggabungkan dua kelompok yang jauh berbeda — Bali ke Labuan Bajo hampir 500
 * km laut, dan pengunjung yang menyaring "Bali" tidak sedang mencari Sumba.
 * Sekarang delapan: Sumatera, Jawa, Bali, Nusa Tenggara, Kalimantan, Sulawesi,
 * Maluku, Papua.
 *
 * Tiap destinasi dipetakan ulang DARI PROVINSINYA, bukan ditebak dari kunci
 * lamanya: "bali_nusa" bisa berarti Bali maupun Nusa Tenggara, dan menebaknya
 * berarti sebagian destinasi hilang dari penyaring yang benar.
 *
 * Yang provinsinya tidak dikenali dibiarkan apa adanya dan dicatat di log —
 * memaksanya ke salah satu wilayah baru hanya memindahkan kesalahan ke tempat
 * yang lebih sulit dilihat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $peta = (array) config('orcha.provinsi_wilayah', []);
        $tertinggal = [];

        foreach (DestinationPopuler::all() as $destinasi) {
            $wilayah = $peta[$destinasi->provinsi] ?? null;

            if ($wilayah === null) {
                $tertinggal[] = $destinasi->destination_name.' ('.($destinasi->provinsi ?: 'tanpa provinsi').')';

                continue;
            }

            // DB::table, bukan Eloquent: tanggal ubah yang bergeser serentak
            // untuk seluruh destinasi menghapus jejak kapan tiap baris
            // benar-benar disunting admin.
            DB::table('tbl_destinasi_populer')
                ->where('id', $destinasi->id)
                ->update(['wilayah' => $wilayah]);
        }

        if ($tertinggal !== []) {
            logger()->warning('Wilayah destinasi tidak bisa dipetakan ulang', ['destinasi' => $tertinggal]);
        }
    }

    /**
     * Kembali ke enam wilayah.
     *
     * Bukan pembalikan yang setara: destinasi yang tadinya "bali_nusa" tidak
     * bisa dibedakan lagi mana yang Bali dan mana yang Nusa Tenggara. Yang
     * dikembalikan hanya pengelompokannya.
     */
    public function down(): void
    {
        DB::table('tbl_destinasi_populer')->whereIn('wilayah', ['bali', 'nusa_tenggara'])
            ->update(['wilayah' => 'bali_nusa']);

        DB::table('tbl_destinasi_populer')->whereIn('wilayah', ['maluku', 'papua'])
            ->update(['wilayah' => 'maluku_papua']);
    }
};
