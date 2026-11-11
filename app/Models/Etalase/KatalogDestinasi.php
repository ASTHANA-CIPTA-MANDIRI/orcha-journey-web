<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nama destinasi yang ditambahkan admin sendiri.
 *
 * Dihapus secara LEMBUT: provinsi & daerahnya dulu diisikan sendiri oleh
 * pencari lokasi, jadi baris yang terhapus permanen tidak bisa diketik
 * ulang dengan benar oleh siapa pun. Menambahkan nama yang sama lewat jalan
 * biasa memulihkannya utuh — lihat ProvinsiController::simpanKatalogDestinasi.
 */
class KatalogDestinasi extends Model
{
    use SoftDeletes;

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

    /**
     * Nama → id, HANYA untuk nama yang akan benar-benar hilang dari daftar
     * bila barisnya dihapus.
     *
     * `gabungan()` menumpuk tiga sumber. Nama yang juga ada di katalog
     * bawaan atau sudah tercatat sebagai destinasi tetap muncul di daftar
     * sesudah baris tambahannya dihapus — tombol hapus di sebelahnya
     * berarti menjanjikan sesuatu yang tidak terjadi: admin menekannya,
     * namanya masih di sana, dan ia menekannya lagi.
     *
     * @return array<string, int>
     */
    public static function bisaDihapus(): array
    {
        $rapi = fn ($t) => mb_strtolower(trim((string) $t));

        $lain = collect(array_keys(config('orcha.katalog_destinasi', [])))
            ->merge(DestinationPopuler::query()->pluck('destination_name'))
            ->map($rapi)->flip();

        return static::orderBy('nama')->get(['id', 'nama'])
            ->reject(fn ($b) => $lain->has($rapi($b->nama)))
            ->mapWithKeys(fn ($b) => [$b->nama => (int) $b->id])
            ->all();
    }
}
