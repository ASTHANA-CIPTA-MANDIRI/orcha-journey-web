<?php

namespace App\Support\Blog;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyelamatkan gambar yang ditempel ke dalam isi artikel.
 *
 * Penyunting teks menyisipkan gambar yang di-paste sebagai data URI base64 —
 * seluruh isi berkasnya ikut tertulis di dalam HTML. Kalau dibiarkan, satu foto
 * kamera bisa menambah 3–8 MB pada kolom isi, dan gumpalan itu:
 *
 *   - dikirim ULANG ke setiap pembaca pada setiap kali halaman dibuka;
 *   - tidak bisa disimpan cache peramban, karena ia bagian dari HTML;
 *   - ikut terbawa setiap kali daftar artikel diambil admin;
 *   - membuat pencarian di kolom isi merangkak.
 *
 * Tidak ada satu pun galat yang muncul saat itu terjadi. Artikelnya tampil
 * benar, dan yang membengkak hanya angka-angka yang tidak dilihat siapa pun
 * sampai halamannya terasa lambat.
 *
 * Berkasnya disimpan sebagai WebP, sama seperti seluruh unggahan lain di Orcha.
 */
class GambarIsiArtikel
{
    /** Sisi terpanjang untuk gambar di dalam artikel. */
    private const SISI_MAKS = 1600;

    private const MUTU = 82;

    private const FOLDER = 'artikel/isi';

    /**
     * Ganti setiap gambar base64 di dalam HTML dengan berkas WebP.
     *
     * HTML dikembalikan apa adanya bila tidak ada base64 sama sekali — jalur
     * yang paling sering terjadi, dan tidak perlu membayar apa pun.
     */
    public static function proses(string $html): string
    {
        if (stripos($html, 'base64,') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=("|\')(data:image\/(?:png|jpe?g|webp|gif);base64,[^"\']+)\1[^>]*>/i',
            function (array $cocok) {
                $jalur = self::simpan($cocok[2]);

                // Gagal menyimpan? Tag-nya dibiarkan utuh. Artikel yang
                // kehilangan gambarnya diam-diam jauh lebih buruk daripada
                // artikel yang gambarnya masih base64.
                if ($jalur === null) {
                    return $cocok[0];
                }

                $tag = str_replace($cocok[2], $jalur, $cocok[0]);

                // Gambar di tengah artikel hampir selalu di bawah layar
                // pertama, jadi selalu ditandai lazy — sama seperti gambar
                // lain di halaman publik.
                if (stripos($tag, 'loading=') === false) {
                    $tag = preg_replace('/<img\b/i', '<img loading="lazy" decoding="async"', $tag, 1);
                }

                return $tag;
            },
            $html
        ) ?? $html;
    }

    /** @return string|null jalur publik (/storage/...), null bila gagal */
    private static function simpan(string $dataUri): ?string
    {
        if (! preg_match('/^data:image\/(?:png|jpe?g|webp|gif);base64,(.+)$/i', $dataUri, $cocok)) {
            return null;
        }

        $mentah = base64_decode($cocok[1], true);

        if ($mentah === false || $mentah === '') {
            return null;
        }

        if (! function_exists('imagewebp')) {
            Log::warning('GD tanpa dukungan WebP; gambar isi artikel dibiarkan base64.');

            return null;
        }

        $gambar = @imagecreatefromstring($mentah);

        if (! $gambar) {
            return null;
        }

        $gambar = self::kecilkan($gambar);

        ob_start();
        $berhasil = imagewebp($gambar, null, self::MUTU);
        $isi = ob_get_clean();
        imagedestroy($gambar);

        if (! $berhasil || $isi === '' || $isi === false) {
            return null;
        }

        $tujuan = self::FOLDER.'/'.Str::uuid().'.webp';
        Storage::disk('public')->put($tujuan, $isi);

        return '/storage/'.$tujuan;
    }

    /**
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private static function kecilkan($gambar)
    {
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $terpanjang = max($lebar, $tinggi);

        if ($terpanjang <= self::SISI_MAKS) {
            return $gambar;
        }

        $rasio = self::SISI_MAKS / $terpanjang;
        $lebarBaru = max(1, (int) round($lebar * $rasio));
        $tinggiBaru = max(1, (int) round($tinggi * $rasio));

        $kecil = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagealphablending($kecil, false);
        imagesavealpha($kecil, true);
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        return $kecil;
    }
}
