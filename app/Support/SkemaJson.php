<?php

namespace App\Support;

/**
 * Menyandi data terstruktur (JSON-LD) untuk ditaruh di dalam <script>.
 *
 * ADA KARENA PERNAH BOCOR. Kelima blok ld+json di situs ini menyandi dengan
 * JSON_UNESCAPED_SLASHES, dan bendera itu mematikan satu-satunya hal yang
 * menahan teks keluar dari blok skripnya: garis miring yang biasanya disandi
 * jadi "\/". Tanpa itu, judul artikel berisi "</script><script>…</script>"
 * menutup blok JSON-LD dan sisanya dijalankan peramban sebagai skrip.
 *
 * Terbukti, bukan diduga: judul artikel uji berisi alert(document.cookie)
 * benar-benar lepas dan berjalan di halaman blog.
 *
 * Yang mengisi nama paket, nama destinasi, dan judul artikel adalah admin —
 * jadi ini bukan jalan masuk bagi orang asing. Tetapi satu akun staf yang
 * jatuh, atau satu orang dalam yang berniat buruk, cukup untuk menjalankan
 * skrip di peramban SETIAP pengunjung halaman itu. Dan yang dicuri di halaman
 * pembayaran bukan sekadar cookie.
 *
 * JSON_HEX_TAG menyandi "<" dan ">" jadi < dan >. Hasilnya tetap
 * JSON-LD yang sah dan tetap terbaca Google; yang hilang cuma kemampuannya
 * menutup tag.
 *
 * JANGAN dipakai untuk JSON yang dikirim ke luar. Badan permintaan DOKU
 * ditandatangani atas rangkaian byte-nya yang persis, dan mengubah
 * penyandiannya membuat tanda tangannya tidak lagi cocok.
 */
class SkemaJson
{
    /** @param  array<string, mixed>  $data */
    public static function tag(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        );
    }
}
