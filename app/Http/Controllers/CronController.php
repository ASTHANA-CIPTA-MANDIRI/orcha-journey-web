<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

/**
 * Pemicu cron CADANGAN lewat HTTP.
 *
 * Dipakai layanan cron eksternal (mis. cron-job.org) memanggil penjadwal tiap
 * menit BILA cron hPanel mati. Bukan kemewahan: di server yang sama, cron
 * hPanel Phoenix pernah mati diam-diam dan baru ketahuan karena pembayaran
 * QRIS berhenti terdeteksi — tidak ada galat, tidak ada gejala lain.
 *
 * Aman: hanya berjalan bila token cocok. Token kosong berarti 404, yaitu
 * nonaktif — sehingga memasang berkas ini tanpa mengisi CRON_TOKEN tidak
 * membuka apa pun.
 */
class CronController extends Controller
{
    public function __invoke(string $token)
    {
        $diharapkan = (string) config('orcha.cron_token');

        abort_if($diharapkan === '', 404);
        abort_unless(hash_equals($diharapkan, $token), 404);

        $log = [];

        /*
         | Tugas yang paling cepat terasa bila terlewat dijalankan LANGSUNG
         | in-process, bukan lewat schedule:run.
         |
         | Sebabnya sudah terbukti di Phoenix: lewat penjadwal, mutex
         | withoutOverlapping yang nyangkut membuat perintahnya dilewati
         | DIAM-DIAM — schedule:run tidak melempar galat, jadi jaring
         | pengamannya sendiri tidak pernah tahu ada yang gagal.
         |
         | Keduanya idempoten, jadi aman meski juga jalan lewat cron hPanel.
         */
        foreach (['orcha:lepas-kursi', 'orcha:selaraskan-status'] as $perintah) {
            try {
                Artisan::call($perintah);
                $keluaran = trim(Artisan::output());
                $log[] = $perintah.' OK'.($keluaran !== '' ? ' — '.str_replace("\n", ' | ', $keluaran) : '');
            } catch (\Throwable $e) {
                $log[] = $perintah.' GAGAL: '.$e->getMessage();
            }
        }

        // Sisanya lewat penjadwal penuh, yang menghormati jadwal masing-masing.
        try {
            Artisan::call('schedule:run');
            $log[] = 'schedule:run OK';
        } catch (\Throwable $e) {
            $log[] = 'schedule:run GAGAL: '.$e->getMessage();
        }

        return response(implode("\n", $log)."\n", 200)
            ->header('Content-Type', 'text/plain');
    }
}
