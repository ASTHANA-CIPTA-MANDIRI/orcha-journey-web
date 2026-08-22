<?php

use App\Models\SewaKendaraan\Car;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Orcha Journey — Open Trip, Private Trip, Study Tour & Sewa Kendaraan')] class extends Component {
    /**
     * Empat lini layanan Orcha Journey. Statis karena ini copy penawaran,
     * bukan data yang dikelola admin. Sengaja bukan public property supaya
     * tidak ikut dikirim ke browser lewat snapshot Livewire.
     */
    private function layanan(): array
    {
        return [
            [
                'icon' => 'users',
                'title' => 'Open Trip',
                'desc' => 'Gabung rombongan dengan peserta lain. Biaya patungan, tanggal sudah kami tetapkan, dan berangkat setelah kuota minimal 6 orang terpenuhi.',
                'points' => ['Jadwal keberangkatan sudah ditetapkan', 'Berangkat minimal 6 orang', 'Sudah termasuk pemandu'],
            ],
            [
                'icon' => 'sparkles',
                'title' => 'Private Trip',
                'desc' => 'Satu rombongan hanya keluarga atau tim Anda. Rute, jam berangkat, dan menu makan bisa diatur sendiri.',
                'points' => ['Itinerary fleksibel', 'Kendaraan eksklusif', 'Cocok untuk keluarga & kantor'],
            ],
            [
                'icon' => 'academic-cap',
                'title' => 'Study Tour',
                'desc' => 'Paket kunjungan edukatif sekolah dan kampus. Lengkap dengan surat jalan, dokumentasi, dan pendamping tiap bus.',
                'points' => ['Kapasitas ratusan peserta', 'Laporan & dokumentasi', 'Asuransi perjalanan'],
            ],
            [
                'icon' => 'truck',
                'title' => 'Sewa Kendaraan',
                'desc' => 'Mobil harian, HiAce untuk rombongan kecil, sampai bus pariwisata besar. Bisa lepas kunci atau dengan sopir.',
                'points' => ['Mobil, HiAce, & Bus', 'Sopir berpengalaman', 'Armada terawat rutin'],
            ],
        ];
    }

    private function bookingSteps(): array
    {
        return [
            [
                'icon' => 'chat-bubble-left-right',
                'title' => 'Konsultasi',
                'desc' => 'Chat kami di WhatsApp. Sebutkan tujuan, tanggal, dan jumlah peserta.',
            ],
            [
                'icon' => 'clipboard-document-check',
                'title' => 'Susun Rencana',
                'desc' => 'Kami kirim itinerary dan rincian biaya, lalu disesuaikan sampai cocok.',
            ],
            [
                'icon' => 'credit-card',
                'title' => 'Bayar DP',
                'desc' => 'Kursi dan armada dikunci setelah uang muka masuk. Sisa dilunasi saat berangkat.',
            ],
            [
                'icon' => 'paper-airplane',
                'title' => 'Berangkat',
                'desc' => 'Tim kami menjemput tepat waktu. Anda tinggal menikmati perjalanan.',
            ],
        ];
    }

    public function with(): array
    {
        // Beranda hanya menampilkan sorotan; daftar lengkapnya ada di
        // halaman /paket-wisata, /sewa-kendaraan, dan /destinasi.
        // Tiga terbaru saja. Enam kartu di beranda berarti menggulung dua baris
        // penuh sebelum sampai ke bagian berikutnya, dan yang di baris kedua
        // praktis tidak pernah terlihat.
        //
        // id sebagai pemutus: paket bawaan tercatat pada detik yang sama, dan
        // urutan tanpa pemutus berarti tiga yang tampil berganti-ganti sendiri
        // tiap halaman dibuka.
        $packages = TravelPackage::query()
            ->tayang()
            ->withCount('pendaftaran')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        // HANYA SATU yang disorot, dan itu inti permintaannya: mata harus
        // punya satu tujuan. Penanda "terlaris" di admin sekarang menempel di
        // tiga dari lima paket; kalau semuanya ikut disorot, tidak ada yang
        // menonjol dan sorotannya kehilangan gunanya.
        //
        // Yang dipilih di antara yang ditandai: pendaftarannya paling banyak,
        // lalu yang paling baru bila seri. Angka pendaftaran dipakai sebagai
        // PEMUTUS, bukan sebagai syarat — penandanya tetap keputusan admin,
        // dan mengabaikan penanda itu berarti mengambil alih keputusan yang
        // bukan milik halaman ini.
        //
        // Karena itu pula lencananya tidak menyebut angka. Saat ini tiga dari
        // lima paket tertandai, salah satunya tanpa satu pun pendaftaran.
        $idSorot = $packages
            ->where('is_best_choice', true)
            ->sortByDesc(fn ($paket) => [$paket->pendaftaran_count, $paket->id])
            ->first()?->id;

        $destinations = DestinationPopuler::query()
            ->orderByDesc('total_visitor')
            ->limit(8)
            ->get();

        // Urutan tampil mengikuti urutan tab (Mobil -> HiAce -> Bus). Diurutkan
        // di PHP, bukan lewat SQL FIELD(), supaya tetap jalan di SQLite saat tes.
        $urutanJenis = array_keys(config('orcha.jenis_kendaraan'));
        $cars = Car::where('is_available', true)
            ->orderBy('price_per_day')
            ->get()
            ->sortBy(fn ($car) => array_search($car->type, $urutanJenis, true))
            ->take(8)
            ->values();

        // Galeri diambil dari foto destinasi yang sudah diunggah admin; kalau
        // belum ada, pakai foto bawaan agar section tidak kosong melompong.
        $galleries = $destinations
            ->flatMap(fn ($d) => array_merge([$d->main_photo], $d->others_photo ?? []))
            ->filter()
            ->take(8)
            ->values()
            ->all();

        if (count($galleries) < 6) {
            $galleries = array_map(
                fn ($file) => asset("images/$file"),
                ['pantai-wide.jpg', 'pantai-senja.jpg', 'gapura.jpg', 'pantai-ramai.jpg', 'laut.jpg', 'pantai-pinggir-laut.jpg', 'pantai-atas.jpg', 'pantai-pinggir.jpg'],
            );
        }

        // Hero otomatis memakai video begitu berkasnya ditaruh di public/videos.
        $heroVideo = collect(['videos/hero.mp4', 'videos/hero.webm'])
            ->first(fn ($path) => file_exists(public_path($path)));

        return [
            'heroVideo' => $heroVideo,
            'kategoriPaket' => config('orcha.kategori_paket'),
            'jenisKendaraan' => config('orcha.jenis_kendaraan'),
            'packages' => $packages,
            'idSorot' => $idSorot,
            'destinations' => $destinations,
            'cars' => $cars,
            'testimonials' => Testimoni::latest('id')->limit(12)->get(),
            'totalTestimoni' => Testimoni::count(),
            'partners' => Partner::all(),
            'galleries' => $galleries,
            'layanan' => $this->layanan(),
            'bookingSteps' => $this->bookingSteps(),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
@endphp

<div id="beranda">

    {{-- ==================================================================
         HERO — satu layar penuh
    =================================================================== --}}
    <section class="relative flex flex-col justify-center hero-orcha h-screen-safe">
        {{-- Kalau ada berkas video di public/videos, hero otomatis memakainya.
             Selama belum ada, foto pantai + lapisan ombak beranimasi yang dipakai. --}}
        <div class="hero-media hero-parallax">
            @if ($heroVideo)
                <video autoplay muted loop playsinline preload="metadata"
                    poster="{{ asset('images/pantai-wide.jpg') }}">
                    <source src="{{ asset($heroVideo) }}"
                        type="video/{{ str_ends_with($heroVideo, '.webm') ? 'webm' : 'mp4' }}">
                </video>
            @else
                <img src="{{ asset('images/pantai-wide.jpg') }}" alt="Panorama pantai" fetchpriority="high"
                    class="hero-breathe">
            @endif
        </div>
        <div class="hero-overlay"></div>

        <div class="container-orcha pt-28 pb-32 sm:pt-32 sm:pb-36">
            <div class="max-w-3xl xl:max-w-4xl">
                <span
                    class="inline-flex items-center gap-2 px-4 py-2 mb-6 text-xs font-bold tracking-widest text-white uppercase border rounded-full reveal border-white/25 bg-white/10 backdrop-blur">
                    <span class="w-2 h-2 rounded-full bg-orcha-sun animate-pulse"></span>
                    Travel Agent Yogyakarta
                </span>

                <p class="mb-2 aksen-orcha reveal">
                    {{ config('orcha.slogan') }}
                </p>

                <h1 class="font-heading font-black text-white leading-[1.05] tracking-tight reveal"
                    style="font-size: clamp(2.4rem, 6.5vw, 5rem);">
                    Repotnya biar kami,
                    <span class="block text-orcha-sun">serunya buat Anda.</span>
                </h1>

                <p class="max-w-2xl mt-6 text-slate-200 pengantar-orcha reveal">
                    Open trip, private trip, study tour, sampai sewa mobil, HiAce, dan bus pariwisata.
                    Sebutkan tujuan dan tanggalnya — itinerary, armada, sampai sopirnya kami yang siapkan.
                </p>

                <div class="flex flex-col gap-3 mt-9 sm:flex-row sm:items-center reveal">
                    <a href="#paket" class="btn-orcha btn-orcha-sun">
                        <x-heroicon-o-map class="w-5 h-5" />
                        Lihat Paket Wisata
                    </a>
                    <a href="{{ $wa('Halo Orcha Journey, saya ingin konsultasi rencana perjalanan.') }}"
                        target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-ghost">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Konsultasi Gratis
                    </a>
                </div>

                <dl class="grid max-w-2xl grid-cols-2 gap-4 mt-12 sm:grid-cols-4 sm:gap-6 reveal">
                    @foreach ([['Destinasi', $destinations->count() ?: 20, '+'], ['Armada siap jalan', $cars->count() ?: 15, '+'], ['Paket wisata', $packages->count() ?: 10, '+'], ['Ulasan pelanggan', $totalTestimoni ?: 50, '+']] as [$label, $value, $suffix])
                        <div class="px-4 py-3 border rounded-2xl border-white/15 bg-white/5 backdrop-blur-sm">
                            <dt class="text-[0.7rem] font-semibold tracking-wider uppercase text-slate-300">
                                {{ $label }}</dt>
                            <dd class="mt-1 text-2xl font-black text-white font-heading sm:text-3xl">
                                {{ $value }}{{ $suffix }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="absolute z-10 hidden -translate-x-1/2 bottom-28 left-1/2 sm:block scroll-cue">
            <span></span>
        </div>

        {{-- Tiga lapis ombak yang bergerak dengan kecepatan berbeda --}}
        <div class="ocean" aria-hidden="true">
            @foreach ([['wave-1', '#1AB0E2'], ['wave-2', '#0086C3'], ['wave-3', '#ffffff']] as [$kelas, $warna])
                <svg class="{{ $kelas }}" viewBox="0 0 2880 200" preserveAspectRatio="none"
                    style="color: {{ $warna }}" fill="currentColor">
                    <path
                        d="M0,120 C240,60 480,180 720,120 C960,60 1200,180 1440,120 C1680,60 1920,180 2160,120 C2400,60 2640,180 2880,120 L2880,200 L0,200 Z" />
                </svg>
            @endforeach
        </div>
    </section>

    {{-- ==================================================================
         LAYANAN
    =================================================================== --}}
    <section id="layanan" class="relative bg-white section-orcha scroll-mt-20">
        <div class="container-orcha">
            <div class="max-w-3xl mb-12 reveal sm:mb-16">
                <p class="eyebrow">
                    <span class="w-8 h-px bg-orcha-wave"></span> Layanan Kami
                </p>
                <h2 class="mt-3 title-orcha">Empat cara menikmati <span class="text-gradient-orcha">perjalanan
                        Anda</span></h2>
                <p class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
                    Mau berangkat sendiri, bareng keluarga, satu sekolah, atau cuma butuh kendaraannya saja —
                    semuanya kami layani.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 sm:gap-6">
                @foreach ($layanan as $item)
                    <article class="flex flex-col p-6 card-orcha reveal sm:p-7">
                        <div
                            class="flex items-center justify-center mb-5 text-white w-14 h-14 rounded-2xl bg-gradient-to-br from-orcha-sky to-orcha-ocean shadow-orcha">
                            <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-7 h-7" />
                        </div>
                        <h3 class="text-xl font-bold font-heading text-orcha-navy">{{ $item['title'] }}</h3>
                        <p class="flex-1 mt-3 text-sm leading-relaxed text-slate-600">{{ $item['desc'] }}</p>
                        <ul class="pt-5 mt-5 space-y-2 border-t border-orcha-foam">
                            @foreach ($item['points'] as $point)
                                <li class="flex items-start gap-2 text-sm text-slate-600">
                                    <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-orcha-sky" />
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================================================================
         PAKET WISATA — tab kategori (Open Trip / Private Trip / Study Tour)
    =================================================================== --}}
    <section id="paket" class="relative bg-orcha-foam/60 section-orcha scroll-mt-20">
        <div class="container-orcha">
            <div class="flex flex-col gap-6 mb-10 lg:flex-row lg:items-end lg:justify-between reveal">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Paket Wisata</p>
                    <h2 class="mt-3 title-orcha">Pilih paket, <span class="text-gradient-orcha">kami urus
                            sisanya</span></h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-600">
                        Harga sudah termasuk transportasi, pemandu, dan tiket masuk destinasi yang tertera.
                    </p>
                </div>

                <div class="tab-scroller lg:justify-end">
                    @foreach ($kategoriPaket as $key => $label)
                        <a href="{{ route('paket-wisata', str_replace('_', '-', $key)) }}"
                            class="tab-orcha">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('paket-wisata') }}" class="tab-orcha tab-orcha-active">Semua Paket</a>
                </div>
            </div>

            @if ($packages->isEmpty())
                <div class="p-10 text-center bg-white card-orcha">
                    <x-heroicon-o-map class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Paket wisata sedang disiapkan.</p>
                    <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk penawaran khusus.</p>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                @foreach ($packages as $package)
                    {{-- lencana-unggulan dimatikan supaya lencana "Terlaris"
                         bawaan kartu tidak ikut muncul di dua kartu lain yang
                         kebetulan juga ditandai admin. Di beranda, yang berhak
                         memakai lencana itu hanya yang disorot. --}}
                    <x-paket-wisata.kartu :paket="$package" :sorot="$package->id === $idSorot"
                        :lencana-unggulan="false" class="reveal" />
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('paket-wisata') }}" class="btn-orcha btn-orcha-outline">
                    Lihat Semua Paket Wisata
                    <x-heroicon-o-arrow-right class="w-5 h-5" />
                </a>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         DESTINASI POPULER — slider snap, tidak membajak scroll halaman
    =================================================================== --}}
    @if ($destinations->isNotEmpty())
        <section id="destinasi" class="relative overflow-hidden bg-orcha-navy section-orcha scroll-mt-20">
            <div class="absolute inset-0 opacity-60 pointer-events-none"
                style="background-image: radial-gradient(60% 55% at 10% 10%, rgba(26,176,226,.28), transparent 70%), radial-gradient(50% 50% at 95% 90%, rgba(255,199,78,.16), transparent 70%);">
            </div>

            <div class="relative container-orcha">
                <div class="flex flex-col gap-6 mb-10 lg:flex-row lg:items-end lg:justify-between reveal">
                    <div class="max-w-3xl">
                        <p class="eyebrow eyebrow-light"><span class="w-8 h-px bg-orcha-sun"></span> Destinasi Populer
                        </p>
                        <h2 class="mt-3 title-orcha title-orcha-light">Tempat yang paling sering <span
                                class="text-orcha-sky">diminta pelanggan</span></h2>
                        <p class="mt-4 text-base leading-relaxed text-slate-300">
                            Geser untuk melihat destinasi favorit beserta jumlah pengunjung yang sudah kami antar.
                        </p>
                    </div>

                    <a href="{{ route('destinasi') }}" class="btn-orcha btn-orcha-ghost shrink-0">
                        Semua Destinasi
                        <x-heroicon-o-arrow-right class="w-5 h-5" />
                    </a>
                </div>
            </div>

            <div class="relative snap-row reveal">
                @foreach ($destinations as $dest)
                    <article
                        class="relative overflow-hidden group rounded-3xl w-[78vw] sm:w-[52vw] lg:w-[30vw] xl:w-[24vw] aspect-[3/4] shadow-orcha-lg">
                        <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.jpg') }}"
                            alt="{{ $dest->destination_name }}" loading="lazy"
                            class="absolute inset-0 object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-orcha-navy via-orcha-navy/40 to-transparent">
                        </div>

                        <div class="relative flex flex-col justify-end h-full p-6">
                            <span
                                class="inline-flex items-center self-start gap-1.5 px-3 py-1 mb-3 text-xs font-bold text-orcha-navy rounded-full bg-orcha-sun">
                                <x-heroicon-s-user-group class="w-4 h-4" />
                                {{ shortNumber($dest->total_visitor) }} pengunjung
                            </span>
                            <h3 class="text-2xl font-bold leading-tight text-white font-heading sm:text-3xl">
                                {{ $dest->destination_name }}
                            </h3>
                            @if ($dest->provinsi)
                                <p class="flex items-center gap-1 mt-1 text-sm text-slate-300">
                                    <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                    {{ $dest->provinsi }}
                                </p>
                            @endif

                            @if (!empty($dest->others_photo))
                                <div class="flex gap-2 mt-4">
                                    @foreach (array_slice($dest->others_photo, 0, 3) as $thumb)
                                        <img src="{{ $thumb }}" alt="" loading="lazy"
                                            class="object-cover w-12 h-12 border-2 rounded-xl border-white/60">
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ==================================================================
         ARMADA — tab Mobil / HiAce / Bus
    =================================================================== --}}
    <section id="armada" class="relative bg-white section-orcha scroll-mt-20">
        <div class="container-orcha">
            <div class="flex flex-col gap-6 mb-10 lg:flex-row lg:items-end lg:justify-between reveal">
                <div class="max-w-2xl">
                    <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Sewa Kendaraan</p>
                    <h2 class="mt-3 title-orcha">Armada untuk <span class="text-gradient-orcha">rombongan berapa
                            pun</span></h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-600">
                        Dari mobil keluarga sampai bus besar. Semua unit dicek berkala dan bisa dipesan dengan sopir.
                    </p>
                </div>

                <div class="tab-scroller lg:justify-end">
                    @foreach ($jenisKendaraan as $key => $label)
                        <a href="{{ route('sewa-kendaraan', $key) }}" class="tab-orcha">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('sewa-kendaraan') }}" class="tab-orcha tab-orcha-active">Semua Armada</a>
                </div>
            </div>

            @if ($cars->isEmpty())
                <div class="p-10 text-center card-orcha">
                    <x-heroicon-o-truck class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Data armada belum tersedia.</p>
                    <p class="mt-1 text-sm text-slate-500">Silakan tanyakan ketersediaan lewat WhatsApp.</p>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 sm:gap-6">
                @foreach ($cars as $car)
                    <x-sewa-kendaraan.kartu :kendaraan="$car" class="reveal" />
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('sewa-kendaraan') }}" class="btn-orcha btn-orcha-outline">
                    Lihat Semua Armada
                    <x-heroicon-o-arrow-right class="w-5 h-5" />
                </a>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         CARA PESAN
    =================================================================== --}}
    <section id="cara-pesan" class="relative overflow-hidden bg-orcha-foam/60 section-orcha scroll-mt-20">
        <div class="container-orcha">
            <div class="max-w-2xl mb-12 reveal">
                <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Cara Pesan</p>
                <h2 class="mt-3 title-orcha">Empat langkah, <span class="text-gradient-orcha">selesai</span></h2>
            </div>

            <ol class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 sm:gap-6">
                @foreach ($bookingSteps as $index => $step)
                    <li class="relative p-6 bg-white card-orcha reveal sm:p-7">
                        <span
                            class="absolute text-6xl font-black leading-none select-none top-4 right-5 font-heading text-orcha-foam">
                            {{ $index + 1 }}
                        </span>
                        <div
                            class="relative flex items-center justify-center mb-5 w-14 h-14 rounded-2xl bg-gradient-to-br from-orcha-sun to-orcha-sunset text-orcha-navy">
                            <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-7 h-7" />
                        </div>
                        <h3 class="relative text-lg font-bold font-heading text-orcha-navy">{{ $step['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ==================================================================
         GALERI
    =================================================================== --}}
    <section id="galeri" class="relative overflow-hidden bg-white section-orcha scroll-mt-20">
        <div class="container-orcha">
            <div class="max-w-2xl mb-10 text-center mx-auto reveal">
                <p class="justify-center eyebrow">Galeri</p>
                <h2 class="mt-3 title-orcha">Momen perjalanan <span class="text-gradient-orcha">pelanggan kami</span>
                </h2>
            </div>
        </div>

        @php
            // Digandakan sampai jalurnya lebih panjang dari layar terlebar,
            // supaya perulangannya tidak pernah terlihat putus.
            $jalurGaleri = $galleries;
            while (count($jalurGaleri) < 10) {
                $jalurGaleri = array_merge($jalurGaleri, $galleries);
            }
            $jalurGaleri = array_merge($jalurGaleri, $jalurGaleri);
        @endphp

        <div class="space-y-4 marquee-wrap">
            <div class="flex overflow-hidden">
                <div class="marquee">
                    @foreach ($jalurGaleri as $img)
                        <div
                            class="relative overflow-hidden shadow-orcha rounded-2xl w-56 sm:w-72 lg:w-80 aspect-[4/3] shrink-0">
                            <img src="{{ $img }}" alt="Dokumentasi perjalanan" loading="lazy"
                                class="object-cover w-full h-full transition-transform duration-700 hover:scale-110">
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex overflow-hidden">
                <div class="marquee marquee-reverse marquee-slow">
                    @foreach (array_reverse($jalurGaleri) as $img)
                        <div
                            class="relative overflow-hidden shadow-orcha rounded-2xl w-56 sm:w-72 lg:w-80 aspect-[4/3] shrink-0">
                            <img src="{{ $img }}" alt="Dokumentasi perjalanan" loading="lazy"
                                class="object-cover w-full h-full transition-transform duration-700 hover:scale-110">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         TESTIMONI
    =================================================================== --}}
    @if ($testimonials->isNotEmpty())
        <section id="testimoni" class="relative overflow-hidden section-orcha scroll-mt-20">
            <img src="{{ asset('images/pantai-senja.jpg') }}" alt="" loading="lazy"
                class="absolute inset-0 object-cover w-full h-full">
            <div class="absolute inset-0 bg-gradient-to-br from-orcha-navy/95 via-orcha-navy/85 to-orcha-abyss/85">
            </div>

            {{-- Slider berjalan sendiri terus-menerus lewat animasi CSS, jadi
                 tetap jalan walau JavaScript gagal dimuat. Berhenti saat
                 kursor menyentuhnya dan saat pengunjung mematikan animasi. --}}
            <div class="relative">
                <div class="container-orcha">
                    <div class="flex flex-col gap-6 mb-10 lg:flex-row lg:items-end lg:justify-between reveal">
                        <div class="max-w-2xl">
                            <p class="eyebrow eyebrow-light"><span class="w-8 h-px bg-orcha-sun"></span> Testimoni</p>
                            <h2 class="mt-3 title-orcha title-orcha-light">Kata mereka yang <span
                                    class="text-orcha-sky">sudah jalan</span> bersama kami</h2>
                            <p class="mt-4 text-base leading-relaxed text-slate-300">
                                Ulasan asli dari peserta open trip, rombongan study tour, dan penyewa armada.
                            </p>
                        </div>

                        <a href="{{ route('testimoni') }}" class="btn-orcha btn-orcha-sun shrink-0">
                            Lihat Semua Testimoni
                            <x-heroicon-o-arrow-right class="w-5 h-5" />
                        </a>
                    </div>
                </div>

                @php
                    // Digandakan sampai jalurnya cukup panjang agar perulangannya mulus
                    $jalurTestimoni = $testimonials->all();
                    while (count($jalurTestimoni) < 8) {
                        $jalurTestimoni = array_merge($jalurTestimoni, $testimonials->all());
                    }
                    $jalurTestimoni = array_merge($jalurTestimoni, $jalurTestimoni);
                @endphp

                <div class="flex overflow-hidden marquee-wrap">
                    <div class="marquee marquee-slow">
                        @foreach ($jalurTestimoni as $testimoni)
                            <figure
                                class="flex flex-col p-6 card-glass w-[82vw] sm:w-[46vw] lg:w-[31vw] xl:w-[24vw] shrink-0">
                                <div class="flex items-center gap-1 mb-4">
                                    @for ($i = 0; $i < 5; $i++)
                                        <x-heroicon-s-star
                                            class="w-4 h-4 {{ $i < $testimoni->rating ? 'text-orcha-sun' : 'text-white/25' }}" />
                                    @endfor
                                </div>
                                <blockquote class="flex-1 text-sm leading-relaxed text-slate-100">
                                    “{{ $testimoni->testimonial }}”
                                </blockquote>
                                <figcaption class="flex items-center gap-3 pt-5 mt-5 border-t border-white/15">
                                    @if ($testimoni->avatar)
                                        <img src="{{ $testimoni->avatar }}" alt="{{ $testimoni->customer_name }}"
                                            loading="lazy" class="object-cover rounded-full w-11 h-11">
                                    @else
                                        <span
                                            class="flex items-center justify-center font-bold text-white rounded-full w-11 h-11 bg-orcha-wave">
                                            {{ mb_strtoupper(mb_substr($testimoni->customer_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="text-sm font-bold text-white">{{ $testimoni->customer_name }}</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 text-center container-orcha">
                    <a href="{{ $wa('Halo Orcha Journey, saya ingin merencanakan perjalanan.') }}" target="_blank"
                        rel="noopener noreferrer" class="btn-orcha btn-orcha-ghost">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Mulai Rencanakan Perjalanan
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ==================================================================
         PARTNER
    =================================================================== --}}
    @if ($partners->isNotEmpty())
        <section class="relative overflow-hidden bg-white section-orcha">
            <div class="container-orcha">
                <div class="max-w-2xl mx-auto mb-10 text-center reveal">
                    <p class="justify-center eyebrow">Mitra Perjalanan</p>
                    <h2 class="mt-3 title-orcha">Didukung <span class="text-gradient-orcha">partner terpercaya</span>
                    </h2>
                </div>
            </div>

            @php
                $jalurPartner = $partners->all();
                while (count($jalurPartner) < 12) {
                    $jalurPartner = array_merge($jalurPartner, $partners->all());
                }
                $jalurPartner = array_merge($jalurPartner, $jalurPartner);
            @endphp

            <div class="flex overflow-hidden marquee-wrap">
                <div class="marquee marquee-slow">
                    @foreach ($jalurPartner as $partner)
                        <div
                            class="flex items-center gap-3 px-6 py-4 border shrink-0 rounded-2xl border-orcha-foam bg-orcha-foam/40">
                            <span
                                class="flex items-center justify-center w-12 h-12 overflow-hidden font-bold text-white rounded-full shrink-0 bg-orcha-wave">
                                @if ($partner->foto)
                                    <img src="{{ $partner->foto }}" alt="{{ $partner->partner_name }}"
                                        loading="lazy" class="object-cover w-full h-full">
                                @else
                                    {{ $partner->initials() }}
                                @endif
                            </span>
                            <span
                                class="text-sm font-semibold whitespace-nowrap text-orcha-navy">{{ $partner->partner_name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ==================================================================
         CTA PENUTUP
    =================================================================== --}}
    <section class="relative overflow-hidden bg-orcha-navy">
        <img src="{{ asset('images/FOOTER/beranda.jpg') }}" alt="" loading="lazy"
            class="absolute inset-0 object-cover w-full h-full opacity-30">
        <div class="relative container-orcha py-16 sm:py-24">
            <div class="max-w-3xl mx-auto text-center reveal">
                <h2 class="title-orcha title-orcha-light">Sudah punya tanggal? <span class="text-orcha-sun">Kami siap
                        berangkat.</span></h2>
                <p class="mt-4 text-base leading-relaxed text-slate-300 sm:text-lg">
                    Ceritakan tujuan dan jumlah peserta, kami balas dengan itinerary serta rincian biaya di hari yang
                    sama.
                </p>
                <div class="flex flex-col justify-center gap-3 mt-8 sm:flex-row">
                    <a href="{{ $wa('Halo Orcha Journey, saya mau tanya jadwal dan harga.') }}" target="_blank"
                        rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Chat via WhatsApp
                    </a>
                    <a href="mailto:{{ config('orcha.email') }}" class="btn-orcha btn-orcha-ghost">
                        <x-heroicon-o-envelope class="w-5 h-5" />
                        Kirim Email
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
