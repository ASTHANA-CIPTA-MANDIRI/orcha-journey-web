<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;

/**
 * Provinsi yang ditambahkan admin sendiri, melengkapi daftar bawaan di config.
 */
class ProvinsiTambahan extends Model
{
    protected $table = 'provinsi_tambahan';

    protected $fillable = ['nama', 'wilayah'];

    /**
     * Menormalkan sebelum disimpan.
     *
     * Spasi berlebih dan huruf besar-kecil yang tidak seragam menghasilkan entri
     * kembar yang tampak sama di daftar — " bali " dan "Bali" akan lolos batasan
     * unik padahal maksudnya satu.
     */
    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->nama = self::rapikan($entri->nama);
        });
    }

    public static function rapikan(?string $nama): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', (string) $nama));

        // Provinsi yang sudah ada di daftar bawaan dipakai ejaannya, supaya
        // "jawa timur" tidak menjadi provinsi kedua di samping "Jawa Timur".
        foreach (array_keys((array) config('orcha.provinsi_wilayah', [])) as $bawaan) {
            if (mb_strtolower($bawaan) === mb_strtolower($nama)) {
                return $bawaan;
            }
        }

        return $nama;
    }

    /**
     * Daftar provinsi lengkap: bawaan ditambah yang ditulis admin.
     *
     * @return array<string, string> nama provinsi => kunci wilayah
     */
    public static function gabungan(): array
    {
        return array_merge(
            (array) config('orcha.provinsi_wilayah', []),
            static::orderBy('nama')->pluck('wilayah', 'nama')->all(),
        );
    }

    /**
     * Entri tambahan beserta idnya — hanya entri inilah yang boleh dihapus dari
     * daftar pilihan; yang bawaan ikut versi kode.
     */
    public static function kustom(): array
    {
        return static::orderBy('nama')
            ->get(['id', 'nama', 'wilayah'])
            ->map(fn ($baris) => ['id' => $baris->id, 'nama' => $baris->nama, 'wilayah' => $baris->wilayah])
            ->all();
    }
}
