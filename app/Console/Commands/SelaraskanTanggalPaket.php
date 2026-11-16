<?php

namespace App\Console\Commands;

use App\Models\PaketWisata\TravelPackage;
use Illuminate\Console\Command;

/**
 * Menyelaraskan kolom tanggal milik paket dengan keberangkatannya.
 *
 * Sekali pakai untuk data yang telanjur melenceng. Sejak 16 Nov 2026
 * penyelarasan terjadi sendiri setiap keberangkatan disimpan atau dihapus
 * (lihat Keberangkatan::booted), tetapi paket yang keberangkatannya diubah
 * SEBELUM itu masih memegang tanggal lamanya — dan kartu katalognya memajang
 * tanggal yang tidak bisa dipesan siapa pun.
 */
class SelaraskanTanggalPaket extends Command
{
    protected $signature = 'paket:selaraskan-tanggal {--kering : Tampilkan saja, jangan simpan}';

    protected $description = 'Menyamakan tanggal paket dengan keberangkatan terdekatnya';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');
        $berubah = 0;

        $daftar = TravelPackage::query()->has('keberangkatan')->orderBy('id')->get();

        $this->info($daftar->count().' paket punya keberangkatan.');
        $this->newLine();

        foreach ($daftar as $paket) {
            $sebelum = [
                $paket->tanggal_berangkat?->toDateString(),
                $paket->tanggal_pulang?->toDateString(),
            ];

            // Dihitung pada salinan saat mode kering, supaya tidak ada yang
            // tersimpan hanya karena ingin melihat hasilnya.
            $uji = $kering ? $paket->replicate() : $paket;
            $uji->exists = ! $kering;
            $uji->id = $paket->id;

            $kering
                ? $this->hitungSaja($paket, $sebelum)
                : $paket->selaraskanTanggal();

            $sesudah = $kering
                ? $this->hitungSaja($paket, $sebelum)
                : [
                    $paket->fresh()->tanggal_berangkat?->toDateString(),
                    $paket->fresh()->tanggal_pulang?->toDateString(),
                ];

            if ($sebelum !== $sesudah) {
                $berubah++;
                $this->line(sprintf(
                    '  paket %-3s %-38s %s → %s',
                    $paket->id,
                    mb_substr($paket->name, 0, 38),
                    $sebelum[0] ?? 'kosong',
                    $sesudah[0] ?? 'kosong',
                ));
            }
        }

        $this->newLine();
        $this->info($kering
            ? $berubah.' paket AKAN berubah. Jalankan tanpa --kering untuk menyimpannya.'
            : $berubah.' paket diselaraskan.');

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function hitungSaja(TravelPackage $paket, array $sebelum): array
    {
        $acuan = $paket->keberangkatan()
            ->where('aktif', true)
            ->whereDate('tanggal_berangkat', '>=', today())
            ->orderBy('tanggal_berangkat')
            ->first()
            ?? $paket->keberangkatan()->orderByDesc('tanggal_berangkat')->first();

        return $acuan
            ? [$acuan->tanggal_berangkat?->toDateString(), $acuan->tanggal_pulang?->toDateString()]
            : $sebelum;
    }
}
