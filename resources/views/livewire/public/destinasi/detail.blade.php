<?php

use App\Models\Etalase\DestinationPopuler;
use App\Support\Seo;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Halaman satu destinasi.
 *
 * Ada bukan untuk menggantikan panel detail di halaman daftar — panel itu
 * melayani pengunjung yang sedang menyusuri daftar dengan penyaring wilayah
 * yang sudah ia atur, dan alasannya ditulis di halaman itu sendiri.
 *
 * Yang ini melayani orang yang datang dari LUAR: hasil pencarian dan tautan
 * yang dibagikan. Keduanya butuh alamat yang berdiri sendiri, judul halaman
 * yang memuat nama destinasinya, dan keterangan yang menyebut tempat itu —
 * bukan kalimat umum tentang Orcha yang sama untuk semua halaman.
 */
new #[Layout('components.layouts.guest')] class extends Component {
    public DestinationPopuler $destinasi;

    public function mount(DestinationPopuler $destinasi): void
    {
        $this->destinasi = $destinasi;
    }

    public function rendering(View $view): void
    {
        $d = $this->destinasi;

        // Daerahnya ikut di judul: "Pantai Indrayanti" ada di lebih dari satu
        // tempat, dan yang mencari biasanya menyebut daerahnya sekalian.
        $view->title(trim($d->destination_name.' — '.($d->alamat_singkat ?: $d->wilayah_label)).' | Orcha Journey');

        $view->layoutData([
            'seoKeterangan' => Seo::keterangan(khusus: $this->kalimatSeo()),
            'seoGambar' => $d->main_photo ?: asset('images/pantai-senja.webp'),
        ]);
    }

    /**
     * Cuplikan di hasil pencarian.
     *
     * Keterangan destinasinya dipakai lebih dulu bila ada — itu kalimat yang
     * benar-benar menerangkan tempatnya. Yang belum punya keterangan tetap
     * mendapat kalimat utuh yang dirakit dari lokasinya, bukan cuplikan kosong
     * yang lalu diisi Google dengan potongan teks acak dari halaman.
     */
    private function kalimatSeo(): string
    {
        $d = $this->destinasi;

        if (filled($d->deskripsi)) {
            return $d->deskripsi;
        }

        $tempat = $d->alamat_singkat ?: $d->wilayah_label;

        return "Perjalanan ke {$d->destination_name}"
            .($tempat ? ", {$tempat}" : '')
            .'. Orcha Journey menyusun rute, armada, dan biayanya — open trip, private trip, maupun study tour.';
    }

    public function with(): array
    {
        return [
            // Destinasi lain di wilayah yang sama: yang sedang membaca satu
            // tempat biasanya belum memutuskan, dan tetangganya yang sewilayah
            // adalah pembanding yang paling masuk akal.
            'sekitar' => DestinationPopuler::query()
                ->where('wilayah', $this->destinasi->wilayah)
                ->whereKeyNot($this->destinasi->id)
                ->latest('total_visitor')
                ->limit(3)
                ->get(),
        ];
    }
}; ?>

@php
    $d = $destinasi;

    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' .
        rawurlencode("Halo Orcha Journey, saya ingin ke {$d->destination_name}. Ada paket atau armadanya?");

    $galeri = array_values(array_filter(array_merge([$d->main_photo], $d->others_photo ?? [])));

    /*
     | Data terstruktur TouristAttraction.
     |
     | Yang dikirim sebagai data, bukan kalimat: nama, gambar, dan lokasinya.
     | Itu yang membuat mesin pencari boleh memperlakukan halaman ini sebagai
     | TEMPAT — bukan sekadar halaman yang kebetulan menyebut nama tempat.
     |
     | Alamatnya hanya disertakan sejauh yang benar-benar diketahui. Menuliskan
     | provinsi sebagai alamat lengkap akan membuat peta menunjuk titik yang
     | salah, dan titik yang salah lebih merugikan daripada tidak ada titik.
     */
    $skemaDestinasi = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'TouristAttraction',
        'name' => $d->destination_name,
        'description' => $d->deskripsi ?: null,
        'image' => $galeri ?: null,
        'url' => route('destinasi.detail', $d),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'addressLocality' => $d->daerah ?: null,
            'addressRegion' => $d->provinsi ?: null,
            'addressCountry' => 'ID',
        ]),
    ], fn ($nilai) => $nilai !== null && $nilai !== [] && $nilai !== '');
