<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Surat pernyataan penggantian peserta, berbentuk PDF.
 *
 * SATU surat untuk satu pendaftaran, berapa pun penggantiannya. Pihak yang
 * menyatakan sama, pendaftaran yang dirujuk sama, kebijakan yang mendasarinya
 * sama — yang berbeda cuma barisnya, dan baris memang tempatnya di dalam tabel.
 * Surat per penggantian membuat pemesan menandatangani dua berkas bermaterai
 * untuk satu pemesanan.
 *
 * Sempat DOCX supaya bisa disunting; alasan itu gugur begitu penggantian
 * dicatat lewat tombol di admin — ejaannya sudah dikunci sistem, dan yang
 * sampai ke pemesan justru harus persis yang tercatat.
 *
 * Rupanya sengaja disamakan dengan kwitansi — pita navy, garis emas, pita kaki
 * bernomor — supaya berkas yang sampai ke pemesan terlihat berasal dari satu
 * tempat.
 */
class SuratPenggantian
{
    /**
     * @param  array<int, array<string, mixed>>  $riwayat  isi riwayat_penggantian, terlama dulu
     */
    public static function buat(PendaftaranOpenTrip $pendaftaran, array $riwayat): string
    {
        return Pdf::loadView('pdf.surat-penggantian', compact('pendaftaran', 'riwayat'))
            ->setPaper('a4')
            ->output();
    }

    /** Nama berkas yang enak dibaca di kotak masuk. */
    public static function namaBerkas(string $kode): string
    {
        return 'SURAT-PENGGANTIAN-PESERTA-'.str($kode)->slug()->upper().'.pdf';
    }
}
