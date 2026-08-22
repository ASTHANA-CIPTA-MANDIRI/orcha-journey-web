@props([
    'paket',
    // Satu kartu yang sengaja dijadikan tujuan mata. Dipakai beranda, yang
    // hanya menampilkan tiga paket; di halaman daftar lengkap tidak ada yang
    // disorot — kalau setiap layar punya "yang paling", tidak ada yang paling.
    'sorot' => false,
    // Lencana "Terlaris" bawaan yang mengikuti penanda admin. Dimatikan di
    // tempat yang sudah punya sorotan sendiri, supaya lencananya tidak muncul
    // di beberapa kartu sekaligus.
    'lencanaUnggulan' => true,
])

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa =
        'https://api.whatsapp.com/send?phone=' .
        config('orcha.whatsapp') .
        '&text=' .
        rawurlencode("Halo Orcha Journey, saya tertarik paket {$paket->name} ({$paket->category_label}). Boleh minta detailnya?");
    $unggulan = $sorot || ($lencanaUnggulan && (bool) $paket->is_best_choice);
    $destinasi = collect($paket->destination_list ?? [])->filter()->values();
@endphp

@php
    // Yang disorot dibedakan pada EMPAT hal sekaligus, bukan satu: garis tepi
    // tebal, cahaya hangat di bawahnya, terangkat sedikit, dan tidak ikut
    // diredupkan. Satu pembeda saja — garis tepi tipis seperti sebelumnya —
    // hilang begitu kartunya berdampingan dengan dua kartu lain yang sama
    // ramainya.
    // Kelas sorotnya didefinisikan di new-homepage.css, bukan dirangkai dari
    // utilitas ring — ring digambar lewat box-shadow dan berebut properti itu
    // dengan bayangan .card-orcha, sehingga garisnya hilang sama sekali.
    $kelasSorot = $sorot
        ? 'kartu-sorot lg:-translate-y-3 z-10'
        : ($unggulan ? 'ring-2 ring-orcha-sun' : '');

@endphp

