<?php

namespace App\Models\SewaKendaraan;

use Illuminate\Database\Eloquent\Model;

/**
 * Entri katalog kendaraan yang ditambahkan admin sendiri.
 *
 * Satu baris = satu entri yang bisa dihapus tersendiri, pada tiga tingkat:
 * merek saja, satu model di bawah merek, atau satu tipe di bawah model.
 */
class KatalogTambahan extends Model
{
    protected $table = 'katalog_kendaraan_tambahan';

    protected $fillable = ['merek', 'model', 'varian'];

    /**
     * Menormalkan sebelum disimpan.
     *
     * Spasi berlebih dan huruf besar-kecil yang tidak seragam menghasilkan entri
     * kembar yang tampak sama di daftar — " toyota " dan "Toyota" akan lolos
     * batasan unik padahal maksudnya satu. Merek dirapikan huruf awalnya,
     * modelnya dibiarkan apa adanya karena nama model memang mengandung huruf
     * besar yang tidak beraturan ("MG ZS", "bZ4X", "Air ev").
     */
    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->merek = self::rapikanMerek($entri->merek);
            $entri->model = trim((string) $entri->model) ?: null;
            $entri->varian = trim(preg_replace('/\s+/', ' ', (string) $entri->varian)) ?: null;
        });
    }

    public static function rapikanMerek(?string $merek): string
    {
        $merek = trim(preg_replace('/\s+/', ' ', (string) $merek));

        // Merek yang sudah ada di katalog bawaan dipakai ejaannya, supaya
        // "toyota" tidak menjadi merek kedua di samping "Toyota".
        foreach (array_keys((array) config('orcha.katalog_kendaraan', [])) as $bawaan) {
            if (mb_strtolower($bawaan) === mb_strtolower($merek)) {
                return $bawaan;
            }
        }

        return $merek;
    }
}
