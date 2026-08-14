<?php

namespace Database\Seeders;

use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class TravelPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->openTripBanyuwangi();

        foreach ($this->paketLain() as $paket) {
            TravelPackage::create($paket);
        }
    }

    /**
     * Open Trip Banyuwangi 3D2N — paket nyata yang sedang dijual, datanya
     * mengikuti poster resmi Orcha Journey (keberangkatan 19–21 Oktober).
     */
    private function openTripBanyuwangi(): void
    {
        TravelPackage::create([
            'name' => 'Open Trip Banyuwangi',
            'category' => 'open_trip',
            'duration' => '3 hari 2 malam',
            'tanggal_berangkat' => '2026-10-19',
            'tanggal_pulang' => '2026-10-21',
            'titik_jemput' => 'Jogja, Klaten, Surakarta',
            'minimal_peserta' => 6,
            'price' => 1430000,
            'original_price' => 1700000,
            'discount_percentage' => 16,
            'catatan_promo' => 'Promo Early Bird — 5 orang pertama',
            'is_best_choice' => true,
            'destination_list' => [
                'De Djawatan',
                'Pulau Tabuhan',
                'Baluran',
                'Pulau Menjangan',
                'Savana Bekol',
                'Pantai Bama',
                'Grand Watu Dodol',
            ],
            'fasilitas' => [
                'Transportasi AC PP',
                'Driver + Tour Leader',
                'BBM, Tol, Retribusi',
                'Makan 5x',
                'Homestay',
                'Welcoming Drink & Snack',
                'Merchandise',
                'Dokumentasi',
                'Banner & P3K',
            ],
            'itinerary' => [
                [
                    'hari' => 'Day 1',
                    'agenda' => [
                        ['jam' => '18.00', 'kegiatan' => 'Penjemputan Meeting Point'],
                        ['jam' => '19.00', 'kegiatan' => 'Perjalanan Banyuwangi'],
                        ['jam' => '19.30', 'kegiatan' => 'Makan Malam dan Istirahat'],
                        ['jam' => '20.00', 'kegiatan' => 'Melanjutkan Perjalanan Menuju Banyuwangi'],
                    ],
                ],
                [
                    'hari' => 'Day 2',
                    'agenda' => [
                        ['jam' => '03.00', 'kegiatan' => 'Tiba di Banyuwangi: bersih-bersih dan sholat'],
                        ['jam' => '07.30', 'kegiatan' => 'Sarapan'],
                        ['jam' => '08.00', 'kegiatan' => 'Wisata TN Baluran, Savana Bekol, dan Pantai Bama'],
                        ['jam' => '12.30', 'kegiatan' => 'Perjalanan De Djawatan & makan siang'],
                        ['jam' => '14.00', 'kegiatan' => 'Explore De Djawatan Forest'],
                        ['jam' => '15.30', 'kegiatan' => 'Perjalanan menuju Osing Homestay Oleh-Oleh'],
                        ['jam' => '16.30', 'kegiatan' => 'Belanja oleh-oleh'],
                        ['jam' => '18.00', 'kegiatan' => 'Perjalanan menuju Grafika Resto'],
                        ['jam' => '18.30', 'kegiatan' => 'Grafika Resto — makan malam'],
                        ['jam' => '19.30', 'kegiatan' => 'Perjalanan menuju homestay'],
                        ['jam' => '20.00', 'kegiatan' => 'Check-in dan istirahat'],
                    ],
                ],
                [
                    'hari' => 'Day 3',
                    'agenda' => [
                        ['jam' => '06.00', 'kegiatan' => 'Sarapan dan persiapan'],
                        ['jam' => '07.30', 'kegiatan' => 'Berangkat menuju Grand Watudodol'],
                        ['jam' => '08.00', 'kegiatan' => 'Penyeberangan ke Menjangan, explore Menjangan, snorkeling dan makan siang, explore Tabuhan'],
                        ['jam' => '15.30', 'kegiatan' => 'Tiba di Grand Watudodol: bersih-bersih & makan'],
                        ['jam' => '16.30', 'kegiatan' => 'Perjalanan pulang'],
                        ['jam' => '03.30', 'kegiatan' => 'Perkiraan sampai Yogyakarta'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Paket contoh lain — silakan diubah atau dihapus lewat panel admin.
     */
    private function paketLain(): array
    {
        return [
            [
                'name' => 'Open Trip Jogja Hemat',
                'category' => 'open_trip',
                'duration' => '1 hari · berangkat tiap Sabtu',
                'titik_jemput' => 'Jogja',
                'minimal_peserta' => 6,
                'price' => 350000,
                'original_price' => 450000,
                'discount_percentage' => 22,
                'is_best_choice' => false,
                'destination_list' => ['Candi Borobudur', 'Malioboro', 'Tugu Jogja'],
                'fasilitas' => ['Transportasi AC', 'Driver + Tour Leader', 'Tiket masuk destinasi', 'Dokumentasi'],
            ],
            [
                'name' => 'Open Trip Pantai Gunungkidul',
                'category' => 'open_trip',
                'duration' => '1 hari',
                'titik_jemput' => 'Jogja',
                'minimal_peserta' => 6,
                'price' => 285000,
                'original_price' => 350000,
                'discount_percentage' => 18,
                'is_best_choice' => false,
                'destination_list' => ['Pantai Indrayanti', 'Pantai Krakal', 'Pantai Drini', 'Bukit Bintang'],
                'fasilitas' => ['Transportasi AC', 'Driver + Tour Leader', 'Retribusi pantai', 'Dokumentasi'],
            ],
            [
                'name' => 'Private Trip Keluarga Jogja',
                'category' => 'private_trip',
                'duration' => '2 hari 1 malam · maks. 7 orang',
                'titik_jemput' => 'Menyesuaikan permintaan',
                'minimal_peserta' => 2,
                'price' => 1500000,
                'original_price' => 2000000,
                'discount_percentage' => 25,
                'is_best_choice' => false,
                'destination_list' => ['Candi Prambanan', 'Tebing Breksi', 'Heha Sky View', 'Malioboro'],
                'fasilitas' => ['Kendaraan eksklusif', 'Driver + Tour Leader', 'Penginapan', 'Dokumentasi'],
            ],
            [
                'name' => 'Private Trip Premium',
                'category' => 'private_trip',
                'duration' => '3 hari 2 malam · hotel bintang 3',
                'titik_jemput' => 'Menyesuaikan permintaan',
                'minimal_peserta' => 2,
                'price' => 2500000,
                'original_price' => 3500000,
                'discount_percentage' => 29,
                'is_best_choice' => true,
                'destination_list' => ['Borobudur Sunrise', 'Candi Prambanan', 'Pantai Parangtritis', 'Goa Pindul', 'Malioboro'],
                'fasilitas' => ['Kendaraan eksklusif', 'Hotel bintang 3', 'Makan 6x', 'Driver + Tour Leader', 'Dokumentasi'],
            ],
            [
                'name' => 'Study Tour SMA & Kampus',
                'category' => 'study_tour',
                'duration' => '3 hari 2 malam · min. 45 peserta',
                'titik_jemput' => 'Menyesuaikan lokasi sekolah',
                'minimal_peserta' => 45,
                'price' => 985000,
                'original_price' => 1200000,
                'discount_percentage' => 18,
                'is_best_choice' => true,
                'destination_list' => ['Kunjungan Kampus UGM', 'Candi Prambanan', 'Pabrik Bakpia', 'Pantai Parangtritis', 'Malioboro'],
                'fasilitas' => ['Bus pariwisata', 'Pendamping tiap bus', 'Penginapan', 'Makan 6x', 'Asuransi perjalanan', 'Dokumentasi'],
            ],
            [
                'name' => 'Study Tour SD/SMP Jogja',
                'category' => 'study_tour',
                'duration' => '2 hari 1 malam · min. 40 peserta',
                'titik_jemput' => 'Menyesuaikan lokasi sekolah',
                'minimal_peserta' => 40,
                'price' => 675000,
                'original_price' => 800000,
                'discount_percentage' => 16,
                'is_best_choice' => false,
                'destination_list' => ['Museum Dirgantara', 'Taman Pintar', 'Candi Borobudur', 'Malioboro'],
                'fasilitas' => ['Bus pariwisata', 'Pendamping tiap bus', 'Penginapan', 'Makan 4x', 'Asuransi perjalanan'],
            ],
        ];
    }
}
