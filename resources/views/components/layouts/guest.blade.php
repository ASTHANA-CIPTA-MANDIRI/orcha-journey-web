<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Orcha Journey — Open Trip, Private Trip, Study Tour & Sewa Kendaraan' }}</title>
    <meta name="description"
        content="Orcha Journey melayani open trip, private trip, study tour, serta sewa mobil, HiAce, dan bus pariwisata di Yogyakarta dan sekitarnya.">
    <meta name="theme-color" content="#001220">


    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800;900&family=Great+Vibes&display=swap"
        rel="stylesheet">

    @vite(['resources/css/new-homepage.css', 'resources/js/new-homepage.js'])
</head>

@php
    $waNumber = config('orcha.whatsapp');
    $waLink = 'https://api.whatsapp.com/send?phone=' . $waNumber . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya soal paket wisata.');
    // Menu utama. Tiap butir menyimpan sendiri kapan dianggap "sedang dibuka",
    // sehingga penanda halaman aktif ikut menyala di menu desktop dan mobile.
    // 'ikon' hanya dipakai menu mobile. Di layar sempit menunya berupa daftar
    // tegak yang panjang, dan tanpa ikon tiap barisnya terlihat sama — mata
    // harus membaca satu per satu. Menu desktop tetap teks saja: berjajar
    // mendatar, ikon di situ justru menambah keramaian tanpa menambah petunjuk.
    $navLinks = [
        ['label' => 'Beranda', 'url' => route('home'), 'aktif' => request()->routeIs('home'), 'ikon' => 'heroicon-o-home'],
        ['label' => 'Tentang Kami', 'url' => route('tentang-kami'), 'aktif' => request()->routeIs('tentang-kami'), 'ikon' => 'heroicon-o-users'],
        ['label' => 'Paket Wisata', 'url' => route('paket-wisata'), 'aktif' => request()->routeIs('paket-wisata'), 'ikon' => 'heroicon-o-map'],
        ['label' => 'Sewa Kendaraan', 'url' => route('sewa-kendaraan'), 'aktif' => request()->routeIs('sewa-kendaraan'), 'ikon' => 'heroicon-o-truck'],
        ['label' => 'Destinasi', 'url' => route('destinasi'), 'aktif' => request()->routeIs('destinasi'), 'ikon' => 'heroicon-o-map-pin'],
        ['label' => 'Kontak', 'url' => route('kontak'), 'aktif' => request()->routeIs('kontak'), 'ikon' => 'heroicon-o-chat-bubble-left-right'],
    ];
@endphp

