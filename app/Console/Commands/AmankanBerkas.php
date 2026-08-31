<?php

namespace App\Console\Commands;

use App\Support\BerkasRahasia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan bukti transfer & berkas jaminan yang sudah terlanjur ada di disk
 * publik ke disk privat.
 *
 * Perubahan kodenya hanya mengurus unggahan BARU. Berkas yang sudah ada tetap
 * di public/storage dan tetap bisa diambil siapa pun yang memegang alamatnya —
 * justru berkas-berkas itulah yang alamatnya sudah paling lama beredar.
 *
 * Aman dijalankan berkali-kali: yang sudah pindah dilewati.
 */
class AmankanBerkas extends Command
{
    protected $signature = 'orcha:amankan-berkas {--percobaan : Hanya menghitung, tanpa memindahkan}';

    protected $description = 'Memindahkan bukti transfer & jaminan dari disk publik ke disk privat';

    public function handle(): int
    {
        $percobaan = (bool) $this->option('percobaan');
        $pindah = 0;
        $lewat = 0;

        foreach (BerkasRahasia::FOLDER as $folder) {
            foreach (Storage::disk('public')->files($folder) as $jalur) {
                if (Storage::disk('rahasia')->exists($jalur)) {
                    // Sudah pernah dipindahkan pada jalan sebelumnya.
                    $lewat++;

                    continue;
                }

                if (! $percobaan) {
                    Storage::disk('rahasia')->put($jalur, Storage::disk('public')->get($jalur));

                    /*
                     | Salinan publiknya dihapus SETELAH salinan privatnya
                     | benar-benar ada. Urutan sebaliknya berisiko kehilangan
                     | bukti transfer pelanggan bila penulisannya gagal di
                     | tengah — dan bukti yang hilang tidak bisa diminta ulang
                     | dari orang yang sudah membayar berbulan-bulan lalu.
                     */
                    if (Storage::disk('rahasia')->exists($jalur)) {
                        Storage::disk('public')->delete($jalur);
                    }
                }

                $pindah++;
            }
        }

        $this->info(($percobaan ? '[PERCOBAAN] ' : '')
            ."{$pindah} berkas dipindahkan ke disk privat, {$lewat} sudah aman sebelumnya.");

        if ($pindah > 0 && ! $percobaan) {
            $this->warn('Alamat lama /storage/bukti-bayar/... kini menjawab 404. Itu memang tujuannya.');
        }

        return self::SUCCESS;
    }
}
