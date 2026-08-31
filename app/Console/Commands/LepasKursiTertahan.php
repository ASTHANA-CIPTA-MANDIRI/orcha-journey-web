<?php

namespace App\Console\Commands;

use App\Support\LepaskanKursiTertahan;
use Illuminate\Console\Command;

/**
 * Melepas kursi yang ditahan pemesanan yang tidak pernah membayar.
 *
 * DIPANGGIL CRON LANGSUNG, bukan lewat schedule:run.
 *
 * Hosting yang dipakai mematikan proc_open, dan schedule:run menjalankan tiap
 * tugasnya sebagai proses anak — sehingga seluruh Schedule::command() gagal
 * diam-diam tiap menit. Perintah ini berdiri sendiri supaya tidak bergantung
 * pada hal itu:
 *
 *     0 * * * * cd /jalur/ke/orcha && php artisan orcha:lepas-kursi >> storage/logs/cron.log 2>&1
 *
 * Tiap jam sudah cukup: batasnya 72 jam, jadi selisih satu jam tidak berarti
 * apa-apa — sedangkan tiap menit hanya menambah 60 kali pemeriksaan yang
 * hampir selalu tidak menemukan apa-apa.
 */
class LepasKursiTertahan extends Command
{
    protected $signature = 'orcha:lepas-kursi
                            {--percobaan : Hanya menampilkan yang akan dilepas, tanpa mengubah apa pun}';

    protected $description = 'Melepas kursi pemesanan open trip yang tidak membayar dalam batas waktu';

    public function handle(): int
    {
        $percobaan = (bool) $this->option('percobaan');
        $jam = (int) config('orcha.pembayaran.dp_lepas_jam', 72);

        $hasil = LepaskanKursiTertahan::jalankan($percobaan);

        if ($hasil['dilepas'] === 0) {
            $this->info("Tidak ada kursi yang perlu dilepas (batas {$jam} jam).");

            return self::SUCCESS;
        }

        $this->info(($percobaan ? '[PERCOBAAN] ' : '')
            ."{$hasil['dilepas']} pemesanan dilepas (batas {$jam} jam):");

        foreach ($hasil['kode'] as $kode) {
            $this->line('  - '.$kode);
        }

        return self::SUCCESS;
    }
}
