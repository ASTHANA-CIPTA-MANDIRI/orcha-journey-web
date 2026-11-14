<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ubah gambar unggahan jadi WebP.
 *
 * Admin tetap boleh mengunggah JPG atau PNG — yang tersimpan selalu WebP,
 * biasanya sepertiga sampai separuh ukuran aslinya. Halaman publik memuat
 * banyak gambar sekaligus (kartu paket, armada, destinasi), jadi selisih itu
 * langsung terasa.
 *
 * Memakai GD bawaan PHP, tanpa paket tambahan: hosting yang dipakai membatasi
 * pemasangan ekstensi, dan GD dengan dukungan WebP sudah tersedia di sana.
 */
class GambarWebp
{
    /** Sisi terpanjang. Lebih dari ini mubazir untuk tampilan web. */
    private const SISI_MAKS = 1920;

    private const MUTU = 82;

    /** Cukup untuk foto kamera ponsel kelas atas (±50 MP) di GD. */
    private const MEMORI_MINIMUM = 512 * 1024 * 1024;

    /**
     * Simpan sebagai WebP dan kembalikan jalur publiknya (/storage/...).
     *
     * Bila WebP tidak didukung server atau berkasnya gagal dibaca, gambar
     * disimpan apa adanya — lebih baik gambar aslinya masuk daripada admin
     * kehilangan unggahannya.
     */
    public static function simpan(UploadedFile $berkas, string $folder): string
    {
        self::sediakanMemori();

        $nama = Str::uuid().'.webp';
        $tujuan = trim($folder, '/').'/'.$nama;

        /*
         | Folder rahasia (bukti transfer, jaminan sewa) tidak pernah masuk disk
         | publik. Jalur yang DIKEMBALIKAN tetap berbentuk "/storage/..." supaya
         | seluruh basis data dan pemanggil lama tidak perlu ikut berubah —
         | yang menafsirkannya BerkasRahasia saat jalurnya hendak dikirim ke
         | panel admin.
         */
        $disk = BerkasRahasia::rahasia('/'.$tujuan) ? 'rahasia' : 'public';

        $gambar = self::baca($berkas);

        if (! $gambar) {
            return '/storage/'.$berkas->store($folder, $disk);
        }

        $gambar = self::kecilkan($gambar);

        ob_start();
        $berhasil = imagewebp($gambar, null, self::MUTU);
        $isi = ob_get_clean();
        imagedestroy($gambar);

        if (! $berhasil || $isi === '' || $isi === false) {
            return '/storage/'.$berkas->store($folder, $disk);
        }

        Storage::disk($disk)->put($tujuan, $isi);

        return '/storage/'.$tujuan;
    }

    /**
     * Menaikkan batas memori bila yang berlaku terlalu kecil untuk GD.
     *
     * GD membongkar gambar jadi piksel mentah: satu foto 50 MP memakan sekitar
     * 200 MB, berapa pun kecilnya berkas JPG-nya. Selama unggahan dibatasi
     * 4 MB hal ini tidak pernah terjadi; sejak batas itu dicabut — karena
     * toh semuanya dikecilkan jadi WebP 1920px di sini — ia jadi mungkin, dan
     * kehabisan memori di GD bukan galat yang bisa ditangkap: prosesnya mati,
     * dan yang dilihat admin hanya "server tidak bisa dihubungi".
     *
     * HANYA menaikkan, tidak pernah menurunkan: server yang sudah diberi
     * batas lebih longgar oleh pengelolanya tidak ikut dikecilkan.
     */
    private static function sediakanMemori(): void
    {
        $kini = self::bita((string) ini_get('memory_limit'));

        // -1 berarti tak terbatas; tidak ada yang perlu ditambah.
        if ($kini < 0) {
            return;
        }

        if ($kini < self::MEMORI_MINIMUM) {
            @ini_set('memory_limit', self::MEMORI_MINIMUM);
        }
    }

