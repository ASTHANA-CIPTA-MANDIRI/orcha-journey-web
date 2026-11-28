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

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, blog, dan halaman paket.

         Sebelumnya halaman ini memakai .section-orcha di atas latar putih,
         sehingga ada pita putih kosong setinggi hampir 150px antara foto hero
         dan isinya, dan kotak-kotaknya memakai .card-orcha yang sudutnya lebih
         tajam. Berdampingan dengan halaman lain, halaman inilah yang terbaca
         "belum selesai dirapikan". --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        {{-- Ringkasan penilaian --}}
        <div class="grid gap-4 sm:gap-5 lg:grid-cols-12">
                <div class="text-center panel-orcha reveal lg:col-span-4">
                    <p class="label-bagian justify-center"><span class="w-6 h-px bg-orcha-sun"></span> Nilai</p>
                    <p class="mt-3 angka-nilai">
                        {{ number_format($rataRata, 1, ',', '.') }}</p>
                    <div class="flex items-center justify-center gap-1 mt-2">
                        @for ($i = 0; $i < 5; $i++)
                            <x-heroicon-s-star
                                class="w-5 h-5 {{ $i < round($rataRata) ? 'text-orcha-sun' : 'text-slate-200' }}" />
                        @endfor
                    </div>
                    <p class="mt-3 text-sm text-slate-500">dari {{ $total }} ulasan pelanggan</p>
                </div>

                <div class="panel-orcha reveal lg:col-span-8">
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Sebaran</p>
                    <h2 class="mt-3 mb-5 judul-panel-kecil">Berapa bintang yang mereka beri.</h2>

                    <div class="space-y-2.5">
                        @foreach ($sebaran as $baris)
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1 text-sm font-semibold shrink-0 w-14 text-slate-600">
                                    {{ $baris['bintang'] }}
                                    <x-heroicon-s-star class="w-4 h-4 text-orcha-sun" />
                                </span>
                                <div class="flex-1 h-2 overflow-hidden rounded-full bg-orcha-foam">
                                    {{-- EMAS, bukan toska seperti ubin ikon di halaman lain.

                                         Bilah ini berdiri sebaris dengan bintang emas di
                                         kirinya dan menghitung hal yang sama. Bilah toska di
                                         samping bintang emas terbaca sebagai dua ukuran yang
                                         berbeda, padahal keduanya satu. --}}
                                    <div class="h-full rounded-full bilah-nilai"
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
        <div class="panel-orcha reveal">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
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
        </div>

        {{-- Daftar testimoni --}}
        @if ($testimonials->isEmpty())
            <div class="text-center panel-orcha reveal">
                <span class="grid mx-auto w-14 h-14 place-items-center rounded-2xl ubin-toska">
                    <x-heroicon-o-chat-bubble-left-right class="w-7 h-7" />
                </span>
                <p class="mt-4 font-bold text-orcha-navy">Tidak ada testimoni yang cocok.</p>
                <p class="mt-1 text-sm text-slate-500">Coba ubah kata kunci atau pilih bintang lain.</p>
                <button type="button" wire:click="resetFilter" class="mt-5 btn-orcha btn-orcha-outline">
                    Tampilkan Semua
                </button>
            </div>
        @else
            {{-- .reveal dipasang pada WADAHNYA, bukan tiap kartu.

                 Tiap kartu punya transform sendiri saat disentuh, dan
                 .reveal mengunci konteks penumpukan lewat animasi yang
                 forwards — dua hal yang tidak perlu dipertemukan di satu
                 elemen. --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 sm:gap-5 reveal">
                    @foreach ($testimonials as $testimoni)
                        <figure class="kartu-testimoni">
                            <span class="kutip-testimoni" aria-hidden="true">&ldquo;</span>

                            <div class="relative flex items-center gap-1 mb-4">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-heroicon-s-star
                                        class="w-4 h-4 {{ $i < $testimoni->rating ? 'text-orcha-sun' : 'text-slate-200' }}" />
                                @endfor
                            </div>

                            <blockquote class="relative flex-1 text-sm leading-relaxed text-slate-600">
                                “{{ $testimoni->testimonial }}”
                            </blockquote>

                            {{-- Garis putus-putus, seirama garis sobek kartu biaya di
                                 halaman pembayaran. border-orcha-foam di atas kartu krem
                                 nyaris tidak terlihat. --}}
                            <figcaption
                                class="relative flex items-center gap-3 pt-5 mt-5 border-t border-dashed border-[#e4d7c3]">
                            {{-- Huruf awalnya SELALU digambar, fotonya menumpang di atasnya.

                                 Sebelumnya huruf awal hanya muncul bila kolom avatar
                                 KOSONG. Avatar yang terisi tetapi berkasnya hilang —
                                 kejadian biasa, berkasnya diunggah di server dan tidak
                                 ikut ke mana-mana — menggambar ikon gambar rusak beserta
                                 nama pelanggan sebagai alt text di tengah kartu. Tidak ada
                                 galat; yang rusak cuma rupanya, dan justru di kartu yang
                                 tugasnya meyakinkan calon pembeli.

                                 onerror membuang <img>-nya, dan huruf awal di belakangnya
                                 langsung terlihat. --}}
                                <span
                                    class="relative grid font-bold text-white rounded-full shrink-0 w-11 h-11 place-items-center bg-orcha-wave">
                                    {{ mb_strtoupper(mb_substr($testimoni->customer_name, 0, 1)) }}

                                    @if ($testimoni->avatar)
                                        <img src="{{ $testimoni->avatar }}" alt="{{ $testimoni->customer_name }}"
                                            loading="lazy" onerror="this.remove();"
                                            class="absolute inset-0 object-cover w-full h-full rounded-full">
                                    @endif
                                </span>
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
                                    <p class="text-xs label-ringkas">
                                        {{ $testimoni->created_at?->translatedFormat('d F Y') }}</p>
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>

            <div class="mt-8">
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
            <div class="panel-orcha panel-orcha-krem reveal" id="kirim-testimoni">
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
                    {{-- DUA KOLOM di layar lebar: ajakan di kiri, isian di kanan.

                         Satu kolom, panel ini selebar halaman (±1.280px di layar
                         1440) sementara isiannya hanya memakai separuh kirinya —
                         dan isian "4 digit" yang dipatok 9rem meninggalkan 520px
                         kosong di kolomnya sendiri. Yang tergambar: panel
                         terluas di halaman dengan lebih dari separuh bidangnya
                         kosong, tepat di bawah deretan kartu yang padat. --}}
                    {{-- Tombol WhatsApp berdiri SENDIRI sebagai petak ketiga, bukan
                         ikut di dalam kolom kiri.

                         Urutan markahnya ajakan → formulir → WhatsApp, jadi di layar
                         sempit jalan pintasnya berada SESUDAH formulirnya. Ikut di
                         dalam kolom kiri, ia muncul sebelum isian pertama, dan yang
                         menggulung berhenti di situ — mengambil jalan pintas yang
                         justru lebih panjang bagi kedua belah pihak. Di layar lebar
                         ia dikembalikan ke bawah ajakan lewat penempatan petak. --}}
                    <div
                        class="grid gap-7 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:grid-rows-[auto_1fr] lg:gap-x-10 lg:gap-y-5">
                        <div class="lg:col-start-1 lg:row-start-1">
                            <span class="grid w-12 h-12 place-items-center rounded-2xl ubin-toska">
                                <x-heroicon-o-pencil-square class="w-6 h-6" />
                            </span>

                            <p class="mt-4 label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Cerita Anda</p>
                            <h2 class="mt-3 judul-panel-kecil">Sudah jalan bersama kami?</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                Tuliskan ceritanya di sini. Kami perlu kode pesanan Anda supaya yang tayang di
                                halaman ini benar-benar datang dari yang pernah berangkat.
                            </p>

                            <p class="mt-4 lencana-aman">
                                <x-heroicon-s-check-badge class="w-4 h-4" />
                                <span>Yang kodenya cocok ditandai Terverifikasi.</span>
                            </p>

                        </div>

                        <div class="lg:col-start-2 lg:row-start-1 lg:row-span-2">
                    {{-- Kode panjang, empat digit pendek — lebarnya sesuai isinya,
                         sama seperti pencari pesanan di halaman pembayaran. --}}
                    <div class="grid gap-4 sm:grid-cols-5">
                        <div class="sm:col-span-3">
                            <label for="ts-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
                            <input id="ts-kode" type="text" wire:model="kode" maxlength="30"
                                placeholder="OT-3108-K7QMXV" class="uppercase isian-orcha @error('kode') isian-galat @enderror">
                            @error('kode')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="ts-digit" class="label-orcha">4 digit WhatsApp <x-wajib /></label>
                            <input id="ts-digit" type="text" inputmode="numeric" wire:model="empatDigit" maxlength="4"
                                placeholder="7890"
                                class="isian-orcha tracking-[.4em] font-bold text-center @error('empatDigit') isian-galat @enderror">
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

                    <button type="button" wire:click="kirim" class="w-full mt-5 btn-orcha btn-orcha-sun sm:w-auto">
                        <span wire:loading.remove wire:target="kirim">Kirim Testimoni</span>
                        <span wire:loading wire:target="kirim">Mengirim…</span>
                    </button>
                        </div>

                        <div class="lg:col-start-1 lg:row-start-2 lg:self-start">
                            <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                                class="inline-flex btn-orcha btn-orcha-outline">
                                <x-bi-whatsapp class="w-5 h-5" />
                                Lewat WhatsApp
                            </a>
                        </div>
                    </div>
                @endif
            </div>
    </div>
</div>