@endphp

<div>
    <script type="application/ld+json">{!! json_encode($skemaDestinasi, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <x-page-hero :title="$d->destination_name" :eyebrow="$d->wilayah_label"
        :subtitle="$d->alamat_singkat" image="images/HERO/destinasi.webp" />

    <section class="py-12 sm:py-16">
        <div class="px-4 mx-auto max-w-5xl sm:px-6">

            <nav class="mb-8 text-sm text-slate-500" aria-label="Remah roti">
                <a href="{{ route('destinasi') }}" wire:navigate class="font-semibold text-orcha-ocean hover:underline">
                    Destinasi Populer
                </a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span>{{ $d->destination_name }}</span>
            </nav>

            @if ($galeri)
                <div class="grid gap-3 mb-10 sm:grid-cols-2">
                    <img src="{{ $galeri[0] }}" alt="{{ $d->destination_name }}" loading="lazy" decoding="async"
                        class="object-cover w-full h-full rounded-2xl sm:row-span-2 aspect-[4/3] sm:aspect-auto">

                    @foreach (array_slice($galeri, 1, 2) as $foto)
                        <img src="{{ $foto }}" alt="{{ $d->destination_name }}" loading="lazy" decoding="async"
                            class="object-cover w-full rounded-2xl aspect-[4/3]">
                    @endforeach
                </div>
            @endif

            <div class="p-6 sm:p-8 card-orcha">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mb-5 text-sm text-slate-600">
                    @if ($d->alamat_singkat)
                        <span class="flex items-center gap-1.5">
                            <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                            {{ $d->alamat_singkat }}
                        </span>
                    @endif

                    {{-- Nol pengunjung tidak ditampilkan: ia terbaca terbalik dari
                         maksudnya. Aturan yang sama dipakai lencana di kartu daftar. --}}
                    @if ($d->total_visitor > 0)
                        <span class="flex items-center gap-1.5">
                            <x-heroicon-s-user-group class="w-4 h-4 text-orcha-ocean" />
                            {{ shortNumber($d->total_visitor) }} pengunjung sudah kami antar
                        </span>
                    @endif
                </div>

                @if ($d->deskripsi)
                    <p class="leading-relaxed text-slate-700">{{ $d->deskripsi }}</p>
                @endif

                <div class="flex flex-wrap gap-3 mt-7">
                    <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                        class="btn-orcha btn-orcha-primary">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Tanya Paket ke Sini
                    </a>

                    <a href="{{ route('paket-wisata') }}" wire:navigate class="btn-orcha btn-orcha-outline">
                        Lihat Paket Wisata
                    </a>
                </div>
            </div>

            @if ($sekitar->isNotEmpty())
                <h2 class="mt-14 mb-5 text-xl font-bold font-heading text-orcha-navy">
                    Destinasi lain di {{ $d->wilayah_label }}
                </h2>

                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach ($sekitar as $lain)
                        <a href="{{ route('destinasi.detail', $lain) }}" wire:navigate
                            class="flex flex-col overflow-hidden card-orcha group">
                            <div class="overflow-hidden aspect-[4/3]">
                                <img src="{{ $lain->main_photo ?: asset('images/pantai-senja.webp') }}"
                                    alt="{{ $lain->destination_name }}" loading="lazy" decoding="async"
                                    class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">
                            </div>
                            <div class="p-4">
                                <p class="font-bold leading-snug text-orcha-navy">{{ $lain->destination_name }}</p>
                                @if ($lain->alamat_singkat)
                                    <p class="mt-1 text-xs text-slate-500">{{ $lain->alamat_singkat }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
