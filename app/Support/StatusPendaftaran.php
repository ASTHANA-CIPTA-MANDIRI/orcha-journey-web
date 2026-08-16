<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Support\Facades\Log;

/**
 * Menyelaraskan status pendaftaran dengan uang yang benar-benar masuk.
 *
 * Sebelumnya admin mengubah dua tempat untuk satu kejadian: menyetujui bukti
 * transfer, lalu mengingat untuk mengubah status pendaftarannya. Langkah kedua
 * itu yang paling sering terlewat, dan akibatnya daftar pendaftaran menunjukkan
 * "Baru" untuk peserta yang uangnya sudah diterima seminggu lalu.
 *
 * Yang menentukan status di sini hanya uang, bukan tombol:
 *
 *   belum ada yang masuk  → status dibiarkan apa adanya (admin yang pegang)
 *   sebagian sudah masuk  → DP Masuk
 *   sudah penuh           → Lunas
 *
 * "Batal" tidak pernah disentuh. Pembatalan adalah keputusan manusia, dan uang
 * yang masuk sesudahnya tidak boleh diam-diam menghidupkan lagi pesanan yang
 * sudah dinyatakan batal.
 */
class StatusPendaftaran
{
    /** @return string|null status baru bila berubah, null bila tidak */
    public static function selaraskan(?PendaftaranOpenTrip $pendaftaran): ?string
    {
        if (! $pendaftaran || $pendaftaran->status === 'batal') {
            return null;
        }

        $tagihan = TagihanPesanan::untuk($pendaftaran);

        // Paket yang harganya belum diisi tidak bisa dihitung lunasnya.
        if ($tagihan === []) {
            return null;
        }

        $baru = match (true) {
            $tagihan['lunas'] => 'lunas',
            $tagihan['sudah'] > 0 => 'dp_masuk',
            default => null,
        };

        if ($baru === null || $baru === $pendaftaran->status) {
            return null;
        }

        $sebelum = $pendaftaran->status;
        $pendaftaran->update(['status' => $baru]);

        Log::info('Status pendaftaran diselaraskan dengan pembayaran', [
            'kode' => $pendaftaran->kode,
            'dari' => $sebelum,
            'ke' => $baru,
            'sudah_masuk' => $tagihan['sudah'],
            'total' => $tagihan['total'],
        ]);

        return $baru;
    }
}
