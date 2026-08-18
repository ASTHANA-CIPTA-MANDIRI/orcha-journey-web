<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;

/**
 * Nama destinasi yang ditambahkan admin sendiri.
 */
class KatalogDestinasi extends Model
{
    protected $table = 'katalog_destinasi_tambahan';

    protected $fillable = ['nama', 'provinsi', 'daerah'];

    protected static function booted(): void
    {
        static::saving(function (self $entri) {
            $entri->nama = trim(preg_replace('/\s+/', ' ', (string) $entri->nama));
            $entri->provinsi = trim((string) $entri->provinsi) ?: null;
            $entri->daerah = trim((string) $entri->daerah) ?: null;
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
     * Tiap baris membawa provinsi DAN daerah, sehingga satu pilihan mengisi
     * empat isian sekaligus.
     *
     * @return array<string, array{provinsi: ?string, daerah: ?string}>
     */
    public static function gabungan(): array
    {
        $bawaan = collect(config('orcha.katalog_destinasi', []))
            ->map(fn ($baris) => [
                'provinsi' => $baris['provinsi'] ?? null,
                'daerah' => $baris['daerah'] ?? null,
            ]);

        $tambahan = static::orderBy('nama')->get(['nama', 'provinsi', 'daerah'])
            ->mapWithKeys(fn ($b) => [$b->nama => ['provinsi' => $b->provinsi, 'daerah' => $b->daerah]]);

        $tercatat = DestinationPopuler::query()
            ->orderBy('destination_name')
            ->get(['destination_name', 'provinsi', 'daerah'])
            ->mapWithKeys(fn ($b) => [$b->destination_name => [
                'provinsi' => $b->provinsi,
                'daerah' => $b->daerah,
            ]]);

        return $bawaan->merge($tambahan)->merge($tercatat)->all();
    }

    /**
     * Entri tambahan beserta idnya — hanya inilah yang boleh dihapus dari
     * daftar. Katalog bawaan ikut versi kode, dan nama destinasi yang sudah
     * tercatat dipakai barisnya sendiri.
     */
    public static function kustom(): array
    {
        return static::orderBy('nama')
            ->get(['id', 'nama', 'provinsi', 'daerah'])
            ->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama,
                'provinsi' => $b->provinsi, 'daerah' => $b->daerah])
            ->all();
    }
}
