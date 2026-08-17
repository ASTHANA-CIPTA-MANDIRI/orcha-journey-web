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

        foreach (self::rincian() as $merek => $model) {
            $nama = array_keys($model);
            sort($nama, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[$merek] = $nama;
        }

        return $hasil;
    }

    /**
     * Keterangan tiap model: kursi, jenis, isi silinder, dan tipe yang tersedia.
     *
     * Dipakai mengisi isian di formulir armada secara otomatis. Nilai yang belum
     * dipastikan dikembalikan null, dan isian yang bersangkutan tidak diisi —
     * lebih baik kosong daripada angka karangan, karena angka yang sudah tertulis
     * cenderung tidak diperiksa lagi.
     *
     * @return array<string, array<string, array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}>>
     */
    public static function rincian(): array
    {
        $katalog = self::dariConfig();

        foreach ([self::dariTambahan(), self::dariArmada()] as $sumber) {
            foreach ($sumber as $merek => $model) {
                $katalog[$merek] ??= [];

                foreach ($model as $nama => $isi) {
                    $katalog[$merek][$nama] = self::gabungRincian($katalog[$merek][$nama] ?? null, $isi);
                }
            }
        }

        ksort($katalog, SORT_NATURAL | SORT_FLAG_CASE);

        // Jenis disimpulkan dari jumlah kursinya, bukan ditulis 180 kali.
        //
        // Aturannya: 25 kursi ke atas bus, 12 sampai 24 minibus (hiace), sisanya
        // mobil. Batas 12 dipilih supaya MPV mewah berkursi 11 seperti Carnival
        // dan Staria tetap terbaca mobil, sedangkan HiAce Premio 14 dan Sprinter
        // 15 masuk kelas minibus — sesuai cara unit itu benar-benar disewakan.
        foreach ($katalog as $merek => $model) {
            foreach ($model as $nama => $isi) {
                $katalog[$merek][$nama]['jenis'] = self::jenisDariKursi($isi['kursi']);
            }
        }

        return $katalog;
    }

    /**
     * Jumlah kursi per model.
     *
     * @return array<string, array<string, int>>
     */
    public static function kapasitas(): array
    {
        return self::petaNilai('kursi');
    }

    /**
     * Jenis kendaraan per model, mengikuti kunci config orcha.jenis_kendaraan.
     *
     * @return array<string, array<string, string>>
     */
    public static function jenis(): array
    {
        return self::petaNilai('jenis');
    }

    /**
     * Isi silinder (cc) per model.
     *
     * @return array<string, array<string, int>>
     */
    public static function mesin(): array
    {
        return self::petaNilai('cc');
    }

    /**
     * Tipe/varian yang tersedia per model, untuk daftar pilihan tipe.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function varian(): array
    {
        $hasil = [];

        foreach (self::rincian() as $merek => $model) {
            foreach ($model as $nama => $isi) {
                if ($isi['varian'] !== []) {
                    $hasil[$merek][$nama] = $isi['varian'];
                }
            }
        }

        return $hasil;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function petaNilai(string $kunci): array
    {
        $hasil = [];

        foreach (self::rincian() as $merek => $model) {
            $terisi = [];

            foreach ($model as $nama => $isi) {
                if (($isi[$kunci] ?? null) !== null) {
                    $terisi[$nama] = $isi[$kunci];
                }
            }

            if ($terisi !== []) {
                $hasil[$merek] = $terisi;
            }
        }

        return $hasil;
    }

    private static function jenisDariKursi(?int $kursi): ?string
    {
        if ($kursi === null || $kursi < 1) {
            return null;
        }

        return match (true) {
            $kursi >= 25 => 'bus',
            $kursi >= 12 => 'hiace',
            default => 'mobil',
        };
    }

    /**
     * Menggabungkan dua keterangan model.
     *
     * Sumber yang lebih baru menimpa yang lama HANYA untuk nilai yang benar-benar
     * diketahuinya. Nilai kosong tidak menghapus angka yang sudah ada: unit di
     * armada yang cc-nya belum diisi tidak boleh menghapus 1200 cc dari katalog.
     *
     * @param  array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}|null  $lama
     * @param  array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}  $baru
     * @return array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}
     */
    private static function gabungRincian(?array $lama, array $baru): array
    {
        $lama ??= ['kursi' => null, 'jenis' => null, 'cc' => null, 'varian' => []];

        return [
            'kursi' => $baru['kursi'] ?? $lama['kursi'],
            'jenis' => $baru['jenis'] ?? $lama['jenis'],
            'cc' => $baru['cc'] ?? $lama['cc'],
            'varian' => self::gabungVarian($lama['varian'], $baru['varian']),
        ];
    }

    /**
     * @param  list<string>  $lama
     * @param  list<string>  $baru
     * @return list<string>
     */
    private static function gabungVarian(array $lama, array $baru): array
    {
        $semua = array_values(array_unique(array_filter(array_merge($lama, $baru))));
        sort($semua, SORT_NATURAL | SORT_FLAG_CASE);

        return $semua;
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
                $hasil[$entri->merek][$entri->model] = [
                    'kursi' => null, 'jenis' => null, 'cc' => null, 'varian' => [],
                ];
            }
        }

        return $hasil;
    }

    /**
     * Katalog bawaan dari config.
     *
     * Dua bentuk diterima: angka kursi saja ('Avanza' => 7) atau rincian
     * ('Agya' => ['kursi' => 5, 'cc' => 1200, 'varian' => [...]]). Bentuk pendek
     * ada supaya config tetap terbaca — menuliskan rincian untuk 180 model
     * berarti mengarang cc dan tipe untuk sebagian besarnya.
     *
     * @return array<string, array<string, array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}>>
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

            foreach ((array) $model as $nama => $isi) {
                $nama = trim((string) $nama);

                if ($nama === '') {
                    continue;
                }

                $isi = is_array($isi) ? $isi : ['kursi' => $isi];

                $hasil[$merek][$nama] = [
                    'kursi' => isset($isi['kursi']) && is_numeric($isi['kursi']) ? (int) $isi['kursi'] : null,
                    'jenis' => null,
                    'cc' => isset($isi['cc']) && is_numeric($isi['cc']) ? (int) $isi['cc'] : null,
                    'varian' => array_values(array_filter(
                        array_map(fn ($v) => trim((string) $v), (array) ($isi['varian'] ?? [])),
                    )),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Merek, model, dan rinciannya yang sudah tercatat di armada.
     *
     * Unit nyata lebih berhak daripada angka rujukan: Avanza yang dipasangi 6
     * kursi tidak seharusnya terus menawarkan 7. Tipe yang dipakai unit ikut
     * masuk daftar pilihan tipe, jadi tipe yang pernah ditulis sekali tidak
     * perlu ditulis ulang.
     *
     * @return array<string, array<string, array{kursi: int|null, jenis: string|null, cc: int|null, varian: list<string>}>>
     */
    private static function dariArmada(): array
    {
        $hasil = [];

        Car::query()
            ->select('brand', 'name', 'varian', 'capacity', 'cc')
            ->distinct()
            ->get()
            ->each(function (Car $mobil) use (&$hasil) {
                $merek = trim((string) $mobil->brand);
                $nama = trim((string) $mobil->name);

                if ($merek === '' || $nama === '') {
                    return;
                }

                $kursi = (int) $mobil->capacity;
                $cc = (int) $mobil->cc;
                $varian = trim((string) $mobil->varian);

                $hasil[$merek] ??= [];
                $hasil[$merek][$nama] = [
                    'kursi' => $kursi > 0 ? $kursi : null,
                    'jenis' => null,
                    'cc' => $cc > 0 ? $cc : null,
                    'varian' => $varian !== '' ? [$varian] : [],
                ];
            });

        return $hasil;
    }
}
