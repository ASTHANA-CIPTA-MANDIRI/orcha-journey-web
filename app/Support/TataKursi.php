<?php

namespace App\Support;

/**
 * Menerjemahkan "bus 44 kursi, tata 2-2" jadi petak kursi yang bisa digambar.
 *
 * Nomor kursi saja tidak cukup untuk membagi tempat duduk. Admin yang
 * memasangkan orang dengan kursi perlu melihat MANA yang di jendela, mana yang
 * berdampingan, dan di mana lorongnya — karena itulah isi permintaan yang
 * sebenarnya datang ("ibu saya minta dekat jendela", "kami tiga orang, jangan
 * dipisah"). Daftar nomor 1..44 tidak bisa menjawab satu pun dari itu.
 *
 * Yang dikembalikan adalah baris-baris berisi sel. Sel bernilai null adalah
 * LORONG, bukan kursi kosong — dua hal yang tidak boleh terbaca sama, karena
 * yang satu bisa diisi orang dan yang lain tidak.
 */
class TataKursi
{
    /**
     * Tata letak yang dikenal, beserta jumlah kursi per baris.
     *
     * Disebut lewat nama kendaraannya, bukan angka telanjang: admin tahu
     * armadanya "bus besar" atau "hiace", dan tidak harus tahu bahwa yang
     * pertama berarti 2-3.
     */
    public const TATA = [
        '2-2' => ['Bus sedang / big bus 2-2', 2, 2],
        '2-3' => ['Bus besar 2-3', 2, 3],
        '2-1' => ['Hiace / elf 2-1', 2, 1],
        '1-1' => ['Minibus 1-1', 1, 1],
    ];

    /**
     * Petak kursi satu kendaraan.
     *
     * Baris terakhir sengaja dibiarkan jadi BANGKU BELAKANG selebar lorong
     * bila jumlah kursinya tidak habis dibagi lebarnya — itu memang bentuk bus
     * sungguhan, dan memaksanya rapi akan menghasilkan petak yang kursinya
     * tidak sejumlah yang dibayar.
     *
     * @return array{
     *     nama: string,
     *     tata: string,
     *     lebar: int,
     *     jumlah_kursi: int,
     *     baris: array<int, array<int, ?string>>,
     *     kursi: array<int, string>
     * }
     */
    public static function petak(string $nama, string $tata, int $jumlahKursi): array
    {
        [, $kiri, $kanan] = self::TATA[$tata] ?? self::TATA['2-2'];

        $jumlahKursi = max(0, min(80, $jumlahKursi));
        $lebar = $kiri + 1 + $kanan;

        $baris = [];
        $kursi = [];
        $nomor = 1;

        // Baris penuh dulu. Yang tersisa — kurang dari satu baris — jadi bangku
        // belakang, karena itulah tempatnya di kendaraan sungguhan.
        $perBaris = $kiri + $kanan;
        $barisPenuh = $perBaris === 0 ? 0 : intdiv($jumlahKursi, $perBaris);
        $sisa = $jumlahKursi - ($barisPenuh * $perBaris);

        for ($b = 0; $b < $barisPenuh; $b++) {
            $sel = [];

            for ($i = 0; $i < $kiri; $i++) {
                $sel[] = (string) $nomor;
                $kursi[] = (string) $nomor;
                $nomor++;
            }

            $sel[] = null;

            for ($i = 0; $i < $kanan; $i++) {
                $sel[] = (string) $nomor;
                $kursi[] = (string) $nomor;
                $nomor++;
            }

            $baris[] = $sel;
        }

        if ($sisa > 0) {
            $sel = [];

            for ($i = 0; $i < $sisa; $i++) {
                $sel[] = (string) $nomor;
                $kursi[] = (string) $nomor;
                $nomor++;
            }

            // Bangku belakang tidak punya lorong; lebarnya disamakan dengan
            // baris lain supaya grid-nya tidak bergeser saat digambar.
            while (count($sel) < $lebar) {
                $sel[] = null;
            }

            $baris[] = $sel;
        }

        return [
            'nama' => $nama,
            'tata' => array_key_exists($tata, self::TATA) ? $tata : '2-2',
            'lebar' => $lebar,
            'jumlah_kursi' => $jumlahKursi,
            'baris' => $baris,
            'kursi' => $kursi,
        ];
    }

    /**
     * Petak untuk seluruh kendaraan satu rombongan.
     *
     * @param  array<int, array<string, mixed>>  $kendaraan
     * @return array<int, array<string, mixed>>
     */
    public static function semua(?array $kendaraan): array
    {
        return collect($kendaraan ?? [])
            ->map(fn ($satu) => self::petak(
                trim((string) ($satu['nama'] ?? '')) ?: 'Kendaraan',
                (string) ($satu['tata'] ?? '2-2'),
                (int) ($satu['jumlah_kursi'] ?? 0),
            ))
            ->filter(fn ($satu) => $satu['jumlah_kursi'] > 0)
            ->values()
            ->all();
    }

    /** Pilihan tata letak untuk pemilih di layar admin. */
    public static function pilihan(): array
    {
        return collect(self::TATA)->map(fn ($satu) => $satu[0])->all();
    }
}
