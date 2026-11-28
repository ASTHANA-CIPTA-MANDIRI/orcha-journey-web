<?php

use App\Models\Blog\Artikel;
use App\Models\Blog\KategoriArtikel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.guest')] #[Title('Blog — Panduan & Cerita Perjalanan | Orcha Journey')] class extends Component
{
    use WithPagination;

    /*
     | Pencarian dan kategori ikut di ALAMAT, bukan hanya keadaan di layar.
     |
     | Pembaca yang menemukan daftar yang pas biasanya mengirimkannya ke teman
     | seperjalanan; tautan yang selalu mendarat di daftar penuh memaksa
     | penerimanya menyaring ulang sendiri. Tombol kembali peramban juga jadi
     | membatalkan penyaring, bukan meninggalkan halaman.
     */
    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    #[Url(as: 'kategori', except: '')]
    public string $kategori = '';

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedKategori(): void
    {
        $this->resetPage();
    }

    public function bersihkan(): void
    {
        $this->reset(['cari', 'kategori']);
        $this->resetPage();
    }

    public function with(): array
    {
        $semuaKategori = KategoriArtikel::daftar();

        $kategoriSah = array_key_exists($this->kategori, $semuaKategori)
            ? $this->kategori
            : null;

        $dasar = fn () => Artikel::query()->tayang();

        return [
            'artikel' => $dasar()
                ->cari($this->cari ?: null)
                ->kategori($kategoriSah)
                ->latest('terbit_pada')
                ->latest('id')
                ->paginate(config('orcha.artikel_per_halaman')),

            /*
             | Artikel sorotan: yang terbaru, dan HANYA saat daftar sedang tidak
             | disaring. Saat pengunjung mencari sesuatu, kartu besar berisi
             | tulisan yang tidak ia cari justru mendorong hasilnya ke bawah.
             */
            'sorotan' => ($this->cari === '' && $kategoriSah === null)
                ? $dasar()->latest('terbit_pada')->latest('id')->first()
                : null,

            // Jumlah per kategori: tab yang angkanya nol tetap ditampilkan agar
            // pembaca tahu rubriknya ada, tetapi tidak menjanjikan isi.
            'jumlahKategori' => collect($semuaKategori)
                ->map(fn ($label, $kunci) => $dasar()->kategori($kunci)->count()),

            'kategoriArtikel' => $semuaKategori,
            'kategoriAktif' => $kategoriSah,
            'adaPenyaring' => $this->cari !== '' || $kategoriSah !== null,
            'totalSemua' => $dasar()->count(),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Blog Orcha Journey" eyebrow="Panduan & Cerita"
        subtitle="Panduan perjalanan, cerita destinasi, dan tips persiapan dari tim yang mengantar rombongan setiap minggu."
        image="images/HERO/blog.webp" posisi="center 88%" />

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, Tentang Kami, Paket Wisata, Sewa Kendaraan, dan Destinasi.
         Sebelumnya halaman ini memakai .section-orcha di atas latar putih,
         sehingga ada pita putih kosong setinggi hampir 150px antara foto hero
         dan isinya — satu-satunya halaman yang masih begitu. --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        {{-- ============ SOROTAN ============
             Panel GELAP, warna logo. Halaman ini tadinya putih dari atas sampai
             bawah: kartu putih di atas latar putih, tanpa satu pun bidang yang
             membawa warna perusahaan. Tulisan terbaru adalah yang paling pantas
             memegang bidang itu — ia yang ingin dibaca lebih dulu.

             Hanya muncul di daftar yang belum disaring; alasannya di with(). --}}
        @if ($sorotan)
            <section class="relative overflow-hidden panel-orcha panel-orcha-gelap">
                {{-- Lambang orca sebagai cap air, sama seperti "Cara Pesan" di
                     beranda. aria-hidden + alt kosong: ini hiasan. --}}
                <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true" loading="lazy"
                    width="512" height="512" class="cap-orca">

                <div class="relative grid items-center gap-7 lg:grid-cols-[minmax(0,1.02fr)_minmax(0,1fr)] lg:gap-11">
                    {{-- prioritas: sorotan berada di layar pertama, jadi fotonya
                         tidak boleh lazy. Menundanya justru memperlambat yang
                         paling ingin dilihat pembaca. --}}
                    {{-- Sudut daun yang SAMA dengan kartu di daftar bawah —
                         melengkung lebar di kiri-atas dan kanan-bawah, nyaris
                         tajam di dua sudut lainnya. Tanpa ini sorotan tetap
                         persegi panjang biasa sementara lima kartu di bawahnya
                         sudah berbentuk, dan halaman terbaca seperti dua
                         rancangan yang ditempel. --}}
                    <a href="{{ route('blog.detail', $sorotan) }}" wire:navigate
                        class="block group rounded-[3.1rem_0.8rem_3.1rem_0.8rem] overflow-hidden ring-1 ring-white/10">
                        <x-blog.sampul :artikel="$sorotan" :prioritas="true" :terang="true" class="aspect-[16/10]" />
                    </a>

                    <div>
                        <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Tulisan Terbaru</p>

                        <h2 class="mt-3 judul-panel">
                            <a href="{{ route('blog.detail', $sorotan) }}" wire:navigate
                                class="transition hover:text-orcha-sun">
                                {{ $sorotan->judul }}
                            </a>
                        </h2>

                        <div class="flex flex-wrap items-center mt-4 text-xs gap-x-3 gap-y-1 text-slate-400">
                            @if ($sorotan->kategori_label)
                                <span class="font-bold tracking-wide uppercase text-orcha-sun">
                                    {{ $sorotan->kategori_label }}
                                </span>
                                <span class="text-white/25" aria-hidden="true">&bull;</span>
                            @endif
                            <time datetime="{{ $sorotan->terbit_pada?->toDateString() }}">{{ $sorotan->tanggal_terbit }}</time>
                            <span class="text-white/25" aria-hidden="true">&bull;</span>
                            <span>{{ $sorotan->lama_baca }} menit baca</span>
                        </div>

                        <p class="mt-4 leading-relaxed text-slate-300">{{ $sorotan->ringkasan_tampil }}</p>

                        <a href="{{ route('blog.detail', $sorotan) }}" wire:navigate class="mt-6 btn-orcha btn-orcha-sun">
                            Baca selengkapnya
                            <x-heroicon-o-arrow-right class="w-5 h-5" />
                        </a>
                    </div>
                </div>
            </section>
        @endif

        {{-- ============ DAFTAR ============ --}}
        <section class="panel-orcha">
            <div class="flex flex-col gap-5 mb-7 lg:flex-row lg:items-end lg:justify-between">
                {{-- Judulnya IKUT penyaring.

                     Judul tetap "5 panduan dan cerita perjalanan." sementara
                     barisnya di bawah berbunyi "1 tulisan cocok" terbaca saling
                     membantah — dan yang lebih besar hurufnya justru angka yang
                     tidak berlaku lagi. Saat disaring, judulnya menyebutkan apa
                     yang sedang disaring; jumlahnya pindah ke baris kecil di
                     bawahnya. --}}
                @php
                    $labelDaftar = $adaPenyaring ? 'Hasil Saringan' : 'Semua Tulisan';
                    $judulDaftar = match (true) {
                        $cari !== '' => 'Pencarian "' . $cari . '".',
                        $kategoriAktif !== null => $kategoriArtikel[$kategoriAktif] . '.',
                        $totalSemua > 0 => $totalSemua . ' panduan dan cerita perjalanan.',
                        default => 'Panduan dan cerita perjalanan.',
                    };
                @endphp

                <div>
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> {{ $labelDaftar }}</p>
                    <h2 class="mt-3 judul-panel !text-[clamp(1.35rem,2.2vw,1.9rem)]">{{ $judulDaftar }}</h2>

                    {{-- Jumlah hasil hanya ditulis saat penyaringnya menyala.
                         Di daftar penuh angkanya sudah ada di judul tepat di
                         atasnya, dan mengulanginya cuma menambah baris. --}}
                    @if ($adaPenyaring)
                        <p class="mt-2 text-sm text-slate-500">
                            {{ $artikel->total() }} dari {{ $totalSemua }} tulisan.
                            <button type="button" wire:click="bersihkan"
                                class="font-semibold underline transition text-orcha-amber underline-offset-2 hover:text-orcha-sunset">
                                Tampilkan semua
                            </button>
                        </p>
                    @endif
                </div>

                {{-- Bentuknya disamakan dengan kotak cari di halaman destinasi dan
                     testimoni, dan itu memakai utilitas Tailwind — BUKAN
                     .isian-orcha.

                     .isian-orcha menyetel `padding: .8rem 1rem` lewat shorthand,
                     jadi ruang kirinya persis di posisi ikon (left-4) dan tulisan
                     placeholder tertimpa ikonnya. Menambahkan pl-11 di sampingnya
                     tidak menolong: kekhususannya sama sedangkan new-homepage.css
                     dimuat setelah utilitas, sehingga shorthand-nya yang menang —
                     persis seperti yang sudah diperingatkan di komentar
                     .isian-satuan pada berkas gaya itu. --}}
                <label class="relative w-full lg:max-w-sm shrink-0">
                    <span class="sr-only">Cari artikel</span>
                    <x-heroicon-o-magnifying-glass
                        class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari judul artikel…"
                        class="w-full py-3 pr-4 text-sm bg-white border pl-11 rounded-2xl border-orcha-foam focus:border-orcha-sun focus:outline-none focus:ring-2 focus:ring-orcha-sun/30">
                </label>
            </div>

            {{-- Deret kategori dipusatkan di layar lebar, sama seperti deret
                 wilayah di halaman destinasi: baris di atasnya sudah terbagi
                 kiri-kanan, jadi deret yang ikut rata kiri menggantung tanpa
                 penyeimbang di kanannya. Di layar sempit tetap rata kiri —
                 deretnya digulung mendatar, dan yang digulung harus mulai dari
                 tepi. --}}
            <div class="mb-8 tab-scroller lg:justify-center">
                <button type="button" wire:click="$set('kategori', '')"
                    class="tab-orcha {{ $kategoriAktif === null ? 'tab-orcha-active' : '' }}">
                    Semua <span class="opacity-60">({{ $totalSemua }})</span>
                </button>
                @foreach ($kategoriArtikel as $kunci => $label)
                    <button type="button" wire:click="$set('kategori', '{{ $kunci }}')"
                        class="tab-orcha {{ $kategoriAktif === $kunci ? 'tab-orcha-active' : '' }}">
                        {{ $label }} <span class="opacity-60">({{ $jumlahKategori[$kunci] }})</span>
                    </button>
                @endforeach
            </div>

            @if ($artikel->isEmpty())
                <div class="text-center panel-orcha reveal">
                    <x-heroicon-o-newspaper class="w-12 h-12 mx-auto text-orcha-mist" />

                    @if ($adaPenyaring)
                        <p class="mt-3 font-semibold text-orcha-navy">Tidak ada artikel yang cocok.</p>
                        <p class="mt-1 text-sm text-slate-500">Coba kata lain, atau lihat seluruh tulisan.</p>
                        <button type="button" wire:click="bersihkan" class="mt-5 btn-orcha btn-orcha-outline">
                            Tampilkan semua artikel
                        </button>
                    @else
                        {{-- Kosong karena memang belum ada tulisan. Tidak
                             berpura-pura ini kesalahan pembaca, dan tetap
                             menawarkan jalan ke halaman yang ada isinya. --}}
                        <p class="mt-3 font-semibold text-orcha-navy">Tulisan pertama sedang disiapkan.</p>
                        <p class="max-w-md mx-auto mt-1 text-sm text-slate-500">
                            Blog ini akan diisi panduan perjalanan dan cerita destinasi. Sementara itu, paket yang
                            sudah berjalan bisa dilihat lebih dulu.
                        </p>
                        <a href="{{ route('paket-wisata') }}" class="mt-5 btn-orcha btn-orcha-primary">
                            <x-heroicon-o-map class="w-5 h-5" />
                            Lihat Paket Wisata
                        </a>
                    @endif
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                    @foreach ($artikel as $satu)
                        <x-blog.kartu :artikel="$satu" wire:key="artikel-{{ $satu->id }}" />
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $artikel->links('partials.paginasi-orcha') }}
                </div>
            @endif
        </section>

        {{-- ============ AJAKAN ============
             Penutup yang sama bentuknya dengan halaman destinasi: pembaca yang
             sampai ke dasar halaman blog sedang menimbang berangkat, bukan
             sedang mencari artikel kelima. --}}
        <section class="panel-orcha panel-orcha-krem">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left sm:justify-between">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Pertanyaannya belum terjawab di sini?</p>
                    <p class="mt-1 text-sm text-slate-600">Tanyakan langsung ke tim kami — yang menulis artikel ini
                        orang yang sama yang mengantar rombongan.</p>
                </div>
                <a href="{{ $wa('Halo Orcha Journey, saya baru membaca blog dan ingin bertanya soal perjalanan.') }}"
                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-sun shrink-0">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Tanya Lewat WhatsApp
                </a>
            </div>
        </section>
    </div>
</div>
