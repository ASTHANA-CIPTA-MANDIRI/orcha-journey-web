<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Support\Facades\Log;

/**
 * Menyelaraskan status pesanan dengan uang yang benar-benar diterima.
 *
 * Sebelumnya admin mengubah dua tempat untuk satu kejadian: menyetujui bukti
 * transfer, lalu mengingat untuk mengubah status pesanannya. Langkah kedua itu
 * yang paling sering terlewat, dan akibatnya daftar menunjukkan "Baru" untuk
 * peserta yang uangnya sudah diterima seminggu lalu.
 *
 * Yang dihitung hanya bukti yang sudah DITERIMA, bukan yang masih menunggu
 * dicek. Status "DP Masuk" berarti uangnya sudah ada, bukan sudah diklaim —
 * kalau tidak, siapa pun bisa memajukan status pesanannya sendiri hanya dengan
 * mengunggah gambar.
 *
 * Status yang sudah lebih jauh tidak pernah ditarik mundur, dan "batal" tidak
 * pernah disentuh: pembatalan adalah keputusan manusia, dan uang yang masuk
 * sesudahnya tidak boleh diam-diam menghidupkan lagi pesanan yang sudah
 * dinyatakan batal.
 */
class StatusPendaftaran
{
    /** Status yang berarti pesanannya sudah lewat tahap pembayaran awal. */
    private const SUDAH_LEBIH_JAUH = ['berjalan', 'selesai', 'batal', 'lunas'];

    /** @return string|null status baru bila berubah, null bila tidak */
    public static function selaraskan(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): ?string
    {
        if (! $pesanan || $pesanan->status === 'batal') {
            return null;
        }

        $tagihan = TagihanPesanan::untuk($pesanan, hanyaDiterima: true);

        // Pesanan yang harganya belum ada tidak bisa dihitung lunasnya.
        if ($tagihan === [] || $tagihan['sudah'] <= 0) {
            return null;
        }

        $baru = $pesanan instanceof PendaftaranOpenTrip
            ? ($tagihan['lunas'] ? 'lunas' : 'dp_masuk')
            // Sewa kendaraan tidak mengenal status "lunas": sesudah dibayar,
            // yang berikutnya terjadi adalah unitnya diserahkan.
            : 'dp_masuk';

        if ($baru === $pesanan->status) {
            return null;
        }

        // Jangan menarik mundur pesanan yang sudah berjalan atau selesai.
        if (in_array($pesanan->status, self::SUDAH_LEBIH_JAUH, true) && $baru === 'dp_masuk') {
            return null;
        }

        $sebelum = $pesanan->status;
        $pesanan->update(['status' => $baru]);

        Log::info('Status pesanan diselaraskan dengan pembayaran', [
            'kode' => $pesanan->kode,
            'dari' => $sebelum,
            'ke' => $baru,
            'diterima' => $tagihan['sudah'],
            'total' => $tagihan['total'],
        ]);

        return $baru;
    }
}
