<?php

use App\Models\Etalase\DestinationPopuler;
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

    /**
     * Destinasi yang sedang dibuka detailnya.
     *
     * Ikut di alamat, bukan hanya keadaan di layar: pengunjung yang menemukan
     * satu destinasi menarik biasanya mengirimkannya ke teman seperjalanan, dan
     * tautan yang selalu mendarat di daftar memaksa penerimanya mencari sendiri.
     * Tombol kembali peramban pun jadi menutup detail, bukan meninggalkan
     * halaman.
     */
    #[Url(as: 'lihat', except: null)]
    public ?int $lihat = null;

    public function buka(int $id): void
    {
        $this->lihat = $id;
    }

    public function tutupDetail(): void
    {
        $this->lihat = null;
    }

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
                // Yang baru ditambahkan di atas — halaman ini ikut jadi kabar
                // "ada apa yang baru", bukan daftar yang sama setiap kali
                // dibuka. Jumlah pengunjungnya tetap tampil di tiap kartu,
                // jadi yang hilang urutannya saja, bukan keterangannya.
                ->latest()
                ->latest('id')
                ->paginate(9),
            // Dicari terpisah dari daftar: destinasi yang dibuka lewat tautan
            // belum tentu ada di halaman yang sedang tampil, apalagi saat
            // penyaringnya sedang menyala.
            'detail' => $this->lihat ? DestinationPopuler::find($this->lihat) : null,
            'total' => DestinationPopuler::count(),
            'totalPengunjung' => (int) DestinationPopuler::sum('total_visitor'),
            'totalProvinsi' => DestinationPopuler::distinct()->count('provinsi'),
            'daftarWilayah' => collect(\App\Models\Etalase\WilayahTambahan::gabungan())
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
        image="images/HERO/destinasi.webp" />

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
            {{-- Dipusatkan di layar lebar. Baris di atasnya sudah terbagi kiri-kanan
                 (ringkasan dan pencarian), jadi deretan tab yang ikut rata kiri
                 menggantung tanpa penyeimbang di kanannya. Di layar sempit tetap
                 rata kiri: deretnya digulung mendatar, dan yang digulung harus
                 mulai dari tepi. --}}
            <div class="mb-10 tab-scroller lg:justify-center">
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
                                <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.webp') }}"
                                    alt="{{ $dest->destination_name }}" loading="lazy"
                                    class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-orcha-navy/85 via-orcha-navy/10 to-transparent">
                                </div>

                                {{-- Nol pengunjung bukan bukti apa-apa; ia justru membaca
                                     terbalik dari maksud lencananya. Destinasi yang baru
                                     dicatat memang belum pernah kami antar siapa pun —
                                     yang perlu dikatakan "baru", bukan "0". --}}
                                @if ($dest->total_visitor > 0)
                                    <span
                                        class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full top-3 left-3 text-orcha-navy bg-orcha-sun">
                                        <x-heroicon-s-user-group class="w-4 h-4" />
                                        {{ shortNumber($dest->total_visitor) }} pengunjung
                                    </span>
                                @elseif ($dest->created_at?->gt(now()->subDays(30)))
                                    <span
                                        class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-white rounded-full top-3 left-3 bg-orcha-sky">
                                        <x-heroicon-s-sparkles class="w-4 h-4" />
                                        Baru
                                    </span>
                                @endif

                                <span
                                    class="absolute px-3 py-1 text-xs font-bold rounded-full top-3 right-3 text-white bg-white/20 backdrop-blur">
                                    {{ $dest->wilayah_label }}
                                </span>

                                <div class="absolute bottom-4 left-4 right-4">
                                    {{-- Namanya tautan sungguhan ke halaman destinasinya.

                                         Tombol "Lihat Detail" di bawah membuka panel di halaman
                                         ini — itu tetap, alasannya ada di panelnya. Tetapi
                                         tombol bukan tautan: mesin pencari tidak menekannya,
                                         sehingga tanpa <a> di sini tidak ada satu pun jalan
                                         menuju halaman destinasi selain peta situs. --}}
                                    <h2 class="text-xl font-bold leading-tight text-white font-heading sm:text-2xl">
                                        <a href="{{ route('destinasi.detail', $dest) }}" wire:navigate
                                            class="transition hover:text-orcha-sun">
                                            {{ $dest->destination_name }}
                                        </a>
                                    </h2>
                                    @if ($dest->alamat_singkat)
                                        {{-- Daerahnya disebut lebih dulu: itu yang dicari dan
                                             ditanyakan penyewa — berangkat dari mana, menginap
                                             di mana. "Jawa Timur" saja membentang 47 ribu km
                                             persegi. --}}
                                        <p class="flex items-center gap-1 mt-1 text-sm text-slate-200">
                                            <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                            {{ $dest->alamat_singkat }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col flex-1 p-5 sm:p-6">
                                {{-- Dipotong di tiga baris dan tingginya dipatok. Keterangan
                                     yang panjangnya berbeda-beda menggeser galeri kecil di
                                     bawahnya ke ketinggian yang berbeda pula, dan sebaris
                                     kartu jadi terbaca berantakan walaupun tiap kartunya
                                     rapi. --}}
                                <p class="min-h-[4.1rem] mb-4 text-sm leading-relaxed line-clamp-3 text-slate-600">
                                    {{ $dest->deskripsi }}
                                </p>

                                {{-- Galeri kecil dan tombolnya satu kelompok yang menempel ke
                                     dasar kartu. Dipisah, galerinya mengikuti panjang
                                     keterangan sementara tombolnya menempel ke dasar — dua
                                     ketinggian berbeda dalam satu baris kartu. --}}
                                <div class="mt-auto">
                                @if (!empty($dest->others_photo))
                                    <div class="flex items-center justify-between gap-2 mb-4">
                                        <div class="flex gap-2">
                                            @foreach (array_slice($dest->others_photo, 0, 4) as $thumb)
                                                <img src="{{ $thumb }}" alt="" loading="lazy"
                                                    class="object-cover w-12 h-12 rounded-xl ring-1 ring-orcha-foam">
                                            @endforeach
                                        </div>

                                        {{-- Rata kanan, seimbang dengan gambarnya di kiri:
                                             yang kiri contoh, yang kanan berapa banyak. --}}
                                        <span class="text-xs font-semibold whitespace-nowrap text-slate-400">
                                            {{ count($dest->others_photo) + 1 }} foto
                                        </span>
                                    </div>
                                @endif

                                {{-- Dua tindakan berdampingan: melihat dulu, atau langsung
                                     bertanya. Sebelumnya hanya ada tombol WhatsApp, sehingga
                                     satu-satunya cara mengetahui lebih banyak adalah menanyakannya
                                     — pertanyaan yang jawabannya sudah ada di sini. --}}
                                <div class="flex gap-2">
                                    <button type="button" wire:click="buka({{ $dest->id }})"
                                        class="flex-1 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                        <x-heroicon-o-photo class="w-4 h-4" />
                                        Lihat Detail
                                    </button>

                                    <a href="{{ $wa("Halo Orcha Journey, saya ingin ke {$dest->destination_name}. Ada paket atau armadanya?") }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="btn-orcha btn-orcha-outline !py-2.5 !text-sm !px-4"
                                        aria-label="Tanya paket ke {{ $dest->destination_name }} lewat WhatsApp">
                                        <x-bi-whatsapp class="w-4 h-4" />
                                    </a>
                                </div>
                                </div>
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

    {{-- Jendela detail destinasi.

         Di halaman yang sama, bukan halaman tersendiri: pengunjung sedang
         menyusuri daftar dengan penyaring wilayah yang sudah ia atur, dan
         memindahkannya ke halaman lain berarti ia harus menyusunnya lagi saat
         kembali. Keadaan terbukanya tetap ikut di alamat, jadi tautannya bisa
         dibagikan dan tombol kembali peramban menutupnya. --}}
    @if ($detail)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
            role="dialog" aria-modal="true" aria-labelledby="judul-destinasi" wire:key="detail-{{ $detail->id }}">
            {{-- Latar gelap ikut menutup: itu yang pertama dicoba orang saat
                 ingin keluar, sebelum mencari tombol silang. --}}
            <button type="button" wire:click="tutupDetail" aria-label="Tutup detail"
                class="absolute inset-0 bg-orcha-navy/70 backdrop-blur-sm"></button>

            @php
                $galeri = array_values(array_filter(array_merge(
                    [$detail->main_photo],
                    $detail->others_photo ?? [],
                )));
            @endphp

            <div x-data="{ aktif: 0 }" @keydown.escape.window="$wire.tutupDetail()"
                class="relative w-full max-w-3xl overflow-hidden bg-white shadow-2xl rounded-t-3xl sm:rounded-3xl max-h-[92vh] overflow-y-auto">

                <div class="relative bg-orcha-foam aspect-[16/10]">
                    @forelse ($galeri as $urutan => $foto)
                        <img src="{{ $foto }}" alt="{{ $detail->destination_name }}"
                            x-show="aktif === {{ $urutan }}" x-cloak loading="lazy" decoding="async"
                            class="absolute inset-0 object-cover w-full h-full">
                    @empty
                        <div class="flex items-center justify-center w-full h-full text-slate-400">
                            <x-heroicon-o-photo class="w-12 h-12" />
                        </div>
                    @endforelse

                    <div class="absolute inset-x-0 bottom-0 h-32 pointer-events-none bg-gradient-to-t from-orcha-navy/90 to-transparent">
                    </div>

                    <button type="button" wire:click="tutupDetail" aria-label="Tutup detail"
                        class="absolute flex items-center justify-center w-9 h-9 rounded-full top-4 right-4 bg-white/90 text-orcha-navy backdrop-blur hover:bg-white">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>

                    <span class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full top-4 left-4 text-orcha-navy bg-orcha-sun">
                        <x-heroicon-s-user-group class="w-4 h-4" />
                        {{ shortNumber($detail->total_visitor) }} pengunjung diantar
                    </span>

                    <div class="absolute inset-x-0 bottom-0 p-5 sm:p-7">
                        <h2 id="judul-destinasi" class="text-2xl font-bold text-white font-heading sm:text-3xl">
                            {{ $detail->destination_name }}
                        </h2>
                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-sm text-slate-200">
                            @if ($detail->alamat_singkat)
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                    {{ $detail->alamat_singkat }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-globe-asia-australia class="w-4 h-4 text-orcha-sun" />
                                {{ $detail->wilayah_label }}
                            </span>
                        </p>
                    </div>
                </div>

                {{-- Foto lain dipilih di sisi peramban: mengganti gambar tidak
                     mengubah apa pun di server, dan perjalanan bolak-balik ke
                     server hanya membuat galerinya terasa berat. --}}
                @if (count($galeri) > 1)
                    <div class="flex gap-2 px-5 pt-4 overflow-x-auto sm:px-7">
                        @foreach ($galeri as $urutan => $foto)
                            <button type="button" @click="aktif = {{ $urutan }}"
                                :class="aktif === {{ $urutan }} ? 'ring-2 ring-orcha-ocean' : 'ring-1 ring-orcha-foam opacity-70'"
                                class="overflow-hidden rounded-xl shrink-0"
                                aria-label="Lihat foto {{ $urutan + 1 }}">
                                <img src="{{ $foto }}" alt="" loading="lazy" decoding="async"
                                    class="object-cover w-16 h-16">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="p-5 sm:p-7">
                    @if ($detail->deskripsi)
                        <p class="text-sm leading-relaxed text-slate-600">{{ $detail->deskripsi }}</p>
                    @else
                        <p class="text-sm text-slate-400">Keterangan destinasi ini belum ditulis.</p>
                    @endif

                    {{-- Dua jalan lanjut yang berbeda: rombongan yang butuh paket
                         lengkap, dan yang hanya perlu kendaraannya. Sebelumnya
                         hanya WhatsApp, sehingga yang sudah tahu ingin menyewa
                         unit tetap harus bertanya dulu. --}}
                    <div class="grid gap-2 mt-6 sm:grid-cols-2">
                        <a href="{{ $wa("Halo Orcha Journey, saya ingin ke {$detail->destination_name}. Ada paket atau armadanya?") }}"
                            target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                            <x-bi-whatsapp class="w-4 h-4" />
                            Tanya Paket ke Sini
                        </a>
                        <a href="{{ route('sewa-kendaraan') }}" wire:navigate
                            class="btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                            <x-heroicon-o-truck class="w-4 h-4" />
                            Lihat Armada
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
