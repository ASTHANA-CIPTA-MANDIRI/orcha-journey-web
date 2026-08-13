<?php

use App\Models\DestinationPopuler;
use App\Models\Partner;
use App\Models\Car;
use App\Models\Testimoni;
use App\Models\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Orca Journey')] class extends Component {
    public $galleries = [];

    public $destinations = [
        [
            'title' => 'Pantai Depok',
            'bg' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80&w=1920',
            'thumbs' => ['https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1510414842594-a61c69b5ae57?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&q=80&w=400'],
        ],
        [
            'title' => 'Kontrakan Syariah',
            'bg' => 'https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&q=80&w=1920',
            'thumbs' => ['https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400'],
        ],
        [
            'title' => 'Pantai Parangtritis',
            'bg' => 'https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&q=80&w=1920',
            'thumbs' => ['https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400'],
        ],
        [
            'title' => 'Burjo Andeska',
            'bg' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80&w=1920',
            'thumbs' => ['https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1506477331477-33d5d8b3dc85?auto=format&fit=crop&q=80&w=400', 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=400'],
        ],
    ];

    public array $bookingSteps = [
        [
            'icon' => 'map-pin', // nanti bisa di-blade pakai <x-icon name="map-pin" />
            'title' => 'Pilih Destinasi',
            'desc' => 'Pilih paket wisata yang sesuai dengan kebutuhan Anda',
        ],
        [
            'icon' => 'users',
            'title' => 'Isi Data Pengunjung',
            'desc' => 'Lengkapi data diri dan jumlah peserta wisata',
        ],
        [
            'icon' => 'check-circle',
            'title' => 'Konfirmasi & Bayar',
            'desc' => 'Lakukan pembayaran dan tunggu konfirmasi dari kami',
        ],
    ];

    public function mount()
    {
        $this->galleries = [asset('images/pantai-atas.jpg'), asset('images/pantai-atas.jpg'), asset('images/pantai-atas.jpg'), asset('images/pantai-atas.jpg'), asset('images/pantai-atas.jpg'), asset('images/pantai-atas.jpg')];
    }

    public function with()
    {
        return [
            'packages' => TravelPackage::limit(3)->get(),
            'topDestinations' => DestinationPopuler::limit(3)->get(),
            'bookingSteps' => $this->bookingSteps,
            'testimonials' => Testimoni::limit(3)->get(),
            'partners' => Partner::all(),
            'cars' => Car::limit(6)->get(),
            'galleries' => $this->galleries,
        ];
    }
}; ?>

