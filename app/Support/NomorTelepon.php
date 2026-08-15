<?php

namespace App\Support;

/**
 * Perapi nomor telepon Indonesia.
 *
 * Pelanggan menuliskan nomornya bermacam-macam: "0812 3456 7890",
 * "+62 812-3456-7890", "62812345678900". Semua bentuk itu dirapikan jadi satu
 * bentuk baku yang enak dibaca dan enak ditelepon:
 *
 *     0812-3456-7890
 *
 * Yang disimpan tetap bentuk itu — bertanda hubung — supaya admin membaca
 * nomor yang sama dengan yang dilihat pelanggan. Untuk tautan WhatsApp,
 * tanda hubungnya dibuang lagi lewat wa().
 */
class NomorTelepon
{
    /** Ambil angkanya saja, dengan awalan diseragamkan jadi 0. */
    public static function angka(?string $nomor): string
    {
        $angka = preg_replace('/\D/', '', (string) $nomor);

        if ($angka === '') {
            return '';
        }

        // +62 / 62 → 0, dan 8xx yang lupa nolnya juga dilengkapi
        if (str_starts_with($angka, '62')) {
            $angka = '0'.substr($angka, 2);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '0'.$angka;
        }

        return $angka;
    }

    /**
     * Bentuk baku bertanda hubung: 0812-3456-7890.
     *
     * Dipenggal 4-4-sisanya karena itu cara orang Indonesia membaca nomor
     * ponsel; nomor yang belum lengkap tetap dirapikan sebisanya supaya
     * penggalannya tidak melompat-lompat saat diketik.
     */
    public static function rapi(?string $nomor): string
    {
        $angka = self::angka($nomor);

        if ($angka === '') {
            return '';
        }

        $bagian = array_filter([
            substr($angka, 0, 4),
            substr($angka, 4, 4),
            substr($angka, 8),
        ], fn ($potong) => $potong !== '' && $potong !== false);

        return implode('-', $bagian);
    }

    /** Bentuk untuk tautan WhatsApp: 6281234567890. */
    public static function wa(?string $nomor): string
    {
        $angka = self::angka($nomor);

        return $angka === '' ? '' : '62'.ltrim($angka, '0');
    }

    /** Nomor ponsel Indonesia yang masuk akal panjangnya. */
    public static function sah(?string $nomor): bool
    {
        $angka = self::angka($nomor);

        return preg_match('/^0\d{8,13}$/', $angka) === 1;
    }
}
