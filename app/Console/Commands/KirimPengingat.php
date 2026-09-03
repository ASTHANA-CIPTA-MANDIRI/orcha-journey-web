<?php

namespace App\Console\Commands;

use App\Support\PengingatPesanan;
use Illuminate\Console\Command;

/**
 * Dua pengingat yang selama ini tidak pernah dikirim siapa pun.
 *
 * Dipanggil cron langsung, sama seperti perintah Orcha lain — hosting
 * mematikan proc_open sehingga schedule:run tidak bisa diandalkan:
 *
 *     0 9 * * * cd /jalur/ke/orcha && php artisan orcha:pengingat >> storage/logs/cron.log 2>&1
 *
 * Sekali sehari pukul sembilan pagi. Bukan tengah malam: pengingat pelunasan
 * menyuruh orang ke bank, dan briefing keberangkatan menyuruhnya berkemas —
 * keduanya tidak bisa dikerjakan pada jam dua pagi, dan surat yang datang saat
 * tidak bisa ditindaklanjuti sudah tenggelam sebelum dibaca.
 */
class KirimPengingat extends Command
{
    protected $signature = 'orcha:pengingat
                            {--percobaan : Hanya menampilkan siapa yang akan dikirimi}';

    protected $description = 'Mengirim pengingat pelunasan dan briefing keberangkatan';

    public function handle(): int
    {
        $percobaan = (bool) $this->option('percobaan');
        $hasil = PengingatPesanan::jalankan($percobaan);

        $awalan = $percobaan ? '[PERCOBAAN] ' : '';

        $this->baris('Pengingat pelunasan', $hasil['pelunasan']);
        $this->baris('Briefing keberangkatan', $hasil['briefing']);

        $jumlah = count($hasil['pelunasan']) + count($hasil['briefing']);

        $this->info($awalan.$jumlah.' pengingat dikirim.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $kode
     */
    private function baris(string $judul, array $kode): void
    {
        if ($kode === []) {
            $this->line($judul.': tidak ada.');

            return;
        }

        $this->line($judul.': '.count($kode).' —');

        foreach ($kode as $satu) {
            $this->line('  - '.$satu);
        }
    }
}
