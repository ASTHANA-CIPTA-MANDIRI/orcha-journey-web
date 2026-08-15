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

    /**
     * Simpan sebagai WebP dan kembalikan jalur publiknya (/storage/...).
     *
     * Bila WebP tidak didukung server atau berkasnya gagal dibaca, gambar
     * disimpan apa adanya — lebih baik gambar aslinya masuk daripada admin
     * kehilangan unggahannya.
     */
    public static function simpan(UploadedFile $berkas, string $folder): string
    {
        $nama = Str::uuid().'.webp';
        $tujuan = trim($folder, '/').'/'.$nama;

        $gambar = self::baca($berkas);

        if (! $gambar) {
            return '/storage/'.$berkas->store($folder, 'public');
        }

        $gambar = self::kecilkan($gambar);

        ob_start();
        $berhasil = imagewebp($gambar, null, self::MUTU);
        $isi = ob_get_clean();
        imagedestroy($gambar);

        if (! $berhasil || $isi === '' || $isi === false) {
            return '/storage/'.$berkas->store($folder, 'public');
        }

        Storage::disk('public')->put($tujuan, $isi);

        return '/storage/'.$tujuan;
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

        return $gambar;
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
