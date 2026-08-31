<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Berkas yang tidak boleh bisa dibuka siapa pun yang memegang alamatnya.
 *
 * Bukti transfer dan berkas jaminan sewa selama ini disimpan di disk `public`,
 * yang di-symlink ke public/storage — jadi keduanya dapat diambil lewat URL
 * tanpa login sama sekali. Isinya bukan hal sepele: tangkapan layar mutasi bank
 * berikut nama pemilik rekening, nama bank, nominal, dan sebagian nomor
 * rekening.
 *
 * Satu-satunya pengaman adalah nama berkasnya yang berupa UUID. Itu memang
 * tidak bisa ditebak — tetapi juga tidak pernah kedaluwarsa: sekali alamatnya
 * bocor (diteruskan di grup, terbawa header referrer, tertangkap perayap), ia
 * terbuka selamanya. robots.txt pun tidak menutup /storage.
 *
 * PENTING — kenapa bentuk jalurnya tidak diubah:
 *
 * Panel admin (lemon) menggambar apa pun yang dikirim API ini. Selama API
 * mengembalikan alamat yang bisa dibuka peramban, lemon bekerja apa adanya
 * tanpa satu baris pun berubah di sana. Karena itu yang diganti bukan bentuk
 * datanya, melainkan alamat yang dikirimkan: URL bertanda tangan yang
 * kedaluwarsa sendiri.
 */
class BerkasRahasia
{
    /**
     * Folder yang isinya tidak boleh terbuka bebas.
     *
     * Sengaja daftar putih, bukan daftar hitam: folder baru harus dipikirkan
     * dulu sebelum dinyatakan rahasia — sedangkan daftar hitam membuat folder
     * sensitif yang lupa didaftarkan diam-diam jadi publik.
     */
    public const FOLDER = ['bukti-bayar', 'jaminan'];

    /**
     * Berapa lama satu alamat berlaku.
     *
     * Cukup panjang untuk satu sesi kerja admin — ia membuka daftar pembayaran
     * pagi hari dan masih memeriksanya sore. Cukup pendek supaya alamat yang
     * bocor lewat riwayat peramban atau tangkapan layar tidak berguna lagi
     * keesokan harinya.
     */
    public const JAM_BERLAKU = 12;

    /** Apakah jalur ini menunjuk berkas yang harus dijaga? */
    public static function rahasia(?string $jalur): bool
    {
        if (blank($jalur)) {
            return false;
        }

        foreach (self::FOLDER as $folder) {
            if (str_contains($jalur, '/'.$folder.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alamat yang boleh dikirim ke panel admin.
     *
     * Yang bukan berkas rahasia dikembalikan apa adanya — sebagian besar
     * gambar di sistem ini (foto destinasi, sampul artikel) memang untuk
     * dilihat umum, dan menandatanganinya hanya membuat gambar beranda
     * kedaluwarsa tiap dua belas jam.
     */
    public static function tautan(?string $jalur): ?string
    {
        if (! self::rahasia($jalur)) {
            return $jalur;
        }

        return URL::temporarySignedRoute(
            'berkas.rahasia',
            now()->addHours(self::JAM_BERLAKU),
            ['jalur' => self::relatif($jalur)],
        );
    }

    /**
     * "/storage/bukti-bayar/x.webp" -> "bukti-bayar/x.webp"
     *
     * Bentuk lamanya dipertahankan di basis data. Baris yang sudah tersimpan
     * berjumlah ribuan dan tersebar di beberapa tabel; menulis ulang semuanya
     * demi kerapian menambah satu langkah migrasi yang bisa gagal separuh,
     * padahal cukup ditafsirkan saat dibaca.
     */
    public static function relatif(string $jalur): string
    {
        return ltrim(preg_replace('#^/?storage/#', '', $jalur), '/');
    }
}