<article
    {{ $attributes->merge(['class' => 'relative flex flex-col overflow-hidden bg-white card-orcha group transition-transform ' . $kelasSorot]) }}>

    {{-- ============ SAMPUL ============
         Kartu ini dulu tanpa gambar sama sekali — semuanya teks rata kiri yang
         menumpuk. Sampul memberi jangkar visual sebelum satu kata pun dibaca.

         Memakai foto unggahan admin; bila paket belum punya foto, otomatis
         jatuh ke ilustrasi bawaan sehingga kartunya tidak pernah kosong. --}}
    <div class="relative overflow-hidden aspect-[16/10] bg-orcha-navy">
        <img src="{{ $paket->sampul }}" alt="{{ $paket->name }}" loading="lazy"
            class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">

        <div class="absolute inset-0 bg-gradient-to-t from-orcha-navy via-orcha-navy/55 to-transparent"></div>

        <span
            class="absolute top-4 left-4 px-3 py-1 text-[0.7rem] font-bold tracking-wider uppercase rounded-full bg-white/90 text-orcha-ocean backdrop-blur">
            {{ $paket->category_label }}
        </span>

        @if ($unggulan)
            {{-- Bunyinya tetap "Terlaris", tidak dinaikkan jadi "paling banyak
                 dipesan".

                 Sempat saya tulis begitu supaya klaimnya terdengar berdasar —
                 dan justru itu kesalahannya: kalimat yang menyebut angka bisa
                 dibantah angka. Penanda ini dipasang admin di halaman
                 pengelolaan, tidak dihitung dari jumlah pendaftaran, dan saat
                 diperiksa paket yang tertandai memang ada yang pendaftarannya
                 nol. Yang disorot dibedakan ukurannya, bukan ditambahi klaim
                 yang tidak dijamin datanya. --}}
            <span
                class="absolute top-4 right-4 inline-flex items-center gap-1.5 rounded-full bg-orcha-sun text-orcha-navy font-black uppercase tracking-wider
                    {{ $sorot ? 'px-4 py-1.5 text-xs shadow-lg shadow-orcha-sun/40' : 'px-3 py-1 text-[0.7rem]' }}">
                @if ($sorot)
                    <x-heroicon-s-fire class="w-4 h-4" />
                @endif
                Terlaris
            </span>
        @endif

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-6">
            <h3 class="text-xl font-bold leading-snug text-white sm:text-2xl font-heading">
                {{ $paket->name }}
            </h3>

            @if ($paket->jadwal_label)
                <p class="flex items-center gap-1.5 mt-1.5 text-sm font-semibold text-orcha-sun">
                    <x-heroicon-s-calendar-days class="w-4 h-4 shrink-0" />
                    {{ $paket->jadwal_label }}
                </p>
            @endif
        </div>
    </div>

    <div class="flex flex-col flex-1 p-5 sm:p-6">

        {{-- ============ KETERANGAN SINGKAT ============
             Disusun dua kolom bersekat, bukan empat baris rata kiri berurutan.
             Matanya jadi punya titik henti, bukan satu tiang panjang. --}}
        <div class="grid grid-cols-2 border rounded-2xl border-orcha-foam divide-x divide-orcha-foam">
            <div class="p-3 text-center">
                <x-heroicon-o-clock class="w-5 h-5 mx-auto text-orcha-sky" />
                <p class="mt-1 text-sm font-bold text-orcha-navy">{{ $paket->duration ?: '—' }}</p>
                <p class="text-[0.68rem] tracking-wide uppercase text-slate-400">Durasi</p>
            </div>

            <div class="p-3 text-center">
                <x-heroicon-o-user-group class="w-5 h-5 mx-auto text-orcha-sky" />
                <p class="mt-1 text-sm font-bold text-orcha-navy">
                    {{ $paket->minimal_peserta > 1 ? 'Min. ' . $paket->minimal_peserta . ' orang' : 'Bebas' }}
                </p>
                <p class="text-[0.68rem] tracking-wide uppercase text-slate-400">Peserta</p>
            </div>
        </div>

        @if ($paket->titik_jemput)
            <p class="flex items-start gap-2 mt-3 text-sm text-slate-500">
                <x-heroicon-s-map-pin class="w-4 h-4 mt-0.5 shrink-0 text-orcha-sun" />
                <span>Jemput: <span class="font-semibold text-orcha-navy">{{ $paket->titik_jemput }}</span></span>
            </p>
        @endif

        {{-- ============ DESTINASI ============
             Berupa cip yang mengalir mengikuti lebar kartu, bukan daftar
             menurun — mengisi ruang kosong di kanan dan lebih cepat dipindai. --}}
        @if ($destinasi->isNotEmpty())
            <div class="mt-4">
                <p class="text-[0.68rem] font-bold tracking-wider uppercase text-slate-400">Destinasi termasuk</p>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ($destinasi->take(4) as $satu)
                        <span
                            class="px-2.5 py-1 text-xs font-medium rounded-full bg-orcha-foam text-orcha-navy">{{ $satu }}</span>
                    @endforeach

                    @if ($destinasi->count() > 4)
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-orcha-sky/15 text-orcha-ocean">
                            +{{ $destinasi->count() - 4 }} lagi
                        </span>
                    @endif
                </div>
            </div>
        @endif

        @if ($paket->catatan_promo)
            <p class="inline-flex self-start gap-1.5 items-center px-3 py-1 mt-4 text-xs font-bold rounded-full bg-orcha-sun/20 text-orcha-ocean">
                <x-heroicon-s-sparkles class="w-3.5 h-3.5" />
                {{ $paket->catatan_promo }}
            </p>
        @endif

        {{-- ============ HARGA ============
             Harga di kiri, potongan di kanan: barisnya jadi punya dua sisi,
             tidak lagi semuanya menempel ke tepi kiri. --}}
        <div class="flex items-end justify-between gap-3 pt-4 mt-auto border-t border-orcha-foam">
            <div>
                <p class="text-[0.68rem] tracking-wide uppercase text-slate-400">Mulai dari</p>
                <p class="text-2xl font-black leading-tight font-heading text-orcha-ocean">
                    {{ $rupiah($paket->price) }}
                    <span class="text-xs font-medium text-slate-500">/ orang</span>
                </p>
            </div>

            @if ($paket->diskon_tampil > 0)
                <div class="text-right shrink-0">
                    <span class="inline-block px-2 py-0.5 text-xs font-bold text-red-600 bg-red-100 rounded-full">{{ 'Hemat ' . $paket->diskon_tampil . '%' }}</span>
                    <p class="mt-1 text-xs line-through text-slate-400">{{ $rupiah($paket->original_price) }}</p>
                </div>
            @endif
        </div>

        <div class="mt-4 space-y-2">
            <a href="{{ route('paket-detail', $paket->uuid) }}"
                class="w-full btn-orcha {{ $unggulan ? 'btn-orcha-sun' : 'btn-orcha-primary' }}">
                Lihat Detail &amp; Itinerary
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
