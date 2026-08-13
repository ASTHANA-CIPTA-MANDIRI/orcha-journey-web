<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600&family=Dancing+Script&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <!-- Scripts -->
    @vite(['resources/css/new-homepage.css', 'resources/js/new-homepage.js'])
</head>

<body class="main-text overflow-x-hidden antialiased text-gray-900" x-data="{ mobileMenuOpen: false }">
    <nav x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)"
        :class="scrolled ? 'bg-white/90 backdrop-blur-md shadow-sm border-gray-200/50' : 'bg-transparent border-transparent'"
        class="fixed top-0 z-[999] inset-x-0 transition-all duration-300 border-b">

        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 transition-all duration-300"
                :class="scrolled ? 'h-16' : 'h-20'">

                <a href="/" class="flex items-center gap-3 group">
                    <div
                        class="flex items-center flex-shrink-0 overflow-hidden h-12 transition-transform duration-300 group-hover:scale-105">
                        <img src="{{ asset('/orcha-logo-only.png') }}" alt="Orcha Journey Logo"
                            class="h-full object-contain">
                    </div>
                    <p class="hidden sm:block text-2xl font-black tracking-tight uppercase"
                        :class="scrolled ? 'text-dark' : 'text-white'">
                        Orcha <span class="text-sky-500">Journey</span>
                    </p>
                </a>

                <div class="items-center hidden space-x-8 lg:flex">
                    <a href="/" class="text-sm font-bold tracking-wide text-sky-500 transition-colors">Beranda</a>

                    <a href="/paket-wisata" class="text-sm font-bold tracking-wide hover:text-sky-500 transition-colors"
                        :class="scrolled ? 'text-slate-600' : 'text-slate-300'">Paket Wisata</a>

                    <a href="/sewa-armada" class="text-sm font-bold tracking-wide  hover:text-sky-500 transition-colors"
                        :class="scrolled ? 'text-slate-600' : 'text-slate-300'">Sewa Armada</a>

                    <a href="/tentang-kami" class="text-sm font-bold tracking-wide hover:text-sky-500 transition-colors"
                        :class="scrolled ? 'text-slate-600' : 'text-slate-300'">Tentang Kami</a>

                    <a href="https://wa.me/62800000000" target="_blank"
                        class="px-6 py-2.5 text-sm font-bold text-blue-950 transition-all bg-amber-400 rounded-full hover:bg-amber-300 hover:shadow-lg hover:-translate-y-0.5">
                        Hubungi Kami
                    </a>
                </div>

                <div class="lg:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen"
                        :class="scrolled ? 'text-slate-600' : 'text-slate-300'"
                        class="p-2 transition-colors rounded-lg hover:text-sky-500 hover:bg-slate-100 focus:outline-none">
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4"
            class="absolute inset-x-0 bg-white border-b border-gray-100 shadow-xl lg:hidden top-full">

            <div class="flex flex-col px-6 py-6 space-y-4">
                <a href="/" class="text-base font-bold text-sky-500">Beranda</a>
                <a href="/paket-wisata" class="text-base font-bold text-slate-600 hover:text-sky-500">Paket Wisata</a>
                <a href="/sewa-armada" class="text-base font-bold text-slate-600 hover:text-sky-500">Sewa Armada</a>
                <a href="/tentang-kami" class="text-base font-bold text-slate-600 hover:text-sky-500">Tentang Kami</a>

                <hr class="border-gray-100 my-2">

                <a href="https://wa.me/62800000000" target="_blank"
                    class="block py-3 mt-2 text-center text-base font-bold text-blue-950 transition-colors bg-amber-400 rounded-xl hover:bg-amber-300">
                    Hubungi Kami via WhatsApp
                </a>
            </div>
        </div>
    </nav>
    <div>
        {{ $slot }}
    </div>

    {{-- footer section --}}
    <footer data-aos="fade-up" id="contact"
        class="relative px-4 pt-24 pb-12 overflow-hidden text-white bg-slate-900 sm:px-6 lg:px-8">
        <!-- Background Text -->
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <h1
                class="text-[8rem] md:text-[13rem] lg:text-[15rem] font-extrabold tracking-tighter text-white/5 select-none">
                Orcha Journey
            </h1>
        </div>

        <div class="relative mx-auto max-w-7xl">
            <div class="grid gap-8 mb-20 md:grid-cols-3">
                <div>
                    <h3 class="mb-4 text-2xl font-bold text-cyan-400">Orcha Journey</h3>
                    <p class="text-gray-400">
                        Travel agent terpercaya di Yogyakarta yang memberikan harga terbaik dengan pelayanan terbaik.
                    </p>
                </div>

                <div>
                    <h4 class="mb-4 font-bold">Layanan</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#packages" class="transition hover:text-cyan-400">Paket Wisata</a></li>
                        <li><a href="#packages" class="transition hover:text-cyan-400">Custom Tour</a></li>
                        <li><a href="#packages" class="transition hover:text-cyan-400">Tour Guide</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="mb-4 font-bold">Kontak</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-map-pin class="flex-shrink-0 w-5 h-5 mt-1 text-cyan-400" />
                            <span>perumahan GWI, Jl. Durian No. 115, Banguntapan, Bantul, Yogyakarta</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-s-phone class="w-5 h-5 text-cyan-400" />
                            <span>+62 895-0988-2219</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-s-envelope class="w-5 h-5 text-cyan-400" />
                            <span>info@orchajourney.com</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-bi-instagram class="w-5 h-5 text-cyan-400" />
                            <span><a href="https://www.instagram.com/orcha_journey/" rel="noopener noreferrer"
                                    target="_blank">orcha_journey</a></span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="relative pt-8 text-center text-gray-400">
                <p>&copy; 2025 Orcha Journey. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>

</html>
