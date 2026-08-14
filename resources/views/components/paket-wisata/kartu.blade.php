@props(['paket'])

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa =
        'https://api.whatsapp.com/send?phone=' .
        config('orcha.whatsapp') .
        '&text=' .
        rawurlencode("Halo Orcha Journey, saya tertarik paket {$paket->name} ({$paket->category_label}). Boleh minta detailnya?");
    $unggulan = (bool) $paket->is_best_choice;
@endphp

<article
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden bg-white card-orcha ' . ($unggulan ? 'ring-2 ring-orcha-sun' : '')]) }}>

    <div
        class="relative px-6 pt-6 pb-5 sm:px-7 {{ $unggulan ? 'bg-gradient-to-br from-orcha-navy to-orcha-abyss text-white' : 'bg-gradient-to-br from-orcha-foam to-white' }}">
        @if ($unggulan)
            <span
                class="absolute top-0 right-0 px-4 py-1.5 text-[0.7rem] font-black tracking-wider uppercase rounded-bl-2xl bg-orcha-sun text-orcha-navy">
                Terlaris
            </span>
        @endif

        <span
            class="inline-block px-3 py-1 text-[0.7rem] font-bold tracking-wider uppercase rounded-full {{ $unggulan ? 'bg-white/15 text-orcha-sun' : 'bg-orcha-wave/10 text-orcha-ocean' }}">
            {{ $paket->category_label }}
        </span>

        <h3 class="mt-3 text-2xl font-bold font-heading {{ $unggulan ? 'text-white' : 'text-orcha-navy' }}">
            {{ $paket->name }}
        </h3>

        <div class="mt-2 space-y-1 text-sm {{ $unggulan ? 'text-slate-300' : 'text-slate-500' }}">
            @if ($paket->jadwal_label)
                <p class="flex items-center gap-1.5 font-semibold {{ $unggulan ? 'text-orcha-sun' : 'text-orcha-ocean' }}">
                    <x-heroicon-o-calendar-days class="w-4 h-4" />
                    {{ $paket->jadwal_label }}
                </p>
            @endif

            @if ($paket->duration)
                <p class="flex items-center gap-1.5">
                    <x-heroicon-o-clock class="w-4 h-4" />
                    {{ $paket->duration }}
                </p>
            @endif

            @if ($paket->titik_jemput)
                <p class="flex items-center gap-1.5">
                    <x-heroicon-o-map-pin class="w-4 h-4" />
                    Jemput: {{ $paket->titik_jemput }}
                </p>
            @endif

            @if ($paket->minimal_peserta > 1)
                <p class="flex items-center gap-1.5">
                    <x-heroicon-o-user-group class="w-4 h-4" />
                    Berangkat minimal {{ $paket->minimal_peserta }} orang
                </p>
            @endif
        </div>

        @if ($paket->catatan_promo)
            <p
                class="inline-block px-3 py-1 mt-3 text-xs font-bold rounded-full {{ $unggulan ? 'bg-orcha-sun text-orcha-navy' : 'bg-orcha-sun/20 text-orcha-ocean' }}">
                {{ $paket->catatan_promo }}
            </p>
        @endif

        <div class="flex flex-wrap items-end mt-4 gap-x-3 gap-y-1">
            <p class="text-3xl font-black font-heading {{ $unggulan ? 'text-orcha-sun' : 'text-orcha-ocean' }}">
                {{ $rupiah($paket->price) }}
            </p>
            <span class="text-sm {{ $unggulan ? 'text-slate-400' : 'text-slate-500' }}">/ orang</span>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-2">
            @if ($paket->original_price > 0 && $paket->original_price > $paket->price)
                <span class="text-sm line-through text-slate-400">{{ $rupiah($paket->original_price) }}</span>
            @endif
            @if ($paket->discount_percentage)
                <span class="px-2 py-0.5 text-xs font-bold text-red-600 bg-red-100 rounded-full">
                    Hemat {{ $paket->discount_percentage }}%
                </span>
            @endif
        </div>
    </div>

    <div class="flex flex-col flex-1 p-6 sm:p-7">
        <p class="text-xs font-bold tracking-wider uppercase text-slate-400">Destinasi termasuk</p>
        <ul class="flex-1 mt-3 space-y-2.5">
            @forelse ($paket->destination_list ?? [] as $destinasi)
                <li class="flex items-start gap-2 text-sm text-slate-600">
                    <x-heroicon-s-map-pin class="w-4 h-4 mt-0.5 shrink-0 text-orcha-sky" />
                    <span>{{ $destinasi }}</span>
                </li>
            @empty
                <li class="text-sm text-slate-400">Rincian menyusul — tanyakan ke kami.</li>
            @endforelse
        </ul>

        <div class="mt-7 space-y-2">
            <a href="{{ route('paket-detail', $paket->uuid) }}"
                class="w-full btn-orcha {{ $unggulan ? 'btn-orcha-sun' : 'btn-orcha-primary' }}">
                Lihat Detail & Itinerary
                <x-heroicon-o-arrow-right class="w-5 h-5" />
            </a>

            @if ($paket->category === 'open_trip')
                <a href="{{ route('pendaftaran-open-trip', ['paket' => $paket->uuid]) }}"
                    class="w-full btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                    Daftar Open Trip
                </a>
            @else
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                    class="w-full btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                    <x-bi-whatsapp class="w-4 h-4" />
                    Pesan via WhatsApp
                </a>
            @endif
        </div>
    </div>
</article>
