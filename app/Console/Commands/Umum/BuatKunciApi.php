<?php

namespace App\Console\Commands\Umum;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BuatKunciApi extends Command
{
    protected $signature = 'orcha:kunci-api {--tulis : Tulis langsung ke .env}';

    protected $description = 'Membuat kunci rahasia API dashboard untuk dipakai admin Phoenix';

    public function handle(): int
    {
        $kunci = 'orcha_'.Str::random(56);

        if ($this->option('tulis')) {
            $berkas = base_path('.env');

            if (! file_exists($berkas)) {
                $this->error('Berkas .env tidak ditemukan.');

                return self::FAILURE;
            }

            $isi = file_get_contents($berkas);

            $isi = str_contains($isi, 'ORCHA_API_KEY=')
                ? preg_replace('/^ORCHA_API_KEY=.*$/m', 'ORCHA_API_KEY='.$kunci, $isi)
                : rtrim($isi, "\n")."\n\nORCHA_API_KEY=".$kunci."\n";

            file_put_contents($berkas, $isi);

            $this->info('Kunci baru sudah ditulis ke .env.');
        }

        $this->newLine();
        $this->line('  ORCHA_API_KEY='.$kunci);
        $this->newLine();
        $this->comment('Pasang nilai yang sama persis di .env aplikasi Phoenix.');
        $this->comment('Jangan pernah menaruh kunci ini di kode sisi browser.');

        return self::SUCCESS;
    }
}
