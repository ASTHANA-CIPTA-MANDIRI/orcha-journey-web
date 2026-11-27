<?php

namespace Database\Seeders;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Database\Seeder;

/**
 * Membuang data uji coba yang dibuat DummyOpenTripBanyakTanggalSeeder.
 *
 *   php artisan db:seed --class=HapusDummyOpenTripSeeder
 *
 * Dibuat sebagai seeder tersendiri, bukan sebagai perintah tinker sepanjang
 * dua baris yang harus disalin benar: perintah hapus yang diketik ulang
 * adalah tempat paling mudah untuk tidak sengaja menghapus yang lain.
 *
 * Yang dihapus HANYA yang berpenanda milik seeder itu — bukan "semua paket
 * bernama uji", bukan "pendaftaran tanpa pembayaran".
 */
class HapusDummyOpenTripSeeder extends Seeder
{
    public function run(): void
    {
        $pendaftaran = PendaftaranOpenTrip::where(
            'catatan_internal',
            DummyOpenTripBanyakTanggalSeeder::PENANDA,
        )->delete();

        $paket = 0;

        TravelPackage::where('name', DummyOpenTripBanyakTanggalSeeder::NAMA)
            ->each(function (TravelPackage $p) use (&$paket) {
                $p->delete();
                $paket++;
            });

        $this->command?->info("Dihapus: {$paket} paket uji coba, {$pendaftaran} pendaftaran uji coba.");
    }
}
