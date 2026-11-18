<?php

namespace App\Console\Commands;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\KirimPemberitahuan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Kabar harian sewa kendaraan ke kotak kantor.
 *
 * Tiga daftar pekerjaan sewa sudah lama bisa dilihat di lemon — unit yang
 * berangkat besok, unit yang lewat tenggat, dan pesanan yang vendornya belum
 * dicarikan. Masalahnya ketiganya hanya terlihat kalau ada yang MEMBUKA
 * layarnya, dan yang paling mahal justru yang paling mudah terlewat: pesanan
 * yang vendornya tidak pernah dicarikan berakhir dibatalkan di hari H.
 *
 * Surat ini tidak menggantikan layarnya; ia cuma memastikan ada yang
 * mengetuk. Isinya sengaja ringkas — tiga angka dan daftar pendek — karena
 * surat harian yang panjang berhenti dibaca di minggu kedua.
 *
 * TIDAK mengirim apa pun ke penyewa atau vendor. WhatsApp di sistem ini
 * selalu lewat tangan admin, dan pengingat otomatis yang tiba-tiba menyapa
 * pelanggan bukan keputusan yang boleh diambil sebuah perintah terjadwal.
 */
class KabarHarianSewa extends Command
{
    protected $signature = 'sewa:kabar-harian {--kering : Tampilkan saja, jangan kirim surat}';

    protected $description = 'Kabar harian: sewa berangkat besok, lewat tenggat, dan yang vendornya belum dicarikan';

    public function handle(): int
    {
        $besok = Carbon::tomorrow();

        $berangkat = PenyewaanKendaraan::whereDate('tanggal_mulai', $besok->toDateString())
            ->whereNotIn('status', ['batal', 'selesai'])
            ->orderBy('jam_mulai')
            ->get();

        // Aksesor, bukan kolom — disaring di PHP, sama seperti saringan
        // "belum kembali" di daftar sewa.
        $telat = PenyewaanKendaraan::whereNull('dikembalikan_pada')
            ->whereNotIn('status', ['batal', 'selesai'])
            ->get()
            ->filter(fn (PenyewaanKendaraan $s) => $s->terlambat)
            ->values();

        $tanpaVendor = PenyewaanKendaraan::whereNull('vendor_id')
            ->whereNotIn('status', ['batal', 'selesai'])
            ->whereHas('kendaraan', fn ($k) => $k->where('milik_sendiri', false))
            ->orderBy('tanggal_mulai')
            ->get();

        $this->info("Berangkat besok: {$berangkat->count()}");
        $this->info("Lewat tenggat: {$telat->count()}");
        $this->info("Belum ada vendor: {$tanpaVendor->count()}");

        /*
         | Tidak ada yang perlu dikabarkan = tidak ada surat.
         |
         | Surat harian yang tetap datang saat tidak ada apa-apa mengajari
         | penerimanya bahwa surat ini boleh diabaikan — lalu ia diabaikan
         | juga pada hari ia berisi.
         */
        if ($berangkat->isEmpty() && $telat->isEmpty() && $tanpaVendor->isEmpty()) {
            $this->info('Tidak ada yang perlu dikabarkan hari ini.');

            return self::SUCCESS;
        }

        if ($this->option('kering')) {
            $this->warn('Mode kering: surat tidak dikirim.');

            return self::SUCCESS;
        }

        $daftar = fn ($baris) => $baris->isEmpty()
            ? '—'
            : $baris->take(10)->map(fn (PenyewaanKendaraan $s) => trim(
                $s->kode.' · '.$s->nama.' · '.$s->nama_kendaraan
            ))->implode("\n");

        KirimPemberitahuan::kirim(
            'Kabar harian sewa kendaraan',
            now()->locale('id')->translatedFormat('l, j F Y'),
            [
                'Berangkat besok' => $berangkat->count().' unit',
                'Daftar berangkat besok' => $daftar($berangkat),
                'Lewat tenggat, belum dicatat kembali' => $telat->count().' unit',
                'Daftar lewat tenggat' => $daftar($telat),
                'Belum dicarikan vendornya' => $tanpaVendor->count().' pesanan',
                'Daftar belum ada vendor' => $daftar($tanpaVendor),
            ],
            'Dikirim otomatis tiap pagi. Buka daftar sewa di lemon untuk mengerjakannya.',
        );

        $this->info('Surat kabar harian terkirim.');

        return self::SUCCESS;
    }
}
