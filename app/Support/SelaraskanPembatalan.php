<?php

namespace App\Support;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;

/**
 * Menjalarkan keputusan pembatalan ke pesanan dan bukti pembayarannya.
 *
 * Sebelum ini admin harus mengubah tiga tempat sendiri: status pembatalan,
 * status pesanan, lalu memeriksa bukti bayar yang masih menggantung. Yang
 * paling sering tertinggal adalah yang kedua — pesanan tetap terbaca
 * "dp_masuk" padahal sudah dibatalkan dan dananya sedang dikirim balik.
 *
 * BATASAN YANG DISENGAJA
 *
 * Pengajuan yang baru masuk (diajukan) TIDAK membatalkan pesanan. Itu baru
 * permintaan, dan tim masih boleh menolaknya. Pesanan baru berubah jadi batal
 * ketika pembatalannya benar-benar disetujui.
 *
 * Bukti pembayaran yang masih menunggu juga TIDAK diputuskan sendiri di sini.
 * Menolaknya berarti menghapus catatan uang yang mungkin benar-benar sudah
 * masuk — padahal justru jumlah itulah yang menentukan besar pengembalian.
 * Menerimanya berarti mengakui uang tanpa memeriksa mutasi. Keduanya salah,
 * jadi yang dikerjakan hanya menandainya supaya tidak terlewat.
 */
class SelaraskanPembatalan
{
    /** Keputusan yang berarti pesanannya memang batal. */
    private const DISETUJUI = ['disetujui', 'dana_dikirim'];

    public static function jalankan(Pembatalan $pembatalan): void
    {
        $pesanan = $pembatalan->pesanan();

        if (! $pesanan) {
            return;
        }

        if (in_array($pembatalan->status, self::DISETUJUI, true)) {
            self::batalkan($pembatalan, $pesanan);

            return;
        }

        // Pengajuan yang ditolak mengembalikan pesanan ke jalurnya semula.
        // Statusnya dihitung ulang dari pembayaran yang sudah diterima, bukan
        // ditebak — pesanan yang DP-nya sudah masuk harus kembali ke dp_masuk,
        // bukan ke baru.
        if ($pembatalan->status === 'ditolak' && $pesanan->status === 'batal') {
            $pesanan->update(['status' => 'baru']);
            StatusPendaftaran::selaraskan($pesanan->fresh());
        }
    }

    private static function batalkan(Pembatalan $pembatalan, $pesanan): void
    {
        if ($pesanan->status !== 'batal') {
            $pesanan->update(['status' => 'batal']);
        }

        // Bukti yang masih menunggu ditandai, bukan diputuskan. Catatannya
        // ditambahkan, tidak menimpa — admin mungkin sudah menulis sesuatu di
        // sana, dan catatan orang tidak boleh hilang karena proses otomatis.
        KonfirmasiPembayaran::where('kode', $pembatalan->kode_pendaftaran)
            ->where('status', 'menunggu')
            ->get()
            ->each(function (KonfirmasiPembayaran $bayar) {
                $tanda = 'Pesanan ini dibatalkan. Periksa bukti ini lebih dulu — '
                    .'jumlah yang benar-benar masuk menentukan besar pengembalian.';

                if (str_contains((string) $bayar->catatan_admin, $tanda)) {
                    return;
                }

                $bayar->update([
                    'catatan_admin' => trim(($bayar->catatan_admin ? $bayar->catatan_admin."\n" : '').$tanda),
                ]);
            });
    }
}
