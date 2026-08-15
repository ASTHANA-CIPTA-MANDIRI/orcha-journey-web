<?php

namespace Database\Seeders;

use App\Models\Etalase\DestinationPopuler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DestinationPopulerSeeder extends Seeder
{
    /**
     * Destinasi tersebar di seluruh Indonesia, bukan hanya Yogyakarta.
     *
     * Gambar memakai ilustrasi vektor buatan `php artisan orcha:sampul-destinasi`
     * (lihat App\Support\Etalase\SampulDestinasi) — sengaja bergaya gambar, bukan foto,
     * supaya tidak ada gambar yang seolah-olah memotret tempat aslinya. Begitu
     * admin mengunggah foto asli lewat panel, foto itu yang dipakai.
     */
    public function run(): void
    {
        foreach ($this->daftar() as $destinasi) {
            $slug = Str::slug($destinasi['destination_name']);

            DestinationPopuler::create([
                ...$destinasi,
                'main_photo' => "/images/destinasi/$slug.svg",
                'others_photo' => [
                    "/images/destinasi/$slug-1.svg",
                    "/images/destinasi/$slug-2.svg",
                ],
            ]);
        }
    }

    private function daftar(): array
    {
        return [
            // ---------- Sumatera ----------
            [
                'destination_name' => 'Danau Toba',
                'wilayah' => 'sumatera',
                'provinsi' => 'Sumatera Utara',
                'total_visitor' => 15600,
                'deskripsi' => 'Danau vulkanik terbesar di Asia Tenggara dengan Pulau Samosir di tengahnya. Cocok untuk perjalanan santai berlatar perbukitan dan budaya Batak.',
            ],
            [
                'destination_name' => 'Pulau Weh Sabang',
                'wilayah' => 'sumatera',
                'provinsi' => 'Aceh',
                'total_visitor' => 4400,
                'deskripsi' => 'Titik nol kilometer Indonesia dengan air laut jernih. Favorit peserta yang ingin menyelam dan snorkeling di ujung barat Nusantara.',
            ],

            // ---------- Jawa ----------
            [
                'destination_name' => 'Candi Borobudur',
                'wilayah' => 'jawa',
                'provinsi' => 'Jawa Tengah',
                'total_visitor' => 21750,
                'deskripsi' => 'Candi Buddha terbesar di dunia. Paling berkesan bila dikunjungi pagi hari saat kabut masih menutupi lembah di sekelilingnya.',
            ],
            [
                'destination_name' => 'Karimunjawa',
                'wilayah' => 'jawa',
                'provinsi' => 'Jawa Tengah',
                'total_visitor' => 14200,
                'deskripsi' => 'Gugusan pulau di utara Jepara dengan laut tenang dan terumbu karang dangkal. Umumnya diambil sebagai paket 3 hari 2 malam.',
            ],
            [
                'destination_name' => 'Bromo Tengger Semeru',
                'wilayah' => 'jawa',
                'provinsi' => 'Jawa Timur',
                'total_visitor' => 26700,
                'deskripsi' => 'Lautan pasir dan matahari terbit dari Penanjakan. Perjalanan dimulai dini hari, jadi kami sarankan menginap di sekitar Malang atau Probolinggo.',
            ],
            [
                'destination_name' => 'Kawah Ijen Banyuwangi',
                'wilayah' => 'jawa',
                'provinsi' => 'Jawa Timur',
                'total_visitor' => 16800,
                'deskripsi' => 'Danau kawah belerang dengan api biru yang hanya terlihat sebelum fajar. Perlu kondisi fisik yang siap untuk pendakian sekitar dua jam.',
            ],
            [
                'destination_name' => 'Malioboro',
                'wilayah' => 'jawa',
                'provinsi' => 'DI Yogyakarta',
                'total_visitor' => 18200,
                'deskripsi' => 'Pusat oleh-oleh dan kuliner malam Yogyakarta. Hampir selalu jadi penutup rangkaian perjalanan rombongan kami.',
            ],
            [
                'destination_name' => 'Pantai Indrayanti',
                'wilayah' => 'jawa',
                'provinsi' => 'DI Yogyakarta',
                'total_visitor' => 12400,
                'deskripsi' => 'Pantai berpasir putih di Gunungkidul dengan fasilitas lengkap. Bisa digabung dengan Pantai Krakal dan Drini dalam satu hari.',
            ],
            [
                'destination_name' => 'Pantai Parangtritis',
                'wilayah' => 'jawa',
                'provinsi' => 'DI Yogyakarta',
                'total_visitor' => 9800,
                'deskripsi' => 'Pantai paling dikenal di selatan Yogyakarta, terkenal dengan pemandangan senjanya. Ombaknya besar, jadi tidak untuk berenang.',
            ],
            [
                'destination_name' => 'Tebing Breksi',
                'wilayah' => 'jawa',
                'provinsi' => 'DI Yogyakarta',
                'total_visitor' => 7350,
                'deskripsi' => 'Bekas tambang batu kapur yang diubah jadi taman dengan ukiran tebing. Dekat dengan Candi Prambanan dan Candi Ijo.',
            ],
            [
                'destination_name' => 'Goa Pindul',
                'wilayah' => 'jawa',
                'provinsi' => 'DI Yogyakarta',
                'total_visitor' => 5600,
                'deskripsi' => 'Susur goa dengan ban pelampung menyusuri sungai bawah tanah. Aman untuk pemula dan cocok untuk rombongan sekolah.',
            ],

            // ---------- Bali & Nusa Tenggara ----------
            [
                'destination_name' => 'Pantai Kuta Bali',
                'wilayah' => 'bali_nusa',
                'provinsi' => 'Bali',
                'total_visitor' => 31400,
                'deskripsi' => 'Pantai paling ramai di Bali, mudah dijangkau dari bandara. Biasanya jadi titik pertama rangkaian study tour Bali.',
            ],
            [
                'destination_name' => 'Nusa Penida',
                'wilayah' => 'bali_nusa',
                'provinsi' => 'Bali',
                'total_visitor' => 22500,
                'deskripsi' => 'Pulau di tenggara Bali dengan tebing Kelingking dan Broken Beach. Perlu penyeberangan cepat dari Sanur, sebaiknya berangkat pagi.',
            ],
            [
                'destination_name' => 'Labuan Bajo',
                'wilayah' => 'bali_nusa',
                'provinsi' => 'Nusa Tenggara Timur',
                'total_visitor' => 12900,
                'deskripsi' => 'Pintu masuk Taman Nasional Komodo dan titik awal pelayaran ke Padar serta Pink Beach. Umumnya paket 3–4 hari dengan kapal.',
            ],
            [
                'destination_name' => 'Gili Trawangan',
                'wilayah' => 'bali_nusa',
                'provinsi' => 'Nusa Tenggara Barat',
                'total_visitor' => 11200,
                'deskripsi' => 'Pulau kecil tanpa kendaraan bermotor di barat Lombok. Berkeliling cukup dengan sepeda atau cidomo.',
            ],

            // ---------- Kalimantan ----------
            [
                'destination_name' => 'Kepulauan Derawan',
                'wilayah' => 'kalimantan',
                'provinsi' => 'Kalimantan Timur',
                'total_visitor' => 5300,
                'deskripsi' => 'Gugusan pulau dengan penyu dan danau ubur-ubur tanpa sengat di Kakaban. Perjalanan antarpulau memakai speedboat.',
            ],
            [
                'destination_name' => 'Tanjung Puting',
                'wilayah' => 'kalimantan',
                'provinsi' => 'Kalimantan Tengah',
                'total_visitor' => 2800,
                'deskripsi' => 'Taman nasional tempat rehabilitasi orangutan, disusuri dengan perahu klotok menginap. Cocok untuk perjalanan edukatif.',
            ],

            // ---------- Sulawesi ----------
            [
                'destination_name' => 'Bunaken',
                'wilayah' => 'sulawesi',
                'provinsi' => 'Sulawesi Utara',
                'total_visitor' => 6900,
                'deskripsi' => 'Taman laut dengan dinding karang curam di utara Manado. Salah satu titik selam paling dikenal di Indonesia.',
            ],
            [
                'destination_name' => 'Wakatobi',
                'wilayah' => 'sulawesi',
                'provinsi' => 'Sulawesi Tenggara',
                'total_visitor' => 3900,
                'deskripsi' => 'Empat pulau utama dengan terumbu karang yang masih terjaga. Perjalanan perlu perencanaan lebih awal karena akses penerbangannya terbatas.',
            ],

            // ---------- Maluku & Papua ----------
            [
                'destination_name' => 'Banda Neira',
                'wilayah' => 'maluku_papua',
                'provinsi' => 'Maluku',
                'total_visitor' => 3100,
                'deskripsi' => 'Kepulauan rempah bersejarah dengan benteng peninggalan kolonial dan laut yang jernih. Perjalanan panjang, tetapi sepadan.',
            ],
            [
                'destination_name' => 'Raja Ampat',
                'wilayah' => 'maluku_papua',
                'provinsi' => 'Papua Barat Daya',
                'total_visitor' => 4800,
                'deskripsi' => 'Gugusan karst di atas laut dengan keanekaragaman hayati laut tertinggi di dunia. Umumnya paket 5 hari dengan homestay lokal.',
            ],
        ];
    }
}
