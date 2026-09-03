<?php

namespace App\Console\Commands;

use App\Support\CadanganBasisData as Cadangan;
use App\Support\GoogleDrive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Menyalin basis data dan menitipkannya ke Google Drive.
 *
 * Dipanggil cron langsung, sama seperti perintah Orcha lain — hosting
 * mematikan proc_open sehingga schedule:run tidak bisa diandalkan:
 *
 *     30 2 * * * cd /jalur/ke/orcha && php artisan orcha:cadangan >> storage/logs/cron.log 2>&1
 *
 * Pukul setengah tiga pagi: jam paling sepi, dan menyalin basis data memang
 * membebani server sebentar.
 *
 * CADANGAN LOKAL SELALU DIBUAT LEBIH DULU. Kalau unggahannya gagal — Google
 * sedang bermasalah, kuota habis, token dicabut — cadangannya tetap ada di
 * server. Menggabungkan keduanya jadi satu langkah berarti hari saat Drive
 * bermasalah adalah hari kita tidak punya cadangan sama sekali.
 */
class CadanganBasisData extends Command
{
    protected $signature = 'orcha:cadangan
                            {--tanpa-drive : Hanya menyimpan di server, tidak diunggah}';

    protected $description = 'Menyalin basis data dan mengunggahnya ke Google Drive';

    public function handle(): int
    {
        $sisakan = (int) config('orcha.cadangan.sisakan', 14);

        $this->line('Menyalin basis data…');

        try {
            $jalur = Cadangan::buat();
        } catch (\Throwable $e) {
            /*
             | Gagalnya penyalinan adalah kegagalan yang harus BERISIK.
             |
             | Cadangan yang diam-diam berhenti dibuat baru ketahuan pada hari
             | ia dibutuhkan, dan pada hari itu tidak ada lagi yang bisa
             | dikerjakan. Kode keluarnya bukan nol supaya cron mengirimkan
             | surat kegagalan.
             */
            $this->error('Penyalinan gagal: '.$e->getMessage());
            Log::error('Cadangan basis data gagal dibuat', ['galat' => $e->getMessage()]);

            return self::FAILURE;
        }

        $ukuran = number_format(filesize($jalur) / 1048576, 2, ',', '.');
        $this->info('Tersimpan di server: '.basename($jalur).' ('.$ukuran.' MB)');

        $dibuang = Cadangan::rapikan(dirname($jalur), $sisakan);

        if ($dibuang !== []) {
            $this->line('Cadangan lama dibuang: '.count($dibuang).' berkas.');
        }

        if ($this->option('tanpa-drive')) {
            return self::SUCCESS;
        }

        if (! GoogleDrive::siap()) {
            /*
             | Bukan galat — Drive memang boleh tidak disiapkan, dan
             | cadangannya sudah ada di server. Tetapi disebutkan setiap kali,
             | supaya "sudah ada cadangan" tidak diam-diam berarti "hanya di
             | mesin yang sama dengan data aslinya".
             */
            $this->warn('Google Drive belum disiapkan — cadangan hanya tersimpan di server ini,');
            $this->warn('di mesin yang sama dengan data aslinya. Lihat config/orcha.php kunci "drive".');

            return self::SUCCESS;
        }

        try {
            $this->line('Mengunggah ke Google Drive…');

            GoogleDrive::unggah($jalur);
            $dibuangDrive = GoogleDrive::rapikan($sisakan);

            $this->info('Terunggah ke Google Drive.');

            if ($dibuangDrive !== []) {
                $this->line('Cadangan lama di Drive dibuang: '.count($dibuangDrive).' berkas.');
            }
        } catch (\Throwable $e) {
            // Salinan servernya sudah jadi, jadi ini bukan kegagalan penuh —
            // tetapi tetap dilaporkan gagal supaya cron berkirim surat. Diam
            // di sini berarti cadangan luar berhenti berbulan-bulan tanpa ada
            // yang tahu.
            $this->error('Unggahan gagal: '.$e->getMessage());
            $this->warn('Salinan di server tetap ada: '.basename($jalur));
            Log::error('Cadangan gagal diunggah ke Drive', ['galat' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
