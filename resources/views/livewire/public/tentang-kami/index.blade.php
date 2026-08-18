<?php

use App\Models\SewaKendaraan\Car;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Tentang Kami — Orcha Journey')] class extends Component {
    public function with(): array
    {
        return [
            'angka' => [
                ['label' => 'Destinasi dilayani', 'nilai' => DestinationPopuler::count()],
                ['label' => 'Armada siap jalan', 'nilai' => Car::where('is_available', true)->count()],
                ['label' => 'Paket wisata', 'nilai' => TravelPackage::count()],
                ['label' => 'Ulasan pelanggan', 'nilai' => Testimoni::count()],
            ],
            'nilaiKami' => [
                [
                    'icon' => 'o-banknotes',
                    'judul' => 'Harga Transparan',
                    'isi' => 'Rincian biaya kami tulis apa adanya sejak penawaran pertama. Tidak ada biaya kejutan di tengah perjalanan.',
                ],
                [
                    'icon' => 'o-shield-check',
                    'judul' => 'Armada Terawat',
                    'isi' => 'Setiap unit dicek berkala sebelum jalan. Bila ada kendala di jalan, kami siapkan unit pengganti.',
                ],
                [
                    'icon' => 'o-map',
                    'judul' => 'Pemandu Paham Medan',
                    'isi' => 'Tim kami hafal jalur, jam ramai, dan spot terbaik — sehingga waktu Anda tidak habis di jalan.',
                ],
                [
                    'icon' => 'o-chat-bubble-left-right',
                    'judul' => 'Dibalas Cepat',
                    'isi' => 'Pertanyaan dibalas setiap hari pukul 08.00–21.00 WIB, termasuk saat perjalanan berlangsung.',
                ],
            ],
            'perjalanan' => [
                ['tahun' => 'Awal', 'judul' => 'Berangkat dari hobi jalan-jalan', 'isi' => 'Orcha Journey lahir dari kebiasaan mengatur perjalanan bareng teman dan keluarga di sekitar Yogyakarta.'],
                ['tahun' => 'Tumbuh', 'judul' => 'Melayani rombongan sekolah', 'isi' => 'Permintaan study tour membuat kami menambah armada bus dan menyiapkan pendamping di setiap kendaraan.'],
                ['tahun' => 'Kini', 'judul' => 'Satu tim untuk semua kebutuhan', 'isi' => 'Open trip, private trip, study tour, sampai sewa mobil, HiAce, dan bus — semuanya bisa diurus dalam satu pintu.'],
            ],
            'partners' => Partner::all(),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin tahu lebih banyak tentang layanan Anda.');
@endphp

<div>
    <x-page-hero title="Tentang Kami" eyebrow="Kenali Orcha Journey"
        subtitle="{{ config('orcha.slogan') }} Kami mengurus perjalanan dari rencana sampai pulang, supaya Anda tinggal menikmatinya."
        image="images/HERO/tentang-kami.jpg" posisi="center 62%" />

    {{-- Cerita --}}
    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="lg:col-span-6 reveal">
                    <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Cerita Kami</p>
                    <h2 class="mt-3 title-orcha">Travel agent Yogyakarta yang <span class="text-gradient-orcha">tidak
                            bikin repot</span></h2>
                    <p class="mt-5 leading-relaxed text-slate-600">
                        Orcha Journey berdiri karena satu keluhan yang selalu sama: merencanakan liburan itu melelahkan.
                        Mencari armada, menawar harga, menyusun rute, memastikan semua peserta terangkut — semuanya
                        memakan waktu yang seharusnya dipakai untuk menikmati perjalanan.
                    </p>
                    <p class="mt-4 leading-relaxed text-slate-600">
                        Kami mengambil alih bagian merepotkan itu. Anda cukup menyebutkan tujuan, tanggal, dan jumlah
                        peserta; sisanya kami yang susun — mulai dari itinerary, armada, pemandu, sampai titik jemput
                        yang paling masuk akal.
                    </p>
                    <p class="mt-4 leading-relaxed text-slate-600">
                        Nama <strong class="text-orcha-navy">Orcha</strong> diambil dari orca, si penjelajah samudra
                        yang selalu bergerak dalam kelompok dan tidak pernah meninggalkan anggotanya. Begitu juga cara
                        kami menemani perjalanan Anda.
                    </p>

                    <div class="flex flex-col gap-3 mt-8 sm:flex-row">
                        <a href="{{ route('paket-wisata') }}" class="btn-orcha btn-orcha-primary">
                            <x-heroicon-o-map class="w-5 h-5" />
                            Lihat Paket Wisata
                        </a>
                        <a href="{{ route('kontak') }}" class="btn-orcha btn-orcha-outline">
                            Hubungi Kami
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-6">
                    {{-- Kotaknya lebih tinggi daripada sebelumnya karena fotonya
                         TEGAK (1080x1920). Pada kotak yang lebih lebar daripada
                         tinggi, object-cover pada foto tegak hanya menyisakan
                         pita mendatar di tengahnya — orang dan tempatnya
                         terpotong di atas dan bawah. --}}
                    <div class="grid grid-cols-2 gap-4">
                        <img src="{{ asset('images/tentang-kami/web1.jpg') }}" alt="Perjalanan bersama Orcha Journey"
                            loading="lazy" class="object-cover w-full h-72 sm:h-96 rounded-3xl shadow-orcha">
                        <img src="{{ asset('images/tentang-kami/web9.jpg') }}" alt="Rombongan wisata" loading="lazy"
                            class="object-cover w-full h-72 mt-8 sm:h-96 rounded-3xl shadow-orcha">
                        <img src="{{ asset('images/tentang-kami/web10.jpg') }}" alt="Destinasi yang kami antar"
                            loading="lazy" class="object-cover w-full h-56 sm:h-72 rounded-3xl shadow-orcha">
                        <img src="{{ asset('images/tentang-kami/web11.jpg') }}" alt="Momen di perjalanan" loading="lazy"
                            class="object-cover w-full h-56 mt-8 sm:h-72 rounded-3xl shadow-orcha">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Angka --}}
    <section class="relative overflow-hidden bg-orcha-navy">
        <div class="absolute inset-0 opacity-50 pointer-events-none"
            style="background-image: radial-gradient(60% 55% at 12% 0%, rgba(26,176,226,.3), transparent 70%), radial-gradient(50% 50% at 92% 100%, rgba(255,199,78,.18), transparent 70%);">
        </div>
        <div class="relative container-orcha py-14 sm:py-20">
            <dl class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($angka as $item)
                    <div class="text-center reveal">
                        <dd class="text-3xl font-black text-white font-heading sm:text-4xl">{{ $item['nilai'] }}+</dd>
                        <dt class="mt-2 text-xs font-semibold tracking-wider uppercase text-slate-300">
                            {{ $item['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- Nilai --}}
    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="max-w-2xl mb-12 reveal">
                <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Kenapa Orcha</p>
                <h2 class="mt-3 title-orcha">Empat hal yang kami <span class="text-gradient-orcha">pegang teguh</span>
                </h2>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 sm:gap-6">
                @foreach ($nilaiKami as $nilai)
                    <div class="p-6 card-orcha reveal sm:p-7">
                        <span
                            class="flex items-center justify-center mb-5 text-white w-14 h-14 rounded-2xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                            <x-dynamic-component :component="'heroicon-' . $nilai['icon']" class="w-7 h-7" />
                        </span>
                        <h3 class="text-lg font-bold font-heading text-orcha-navy">{{ $nilai['judul'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $nilai['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Perjalanan kami --}}
    <section class="bg-orcha-foam/60 section-orcha">
        <div class="container-orcha">
            <div class="max-w-2xl mb-12 reveal">
                <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Perjalanan Kami</p>
                <h2 class="mt-3 title-orcha">Dari rombongan kecil ke <span class="text-gradient-orcha">rombongan satu
                        sekolah</span></h2>
            </div>

            <ol class="grid gap-5 md:grid-cols-3 sm:gap-6">
                @foreach ($perjalanan as $tahap)
                    <li class="p-6 bg-white card-orcha reveal sm:p-7">
                        <span
                            class="inline-block px-3 py-1 mb-4 text-xs font-bold tracking-wider uppercase rounded-full bg-orcha-sun/20 text-orcha-ocean">
                            {{ $tahap['tahun'] }}
                        </span>
                        <h3 class="text-lg font-bold font-heading text-orcha-navy">{{ $tahap['judul'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $tahap['isi'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Partner --}}
    @if ($partners->isNotEmpty())
        <section class="overflow-hidden bg-white section-orcha">
            <div class="container-orcha">
                <div class="max-w-2xl mx-auto mb-10 text-center reveal">
                    <p class="justify-center eyebrow">Mitra Perjalanan</p>
                    <h2 class="mt-3 title-orcha">Ditemani <span class="text-gradient-orcha">partner terpercaya</span>
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

    {{-- CTA --}}
    <section class="relative overflow-hidden bg-orcha-navy">
        <img src="{{ asset('images/pantai-wide.jpg') }}" alt="" loading="lazy"
            class="absolute inset-0 object-cover w-full h-full opacity-25">
        <div class="relative container-orcha py-16 sm:py-24">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="title-orcha title-orcha-light">Mari jalan bareng <span class="text-orcha-sun">Orcha
                        Journey</span></h2>
                <p class="mt-4 leading-relaxed text-slate-300">Ceritakan rencana Anda, kami balas dengan itinerary dan
                    rincian biaya di hari yang sama.</p>
                <div class="flex flex-col justify-center gap-3 mt-8 sm:flex-row">
                    <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Chat via WhatsApp
                    </a>
                    <a href="{{ route('kontak') }}" class="btn-orcha btn-orcha-ghost">
                        Lihat Semua Kontak
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
