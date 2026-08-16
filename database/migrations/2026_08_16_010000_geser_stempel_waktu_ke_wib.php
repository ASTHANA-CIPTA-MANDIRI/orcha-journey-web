<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menggeser stempel waktu yang telanjur tersimpan sebagai UTC ke WIB.
 *
 * Zona waktu aplikasi baru saja diubah dari UTC ke Asia/Jakarta. Tanpa
 * penggeseran ini, baris lama akan terbaca tujuh jam lebih awal daripada
 * kejadian sebenarnya — pendaftaran pukul 23.50 WIB akan tampil 16.50, dan
 * urutan riwayatnya jadi menyesatkan saat dicocokkan dengan mutasi rekening.
 *
 * Yang digeser hanya stempel waktu yang DIBUAT MESIN pada saat kejadian
 * (created_at, updated_at, dan sejenisnya). Kolom yang DIISI ADMIN — jadwal
 * tayang paket, tanggal keberangkatan, tanggal transfer — sengaja dibiarkan:
 * admin mengetiknya sebagai waktu setempat, jadi angkanya memang sudah benar
 * begitu aplikasinya membaca WIB.
 *
 * WIB tidak mengenal waktu musim panas, jadi selisihnya tetap 7 jam.
 */
return new class extends Migration
{
    /** Kolom buatan mesin yang perlu digeser. */
    private array $kolom = [
        'created_at',
        'updated_at',
        'email_verified_at',
        'two_factor_confirmed_at',
        'dibaca_pada',
        'failed_at',
    ];

    /** Tabel kerja kerangka kerja: isinya sementara, tidak perlu digeser. */
    private array $lewati = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'password_reset_tokens',
    ];

    public function up(): void
    {
        $this->geser(7);
    }

    public function down(): void
    {
        $this->geser(-7);
    }

    private function geser(int $jam): void
    {
        foreach (Schema::getTableListing() as $tabel) {
            // Sebagian pemasangan memberi awalan nama basis data pada daftarnya.
            $tabel = str_contains($tabel, '.') ? substr($tabel, strrpos($tabel, '.') + 1) : $tabel;

            if (in_array($tabel, $this->lewati, true)) {
                continue;
            }

            foreach ($this->kolom as $kolom) {
                if (! Schema::hasColumn($tabel, $kolom)) {
                    continue;
                }

                DB::table($tabel)->whereNotNull($kolom)->update([
                    $kolom => $this->ungkapan($kolom, $jam),
                ]);
            }
        }
    }

    /** SQLite dan MySQL memakai fungsi yang berbeda untuk menambah jam. */
    private function ungkapan(string $kolom, int $jam): \Illuminate\Database\Query\Expression
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Tandanya ditulis tegas: SQLite membaca '7 hours' dan '+7 hours'
            // sama saja, tetapi yang tegas lebih enak dibaca saat ditelusuri.
            return DB::raw(sprintf("datetime(%s, '%+d hours')", $kolom, $jam));
        }

        return DB::raw("DATE_ADD({$kolom}, INTERVAL {$jam} HOUR)");
    }
};
