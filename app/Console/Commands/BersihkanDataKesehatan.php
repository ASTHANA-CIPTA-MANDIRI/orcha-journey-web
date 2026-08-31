<?php

namespace App\Console\Commands;

use App\Models\JejakAudit;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Menghapus data kesehatan peserta yang tripnya sudah lama selesai.
 *
 * Formulir kesehatan menyimpan riwayat penyakit, riwayat operasi, alergi, obat
 * rutin, dan golongan darah — data pribadi bersifat spesifik. Bocor sekali,
 * akibatnya melekat pada orangnya seumur hidup dan tidak ada cara menariknya
 * kembali.
 *
 * Sebelum ini tidak ada batas apa pun: data medis peserta yang sudah pulang
 * berbulan-bulan lalu masih tersimpan utuh, dan akan terus tersimpan. Data
 * yang tidak disimpan adalah data yang tidak bisa bocor; itu satu-satunya
 * pengamanan yang tidak bisa gagal.
 *
 * Dipanggil cron langsung, sama seperti orcha:lepas-kursi — hosting mematikan
 * proc_open sehingga schedule:run tidak bisa diandalkan:
 *
 *     30 2 * * * cd /jalur/ke/orcha && php artisan orcha:bersihkan-kesehatan >> storage/logs/cron.log 2>&1
 *
 * Sekali sehari sudah cukup; batasnya dihitung dalam hari.
 */
class BersihkanDataKesehatan extends Command
{
    protected $signature = 'orcha:bersihkan-kesehatan
                            {--percobaan : Hanya menghitung, tanpa menghapus apa pun}';

    protected $description = 'Menghapus data kesehatan peserta yang tripnya sudah lewat batas simpan';

    public function handle(): int
    {
        $hari = (int) config('orcha.kesehatan.simpan_hari', 90);
        $batas = now()->subDays($hari)->toDateString();

        /*
         | Dihitung dari TANGGAL KEBERANGKATAN, bukan tanggal pengisian.
         |
         | Gunanya data ini di hari perjalanan; yang menentukan kapan ia tidak
         | diperlukan lagi adalah kapan perjalanannya selesai. Peserta yang
         | mengisi formulir tiga bulan sebelum berangkat tidak boleh datanya
         | terhapus justru sebelum tripnya jalan.
         */
        $kode = PendaftaranOpenTrip::query()
            ->whereNotNull('tanggal_berangkat')
            ->whereDate('tanggal_berangkat', '<=', $batas)
            ->pluck('kode');

        $jumlah = RiwayatKesehatan::whereIn('kode_pendaftaran', $kode)->count();

        if ($jumlah === 0) {
            $this->info("Tidak ada data kesehatan yang perlu dihapus (batas {$hari} hari setelah keberangkatan).");

            return self::SUCCESS;
        }

        if ($this->option('percobaan')) {
            $this->warn("[PERCOBAAN] {$jumlah} data kesehatan akan dihapus (batas {$hari} hari).");

            return self::SUCCESS;
        }

        RiwayatKesehatan::whereIn('kode_pendaftaran', $kode)->delete();

        /*
         | Jejaknya menyimpan JUMLAHNYA, bukan nama pesertanya.
         |
         | Mencatat siapa saja yang datanya dihapus akan menyalin sebagian data
         | itu ke tabel lain yang justru dibaca lebih banyak orang — dan
         | penghapusan yang meninggalkan salinan bukan penghapusan.
         */
        $permintaan = Request::create('/', 'POST');
        $permintaan->attributes->set('admin_pemanggil', 'Sistem');

        JejakAudit::catat(
            $permintaan,
            'hapus data kesehatan kedaluwarsa',
            "{$jumlah} data kesehatan dihapus — tripnya sudah lewat {$hari} hari sejak keberangkatan.",
        );

        $this->info("{$jumlah} data kesehatan dihapus (batas {$hari} hari setelah keberangkatan).");

        return self::SUCCESS;
    }
}
