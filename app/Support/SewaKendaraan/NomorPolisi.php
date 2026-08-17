<?php

namespace App\Support\SewaKendaraan;

/**
 * Merapikan nomor polisi ke satu bentuk baku.
 *
 * Nomor polisi diketik dengan bermacam cara — "ab4169te", "AB-4169-TE",
 * "ab 4169  te" — dan semuanya nomor yang sama. Kalau disimpan apa adanya,
 * mencari unit berdasarkan nopol menjadi tidak dapat diandalkan, dan satu unit
 * bisa tercatat dua kali dengan ejaan berbeda.
 *
 * Bentuk bakunya: huruf wilayah, nomor, huruf seri, dipisah satu spasi dan
 * seluruhnya kapital — "AB 4169 TE".
 */
class NomorPolisi
{
    /**
     * Pola nomor polisi Indonesia sesudah tanda pisahnya dibuang.
     *
     * Sengaja permisif: 1-2 huruf wilayah, 1-5 angka, 0-3 huruf seri. Plat satu
     * huruf ("B 1234 XYZ") dan plat tanpa huruf seri ("AB 1234") sama-sama sah,
     * dan pola yang terlalu ketat akan menolak nomor yang benar-benar ada.
     */
    private const POLA = '/^([A-Z]{1,2})(\d{1,5})([A-Z]{0,3})$/';

    public static function rapikan(?string $nopol): ?string
    {
        $telanjang = self::telanjangi($nopol);

        if ($telanjang === '') {
            return null;
        }

        if (preg_match(self::POLA, $telanjang, $bagian)) {
            return trim($bagian[1].' '.$bagian[2].' '.$bagian[3]);
        }

        // Bentuk yang tidak dikenali TIDAK dibuang. Nomor khusus di luar dugaan
        // pola ini tetap ada, dan membuang isinya berarti kehilangan data yang
        // benar hanya karena bentuknya tidak terduga. Yang dilakukan di sini
        // sebatas dikapitalkan dan spasinya dirapikan.
        return trim(preg_replace('/\s+/', ' ', mb_strtoupper((string) $nopol)));
    }

    /**
     * Apakah nomornya berbentuk nomor polisi yang wajar.
     *
     * Kosong dianggap sah: nopol boleh belum diisi, misalnya unit yang platnya
     * masih dalam proses.
     */
    public static function sah(?string $nopol): bool
    {
        $telanjang = self::telanjangi($nopol);

        return $telanjang === '' || (bool) preg_match(self::POLA, $telanjang);
    }

    private static function telanjangi(?string $nopol): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $nopol));
    }
}
