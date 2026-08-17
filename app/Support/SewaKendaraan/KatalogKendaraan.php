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
     * Daftar nama model per merek, untuk daftar pilihan.
     *
     * @return array<string, list<string>>
     */
    public static function pilihan(): array
    {
        $hasil = [];

        foreach (self::gabungan() as $merek => $model) {
            $nama = array_keys($model);
            sort($nama, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[$merek] = $nama;
        }

        return $hasil;
    }

    /**
     * Jumlah kursi per model, untuk mengisi kapasitas secara otomatis.
     *
     * Model yang kursinya belum dipastikan tidak disertakan — lebih baik isian
     * kapasitas dibiarkan kosong daripada diisi angka yang belum tentu benar,
     * karena angka yang sudah tertulis cenderung tidak diperiksa lagi.
     *
     * @return array<string, array<string, int>>
     */
    public static function kapasitas(): array
    {
        $hasil = [];

        foreach (self::gabungan() as $merek => $model) {
            $terisi = array_filter($model, fn ($kursi) => is_int($kursi) && $kursi > 0);

            if ($terisi !== []) {
                $hasil[$merek] = $terisi;
            }
        }

        return $hasil;
    }

    /**
     * Katalog bawaan + tambahan admin + yang terbaca dari armada.
     *
     * Urutannya menentukan: kursi dari armada MENIMPA bawaan, karena unit nyata
     * lebih berhak daripada angka rujukan. Avanza yang dipasangi 6 kursi di
     * armada tidak seharusnya terus menawarkan 7.
     *
     * @return array<string, array<string, int|null>>
     */
    private static function gabungan(): array
    {
        $katalog = self::dariConfig();

        foreach ([self::dariTambahan(), self::dariArmada()] as $sumber) {
            foreach ($sumber as $merek => $model) {
                $katalog[$merek] ??= [];

                foreach ($model as $nama => $kursi) {
                    // Kursi kosong dari sumber berikutnya tidak menghapus angka
                    // yang sudah diketahui.
                    $katalog[$merek][$nama] = $kursi ?? ($katalog[$merek][$nama] ?? null);
                }
            }
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
     * @return array<string, array<string, null>>
     */
    private static function dariTambahan(): array
    {
        $hasil = [];

        foreach (KatalogTambahan::all() as $entri) {
            $hasil[$entri->merek] ??= [];

            if ($entri->model !== null && $entri->model !== '') {
                $hasil[$entri->merek][$entri->model] = null;
            }
        }

        return $hasil;
    }

    /**
     * @return array<string, array<string, int|null>>
     */
    private static function dariConfig(): array
    {
        $hasil = [];

        foreach ((array) config('orcha.katalog_kendaraan', []) as $merek => $model) {
            $merek = trim((string) $merek);

            if ($merek === '') {
                continue;
            }

            $hasil[$merek] = [];

            foreach ((array) $model as $nama => $kursi) {
                $nama = trim((string) $nama);

                if ($nama !== '') {
                    $hasil[$merek][$nama] = is_numeric($kursi) ? (int) $kursi : null;
                }
            }
        }

        return $hasil;
    }

    /**
     * Merek, model, dan kapasitas yang sudah tercatat di armada.
     *
     * Dibaca lewat satu query berisi nilai unik saja, bukan seluruh baris:
     * daftar ini ikut dikirim pada setiap permintaan rujukan.
     *
     * @return array<string, array<string, int|null>>
     */
    private static function dariArmada(): array
    {
        $hasil = [];

        Car::query()
            ->select('brand', 'name', 'capacity')
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
                    $kursi = (int) $mobil->capacity;
                    $hasil[$merek][$nama] = $kursi > 0 ? $kursi : null;
                }
            });

        return $hasil;
    }
}
