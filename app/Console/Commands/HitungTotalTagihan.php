<?php

namespace App\Console\Commands;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Console\Command;

/**
 * Mengisi kolom total_tagihan untuk pendaftaran yang sudah ada.
 *
 * Kolomnya baru, jadi seluruh baris lama bernilai null sampai perintah ini
 * dijalankan — dan null itu KEADAAN NYATA ("belum pernah dihitung"), bukan
 * nol. Selama belum diisi, daftar yang diurut menurut sisa tagihan menaruh
 * seluruh data lama di satu ujung.
 *
 * Angkanya tidak dihitung ulang di sini: ia memanggil penyimpanan biasa,
 * sehingga yang mengisinya tetap kait saved pada modelnya — satu aturan, satu
 * tempat. Lihat PendaftaranOpenTrip::booted().
 */
class HitungTotalTagihan extends Command
{
    protected $signature = 'pendaftaran:hitung-tagihan {--kering : Hanya melaporkan, tanpa menulis apa pun}';

    protected $description = 'Mengisi total_tagihan pendaftaran yang belum punya angkanya';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');

        $belum = PendaftaranOpenTrip::whereNull('total_tagihan')->count();

        $this->info($belum.' pendaftaran belum punya total_tagihan.');

        if ($belum === 0) {
            return self::SUCCESS;
        }

        if ($kering) {
            /*
             | Beberapa contoh disebut angkanya, bukan sekadar jumlah barisnya.
             |
             | Yang menjalankan ini sebelum deploy perlu bisa membandingkan
             | angkanya dengan tagihan yang ia lihat di layar — jumlah baris
             | saja tidak bisa dipakai memeriksa apa pun.
             */
            PendaftaranOpenTrip::whereNull('total_tagihan')
                ->limit(5)
                ->get()
                ->each(fn ($satu) => $this->line(
                    '  '.$satu->kode.'  '.$satu->nama.'  →  Rp '.number_format($satu->omzet, 0, ',', '.')
                ));

            $this->comment('Kering: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        $terisi = 0;

        PendaftaranOpenTrip::whereNull('total_tagihan')
            ->chunkById(200, function ($baris) use (&$terisi) {
                foreach ($baris as $satu) {
                    // touch() memicu kait saved, dan kait itulah yang
                    // mengisikan angkanya — bukan perintah ini.
                    $satu->save();
                    $terisi++;
                }
            });

        $this->info($terisi.' pendaftaran diisi.');

        $sisa = PendaftaranOpenTrip::whereNull('total_tagihan')->count();

        if ($sisa > 0) {
            $this->warn($sisa.' masih kosong — periksa baris itu sendiri.');
        }

        return self::SUCCESS;
    }
}