<body class="antialiased text-slate-700 bg-white overflow-x-hidden">

    {{-- ============ PRELOADER ============ --}}
    <div id="preloader" class="fixed inset-0 z-[9999] bg-orcha-navy">
        {{-- Tidak ada satu pun berkas gambar di sini, dan itu disengaja.

             Gambar butuh permintaan tersendiri ke server. Pada layar MUAT itu
             jalan buntu: berkasnya sendiri harus tiba lebih dulu, padahal
             kedatangan berkas-berkas itulah yang sedang ditunggu. Semua yang
             ada di bawah ini tergambar seketika bersama halamannya — teks,
             gradasi, dan garis. --}}

        {{-- Foto latar, dengan cara yang tidak menahan apa pun.

             Lapis pertama: pratinjau 28x15 piksel yang ditulis LANGSUNG di
             dalam CSS sebagai data. Besarnya 834 bita, tidak butuh permintaan
             ke server, dan tergambar seketika bersama halamannya — jadi layar
             ini berfoto sejak bingkai pertama, bukan hitam menunggu.

             Lapis kedua: fotonya yang sebenarnya, dipasang tembus pandang dan
             baru dimunculkan setelah benar-benar termuat (onload). Kalau
             lambat, atau tidak datang sama sekali, yang terlihat tetap
             pratinjau buramnya — bukan bidang kosong.

             Inilah sebabnya foto bisa dipakai di sini sementara berkas gambar
             biasa tidak: yang harus tiba lebih dulu cuma 834 bita yang sudah
             ikut di dalam CSS. --}}
        <div class="preloader-foto" aria-hidden="true">
            <img src="{{ asset('images/HERO/destinasi.jpg') }}" alt="" decoding="async"
                onload="this.classList.add('siap')">
        </div>

        <div class="preloader-tirai" aria-hidden="true"></div>
        <div class="preloader-kisi" aria-hidden="true"></div>

        <div class="preloader-content">
            <div class="preloader-bawah">
                {{-- Angkanya TERISI dari bawah seiring muatannya: warnanya
                     dipotong huruf, jadi digitnya sendiri yang jadi takarannya.
                     Batang di dasar layar mengulang hal yang sama untuk yang
                     melihat sekilas. --}}
                <div class="preloader-angka">
                    <span id="preloader-percentage">0</span><span class="persen">%</span>
                </div>

                <p class="preloader-tulisan">
                    Menyiapkan<br>perjalanan Anda
                </p>
            </div>
        </div>

        <div class="preloader-garis" aria-hidden="true"><span></span></div>
    </div>

    {{-- ============ NAVBAR ============ --}}
    <nav x-data="{ open: false, scrolled: false }" x-init="scrolled = window.scrollY > 20"
        @scroll.window="scrolled = window.scrollY > 20" @keydown.escape.window="open = false"
        :class="(scrolled || open) ? 'bg-white/95 backdrop-blur-md shadow-lg shadow-orcha-navy/5' : 'bg-transparent'"
        class="fixed inset-x-0 top-0 z-[900] transition-all duration-300">

        <div class="container-orcha">
            <div class="flex items-center justify-between transition-all duration-300"
                :class="(scrolled || open) ? 'h-16' : 'h-20 lg:h-24'">

                <a href="#beranda" class="flex items-center gap-2 shrink-0 sm:gap-3 group">
                    <img src="{{ asset('orcha-logo-only.png') }}" alt="Logo Orcha Journey" width="56" height="56"
                        class="object-contain w-10 h-10 transition-transform duration-300 sm:w-12 sm:h-12 group-hover:scale-105">
                    <span class="text-lg font-black tracking-tight uppercase sm:text-xl lg:text-2xl font-heading"
                        :class="(scrolled || open) ? 'text-orcha-navy' : 'text-white'">
                        Orcha <span class="text-orcha-sky">Journey</span>
                    </span>
                </a>

                <div class="items-center hidden gap-6 lg:flex xl:gap-8">
                    @foreach ($navLinks as $item)
                        <a href="{{ $item['url'] }}" @if ($item['aktif']) aria-current="page" @endif
                            class="relative text-sm font-semibold transition-colors hover:text-orcha-sky"
                            :class="(scrolled || open)
                                ? '{{ $item['aktif'] ? 'text-orcha-ocean' : 'text-slate-600' }}'
                                : '{{ $item['aktif'] ? 'text-orcha-sun nav-link-hero' : 'text-white nav-link-hero' }}'">
                            {{ $item['label'] }}
                            @if ($item['aktif'])
                                <span class="absolute -bottom-2 inset-x-0 h-0.5 rounded-full bg-current"></span>
                            @endif
                        </a>
                    @endforeach

                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                        class="btn-orcha btn-orcha-sun !py-2.5 !px-6 !text-sm">
                        <x-bi-whatsapp class="w-4 h-4" />
                        Hubungi Kami
                    </a>
                </div>

                {{-- Tiga garis yang BERUBAH jadi silang, bukan dua gambar yang
                     bertukar tempat. Bertukar gambar, perubahannya terjadi
                     seketika dan tidak menerangkan apa-apa; bergerak, ia
                     menunjukkan bahwa tombol yang sama kini menutup apa yang
                     tadi dibukanya. --}}
                <button type="button" @click="open = !open"
                    :aria-label="open ? 'Tutup menu' : 'Buka menu'" :aria-expanded="open"
                    :class="[(scrolled || open) ? 'text-orcha-navy' : 'text-white', open ? 'menu-tombol-buka' : '']"
                    class="p-2 transition menu-tombol rounded-xl lg:hidden hover:bg-orcha-foam/60">
                    <span class="menu-garis"></span>
                    <span class="menu-garis"></span>
                    <span class="menu-garis"></span>
                </button>
            </div>
        </div>

        {{-- ============ MENU MOBILE ============
             Panel bertirai, bukan panel yang menempel begitu saja di bawah
             kepala halaman.

             Sebelumnya halaman di belakangnya tetap terlihat penuh dan tetap
             bisa digulung, jadi menunya terbaca sebagai potongan halaman —
             bukan sebagai lapisan yang sedang dibuka. Tirai gelap memisahkan
             keduanya, dan gulirnya dikunci selama menu terbuka. --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="open = false"
            {{-- Dimulai di bawah bilah kepala (top-16 = tinggi bilah saat menu
                 terbuka), bukan dari puncak layar.

                 Menutupi seluruh layar, tirainya duduk di belakang latar navbar
                 yang tembus 95% dan ikut mengusamkan logonya — terlihat kusam
                 tanpa sebab yang jelas. --}}
            class="fixed inset-x-0 bottom-0 top-16 bg-orcha-navy/45 backdrop-blur-[2px] lg:hidden"
            aria-hidden="true">
        </div>

        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-3"
            x-effect="document.body.style.overflow = open ? 'hidden' : ''"
            class="menu-mobile lg:hidden">
            <div class="container-orcha">
                <div class="py-4">
                    {{-- Tiap butir jadi ubin, bukan baris bergaris tipis. Ikonnya
                         memberi mata titik pegangan, dan yang sedang dibuka
                         ditandai dengan bidang berwarna — bukan cuma titik kecil
                         di ujung kanan yang mudah terlewat. --}}
                    <div class="grid gap-1.5">
                        @foreach ($navLinks as $item)
                            <a href="{{ $item['url'] }}" @click="open = false"
                                @if ($item['aktif']) aria-current="page" @endif
                                class="menu-ubin {{ $item['aktif'] ? 'menu-ubin-aktif' : '' }}">
                                <span class="menu-ubin-ikon">
                                    <x-dynamic-component :component="$item['ikon']" class="w-5 h-5" />
                                </span>
                                <span class="menu-ubin-teks">{{ $item['label'] }}</span>
                                @if ($item['aktif'])
                                    <span class="menu-ubin-tanda">Sedang dibuka</span>
                                @else
                                    <x-heroicon-o-chevron-right class="w-4 h-4 menu-ubin-panah" />
                                @endif
                            </a>
                        @endforeach
                    </div>

                    {{-- Cara menghubungi ditaruh di dasar panel, terpisah garis:
                         itu tindakan, bukan halaman, dan menaruhnya sebaris
                         dengan menu membuat keduanya terlihat setara. --}}
                    <div class="menu-kaki">
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-sun w-full">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Hubungi via WhatsApp
                        </a>

                        <p class="menu-kaki-jam">
                            <x-heroicon-s-clock class="w-4 h-4" />
                            Setiap hari, 08.00 &ndash; 21.00 WIB
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </nav>

    <main>
        {{ $slot }}
    </main>

    {{-- ============ FOOTER ============ --}}
    <footer id="kontak" class="relative overflow-hidden text-slate-300 bg-orcha-navy">
        <div class="absolute inset-0 pointer-events-none opacity-40"
            style="background-image: radial-gradient(60% 50% at 15% 0%, rgba(26,176,226,.35), transparent 70%), radial-gradient(50% 40% at 90% 100%, rgba(255,199,78,.18), transparent 70%);">
        </div>

        <div class="relative container-orcha py-14 sm:py-20">
            {{-- Di layar sempit isinya dipusatkan, dari sm ke atas kembali rata kiri.

                 Satu kolom selebar layar dengan teks rata kiri menyisakan pias kosong
                 yang lebar di kanan tiap baris — terbaca seperti tata letak yang
                 belum selesai. Dipusatkan, tiap blok punya sumbu yang sama dan
                 footernya terbaca sebagai satu kesatuan.

                 Begitu kolomnya berdampingan (sm ke atas), rata kiri justru yang
                 benar: mata menyusuri daftar tautan dari satu tepi yang tetap. --}}
            <div class="grid gap-10 text-center sm:text-left sm:grid-cols-2 lg:grid-cols-12 lg:gap-8">

                <div class="lg:col-span-4">
                    <div class="flex items-center justify-center gap-3 mb-5 sm:justify-start">
                        <img src="{{ asset('orcha-logo-only.png') }}" alt="Orcha Journey" width="48" height="48"
                            class="object-contain w-12 h-12">
                        <span class="text-xl font-black tracking-tight text-white uppercase font-heading">Orcha <span
                                class="text-orcha-sky">Journey</span></span>
                    </div>
                    <p class="max-w-sm mx-auto text-sm leading-relaxed text-slate-400 sm:mx-0">
                        Travel agent Yogyakarta untuk open trip, private trip, study tour, dan sewa kendaraan
                        pariwisata. Harga transparan, armada terawat, dan pemandu yang paham medan.
                    </p>
                    <div class="flex justify-center gap-3 mt-6 sm:justify-start">
                        <a href="https://www.instagram.com/{{ config('orcha.instagram') }}/" target="_blank"
                            rel="noopener noreferrer" aria-label="Instagram Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-white/15 bg-white/5 hover:bg-orcha-sky hover:border-orcha-sky">
                            <x-bi-instagram class="w-5 h-5" />
                        </a>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                            aria-label="WhatsApp Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-white/15 bg-white/5 hover:bg-orcha-sky hover:border-orcha-sky">
                            <x-bi-whatsapp class="w-5 h-5" />
                        </a>
                        <a href="mailto:{{ config('orcha.email') }}" aria-label="Email Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-white/15 bg-white/5 hover:bg-orcha-sky hover:border-orcha-sky">
                            <x-heroicon-o-envelope class="w-5 h-5" />
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-sm font-bold tracking-[0.2em] uppercase text-orcha-sun">Layanan</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('paket-wisata', 'open-trip') }}"
                                class="transition hover:text-orcha-sky">Open Trip</a></li>
                        <li><a href="{{ route('paket-wisata', 'private-trip') }}"
                                class="transition hover:text-orcha-sky">Private Trip</a></li>
                        <li><a href="{{ route('paket-wisata', 'study-tour') }}"
                                class="transition hover:text-orcha-sky">Study Tour</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'mobil') }}"
                                class="transition hover:text-orcha-sky">Sewa Mobil</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'hiace') }}"
                                class="transition hover:text-orcha-sky">Sewa HiAce</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'bus') }}"
                                class="transition hover:text-orcha-sky">Sewa Bus</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-sm font-bold tracking-[0.2em] uppercase text-orcha-sun">Jelajahi</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('home') }}" class="transition hover:text-orcha-sky">Beranda</a></li>
                        <li><a href="{{ route('tentang-kami') }}" class="transition hover:text-orcha-sky">Tentang
                                Kami</a></li>
                        <li><a href="{{ route('destinasi') }}" class="transition hover:text-orcha-sky">Destinasi</a>
                        </li>
                        <li><a href="{{ route('testimoni') }}" class="transition hover:text-orcha-sky">Testimoni</a>
                        </li>
                        <li><a href="{{ route('pendaftaran-open-trip') }}"
                                class="transition hover:text-orcha-sky">Daftar Open Trip</a></li>
                        <li><a href="{{ route('kontak') }}" class="transition hover:text-orcha-sky">Kontak</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-sm font-bold tracking-[0.2em] uppercase text-orcha-sun">Informasi</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('faq') }}" class="transition hover:text-orcha-sky">FAQ</a></li>
                        <li><a href="{{ route('syarat-ketentuan') }}" class="transition hover:text-orcha-sky">Syarat &
                                Ketentuan</a></li>
                        <li><a href="{{ route('ketentuan-pembayaran') }}"
                                class="transition hover:text-orcha-sky">Pembayaran & DP</a></li>
                        <li><a href="{{ route('kebijakan-pengembalian') }}"
                                class="transition hover:text-orcha-sky">Pengembalian Dana</a></li>
                        <li><a href="{{ route('kebijakan-privasi') }}" class="transition hover:text-orcha-sky">Kebijakan
                                Privasi</a></li>
                        <li><a href="{{ route('riwayat-kesehatan') }}" class="transition hover:text-orcha-sky">Riwayat
                                Kesehatan Peserta</a></li>
                        <li><a href="{{ route('konfirmasi-pembayaran') }}"
                                class="transition hover:text-orcha-sky">Konfirmasi Pembayaran</a></li>
                        <li><a href="{{ route('pembatalan') }}" class="transition hover:text-orcha-sky">Ajukan
                                Pembatalan</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-sm font-bold tracking-[0.2em] uppercase text-orcha-sun">Kontak</h4>
                    <ul class="space-y-4 text-sm">
                        <li class="flex items-start justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-map-pin class="w-5 h-5 shrink-0 mt-0.5 text-orcha-sky" />
                            {{-- Lebarnya dibatasi di layar sempit supaya ikon dan teks
                                 terbaca sebagai satu kelompok yang dipusatkan. Dibiarkan
                                 selebar baris, alamat yang panjang mendorong ikonnya
                                 menempel ke tepi kiri layar sementara teksnya di tengah —
                                 keduanya terlihat tidak berhubungan. --}}
                            <span class="max-w-[15rem] sm:max-w-none">{{ config('orcha.alamat') }}</span>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-phone class="w-5 h-5 shrink-0 text-orcha-sky" />
                            <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                class="transition hover:text-orcha-sky">+{{ config('orcha.whatsapp') }}</a>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-envelope class="w-5 h-5 shrink-0 text-orcha-sky" />
                            <a href="mailto:{{ config('orcha.email') }}"
                                class="transition hover:text-orcha-sky">{{ config('orcha.email') }}</a>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-clock class="w-5 h-5 shrink-0 text-orcha-sky" />
                            <span>Setiap hari, 08.00 – 21.00 WIB</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="flex flex-col items-center gap-2 pt-8 mt-12 text-xs text-center border-t border-white/10 text-slate-500 sm:flex-row sm:justify-between sm:text-left">
                <p>&copy; {{ date('Y') }} Orcha Journey. Seluruh hak cipta dilindungi.</p>
                {{-- Slogannya, bukan nama kota: kotanya sudah tersebut di blok
                     alamat tepat di atas baris ini. --}}
                <p>{{ config('orcha.slogan') }}</p>
            </div>
        </div>
    </footer>

    {{-- ============ TOMBOL WHATSAPP MELAYANG ============ --}}
    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer" aria-label="Chat WhatsApp Orcha Journey"
        class="fixed z-[800] flex items-center gap-2 px-4 py-3 font-bold text-white transition-transform duration-300 bg-green-600 rounded-full shadow-2xl bottom-5 right-5 sm:bottom-8 sm:right-8 hover:scale-105">
        <x-bi-whatsapp class="w-6 h-6" />
        <span class="hidden text-sm sm:inline">Chat Kami</span>
    </a>
</body>

</html>
