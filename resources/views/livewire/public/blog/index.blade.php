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

<div>
    <x-page-hero title="Blog Orcha Journey" eyebrow="Panduan & Cerita"
        subtitle="Panduan perjalanan, cerita destinasi, dan tips persiapan dari tim yang mengantar rombongan setiap minggu."
        image="images/HERO/blog.webp" posisi="center 88%" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">

            {{-- ============ SOROTAN ============
                 Kartu lebar berisi tulisan terbaru. Hanya muncul di daftar yang
                 belum disaring; lihat alasannya di with(). --}}
            @if ($sorotan)
                <a href="{{ route('blog.detail', $sorotan) }}"
                    class="grid gap-0 mb-12 overflow-hidden card-orcha group lg:grid-cols-2">
                    <div class="overflow-hidden aspect-[16/10] lg:aspect-auto lg:h-full bg-orcha-navy">
                        {{-- Sorotan berada di layar pertama, jadi prioritas
                             tinggi — bukan lazy. Menundanya justru memperlambat
                             yang paling ingin dilihat pembaca. --}}
                        <img src="{{ $sorotan->sampul_tampil }}" alt="{{ $sorotan->judul }}" fetchpriority="high"
                            decoding="async"
                            class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">
                    </div>

                    <div class="flex flex-col justify-center p-8 lg:p-12">
                        <p class="eyebrow"><span class="w-8 h-px bg-orcha-wave"></span> Tulisan Terbaru</p>

                        <h2 class="mt-3 text-2xl font-bold leading-tight text-orcha-navy sm:text-3xl">
                            {{ $sorotan->judul }}
                        </h2>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-4 text-xs text-slate-500">
                            @if ($sorotan->kategori_label)
                                <span
                                    class="font-bold tracking-wide uppercase text-orcha-ocean">{{ $sorotan->kategori_label }}</span>
                                <span class="text-orcha-mist" aria-hidden="true">•</span>
                            @endif
                            <time
                                datetime="{{ $sorotan->terbit_pada?->toDateString() }}">{{ $sorotan->tanggal_terbit }}</time>
                            <span class="text-orcha-mist" aria-hidden="true">•</span>
                            <span>{{ $sorotan->lama_baca }} menit baca</span>
                        </div>

                        <p class="mt-4 leading-relaxed text-slate-600">{{ $sorotan->ringkasan_tampil }}</p>

                        <span
                            class="inline-flex items-center gap-1.5 mt-6 text-sm font-bold text-orcha-ocean transition group-hover:gap-2.5">
                            Baca selengkapnya
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            @endif

            {{-- ============ PENYARING ============ --}}
            <div class="flex flex-col gap-5 mb-10 lg:flex-row lg:items-center lg:justify-between">
                <div class="tab-scroller">
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
                    <input type="search" wire:model.live.debounce.400ms="cari"
                        placeholder="Cari judul artikel…"
                        class="w-full py-3 pr-4 text-sm bg-white border pl-11 rounded-2xl border-orcha-foam focus:border-orcha-sky focus:outline-none focus:ring-2 focus:ring-orcha-sky/25">
                </label>
            </div>

            {{-- ============ DAFTAR ============ --}}
            @if ($artikel->isEmpty())
                <div class="p-12 text-center card-orcha">
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
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($artikel as $satu)
                        <x-blog.kartu :artikel="$satu" wire:key="artikel-{{ $satu->id }}" />
                    @endforeach
                </div>

                <div class="mt-12">
                    {{ $artikel->links('partials.paginasi-orcha') }}
                </div>
            @endif
        </div>
    </section>
</div>
