<?php

namespace App\Console\Commands;

use App\Models\Blog\Artikel;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menemukan gambar di disk yang tidak lagi dirujuk data mana pun.
 *
 * Menghapus destinasi, paket, atau artikel tidak selalu ikut membuang
 * gambarnya, jadi disk tumbuh terus tanpa ada yang membersihkan.
 *
 * PERINTAH INI TIDAK MENGHAPUS APA PUN KECUALI DIMINTA DENGAN TEGAS.
 *
 * Bawaannya cuma melaporkan. Alasannya: salah menghapus di sini berarti
 * menghilangkan foto yang masih dipakai halaman publik, dan foto destinasi
 * yang hilang tidak bisa dibuat ulang dari mana pun. Melaporkan dulu lalu
 * dibaca manusia jauh lebih murah daripada memulihkan yang telanjur terbuang.
 *
 * Folder bukti-bayar dan jaminan SENGAJA tidak disentuh sama sekali — lihat
 * keterangannya di bawah.
 */
class BersihkanBerkasYatim extends Command
{
    protected $signature = 'orcha:berkas-yatim {--hapus : Benar-benar menghapus, bukan sekadar melaporkan}';

    protected $description = 'Melaporkan (atau menghapus) gambar yang tidak lagi dirujuk data mana pun';

    /**
     * Folder yang diperiksa, beserta dari mana rujukannya dikumpulkan.
     *
     * Daftar putih, bukan "semua folder kecuali". Folder yang lupa didaftarkan
     * akan aman-aman saja; sebaliknya, folder yang lupa dikecualikan akan
     * dihapus isinya.
     */
    private function rujukan(): array
    {
        return [
            'destinasi_populer/utama' => DestinationPopuler::pluck('main_photo')->all(),
            'destinasi_populer/tambahan' => DestinationPopuler::pluck('others_photo')->flatten()->all(),
            'cars' => Car::pluck('image')->all(),
            'partner' => Partner::pluck('foto')->all(),
            'testimoni' => Testimoni::pluck('avatar')->all(),
            'paket' => TravelPackage::pluck('foto')->all(),
            'artikel' => Artikel::pluck('sampul')->all(),
        ];
    }

    public function handle(): int
    {
        $hapus = (bool) $this->option('hapus');
        $totalYatim = 0;
        $totalUkuran = 0;

        foreach ($this->rujukan() as $folder => $dipakai) {
            /*
             | Rujukannya dikumpulkan sebagai NAMA BERKAS, bukan jalur utuh.
             |
             | Jalur tersimpan dalam beberapa bentuk sepanjang umur sistem ini
             | — ada yang '/storage/...', ada yang URL penuh. Membandingkan
             | jalur utuh membuat berkas yang sebenarnya dipakai terlihat
             | yatim, dan itu persis kesalahan yang paling mahal di sini.
             */
            $namaDipakai = collect($dipakai)
                ->filter()
                ->map(fn ($jalur) => basename((string) $jalur))
                ->flip();

            $yatim = collect(Storage::disk('public')->files($folder))
                ->reject(fn ($jalur) => $namaDipakai->has(basename($jalur)));

            if ($yatim->isEmpty()) {
                continue;
            }

            $ukuran = $yatim->sum(fn ($jalur) => Storage::disk('public')->size($jalur));
            $totalYatim += $yatim->count();
            $totalUkuran += $ukuran;

            $this->line(sprintf('%-28s %3d berkas  %s', $folder, $yatim->count(), $this->ukuran($ukuran)));

            if ($hapus) {
                Storage::disk('public')->delete($yatim->all());
            }
        }

        if ($totalYatim === 0) {
            $this->info('Tidak ada berkas yatim.');

            return self::SUCCESS;
        }

        $this->newLine();

        if ($hapus) {
            $this->info("{$totalYatim} berkas dihapus, {$this->ukuran($totalUkuran)} dibebaskan.");

            return self::SUCCESS;
        }

        $this->warn("{$totalYatim} berkas yatim, {$this->ukuran($totalUkuran)} — belum dihapus.");
        $this->line('Periksa dulu daftarnya. Kalau sudah yakin: php artisan orcha:berkas-yatim --hapus');

        /*
         | bukti-bayar dan jaminan tidak ikut diperiksa.
         |
         | Keduanya bukti milik pelanggan, dan rujukannya tersebar di beberapa
         | tabel sekaligus riwayat pembatalan. Salah menghapus di sana berarti
         | menghilangkan bukti transfer orang yang sudah membayar — sesuatu
         | yang tidak bisa diminta ulang berbulan-bulan kemudian.
         */
        $this->newLine();
        $this->line('Folder bukti-bayar dan jaminan sengaja tidak diperiksa perintah ini.');

        return self::SUCCESS;
    }

    private function ukuran(int $bita): string
    {
        return $bita > 1048576
            ? round($bita / 1048576, 1).' MB'
            : round($bita / 1024).' KB';
    }
}
