<?php

use App\Models\Etalase\Testimoni;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.guest')] #[Title('Testimoni Pelanggan — Orcha Journey')] class extends Component {
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'bintang', except: '')]
    public string $rating = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRating(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'rating']);
        $this->resetPage();
    }

    public function with(): array
    {
        $semua = Testimoni::query();

        return [
            'testimonials' => Testimoni::query()
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('testimonial', 'like', "%{$this->search}%")))
                ->when($this->rating, fn ($q) => $q->where('rating', (int) $this->rating))
                ->latest('id')
                ->paginate(9),
            'total' => (clone $semua)->count(),
            'rataRata' => round((float) (clone $semua)->avg('rating'), 1),
            'sebaran' => collect(range(5, 1))
                ->map(fn ($bintang) => [
                    'bintang' => $bintang,
                    'jumlah' => Testimoni::where('rating', $bintang)->count(),
                ]),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin mengirim testimoni perjalanan saya.');
@endphp

<div>
    <x-page-hero title="Testimoni Pelanggan" eyebrow="Apa Kata Mereka"
        subtitle="Semua ulasan dari peserta open trip, private trip, study tour, dan penyewa armada Orcha Journey."
        image="images/HERO/testimoni.webp" posisi="center 60%" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">

            {{-- Ringkasan penilaian --}}
            <div class="grid gap-5 mb-10 lg:grid-cols-12 lg:gap-6">
                <div class="p-6 text-center card-orcha lg:col-span-4 sm:p-8">
                    <p class="text-5xl font-black font-heading text-orcha-navy">
                        {{ number_format($rataRata, 1, ',', '.') }}</p>
                    <div class="flex items-center justify-center gap-1 mt-2">
                        @for ($i = 0; $i < 5; $i++)
                            <x-heroicon-s-star
                                class="w-5 h-5 {{ $i < round($rataRata) ? 'text-orcha-sun' : 'text-slate-200' }}" />
                        @endfor
                    </div>
                    <p class="mt-3 text-sm text-slate-500">dari {{ $total }} ulasan pelanggan</p>
                </div>

                <div class="p-6 card-orcha lg:col-span-8 sm:p-8">
                    <div class="space-y-2.5">
                        @foreach ($sebaran as $baris)
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1 text-sm font-semibold shrink-0 w-14 text-slate-600">
                                    {{ $baris['bintang'] }}
                                    <x-heroicon-s-star class="w-4 h-4 text-orcha-sun" />
                                </span>
                                <div class="flex-1 h-2 overflow-hidden rounded-full bg-orcha-foam">
                                    <div class="h-full rounded-full bg-gradient-to-r from-orcha-sky to-orcha-ocean"
                                        style="width: {{ $total > 0 ? round($baris['jumlah'] / $total * 100) : 0 }}%">
                                    </div>
                                </div>
                                <span class="w-10 text-sm text-right text-slate-500">{{ $baris['jumlah'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Filter --}}
            <div class="flex flex-col gap-4 mb-8 lg:flex-row lg:items-center lg:justify-between">
                <label class="relative w-full lg:max-w-sm">
                    <span class="sr-only">Cari testimoni</span>
                    <x-heroicon-o-magnifying-glass
                        class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.400ms="search"
                        placeholder="Cari nama atau isi ulasan..."
                        class="w-full py-3 pr-4 text-sm bg-white border pl-11 rounded-2xl border-orcha-foam focus:border-orcha-sky focus:outline-none focus:ring-2 focus:ring-orcha-sky/25">
                </label>

                <div class="tab-scroller lg:justify-end">
                    <button type="button" wire:click="$set('rating', '')"
                        class="tab-orcha {{ $rating === '' ? 'tab-orcha-active' : '' }}">Semua</button>
                    @foreach ([5, 4, 3, 2, 1] as $bintang)
                        <button type="button" wire:click="$set('rating', '{{ $bintang }}')"
                            class="tab-orcha {{ (int) $rating === $bintang ? 'tab-orcha-active' : '' }}">
                            {{ $bintang }} Bintang
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Daftar testimoni --}}
            @if ($testimonials->isEmpty())
                <div class="p-12 text-center card-orcha">
                    <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Tidak ada testimoni yang cocok.</p>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah kata kunci atau pilih bintang lain.</p>
                    <button type="button" wire:click="resetFilter" class="mt-5 btn-orcha btn-orcha-outline">
                        Tampilkan Semua
                    </button>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                    @foreach ($testimonials as $testimoni)
                        <figure class="flex flex-col p-6 card-orcha">
                            <div class="flex items-center gap-1 mb-4">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-heroicon-s-star
                                        class="w-4 h-4 {{ $i < $testimoni->rating ? 'text-orcha-sun' : 'text-slate-200' }}" />
                                @endfor
                            </div>

                            <blockquote class="flex-1 text-sm leading-relaxed text-slate-600">
                                “{{ $testimoni->testimonial }}”
                            </blockquote>

                            <figcaption class="flex items-center gap-3 pt-5 mt-5 border-t border-orcha-foam">
                                @if ($testimoni->avatar)
                                    <img src="{{ $testimoni->avatar }}" alt="{{ $testimoni->customer_name }}"
                                        loading="lazy" class="object-cover rounded-full w-11 h-11">
                                @else
                                    <span
                                        class="flex items-center justify-center font-bold text-white rounded-full w-11 h-11 bg-orcha-wave">
                                        {{ mb_strtoupper(mb_substr($testimoni->customer_name, 0, 1)) }}
                                    </span>
                                @endif
                                <div>
                                    <p class="text-sm font-bold text-orcha-navy">{{ $testimoni->customer_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $testimoni->created_at?->translatedFormat('d F Y') }}</p>
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $testimonials->links('partials.paginasi-orcha') }}
                </div>
            @endif

            {{-- Ajakan kirim testimoni --}}
            <div
                class="flex flex-col items-center gap-4 p-8 mt-12 text-center sm:flex-row sm:text-left sm:justify-between rounded-3xl bg-orcha-foam/70">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Sudah jalan bersama kami?</p>
                    <p class="mt-1 text-sm text-slate-600">Kirimkan cerita Anda lewat WhatsApp, akan kami tayangkan di
                        halaman ini.</p>
                </div>
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Kirim Testimoni
                </a>
            </div>
        </div>
    </section>
</div>
