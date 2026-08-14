<?php

namespace App\Console\Commands;

use App\Support\SampulDestinasi;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BuatSampulDestinasi extends Command
{
    protected $signature = 'orcha:sampul-destinasi
                            {nama?* : Nama destinasi (kosongkan untuk memakai daftar bawaan)}
                            {--force : Timpa berkas yang sudah ada}';

    protected $description = 'Membuat ilustrasi sampul (SVG) untuk destinasi yang fotonya belum diunggah';

    /**
     * Daftar bawaan mengikuti destinasi yang dipakai seeder.
     */
    public const BAWAAN = [
        'Karimunjawa',
        'Kawah Ijen Banyuwangi',
        'Nusa Penida',
        'Pantai Kuta Bali',
        'Banda Neira',
        'Raja Ampat',
        'Labuan Bajo',
        'Danau Toba',
        'Bromo Tengger Semeru',
        'Candi Borobudur',
        'Malioboro',
        'Pantai Indrayanti',
        'Kepulauan Derawan',
        'Bunaken',
        'Pulau Weh Sabang',
        'Wakatobi',
        'Gili Trawangan',
        'Tanjung Puting',
        'Tebing Breksi',
        'Goa Pindul',
        'Pantai Parangtritis',
    ];

    public function handle(SampulDestinasi $sampul): int
    {
        $daftar = $this->argument('nama') ?: self::BAWAAN;
        $folder = public_path('images/destinasi');

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $dibuat = 0;
        $dilewati = 0;

        foreach ($daftar as $nama) {
            $slug = Str::slug($nama);

            // Variasi 0 = sampul utama (4:3), 1 & 2 = foto pendamping persegi.
            $berkas = [
                ["$slug.svg", 0, 1200, 900],
                ["$slug-1.svg", 1, 600, 600],
                ["$slug-2.svg", 2, 600, 600],
            ];

            foreach ($berkas as [$namaBerkas, $variasi, $lebar, $tinggi]) {
                $tujuan = "$folder/$namaBerkas";

                if (file_exists($tujuan) && ! $this->option('force')) {
                    $dilewati++;

                    continue;
                }

                file_put_contents($tujuan, $sampul->render($nama, $variasi, $lebar, $tinggi));
                $dibuat++;
            }
        }

        $this->info("Sampul destinasi selesai: $dibuat dibuat, $dilewati dilewati.");
        $this->line('Berkas ada di public/images/destinasi/');

        return self::SUCCESS;
    }
}
