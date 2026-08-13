<?php

namespace App\Livewire\Public;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

class NewLandingPage extends Component
{
    public $bundlings = [];

    public $destinations = [];

    public $cars = [];

    public $galleries = [];

    #[On('data-deleted')]
    public function delete()
    {
        return true;

    }

    public function mount()
    {
        // Data Bundling (Desain Konsisten Rounded)
        $this->bundlings = [
            ['name' => 'Paket Hemat', 'price' => 'Rp 1.5M', 'locations' => ['Pantai Pasir Panjang', 'Bukit Jamur', 'Pusat Oleh-oleh'], 'color' => 'bg-white'],
            ['name' => 'Paket Premium', 'price' => 'Rp 3.5M', 'locations' => ['Pulau Lemukutan', 'Resort', 'Snorkeling', 'Dinner'], 'color' => 'bg-sky-50'],
            ['name' => 'Paket Eksklusif', 'price' => 'Rp 5.0M', 'locations' => ['Semua Destinasi', 'Private Boat', 'Fotografer', 'Hotel Bintang 5'], 'color' => 'bg-white'],
        ];

        // Data Destinasi (Untuk Pinned Slider)
        $this->destinations = [
            [
                'title' => 'Pantai Depok',
                'bg' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80&w=1920',
                'thumbs' => [
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1510414842594-a61c69b5ae57?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&q=80&w=400',
                ],
            ],
            [
                'title' => 'Kontrakan Syariah',
                'bg' => 'https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&q=80&w=1920',
                'thumbs' => [
                    'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400',
                ],
            ],
            [
                'title' => 'Pantai Parangtritis',
                'bg' => 'https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&q=80&w=1920',
                'thumbs' => [
                    'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400',
                ],
            ],
            [
                'title' => 'Burjo Andeska',
                'bg' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80&w=1920',
                'thumbs' => [
                    'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400',
                    'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400',
                ],
            ],
        ];

        // Data Mobil (6 Item dengan Spesifikasi)
        $this->cars = [
            ['name' => 'Toyota Avanza', 'type' => 'MPV', 'price' => 'Rp 350.000/hari', 'seats' => '7 Kursi', 'trans' => 'Manual', 'image' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600'],
            ['name' => 'Toyota Innova Reborn', 'type' => 'MPV Premium', 'price' => 'Rp 600.000/hari', 'seats' => '7 Kursi', 'trans' => 'Matic', 'image' => 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&q=80&w=600'],
            ['name' => 'Toyota Hiace', 'type' => 'Minibus', 'price' => 'Rp 1.200.000/hari', 'seats' => '15 Kursi', 'trans' => 'Manual', 'image' => 'https://images.unsplash.com/photo-1520340356584-f9917d1eea6f?auto=format&fit=crop&q=80&w=600'],
            ['name' => 'Mitsubishi Xpander', 'type' => 'MPV', 'price' => 'Rp 400.000/hari', 'seats' => '7 Kursi', 'trans' => 'Matic', 'image' => 'https://images.unsplash.com/photo-1619682817481-e994891cd1f5?auto=format&fit=crop&q=80&w=600'],
            ['name' => 'Toyota Fortuner', 'type' => 'SUV', 'price' => 'Rp 1.500.000/hari', 'seats' => '7 Kursi', 'trans' => 'Matic', 'image' => 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&q=80&w=600'],
            ['name' => 'Honda Brio', 'type' => 'City Car', 'price' => 'Rp 300.000/hari', 'seats' => '5 Kursi', 'trans' => 'Matic', 'image' => 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?auto=format&fit=crop&q=80&w=600'],
        ];

        // Data Galeri (Menggunakan gambar lokal untuk performa / testing)
        $this->galleries = [
            asset('images/pantai-atas.jpg'),
            asset('images/pantai-atas.jpg'),
            asset('images/pantai-atas.jpg'),
            asset('images/pantai-atas.jpg'),
            asset('images/pantai-atas.jpg'),
            asset('images/pantai-atas.jpg'),
        ];
    }

    #[Layout('components.layouts.guest')]
    public function render()
    {
        return view('livewire.public.new-landing-page');
    }
}
