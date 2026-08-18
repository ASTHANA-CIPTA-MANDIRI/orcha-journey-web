<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;

/**
 * Daerah (kabupaten, kota, atau kawasan) yang ditambahkan admin sendiri.
 */
class DaerahTambahan extends Model
{
    protected $table = 'daerah_tambahan';

    protected $fillable = ['nama', 'provinsi'];

    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->nama = trim(preg_replace('/\s+/', ' ', (string) $entri->nama));
            $entri->provinsi = trim((string) $entri->provinsi);
        });
    }

    /**
     * Daftar daerah lengkap: bawaan, tambahan admin, dan daerah yang sudah
     * dipakai destinasi.
     *
     * Yang sudah dipakai ikut supaya admin tidak perlu menambahkannya lagi ke
     * katalog hanya untuk bisa memilihnya pada destinasi berikutnya.
     *
     * @return array<string, string> nama daerah => provinsi
     */
    public static function gabungan(): array
    {
        $terpakai = DestinationPopuler::query()
            ->whereNotNull('daerah')
            ->where('daerah', '!=', '')
            ->orderBy('daerah')
            ->pluck('provinsi', 'daerah')
            ->filter()
            ->all();

        return array_merge(
            (array) config('orcha.katalog_daerah', []),
            static::orderBy('nama')->pluck('provinsi', 'nama')->all(),
            $terpakai,
        );
    }

    /**
     * Entri tambahan beserta idnya — hanya inilah yang boleh dihapus dari
     * daftar; yang bawaan ikut versi kode.
     */
    public static function kustom(): array
    {
        return static::orderBy('nama')
            ->get(['id', 'nama', 'provinsi'])
            ->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama, 'provinsi' => $b->provinsi])
            ->all();
    }
}