<div>
    {{-- whatsapp button --}}
    <div class="fixed bottom-10 right-10 z-50">
        <a href="https://wa.me/6289509882219?text=Halo%20min,%20saya%20ingin%20memesan%20produk%20Anda." target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-10 right-10 z-50 bg-green-700 rounded-full p-3 shadow-lg hover:scale-110 transition-transform duration-300">
            <x-bi-whatsapp class="h-8 w-8 text-white" />
        </a>
    </div>

    {{-- hero section --}}
    <section class="relative flex items-center justify-center w-full min-h-screen px-4 bg-blue-950 intro-section">
        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center pointer-events-none">
            <h1 class="hero-title text-[15vw] font-black leading-none text-sky-500 uppercase">ORCHA</h1>
            <h1 class="hero-title-outline text-[15vw] font-black leading-none uppercase text-outline-white">JOURNEY</h1>
        </div>
        <img src="https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&q=80&w=800"
            class="hero-img absolute z-0 w-3/4 md:w-1/3 h-auto aspect-[9/14] object-cover opacity-60 rounded-3xl shadow-2xl"
            alt="Travel Hero">
        <div class="absolute z-20 bottom-10">
            <a href="#bundling"
                class="px-8 py-4 font-bold text-blue-950 transition bg-amber-400 rounded-full shadow-lg hover:bg-amber-300 hover:scale-105">
                Mulai Petualangan
            </a>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="bundling" class="min-h-screen flex flex-col items-center justify-center py-24 bg-slate-50">
        <div class="container px-4 mx-auto max-w-7xl">
            <div class="mb-12 text-center">
                <p class="mb-2 font-semibold tracking-wider uppercase text-orange-300">
                    Paket Wisata</p>
                <h3 class="text-3xl font-bold font-heading text-blue-950 md:text-4xl">Pilihan Paket Bundling</h3>
                <p class="max-w-2xl mx-auto mt-4 text-slate-600">
                    Kami menyediakan berbagai paket wisata yang dapat disesuaikan dengan kebutuhan Anda
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @foreach ($packages as $package)
                    <div
                        class="flex flex-col p-8 shadow-xl rounded-2xl overflow-hidden bundle-card {{ $package->is_best_choice ? 'ring-4 ring-orange-300 md:scale-105 transform' : '' }}">
                        <h3 class="text-2xl font-bold text-blue-950 font-heading">{{ $package->name }}</h3>
                        <div class="mb-6">
                            <p
                                class="text-4xl font-black mt-4 {{ $package->is_best_choice ? 'text-orange-300' : 'text-sky-500' }}">
                                Rp {{ number_format($package->price, 0, ',', '.') }}
                            </p>
                            @if ($package->original_price > 0)
                                <span class="text-sm line-through text-slate-400">
                                    Rp {{ number_format($package->original_price, 0, ',', '.') }}
                                </span>
                            @endif

                            @if ($package->discount_percentage)
                                <div
                                    class="inline-block px-2 py-1 ml-2 text-xs font-semibold text-red-600 bg-red-100 rounded">
                                    Hemat {{ $package->discount_percentage }}%
                                </div>
                            @endif
                        </div>
                        <ul class="flex-1 mt-6 space-y-3 text-slate-600">
                            @if ($package->is_best_choice)
                                <div
                                    class="absolute top-0 right-0 px-4 py-1 text-sm font-semibold text-white rounded-bl-lg bg-orange-300">
                                    BEST CHOICE
                                </div>
                            @endif
                        </ul>

                        <div class="space-y-3">
                            <p class="font-semibold text-slate-900">Destinasi Wisata:</p>
                            @foreach ($package->destination_list ?? [] as $destination)
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 {{ $package->is_best_choice ? 'text-orange-300' : 'text-cyan-500' }}"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-slate-600">{{ $destination }}</span>
                                </div>
                            @endforeach

                        </div>
                        <button
                            class="w-full py-3 mt-8 font-bold text-white transition rounded-xl bg-blue-950 hover:bg-sky-500">Pesan
                            Sekarang</button>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Top Destinations -->
    <section class="relative w-full h-screen overflow-hidden bg-black dest-wrapper">
        <div class="mb-12 absolute z-99 top-32 left-10">
            <p class="mb-2 font-semibold tracking-wide uppercase text-orange-300">Destinasi Populer
            </p>
            <h3 class="text-3xl font-bold text-gray-200 md:text-4xl">Tempat Wisata Favorit</h3>
        </div>

        @foreach ($destinations as $index => $dest)
            <div class="absolute inset-0 w-full h-full dest-item" style="z-index: {{ $index === 0 ? 1 : 0 }};">
                <div class="absolute inset-0 w-full h-full dest-inner"
                    style="{{ $index === 0 ? '' : 'visibility: hidden; opacity: 0;' }}">
                    <div class="absolute inset-0 bg-center bg-cover image-bg"
                        style="background-image: url('{{ $dest['bg'] }}'); filter: brightness(0.5);"></div>
                    <div class="relative flex flex-col items-center justify-between w-full h-full p-8 md:flex-row">
                        <div class="relative z-10 w-full content md:w-2/3">
                            <h2
                                class="text-white text-[12vw] md:text-[6.5vw] font-black uppercase leading-tight drop-shadow-[0_10px_10px_rgba(0,0,0,0.8)]">
                                {{ $dest['title'] }}
                            </h2>
                        </div>

                        <div
                            class="relative z-0 flex flex-row justify-end w-full gap-4 py-10 md:py-16 list-image md:flex-col md:w-auto md:h-full">
                            @foreach ($dest['thumbs'] as $thumb)
                                <img src="{{ $thumb }}"
                                    class="object-cover border-4 border-white shadow-2xl rounded-xl w-24 h-32 md:w-40 lg:w-48 md:h-1/3"
                                    alt="Thumb">
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="py-24 bg-white car-section">
        <div class="container px-4 mx-auto max-w-7xl">
            <h2 class="mb-16 text-4xl font-bold text-center text-blue-950">Armada Tersedia</h2>
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @foreach ($cars as $car)
                    <div class="overflow-hidden border border-gray-100 shadow-lg car-card rounded-2xl group">
                        <div class="relative h-48 overflow-hidden bg-gray-200">
                            <img src="{{ $car->image ? $car->image : 'https://imgx.gridoto.com/crop/154x203:3090x2215/700x465/filters:watermark(file/2017/gridoto/img/watermark.png,5,5,60)/photo/2024/12/04/whatsapp-image-2024-12-04-at-22-20241204102536.jpeg' }}"
                                class="object-cover w-full h-full transition duration-500 group-hover:scale-110"
                                alt="{{ $car->brand }}">
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-blue-950">{{ $car->brand }}</h3>
                            <p class="text-sm font-semibold tracking-wide uppercase text-sky-500">{{ $car->type }}
                            </p>

                            <div
                                class="flex items-center justify-between py-3 mb-4 mt-4 text-sm text-gray-600 border-t border-b border-gray-100">
                                <span class="flex items-center"><svg class="w-4 h-4 mr-1 text-amber-500"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z">
                                        </path>
                                    </svg> {{ $car->capacity }}</span>
                                <span class="flex items-center"><svg class="w-4 h-4 mr-1 text-amber-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg> {{ $car->transmission }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xl font-bold text-blue-950">
                                    Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                                </p>
                                <button
                                    class="px-4 py-2 font-semibold text-white transition rounded-lg bg-blue-950 hover:bg-amber-400">Sewa</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- galery sections --}}

    <section class="py-24 overflow-hidden bg-slate-50 gallery-section">
        <div class="container px-4 mx-auto mb-12 text-center max-w-7xl">
            <h2 class="mb-4 text-4xl font-bold text-blue-950">Momen Pelanggan</h2>
            <p class="italic tracking-widest text-gray-500">Kisah perjalanan bersama kami</p>
        </div>

        <div class="relative flex w-full mb-6 overflow-hidden">
            <div class="flex items-center w-max gap-4 px-2 gallery-track-left">
                @foreach (array_merge($galleries, $galleries) as $img)
                    <div
                        class="relative flex-shrink-0 w-64 md:w-80 h-48 md:h-56 overflow-hidden shadow-sm cursor-pointer rounded-2xl group hover:shadow-xl transition-all">
                        <img src="{{ $img }}"
                            class="object-cover w-full h-full transition duration-700 transform group-hover:scale-110"
                            alt="Gallery">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="relative flex w-full overflow-hidden">
            <div class="flex items-center w-max gap-4 px-2 gallery-track-right">
                @foreach (array_merge($galleries, $galleries) as $img)
                    <div
                        class="relative flex-shrink-0 w-64 md:w-80 h-48 md:h-56 overflow-hidden shadow-sm cursor-pointer rounded-2xl group hover:shadow-xl transition-all">
                        <img src="{{ $img }}"
                            class="object-cover w-full h-full transition duration-700 transform group-hover:scale-110"
                            alt="Gallery">
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- {{-- Reviews Section --}} -->
    <section id="reviews" class="px-4 py-16 sm:px-6 lg:px-8 scroll-mt-40 relative">
        <div class="absolute z-10 inset-0 overflow-hidden">
            <img loading="lazy" src="{{ asset('/images/pantai-atas.jpg') }}" class="w-full h-full object-cover"
                alt="">
        </div>
        <div
            class="absolute z-20 inset-0 bg-linear-to-br from-black/70 to-cyan-500/50 flex items-center justify-center">
            <img loading="lazy" data-aos="zoom-in" data-aos-delay="100" src="{{ asset('/orcha-logo.png') }}"
                class="h-[300px] w-[300px]" alt="">
        </div>
        <div class="mx-auto max-w-7xl relative z-40">
            <div class="grid items-start gap-12 md:grid-cols-2">
                <div data-aos="zoom-in" data-aos-delay="200" class="sticky top-24">
                    <p class="mb-4 font-semibold tracking-wide uppercase text-orange-300">Testimoni</p>
                    <h3 class="text-4xl font-bold leading-tight text-white md:text-5xl">
                        Apa kata pelanggan kami sebelumnya?
                    </h3>
                </div>

                <div data-aos="fade-left" data-aos-delay="200" class="space-y-6">
                    @foreach ($testimonials as $idx => $testimoni)
                        <div class="p-6 bg-white/30 backdrop-blur-lg shadow-lg rounded-3xl">
                            <div class="flex items-center gap-4 mb-4">
                                @if ($testimoni->avatar)
                                    <img loading="lazy" src="{{ $testimoni->avatar }}"
                                        alt="{{ $testimoni->avatar }}" class="rounded-full w-14 h-14 object-cover" />
                                @else
                                    <x-heroicon-o-user-circle class="text-slate-100 h-14 w-14 rounded-full " />
                                @endif
                                <div class="flex-1">
                                    <h4 class="font-bold text-white">{{ $testimoni->customer_name }}</h4>
                                    <div class="flex gap-1">
                                        @for ($i = 0; $i < $testimoni->rating; $i++)
                                            <x-heroicon-s-star class="w-4 h-4 text-orange-300" />
                                        @endfor
                                    </div>
                                </div>
                            </div>
                            <p class="italic text-slate-100">"{{ $testimoni->testimonial }}"</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- {{-- Partners Section --}} -->
    <section class="px-4 py-20 bg-white sm:px-6 lg:px-8 scroll-mt-10">
        <div class="py-10 mx-auto max-w-7xl">
            <div data-aos="zoom-in" data-aos-delay="200" class="mb-12 text-center">
                <p class="mb-2 font-semibold tracking-wide uppercase text-orange-300">Partner Terpercaya</p>
                <h3 class="text-3xl font-bold text-gray-900 md:text-4xl">Armada & Mitra Perjalanan</h3>
            </div>

            <div class="flex flex-wrap items-center gap-4 justify-center" data-aos="zoom-in" data-aos-delay="300">
                @foreach ($partners as $partner)
                    <div
                        class="flex items-center justify-center py-6 px-10 transition bg-gray-50 rounded-3xl hover:shadow-lg">
                        <div class="text-center">
                            <div
                                class="flex items-center justify-center w-16 h-16 mx-auto mb-2 text-2xl font-bold text-white rounded-full bg-cyan-500 overflow-hidden">
                                @if ($partner->foto)
                                    <img loading="lazy" src="{{ $partner->foto }}" alt=""
                                        class="w-full h-full object-cover">
                                @else
                                    <p>{{ $partner->initials() }}</p>
                                @endif
                            </div>
                            <p class="text-sm font-semibold text-gray-700">{{ $partner->partner_name }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
