<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Pembuat kwitansi/tanda terima berbentuk PDF.
 *
 * Dipakai lampiran surat pemberitahuan. Sama seperti pengiriman suratnya,
 * kegagalan membuat PDF tidak boleh membatalkan apa pun: pelanggan sudah
 * selesai mengisi formulir, dan berkas ini hanya pelengkap.
 */
class BerkasKwitansi
{
    /**
     * @param  array<string, string|null>  $rincian
     * @param  array<string, mixed>  $biaya  hasil RincianBiaya::untuk(); kosong = tanpa tabel biaya
     * @param  array<string, mixed>  $tagihan  hasil TagihanPesanan::untuk(); kosong = tanpa blok posisi tagihan
     * @return string|null isi berkas PDF, atau null bila gagal dibuat
     */
    public static function buat(
        string $judul,
        string $kode,
        array $rincian,
        ?string $catatan = null,
        ?string $jumlah = null,
        ?string $jumlahLabel = null,
        ?string $capStatus = null,
        array $biaya = [],
        array $tagihan = [],
    ): ?string {
        try {
            return Pdf::loadView('pdf.kwitansi', compact(
                'judul', 'kode', 'rincian', 'catatan', 'jumlah', 'jumlahLabel', 'capStatus', 'biaya', 'tagihan'
            ))->setPaper('a4')->output();
        } catch (\Throwable $e) {
            Log::error('Kwitansi PDF gagal dibuat', [
                'kode' => $kode,
                'galat' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Nama berkas yang enak dibaca di kotak masuk. */
    public static function namaBerkas(string $awalan, string $kode): string
    {
        return str($awalan.'-'.$kode)->slug()->upper()->toString().'.pdf';
    }
}