    /** Mengubah "256M" / "1G" jadi bita. */
    private static function bita(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '') {
            return 0;
        }

        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024 * 1024 * 1024,
            'm' => $angka * 1024 * 1024,
            'k' => $angka * 1024,
            default => $angka,
        };
    }

    /**
     * @return \GdImage|false
     */
    private static function baca(UploadedFile $berkas)
    {
        if (! function_exists('imagewebp')) {
            Log::warning('GD tanpa dukungan WebP; gambar disimpan apa adanya.');

            return false;
        }

        $isi = @file_get_contents($berkas->getRealPath());

        if ($isi === false) {
            return false;
        }

        $gambar = @imagecreatefromstring($isi);

        if (! $gambar) {
            return false;
        }

        // PNG transparan tetap transparan setelah jadi WebP.
        imagepalettetotruecolor($gambar);
        imagealphablending($gambar, false);
        imagesavealpha($gambar, true);

        return self::tegakkan($gambar, $berkas);
    }

    /**
     * Memutar gambar mengikuti tag EXIF Orientation kamera.
     *
     * Kamera ponsel jarang memutar pikselnya. Ia menyimpan foto tegak sebagai
     * piksel MENDATAR, lalu menitipkan catatan "putar 90 derajat" di tag EXIF
     * Orientation. Peramban menghormati catatan itu, jadi JPEG aslinya tampak
     * tegak — tetapi imagecreatefromstring() mengabaikannya, dan WebP hasil
     * GD tidak membawa EXIF sama sekali. Hasilnya: catatannya hilang, dan
     * pikselnya tidak pernah diputar, sehingga foto tersimpan MIRING
     * selamanya tanpa satu pun galat.
     *
     * Terbukti 14 Nov 2026: foto pertama yang diunggah admin tersimpan
     * 1920x1080 mendatar padahal isinya foto tegak.
     *
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private static function tegakkan($gambar, UploadedFile $berkas)
    {
        $arah = self::arahExif($berkas);

        if ($arah <= 1) {
            return $gambar;
        }

        // imagerotate memutar BERLAWANAN arah jarum jam dan memulangkan
        // gambar BARU; yang lama harus dilepas sendiri supaya memorinya tidak
        // menumpuk saat admin mengunggah dua puluh foto sekaligus.
        $putar = function ($lama, int $derajat) {
            $baru = imagerotate($lama, $derajat, 0);

            if (! $baru) {
                return $lama;
            }

            imagedestroy($lama);
            imagealphablending($baru, false);
            imagesavealpha($baru, true);

            return $baru;
        };

        switch ($arah) {
            case 2:
                imageflip($gambar, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $gambar = $putar($gambar, 180);
                break;
            case 4:
                imageflip($gambar, IMG_FLIP_VERTICAL);
                break;
            case 5:
                imageflip($gambar, IMG_FLIP_VERTICAL);
                $gambar = $putar($gambar, -90);
                break;
            case 6:
                $gambar = $putar($gambar, -90);
                break;
            case 7:
                imageflip($gambar, IMG_FLIP_HORIZONTAL);
                $gambar = $putar($gambar, -90);
                break;
            case 8:
                $gambar = $putar($gambar, 90);
                break;
        }

        return $gambar;
    }

    /**
     * Membaca tag EXIF Orientation; 1 (atau 0) berarti tidak perlu diputar.
     *
     * Dibungkus rapat-rapat: exif_read_data memperingatkan — dan di sebagian
     * server melempar — untuk berkas yang bukan JPEG/TIFF, padahal PNG dan
     * WebP memang tidak punya EXIF dan itu bukan keadaan salah.
     */
    private static function arahExif(UploadedFile $berkas): int
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return 1;
        }

        $jalur = $berkas->getRealPath();

        if (! $jalur || ! is_readable($jalur)) {
            return 1;
        }

        try {
            $exif = @exif_read_data($jalur);
        } catch (\Throwable) {
            return 1;
        }

        return (int) ($exif['Orientation'] ?? 1);
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
