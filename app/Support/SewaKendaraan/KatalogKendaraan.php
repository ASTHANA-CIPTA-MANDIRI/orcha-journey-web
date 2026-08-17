<?php

namespace App\Support\SewaKendaraan;

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\KatalogTambahan;

/**
 * Pilihan merek dan model untuk formulir armada.
 *
 * Katalog dasarnya ditulis di config/orcha.php — lihat catatan di sana soal
 * mengapa tidak diambil dari API pihak ketiga. Yang dikerjakan kelas ini adalah
 * menggabungkannya dengan merek dan model yang SUDAH dipakai armada sendiri.
 *
 * Penggabungan itu bukan hiasan. Tanpanya, mengubah unit lama yang mereknya
 * tidak tercantum di katalog akan menghadapkan admin pada dropdown yang tidak
 * memuat merek unit itu sendiri — dan pilihan teraman yang tersisa adalah
 * mengubahnya menjadi merek lain. Data yang sudah benar tidak boleh dipaksa
 * berubah hanya karena katalognya belum lengkap.
 */
class KatalogKendaraan
{
    /**
     * @return array<string, list<string>> merek => daftar model
     */
    public static function pilihan(): array
    {
        $katalog = self::dariConfig();

        foreach (self::dariTambahan() as $merek => $daftarModel) {
            $katalog[$merek] = array_merge($katalog[$merek] ?? [], $daftarModel);
        }

        foreach (self::dariArmada() as $merek => $daftarModel) {
            $katalog[$merek] = array_merge($katalog[$merek] ?? [], $daftarModel);
        }

        foreach ($katalog as $merek => $daftarModel) {
            $bersih = array_values(array_unique($daftarModel));
            sort($bersih, SORT_NATURAL | SORT_FLAG_CASE);
            $katalog[$merek] = $bersih;
        }

        ksort($katalog, SORT_NATURAL | SORT_FLAG_CASE);

        return $katalog;
    }

    /**
     * Entri yang ditambahkan admin sendiri — satu-satunya yang boleh dihapus.
     *
     * Katalog bawaan ikut versi kode, dan merek yang terbaca dari armada tidak
     * boleh dihapus karena unitnya benar-benar memakainya: menghapusnya hanya
     * membuat daftar berbohong tentang isi armada sendiri.
     *
     * @return list<array{id: int, merek: string, model: string|null}>
     */
    public static function kustom(): array
    {
        return KatalogTambahan::query()
            ->orderBy('merek')
            ->orderByRaw('model is null desc')
            ->orderBy('model')
            ->get()
            ->map(fn (KatalogTambahan $e) => [
                'id' => $e->id,
                'merek' => $e->merek,
                'model' => $e->model,
            ])
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private static function dariTambahan(): array
    {
        $hasil = [];

        foreach (KatalogTambahan::all() as $entri) {
            $hasil[$entri->merek] ??= [];

            if ($entri->model !== null && $entri->model !== '') {
                $hasil[$entri->merek][] = $entri->model;
            }
        }

        return $hasil;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function dariConfig(): array
    {
        $hasil = [];

        foreach ((array) config('orcha.katalog_kendaraan', []) as $merek => $daftarModel) {
            $merek = trim((string) $merek);

            if ($merek === '') {
                continue;
            }

            $hasil[$merek] = array_values(array_filter(
                array_map(fn ($model) => trim((string) $model), (array) $daftarModel),
                fn ($model) => $model !== '',
            ));
        }

        return $hasil;
    }

    /**
     * Merek dan model yang sudah tercatat di armada.
     *
     * Dibaca lewat satu query berisi nilai unik saja, bukan seluruh baris:
     * daftar ini ikut dikirim pada setiap permintaan rujukan.
     *
     * @return array<string, list<string>>
     */
    private static function dariArmada(): array
    {
        $hasil = [];

        Car::query()
            ->select('brand', 'name')
            ->distinct()
            ->get()
            ->each(function (Car $mobil) use (&$hasil) {
                $merek = trim((string) $mobil->brand);
                $nama = trim((string) $mobil->name);

                if ($merek === '') {
                    return;
                }

                $hasil[$merek] ??= [];

                if ($nama !== '') {
                    $hasil[$merek][] = $nama;
                }
            });

        return $hasil;
    }
}
