<?php

namespace App\Console\Commands;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\StatusPendaftaran;
use Illuminate\Console\Command;

/**
 * Menyelaraskan status seluruh pesanan dengan pembayaran yang sudah diterima.
 *
 * Penyelarasan otomatis hanya berjalan pada saat status pembayaran DIUBAH.
 * Pesanan yang pembayarannya sudah diterima sebelum aturan itu ada tetap
 * tertinggal di status lamanya — dan tidak ada kejadian berikutnya yang akan
 * memperbaikinya sendiri.
 *
 * Perintah ini menutup selisih itu. Aman dijalankan berulang: yang statusnya
 * sudah benar dilewati, dan yang sudah dibatalkan tidak pernah disentuh.
 */
class SelaraskanStatusPesanan extends Command
{
    protected $signature = 'orcha:selaraskan-status {--pura-pura : Tampilkan yang akan berubah tanpa menyimpan}';

    protected $description = 'Samakan status pendaftaran & penyewaan dengan pembayaran yang sudah diterima';

    public function handle(): int
    {
        $puraPura = (bool) $this->option('pura-pura');
        $berubah = 0;

        foreach ([PendaftaranOpenTrip::class, PenyewaanKendaraan::class] as $model) {
            foreach ($model::cursor() as $pesanan) {
                $sebelum = $pesanan->status;

                if ($puraPura) {
                    // Dihitung tanpa menyimpan: berguna untuk melihat dampaknya
                    // di data sungguhan sebelum benar-benar mengubahnya.
                    $tagihan = \App\Support\TagihanPesanan::untuk($pesanan, hanyaDiterima: true);
                    $akan = $tagihan !== [] && $tagihan['sudah'] > 0
                        ? ($pesanan instanceof PendaftaranOpenTrip && $tagihan['lunas'] ? 'lunas' : 'dp_masuk')
                        : null;

                    if ($akan && $akan !== $sebelum && $sebelum !== 'batal') {
                        $this->line("  {$pesanan->kode}: {$sebelum} → {$akan}");
                        $berubah++;
                    }

                    continue;
                }

                if ($baru = StatusPendaftaran::selaraskan($pesanan)) {
                    $this->line("  {$pesanan->kode}: {$sebelum} → {$baru}");
                    $berubah++;
                }
            }
        }

        $this->info($puraPura
            ? "{$berubah} pesanan akan berubah statusnya."
            : "{$berubah} pesanan diselaraskan.");

        return self::SUCCESS;
    }
}
