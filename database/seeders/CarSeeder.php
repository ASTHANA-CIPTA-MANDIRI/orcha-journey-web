<?php

namespace Database\Seeders;

use App\Models\SewaKendaraan\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    /**
     * Tarif ditulis per jam, per 12 jam, dan per hari karena ketiganya memang
     * berbeda. Biaya sopir dihitung terpisah, harian.
     */
    public function run(): void
    {
        $cars = [
            // ---------- Mobil ----------
            ['name' => 'Sigra R Deluxe', 'brand' => 'Daihatsu', 'type' => 'mobil', 'jam' => 40000, 'setengah' => 200000, 'hari' => 250000, 'sopir' => 150000, 'transmisi' => ['Manual', 'Matic'], 'kursi' => 7, 'tersedia' => true],
            ['name' => 'Brio Satya E', 'brand' => 'Honda', 'type' => 'mobil', 'jam' => 45000, 'setengah' => 240000, 'hari' => 300000, 'sopir' => 150000, 'transmisi' => ['Matic'], 'kursi' => 5, 'tersedia' => true],
            ['name' => 'All New Avanza', 'brand' => 'Toyota', 'type' => 'mobil', 'jam' => 55000, 'setengah' => 280000, 'hari' => 350000, 'sopir' => 150000, 'transmisi' => ['Manual', 'Matic'], 'kursi' => 7, 'tersedia' => true],
            ['name' => 'Xpander Ultimate', 'brand' => 'Mitsubishi', 'type' => 'mobil', 'jam' => 70000, 'setengah' => 360000, 'hari' => 450000, 'sopir' => 150000, 'transmisi' => ['Matic'], 'kursi' => 7, 'tersedia' => true],
            ['name' => 'Innova Reborn Diesel', 'brand' => 'Toyota', 'type' => 'mobil', 'jam' => 90000, 'setengah' => 480000, 'hari' => 600000, 'sopir' => 175000, 'transmisi' => ['Manual', 'Matic'], 'kursi' => 7, 'tersedia' => true],
            ['name' => 'Pajero Sport Dakar', 'brand' => 'Mitsubishi', 'type' => 'mobil', 'jam' => 175000, 'setengah' => 950000, 'hari' => 1200000, 'sopir' => 200000, 'transmisi' => ['Matic'], 'kursi' => 7, 'tersedia' => true],
            ['name' => 'Agya GR Sport', 'brand' => 'Toyota', 'type' => 'mobil', 'jam' => 45000, 'setengah' => 240000, 'hari' => 300000, 'sopir' => 150000, 'transmisi' => ['Matic'], 'kursi' => 5, 'tersedia' => false],

            // ---------- HiAce ----------
            ['name' => 'HiAce Commuter', 'brand' => 'Toyota', 'type' => 'hiace', 'jam' => 150000, 'setengah' => 800000, 'hari' => 1000000, 'sopir' => 200000, 'transmisi' => ['Manual'], 'kursi' => 15, 'tersedia' => true],
            ['name' => 'HiAce Premio', 'brand' => 'Toyota', 'type' => 'hiace', 'jam' => 200000, 'setengah' => 1080000, 'hari' => 1350000, 'sopir' => 200000, 'transmisi' => ['Manual'], 'kursi' => 14, 'tersedia' => true],
            ['name' => 'HiAce Luxury Captain Seat', 'brand' => 'Toyota', 'type' => 'hiace', 'jam' => 245000, 'setengah' => 1320000, 'hari' => 1650000, 'sopir' => 225000, 'transmisi' => ['Matic'], 'kursi' => 11, 'tersedia' => true],

            // ---------- Bus ----------
            ['name' => 'Medium Bus Pariwisata', 'brand' => 'Isuzu Elf Long', 'type' => 'bus', 'jam' => null, 'setengah' => 1450000, 'hari' => 1800000, 'sopir' => 250000, 'transmisi' => ['Manual'], 'kursi' => 25, 'tersedia' => true],
            ['name' => 'Big Bus SHD 59 Seat', 'brand' => 'Hino RK8', 'type' => 'bus', 'jam' => null, 'setengah' => 2600000, 'hari' => 3200000, 'sopir' => 300000, 'transmisi' => ['Manual'], 'kursi' => 59, 'tersedia' => true],
            ['name' => 'Big Bus HDD 47 Seat', 'brand' => 'Mercedes-Benz OH 1626', 'type' => 'bus', 'jam' => null, 'setengah' => 3100000, 'hari' => 3800000, 'sopir' => 300000, 'transmisi' => ['Manual'], 'kursi' => 47, 'tersedia' => true],
        ];

        foreach ($cars as $car) {
            Car::create([
                'name' => $car['name'],
                'brand' => $car['brand'],
                'type' => $car['type'],
                // Membuat Plat Nomor acak, contoh: AB 1234 XY
                'nopol' => 'AB '.rand(1000, 9999).' '.chr(rand(65, 90)).chr(rand(65, 90)),
                'price_per_day' => $car['hari'],
                'harga_per_jam' => $car['jam'],
                'harga_12_jam' => $car['setengah'],
                'harga_sopir' => $car['sopir'],
                'transmission' => $car['transmisi'][0],
                'transmisi_tersedia' => $car['transmisi'],
                'capacity' => $car['kursi'],
                'image' => null,
                'is_available' => $car['tersedia'],
            ]);
        }
    }
}
