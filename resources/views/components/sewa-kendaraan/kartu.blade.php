@props(['kendaraan'])

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');

    // Unit belum tentu punya foto. Daripada kotak kosong, tiap jenis dapat
    // latar gradasi sendiri supaya kartunya tetap terbaca dan tidak seragam.
    $latar = match ($kendaraan->type) {
        'bus' => 'from-orcha-navy to-orcha-ocean',
        'hiace' => 'from-orcha-ocean to-orcha-sky',
        default => 'from-orcha-sky to-orcha-ocean',
    };
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden card-orcha group']) }}>
    <div class="relative overflow-hidden bg-orcha-foam aspect-[16/10]">
        @if ($kendaraan->image)
            <img src="{{ $kendaraan->image }}" alt="{{ $kendaraan->name }}" loading="lazy"
                class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
        @else
            <div class="flex flex-col items-center justify-center w-full h-full bg-gradient-to-br {{ $latar }}">
                <x-heroicon-o-truck
                    class="w-20 h-20 text-white/70 transition-transform duration-700 group-hover:scale-110" />
                <span class="mt-2 text-xs font-bold tracking-[0.2em] uppercase text-white/75">
                    {{ $kendaraan->brand }}
                </span>
            </div>
        @endif

        <span
            class="absolute px-3 py-1 text-xs font-bold tracking-wide uppercase rounded-full top-3 left-3 bg-white/90 text-orcha-ocean backdrop-blur">
            {{ $kendaraan->type_label }}
        </span>

        @unless ($kendaraan->lepas_kunci)
            {{-- Disebut di kartunya, bukan hanya di formulir pemesanan: penyewa
                 yang mencari unit lepas kunci berhak tahu sebelum mengisi apa pun. --}}
            <span
                class="absolute px-3 py-1 text-xs font-bold rounded-full bottom-3 left-3 bg-orcha-sun/90 text-orcha-navy backdrop-blur">
                Selalu dengan sopir
            </span>
        @endunless

        {{-- Transmisi yang benar-benar tersedia, bukan sekadar transmisi satu unit --}}
        <span
            class="absolute px-3 py-1 text-xs font-bold rounded-full top-3 right-3 bg-orcha-navy/80 text-white backdrop-blur">
            {{ $kendaraan->transmisi_label }}
        </span>
    </div>

    <div class="flex flex-col flex-1 p-5 sm:p-6">
        <h3 class="text-lg font-bold font-heading text-orcha-navy">
            {{ $kendaraan->name }}
            @if ($kendaraan->varian)
                <span class="font-semibold text-orcha-ocean">{{ $kendaraan->varian }}</span>
            @endif
        </h3>
        {{-- Tipe, tahun, dan cc: yang ditanyakan penyewa sebelum memesan, dan
             selama ini hanya ada di kepala pemilik. Bagian yang belum diketahui
             dilewati supaya unit lama tetap terbaca wajar. --}}
        <p class="text-sm text-slate-500">
            {{ $kendaraan->brand }}
            @if ($kendaraan->tahun) · {{ $kendaraan->tahun }} @endif
            @if ($kendaraan->cc) · {{ number_format($kendaraan->cc, 0, ',', '.') }} cc @endif
        </p>

        <div class="grid grid-cols-2 gap-3 py-4 mt-4 text-sm border-t border-b text-slate-600 border-orcha-foam">
            <span class="flex items-center gap-2">
                <x-heroicon-o-user-group class="w-4 h-4 text-orcha-sun" />
                {{-- capacity berisi kursi PENUMPANG, jadi inilah angka yang boleh
                     dijanjikan. Kursi totalnya disebut dalam tanda kurung hanya
                     bila berbeda, yaitu saat satu kursi terpakai sopir. --}}
                {{ $kendaraan->capacity }} penumpang
                @unless ($kendaraan->lepas_kunci)
                    <span class="text-xs text-slate-400">({{ $kendaraan->kursi_total }} kursi)</span>
                @endunless
            </span>
            <span class="flex items-center gap-2">
                <x-heroicon-o-cog-6-tooth class="w-4 h-4 text-orcha-sun" />
                {{ $kendaraan->transmisi_label }}
            </span>
        </div>

        {{-- Tarif per satuan waktu — tiap satuan punya harga sendiri --}}
        <dl class="mt-4 space-y-1.5">
            @foreach ([['jam', 'Per jam', $kendaraan->harga_per_jam], ['12jam', 'Paket 12 jam', $kendaraan->harga_12_jam], ['hari', 'Per hari (24 jam)', $kendaraan->price_per_day]] as [$kunci, $label, $harga])
                @if ($harga)
                    <div
                        class="flex items-baseline justify-between gap-3 {{ $kunci === 'hari' ? 'pt-1.5 border-t border-orcha-foam' : '' }}">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd
                            class="font-heading tabular {{ $kunci === 'hari' ? 'text-lg font-black text-orcha-navy' : 'text-sm font-bold text-orcha-ocean' }}">
                            {{ $rupiah($harga) }}
                        </dd>
                    </div>
                @endif
            @endforeach
        </dl>

        {{-- Keterangan BBM/tol/parkir dibaca dari unitnya, bukan ditulis tetap.
             Sebelumnya semua kartu menyatakan "belum termasuk BBM & tol", jadi
             unit all-in pun terbaca sebaliknya — penyewa bertanya ulang, atau
             mengira sudah termasuk padahal belum lalu berselisih saat membayar. --}}
        <div class="mt-2 space-y-1 text-xs">
            @if ($kendaraan->harga_sopir)
                <p class="text-slate-500">Sopir +{{ $rupiah($kendaraan->harga_sopir) }}/hari</p>
            @endif

            <p class="{{ collect($kendaraan->rincian_operasional)->contains('termasuk', true) ? 'font-semibold text-orcha-ocean' : 'text-slate-500' }}">
                {{ $kendaraan->operasional_label }}
            </p>
        </div>

        <a href="{{ route('sewa-kendaraan.pesan', ['unit' => $kendaraan->uuid]) }}"
            class="w-full mt-5 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
            <x-heroicon-o-pencil-square class="w-4 h-4" />
            Pesan Unit Ini
        </a>
    </div>
</article>
