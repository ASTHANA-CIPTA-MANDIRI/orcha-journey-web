<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Wilayah yang ditambahkan admin sendiri, melengkapi delapan kelompok pulau
 * bawaan di config.
 */
class WilayahTambahan extends Model
{
    protected $table = 'wilayah_tambahan';

    protected $fillable = ['kunci', 'label'];

    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->label = trim(preg_replace('/\s+/', ' ', (string) $entri->label));

            if (blank($entri->kunci)) {
                $entri->kunci = static::kunciDari($entri->label);
            }
        });
    }

    /**
     * Kunci dari labelnya: huruf kecil, spasi jadi garis bawah.
     *
     * Dipakai sebagai nilai kolom wilayah tiap destinasi, jadi bentuknya harus
     * sama dengan kunci bawaan ('nusa_tenggara'), bukan bertanda hubung.
     */
    public static function kunciDari(string $label): string
    {
        return Str::of($label)->lower()->slug('_')->limit(40, '')->toString();
    }

    /**
     * Daftar wilayah lengkap: bawaan ditambah yang ditulis admin.
     *
     * @return array<string, string> kunci => label
     */
    public static function gabungan(): array
    {
        return array_merge(
            (array) config('orcha.wilayah', []),
            static::orderBy('label')->pluck('label', 'kunci')->all(),
        );
    }

    /**
     * Entri tambahan beserta idnya — hanya inilah yang boleh dihapus dari
     * daftar; yang bawaan ikut versi kode.
     */
    public static function kustom(): array
    {
        return static::orderBy('label')
            ->get(['id', 'kunci', 'label'])
            ->map(fn ($b) => ['id' => $b->id, 'kunci' => $b->kunci, 'label' => $b->label])
            ->all();
    }
}
