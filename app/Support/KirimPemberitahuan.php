<?php

namespace App\Support;

use App\Mail\PemberitahuanFormulir;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pengirim pemberitahuan formulir.
 *
 * Aturan terpenting di sini: kegagalan mengirim surat TIDAK BOLEH membatalkan
 * apa yang sudah dikerjakan pelanggan. Data pendaftaran atau bukti transfer
 * sudah tersimpan sebelum ini dipanggil; kalau server surat sedang mati,
 * kejadiannya cukup dicatat di log — bukan dilempar ke layar pelanggan yang
 * mengira pendaftarannya gagal lalu mengirim ulang.
 */
class KirimPemberitahuan
{
    /**
     * @param  array<string, string|null>  $rincian
     * @param  array<int, string>  $lampiran
     * @param  array<string, string>  $berkasPdf
     */
    public static function kirim(
        string $judul,
        string $kode,
        array $rincian,
        ?string $catatan = null,
        array $lampiran = [],
        array $berkasPdf = [],
    ): bool {
        $tujuan = config('orcha.email_pemberitahuan');

        // Alamat kosong = pemberitahuan memang dimatikan (mis. saat pengujian).
        if (blank($tujuan)) {
            return false;
        }

        try {
            Mail::to($tujuan)->send(
                new PemberitahuanFormulir($judul, $kode, $rincian, $catatan, $lampiran, $berkasPdf)
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Pemberitahuan formulir gagal dikirim', [
                'judul' => $judul,
                'kode' => $kode,
                'galat' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
