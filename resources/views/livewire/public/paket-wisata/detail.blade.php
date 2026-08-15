<?php

use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component {
    public TravelPackage $paket;

    public function mount(TravelPackage $paket): void
    {
        // Paket draf, terjadwal, atau yang sudah berakhir tidak boleh terbuka
        // meski alamatnya diketahui — halamannya diperlakukan seperti tidak ada.
        abort_unless($paket->sedang_tayang, 404);

        $this->paket = $paket;
    }

    public function with(): array
    {
        return [
            'paketLain' => TravelPackage::tayang()->where('category', $this->paket->category)
                ->whereKeyNot($this->paket->id)
                ->orderBy('price')
                ->limit(3)
                ->get(),
        ];
    }
}; ?>

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode("Halo Orcha Journey, saya ingin bertanya soal {$paket->name}.");
    $dp = config('orcha.pembayaran.dp_persen');
    $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
@endphp

<div>
    <x-page-hero :title="$paket->name" :eyebrow="$paket->category_label"
        :subtitle="$paket->jadwal_label ? 'Keberangkatan ' . $paket->jadwal_label : $paket->duration"
        :gambar-penuh="$paket->sampul" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="space-y-6 lg:col-span-8">

                    {{-- Ringkasan perjalanan --}}
                    <div class="p-6 card-orcha sm:p-8">
                        <h2 class="text-xl font-bold font-heading text-orcha-navy">Ringkasan Perjalanan</h2>

                        <dl class="grid gap-5 mt-5 sm:grid-cols-2">
                            @php
                                $ringkasan = array_filter([
                                    ['o-calendar-days', 'Keberangkatan', $paket->jadwal_label],
                                    ['o-clock', 'Durasi', $paket->duration],
                                    ['o-map-pin', 'Titik jemput', $paket->titik_jemput],
                                    ['o-user-group', 'Minimal peserta', $paket->minimal_peserta > 1 ? $paket->minimal_peserta . ' orang' : null],
                                ], fn ($baris) => filled($baris[2]));
                            @endphp

                            @foreach ($ringkasan as [$ikon, $label, $nilai])
                                <div class="flex items-start gap-3">
                                    <span
                                        class="flex items-center justify-center w-10 h-10 text-white shrink-0 rounded-xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                                        <x-dynamic-component :component="'heroicon-' . $ikon" class="w-5 h-5" />
                                    </span>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wider uppercase text-slate-400">
                                            {{ $label }}</dt>
                                        <dd class="font-bold text-orcha-navy">{{ $nilai }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                        @if ($paket->sudah_lewat)
                            <div class="p-4 mt-6 text-sm border rounded-2xl border-orcha-sun/40 bg-orcha-sun/10 text-orcha-navy">
                                Tanggal keberangkatan paket ini sudah lewat. Hubungi kami untuk menanyakan jadwal
                                berikutnya.
                            </div>
                        @endif
                    </div>

                    {{-- Destinasi --}}
                    @if (!empty($paket->destination_list))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Destinasi yang Dikunjungi</h2>
                            <ul class="grid gap-3 mt-5 sm:grid-cols-2">
                                @foreach ($paket->destination_list as $destinasi)
                                    <li class="flex items-start gap-2 text-sm text-slate-600">
                                        <x-heroicon-s-map-pin class="w-5 h-5 shrink-0 text-orcha-sky" />
                                        <span>{{ $destinasi }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Fasilitas --}}
                    @if (!empty($paket->fasilitas))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Fasilitas Termasuk</h2>
                            <ul class="grid gap-3 mt-5 sm:grid-cols-2">
                                @foreach ($paket->fasilitas as $fasilitas)
                                    <li class="flex items-start gap-2 text-sm text-slate-600">
                                        <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-orcha-sky" />
                                        <span>{{ $fasilitas }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Itinerary --}}
                    @if (!empty($paket->itinerary))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Itinerary</h2>

                            <div class="mt-6 space-y-6">
                                @foreach ($paket->itinerary as $hari)
                                    <div>
                                        <span
                                            class="inline-block px-4 py-1.5 text-sm font-bold text-white rounded-full bg-gradient-to-r from-orcha-abyss to-orcha-ocean">
                                            {{ $hari['hari'] ?? 'Hari' }}
                                        </span>

                                        <ul class="mt-4 space-y-3">
                                            @foreach ($hari['agenda'] ?? [] as $agenda)
                                                {{-- Titik, jam, dan kegiatan sejajar karena ketiganya
                                                     memakai tinggi baris yang sama (leading-6 = 24px);
                                                     titiknya dipusatkan di dalam kotak setinggi itu,
                                                     bukan digeser dengan angka ajaib. --}}
                                                <li class="flex items-start gap-3">
                                                    <span class="flex items-center h-6 shrink-0">
                                                        <span class="w-2.5 h-2.5 rounded-full bg-orcha-sky"></span>
                                                    </span>
                                                    <span
                                                        class="font-mono text-sm font-bold leading-6 shrink-0 text-orcha-ocean w-16 tabular-nums">{{ $agenda['jam'] ?? '' }}</span>
                                                    <span
                                                        class="text-sm leading-6 text-slate-600">{{ $agenda['kegiatan'] ?? '' }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-6 text-xs text-slate-500">
                                Urutan kunjungan dapat menyesuaikan kondisi cuaca, lalu lintas, dan keamanan di lokasi.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Sisi kanan: harga & pendaftaran --}}
                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <div class="overflow-hidden card-orcha">
                            <div class="p-6 text-white bg-gradient-to-br from-orcha-navy to-orcha-abyss sm:p-7">
                                @if ($paket->catatan_promo)
                                    <span
                                        class="inline-block px-3 py-1 mb-3 text-xs font-bold rounded-full bg-orcha-sun text-orcha-navy">
                                        {{ $paket->catatan_promo }}
                                    </span>
                                @endif

                                @if ($paket->original_price > 0 && $paket->original_price > $paket->price)
                                    <p class="text-sm line-through text-slate-400">
                                        {{ $rupiah($paket->original_price) }}</p>
                                @endif

                                <p class="text-3xl font-black font-heading text-orcha-sun">
                                    {{ $rupiah($paket->price) }}</p>
                                <p class="text-sm text-slate-300">per orang</p>
                            </div>

                            <div class="p-6 space-y-3 sm:p-7">
                                @if ($paket->category === 'open_trip')
                                    <a href="{{ route('pendaftaran-open-trip', ['paket' => $paket->uuid]) }}"
                                        class="w-full btn-orcha btn-orcha-primary">
                                        <x-heroicon-o-pencil-square class="w-5 h-5" />
                                        Daftar Sekarang
                                    </a>
                                @endif

                                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                                    class="w-full btn-orcha btn-orcha-outline">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Tanya via WhatsApp
                                </a>

                                <div class="pt-4 mt-4 text-xs border-t border-orcha-foam text-slate-500">
                                    <p>Uang muka <strong class="text-orcha-navy">{{ $dp }}%</strong> saat pemesanan.
                                        Pelunasan paling lambat
                                        <strong class="text-orcha-navy">H-{{ $pelunasan }}</strong>
                                        @if ($paket->batas_pelunasan)
                                            ({{ $paket->batas_pelunasan->translatedFormat('j F Y') }})
                                        @endif
                                        sebelum keberangkatan.
                                    </p>
                                    <a href="{{ route('ketentuan-pembayaran') }}"
                                        class="inline-block mt-2 font-semibold text-orcha-ocean hover:underline">
                                        Lihat ketentuan pembayaran
                                    </a>
                                </div>
                            </div>
                        </div>

                        @if ($paketLain->isNotEmpty())
                            <div class="p-6 card-orcha sm:p-7">
                                <h2 class="text-lg font-bold font-heading text-orcha-navy">
                                    {{ $paket->category_label }} lainnya</h2>
                                <div class="mt-4 space-y-3">
                                    @foreach ($paketLain as $lain)
                                        <a href="{{ route('paket-detail', $lain->uuid) }}"
                                            class="block p-4 transition border rounded-2xl border-orcha-foam hover:border-orcha-sky hover:bg-orcha-foam/40">
                                            <p class="text-sm font-bold text-orcha-navy">{{ $lain->name }}</p>
                                            <p class="mt-1 text-sm text-orcha-ocean">{{ $rupiah($lain->price) }}
                                                <span class="text-slate-500">/ orang</span>
                                            </p>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
