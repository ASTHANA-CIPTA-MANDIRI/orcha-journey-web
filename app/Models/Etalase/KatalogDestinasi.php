<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;

/**
 * Nama destinasi yang ditambahkan admin sendiri.
 */
class KatalogDestinasi extends Model
{
    protected $table = 'katalog_destinasi_tambahan';

    protected $fillable = ['nama', 'provinsi'];

    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->nama = trim(preg_replace('/\s+/', ' ', (string) $entri->nama));
            $entri->provinsi = trim((string) $entri->provinsi) ?: null;
        });
    }

    /**
     * Katalog lengkap: bawaan, tambahan admin, DAN destinasi yang sudah
     * tercatat.
     *
     * Destinasi yang sudah ada ikut supaya admin tidak perlu menambahkannya
     * lagi ke katalog hanya untuk bisa memilihnya — dan supaya nama yang sudah
     * dipakai muncul lebih dulu sebagai kemungkinan duplikat.
     *
     * @return array<string, string|null> nama => provinsi
     */
    public static function gabungan(): array
    {
        $tercatat = DestinationPopuler::query()
            ->orderBy('destination_name')
            ->pluck('provinsi', 'destination_name')
            ->all();

        return array_merge(
            (array) config('orcha.katalog_destinasi', []),
            static::orderBy('nama')->pluck('provinsi', 'nama')->all(),
            $tercatat,
        );
    }

    /**
     * Entri tambahan beserta idnya — hanya inilah yang boleh dihapus dari
     * daftar. Katalog bawaan ikut versi kode, dan nama destinasi yang sudah
     * tercatat dipakai barisnya sendiri.
     */
    public static function kustom(): array
    {
        return static::orderBy('nama')
            ->get(['id', 'nama', 'provinsi'])
            ->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama, 'provinsi' => $b->provinsi])
            ->all();
    }
}
