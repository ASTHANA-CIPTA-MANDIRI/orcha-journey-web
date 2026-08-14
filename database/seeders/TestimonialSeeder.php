<?php

namespace Database\Seeders;

use App\Models\Testimoni;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $testimonials = [
            [
                'customer_name' => 'Budi Santoso',
                'rating' => 5,
                'testimonial' => 'Pelayanan sangat memuaskan! Tour guide ramah dan destinasi wisata sangat menarik. Recommended!',
            ],
            [
                'customer_name' => 'Siti Nurhaliza',
                'rating' => 5,
                'testimonial' => 'Pengalaman liburan terbaik bersama keluarga. Semua sudah diatur dengan baik, tinggal enjoy saja!',
            ],
            [
                'customer_name' => 'Andi Wijaya',
                'rating' => 5,
                'testimonial' => 'Harga terjangkau dengan fasilitas premium. Driver sopan dan hotel nyaman. Pasti akan booking lagi!',
            ],
            [
                'customer_name' => 'Dewi Lestari',
                'rating' => 4,
                'testimonial' => 'Tour sangat menyenangkan, cuma agak terburu-buru di beberapa spot. Overall bagus!',
            ],
            [
                'customer_name' => 'Rudi Hartono',
                'rating' => 5,
                'testimonial' => 'Destinasi wisata lengkap, makanan enak, guide berpengalaman. Worth it banget!',
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimoni::create($testimonial);
        }
    }
}
