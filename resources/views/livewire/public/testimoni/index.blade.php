<?php

use App\Models\Etalase\Testimoni;
use App\Support\PemilikPesanan;
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

    /* ------------------------- Kirim testimoni ------------------------- */

    public string $kode = '';

    public string $empatDigit = '';

    public string $isi = '';

    public int $nilai = 5;

    public bool $terkirim = false;

    /**
     * Testimoni hanya boleh ditulis yang pesanannya terbukti.
     *
     * Kode ditambah empat digit terakhir nomor — penjagaan yang sama dengan
     * halaman lacak pesanan. Itu menyelesaikan dua hal sekaligus: spam tidak
     * punya jalan masuk, dan testimoninya boleh ditandai terverifikasi secara
     * jujur, karena memang bisa ditelusuri ke sebuah pesanan nyata.
     */
    public function kirim(): void
    {
        $this->validate([
            'kode' => ['required', 'string', 'max:30'],
            'empatDigit' => ['required', 'digits:4'],
            'isi' => ['required', 'string', 'min:20', 'max:1000'],
            'nilai' => ['required', 'integer', 'min:1', 'max:5'],
        ], [], [
            'kode' => 'kode pesanan',
            'empatDigit' => '4 digit WhatsApp',
            'isi' => 'cerita perjalanan',
            'nilai' => 'penilaian',
        ]);

        $pesanan = PemilikPesanan::cariTerbatas($this->kode, $this->empatDigit, request()->ip());

        if (! $pesanan) {
            // Satu pesan untuk kedua kegagalan — pembeda apa pun mengubah
            // halaman ini jadi alat pemeriksa kode.
            $this->addError('kode', 'Kode pesanan dan empat digit WhatsApp tidak cocok. Keduanya ada di email yang Anda terima saat memesan.');

            return;
        }

        /*
         | Satu pesanan, satu testimoni.
         |
         | Tanpa ini satu orang bisa mengirim berkali-kali dan memenuhi halaman
         | dengan suaranya sendiri — dan karena semuanya terverifikasi, tidak
         | ada satu pun tanda bahwa itu orang yang sama.
         */
        if (Testimoni::where('kode_pesanan', $pesanan->kode)->exists()) {
            $this->addError('kode', 'Testimoni untuk pesanan ini sudah pernah dikirim. Terima kasih!');

            return;
        }

        Testimoni::create([
            'customer_name' => $pesanan->nama,
            'rating' => $this->nilai,
            'testimonial' => $this->isi,
            'kode_pesanan' => $pesanan->kode,
            // Menunggu disetujui, bukan langsung tayang. Bukan karena
            // penulisnya diragukan — ia sudah membuktikan pesanannya —
            // melainkan karena halaman ini terbaca sebagai suara perusahaan.
            'status' => 'menunggu',
        ]);

        $this->reset(['kode', 'empatDigit', 'isi', 'nilai']);
        $this->terkirim = true;
    }

    public function with(): array
    {
        $semua = Testimoni::query()->tayang();

        return [
            'testimonials' => Testimoni::query()->tayang()
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
                    'jumlah' => Testimoni::tayang()->where('rating', $bintang)->count(),
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
                                    <p class="flex items-center gap-1.5 text-sm font-bold text-orcha-navy">
                                        {{ $testimoni->customer_name }}

                                        {{-- Ditandai hanya bila benar-benar bisa ditelusuri ke
                                             sebuah pesanan. Testimoni yang jelas dikurasi penjual
                                             dibaca sebagai bahan pemasaran; yang terverifikasi
                                             dibaca sebagai kesaksian — dan bedanya justru yang
                                             dicari calon pembeli. --}}
                                        @if ($testimoni->terverifikasi)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[.65rem] font-bold rounded-full text-emerald-700 bg-emerald-100"
                                                title="Ditulis pelanggan yang pesanannya terverifikasi">
                                                <x-heroicon-s-check-badge class="w-3 h-3" />
                                                Terverifikasi
                                            </span>
                                        @endif
                                    </p>
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

            {{-- Formulir kirim testimoni.

                 Sebelumnya bagian ini cuma tombol WhatsApp: pelanggan mengetik
                 ceritanya di sana, lalu admin menyalin dan mengetikkannya ulang
                 di panel. Pekerjaan itu selalu kalah prioritas dibanding pesanan
                 yang sedang berjalan, jadi sebagian besar cerita tidak pernah
                 sampai ke halaman ini.

                 Kode pesanan dipakai sebagai syarat, bukan formulir terbuka.
                 Penulisnya membuktikan ia memang pernah memesan — dan itu
                 menyelesaikan dua hal sekaligus: spam tidak punya jalan masuk,
                 dan testimoninya boleh ditandai terverifikasi secara jujur. --}}
            <div class="p-6 mt-12 sm:p-8 rounded-3xl bg-orcha-foam/70" id="kirim-testimoni">
                @if ($terkirim)
                    <div class="text-center">
                        <p class="text-lg font-bold font-heading text-orcha-navy">Terima kasih — ceritanya sudah kami
                            terima.</p>
                        <p class="mt-2 text-sm text-slate-600">
                            Tim kami membacanya dulu sebelum ditayangkan di halaman ini. Biasanya tidak sampai satu
                            hari kerja.
                        </p>
                    </div>
                @else
                    <p class="text-lg font-bold font-heading text-orcha-navy">Sudah jalan bersama kami?</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Tuliskan ceritanya di sini. Kami perlu kode pesanan Anda supaya yang tayang di halaman ini
                        benar-benar datang dari yang pernah berangkat.
                    </p>

                    <div class="grid gap-4 mt-6 sm:grid-cols-2">
                        <div>
                            <label for="ts-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
                            <input id="ts-kode" type="text" wire:model="kode" maxlength="30"
                                placeholder="OT-3108-K7QMXV" class="uppercase isian-orcha @error('kode') isian-galat @enderror">
                            @error('kode')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="ts-digit" class="label-orcha">4 digit terakhir WhatsApp <x-wajib /></label>
                            <input id="ts-digit" type="text" inputmode="numeric" wire:model="empatDigit" maxlength="4"
                                placeholder="7890"
                                class="isian-orcha tracking-[.4em] font-bold sm:max-w-[9rem] @error('empatDigit') isian-galat @enderror">
                            @error('empatDigit')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="label-orcha">Penilaian <x-wajib /></label>
                        {{-- Bintangnya tombol sungguhan, bukan gambar yang diklik:
                             yang memakai papan ketik dan pembaca layar harus bisa
                             memilih nilainya juga. --}}
                        <div class="flex gap-1 mt-1" role="group" aria-label="Penilaian">
                            @foreach (range(1, 5) as $bintang)
                                <button type="button" wire:click="$set('nilai', {{ $bintang }})"
                                    aria-label="{{ $bintang }} bintang"
                                    aria-pressed="{{ $nilai >= $bintang ? 'true' : 'false' }}"
                                    class="transition {{ $nilai >= $bintang ? 'text-orcha-sun' : 'text-slate-300 hover:text-slate-400' }}">
                                    {{-- Ikon, bukan karakter bintang Unicode (&#9733;).

                                         Karakter itu digambar oleh fon sistem, jadi bentuk dan
                                         tebalnya berubah-ubah antar perangkat — di sebagian
                                         ponsel ia bahkan dirender sebagai emoji berwarna.
                                         Bintang di daftar ulasan sudah memakai ikon; memakai
                                         yang sama di sini membuat keduanya benar-benar
                                         sebentuk. --}}
                                    <x-heroicon-s-star class="w-7 h-7" />
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="ts-isi" class="label-orcha">Cerita perjalanan Anda <x-wajib /></label>
                        <textarea id="ts-isi" wire:model="isi" rows="4" maxlength="1000"
                            placeholder="Apa yang paling berkesan? Bagaimana sopir dan armadanya?"
                            class="isian-orcha @error('isi') isian-galat @enderror"></textarea>
                        @error('isi')
                            <p class="galat-orcha">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-5">
                        <button type="button" wire:click="kirim" class="btn-orcha btn-orcha-primary">
                            <span wire:loading.remove wire:target="kirim">Kirim Testimoni</span>
                            <span wire:loading wire:target="kirim">Mengirim…</span>
                        </button>

                        <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-outline">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Lewat WhatsApp
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
