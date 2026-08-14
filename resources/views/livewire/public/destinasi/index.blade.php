<?php

use App\Models\DestinationPopuler;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.guest')] #[Title('Destinasi Populer — Orcha Journey')] class extends Component {
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'wilayah', except: '')]
    public string $wilayah = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWilayah(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'destinations' => DestinationPopuler::query()
                // Dikelompokkan supaya filter wilayah tidak ikut ter-OR
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('destination_name', 'like', "%{$this->search}%")
                    ->orWhere('provinsi', 'like', "%{$this->search}%")))
                ->diWilayah($this->wilayah ?: null)
                ->orderByDesc('total_visitor')
                ->paginate(9),
            'total' => DestinationPopuler::count(),
            'totalPengunjung' => (int) DestinationPopuler::sum('total_visitor'),
            'totalProvinsi' => DestinationPopuler::distinct()->count('provinsi'),
            'daftarWilayah' => collect(config('orcha.wilayah'))
                ->map(fn ($label, $kunci) => [
                    'kunci' => $kunci,
                    'label' => $label,
                    'jumlah' => DestinationPopuler::where('wilayah', $kunci)->count(),
                ])
                ->filter(fn ($baris) => $baris['jumlah'] > 0)
                ->values(),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Destinasi Populer" eyebrow="Seluruh Indonesia"
        subtitle="Dari Pulau Weh sampai Raja Ampat. Tempat-tempat yang paling sering diminta pelanggan kami, lengkap dengan jumlah pengunjung yang sudah kami antar."
        image="images/pantai-atas.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">

            {{-- Ringkasan + pencarian --}}
            <div class="flex flex-col gap-5 mb-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-3">
                    <div class="px-5 py-3 rounded-2xl bg-orcha-foam/70">
                        <p class="text-xs font-semibold tracking-wider uppercase text-slate-500">Destinasi</p>
                        <p class="text-xl font-black font-heading text-orcha-navy">{{ $total }}</p>
                    </div>
                    <div class="px-5 py-3 rounded-2xl bg-orcha-foam/70">
                        <p class="text-xs font-semibold tracking-wider uppercase text-slate-500">Provinsi</p>
                        <p class="text-xl font-black font-heading text-orcha-navy">{{ $totalProvinsi }}</p>
                    </div>
                    <div class="px-5 py-3 rounded-2xl bg-orcha-foam/70">
                        <p class="text-xs font-semibold tracking-wider uppercase text-slate-500">Total pengunjung
                            diantar</p>
                        <p class="text-xl font-black font-heading text-orcha-navy">
                            {{ shortNumber($totalPengunjung) }}</p>
                    </div>
                </div>

                <label class="relative w-full lg:max-w-sm">
                    <span class="sr-only">Cari destinasi</span>
                    <x-heroicon-o-magnifying-glass
                        class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.400ms="search"
                        placeholder="Cari nama destinasi atau provinsi..."
                        class="w-full py-3 pr-4 text-sm bg-white border pl-11 rounded-2xl border-orcha-foam focus:border-orcha-sky focus:outline-none focus:ring-2 focus:ring-orcha-sky/25">
                </label>
            </div>

            {{-- Filter wilayah --}}
            <div class="mb-10 tab-scroller">
                <button type="button" wire:click="$set('wilayah', '')"
                    class="tab-orcha {{ $wilayah === '' ? 'tab-orcha-active' : '' }}">
                    Semua Wilayah <span class="opacity-60">({{ $total }})</span>
                </button>
                @foreach ($daftarWilayah as $baris)
                    <button type="button" wire:click="$set('wilayah', '{{ $baris['kunci'] }}')"
                        class="tab-orcha {{ $wilayah === $baris['kunci'] ? 'tab-orcha-active' : '' }}">
                        {{ $baris['label'] }} <span class="opacity-60">({{ $baris['jumlah'] }})</span>
                    </button>
                @endforeach
            </div>

            @if ($destinations->isEmpty())
                <div class="p-12 text-center card-orcha">
                    <x-heroicon-o-map-pin class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Destinasi tidak ditemukan.</p>
                    <p class="mt-1 text-sm text-slate-500">Coba kata kunci lain, atau tanyakan destinasi impian Anda ke
                        kami.</p>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                    @foreach ($destinations as $dest)
                        <article class="flex flex-col overflow-hidden card-orcha group">
                            <div class="relative overflow-hidden aspect-[4/3]">
                                <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.jpg') }}"
                                    alt="{{ $dest->destination_name }}" loading="lazy"
                                    class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-orcha-navy/85 via-orcha-navy/10 to-transparent">
                                </div>

                                <span
                                    class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full top-3 left-3 text-orcha-navy bg-orcha-sun">
                                    <x-heroicon-s-user-group class="w-4 h-4" />
                                    {{ shortNumber($dest->total_visitor) }} pengunjung
                                </span>

                                <span
                                    class="absolute px-3 py-1 text-xs font-bold rounded-full top-3 right-3 text-white bg-white/20 backdrop-blur">
                                    {{ $dest->wilayah_label }}
                                </span>

                                <div class="absolute bottom-4 left-4 right-4">
                                    <h2 class="text-xl font-bold leading-tight text-white font-heading sm:text-2xl">
                                        {{ $dest->destination_name }}
                                    </h2>
                                    @if ($dest->provinsi)
                                        <p class="flex items-center gap-1 mt-1 text-sm text-slate-200">
                                            <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                            {{ $dest->provinsi }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col flex-1 p-5 sm:p-6">
                                @if ($dest->deskripsi)
                                    <p class="mb-4 text-sm leading-relaxed text-slate-600">{{ $dest->deskripsi }}</p>
                                @endif

                                @if (!empty($dest->others_photo))
                                    <div class="flex gap-2 mb-4">
                                        @foreach (array_slice($dest->others_photo, 0, 4) as $thumb)
                                            <img src="{{ $thumb }}" alt="" loading="lazy"
                                                class="object-cover w-12 h-12 rounded-xl ring-1 ring-orcha-foam">
                                        @endforeach
                                    </div>
                                @endif

                                <a href="{{ $wa("Halo Orcha Journey, saya ingin ke {$dest->destination_name}. Ada paket atau armadanya?") }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="w-full mt-auto btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                                    <x-bi-whatsapp class="w-4 h-4" />
                                    Tanya Paket ke Sini
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $destinations->links('partials.paginasi-orcha') }}
                </div>
            @endif

            <div
                class="flex flex-col items-center gap-4 p-8 mt-12 text-center sm:flex-row sm:text-left sm:justify-between rounded-3xl bg-orcha-foam/70">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Punya destinasi impian sendiri?</p>
                    <p class="mt-1 text-sm text-slate-600">Sebutkan tempatnya — kami susun rute, armada, dan biayanya
                        untuk Anda.</p>
                </div>
                <a href="{{ $wa('Halo Orcha Journey, saya ingin membuat perjalanan ke destinasi pilihan saya.') }}"
                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Rencanakan Sekarang
                </a>
            </div>
        </div>
    </section>
</div>
