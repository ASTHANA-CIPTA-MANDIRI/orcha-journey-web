<?php

namespace App\Support;

/**
 * Bagian acak pada kode pesanan.
 *
 * Sebelumnya `Str::upper(Str::random(4))`. Str::random mengambil dari 62
 * karakter (a–z, A–Z, 0–9), tetapi Str::upper melipat a–z ke A–Z — sehingga
 * yang tersisa 36 kemungkinan per huruf, dan tidak merata: tiap huruf dua kali
 * lebih sering muncul daripada tiap angka. Empat karakter seperti itu kira-kira
 * setara 20 bit, dan bagian tanggal-bulan di depannya bisa ditebak dari kapan
 * tripnya berlangsung.
 *
 * Sekarang enam karakter yang diambil langsung dari abjadnya, tanpa dilipat.
 *
 * Huruf yang mudah tertukar saat DIBACAKAN lewat telepon dibuang: O dan 0, I
 * dan 1, serta huruf S yang sering terdengar seperti angka 5. Kode ini memang
 * dibacakan — pelanggan menyebutkannya lewat WhatsApp dan telepon — jadi
 * abjad yang lebih pendek tetapi tidak pernah salah dengar lebih berguna
 * daripada abjad penuh yang menghasilkan pengetikan ulang.
 *
 * Sisa 30 karakter pangkat 6 ≈ 729 juta kemungkinan, sekitar 29,4 bit: 430 kali
 * lebih banyak daripada sebelumnya, sementara panjang kodenya hanya bertambah
 * dua huruf.
 *
 * KODE LAMA TETAP BERLAKU. Yang berubah cuma pembangkit kode baru; pesanan yang
 * sudah beredar tidak boleh berganti kode, sebab kodenya sudah ada di email,
 * tanda terima, dan percakapan WhatsApp pelanggan.
 */
class KodePesanan
{
    /**
     * Tanpa O, I, S, 0, 1, dan 5 — pasangan yang paling sering tertukar saat
     * kode dibacakan atau disalin dari tangkapan layar.
     */
    private const ABJAD = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public static function acak(int $panjang = 6): string
    {
        $abjad = self::ABJAD;
        $batas = strlen($abjad) - 1;
        $hasil = '';

        for ($i = 0; $i < $panjang; $i++) {
            // random_int, bukan rand(): yang ini memakai sumber acak sistem,
            // sehingga urutannya tidak bisa diramalkan dari kode yang sudah
            // terbit sebelumnya.
            $hasil .= $abjad[random_int(0, $batas)];
        }

        return $hasil;
    }

    /** Kode utuh, mis. OT-3108-K7QMXV. */
    public static function untuk(string $awalan): string
    {
        return $awalan.'-'.now()->format('dm').'-'.self::acak();
    }
}
