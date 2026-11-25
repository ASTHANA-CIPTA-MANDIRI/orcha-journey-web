<?php

use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Paket Wisata — Open Trip, Private Trip & Study Tour | Orcha Journey')] class extends Component {
    public ?string $kategori = null;

    public function mount(?string $kategori = null): void
    {
        if ($kategori !== null) {
            $kunci = str_replace('-', '_', $kategori);

            abort_unless(array_key_exists($kunci, config('orcha.kategori_paket')), 404);

            $this->kategori = $kunci;
        }
    }

    public function with(): array
    {
        $keterangan = [
            'open_trip' => 'Berangkat bareng peserta lain dengan biaya patungan. Tanggalnya sudah kami tetapkan, dan trip berjalan setelah kuota minimal 6 orang terpenuhi.',
            'private_trip' => 'Satu rombongan hanya diisi keluarga atau tim Anda. Rute, jam berangkat, sampai tempat makan bisa diatur sendiri.',
            'study_tour' => 'Paket kunjungan edukatif untuk sekolah dan kampus, lengkap dengan surat jalan, dokumentasi, dan pendamping di tiap bus.',
        ];

        $label = $this->kategori ? config('orcha.kategori_paket')[$this->kategori] : null;

        return [
            'judul' => $label ? "Paket $label" : 'Paket Wisata',
            'keterangan' => $this->kategori
                ? $keterangan[$this->kategori]
                : 'Open trip, private trip, dan study tour dengan harga yang sudah termasuk transportasi, pemandu, serta tiket masuk destinasi yang tertera.',
            'kategoriAktif' => $this->kategori,
            'kategoriPaket' => config('orcha.kategori_paket'),
            'packages' => TravelPackage::query()
                ->tayang()
                // Dimuat di muka: kartunya membaca keberangkatan terdekat dan
                // jumlah tanggal lainnya, dan tanpa ini tiap kartu menembak
                // query sendiri.
                ->with('keberangkatan')
                ->when($this->kategori, fn ($q) => $q->where('category', $this->kategori))
                ->orderByDesc('is_best_choice')
                ->orderBy('price')
                ->get(),
            'jumlahPerKategori' => collect(config('orcha.kategori_paket'))
                ->map(fn ($label, $kunci) => TravelPackage::tayang()->where('category', $kunci)->count()),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin dibuatkan paket wisata sesuai kebutuhan saya.');
@endphp

<div>
    <x-page-hero :title="$judul" eyebrow="Paket Wisata" :subtitle="$keterangan"
        image="images/HERO/paket-wisata.webp" />

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda dan Tentang Kami. Sebelumnya halaman ini memakai bagian putih
         selebar layar yang berhenti rapi di bawah hero, jadi ada pita kosong
         besar di antara keduanya dan kepala halaman terbaca terputus dari
         isinya. --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        <section class="panel-orcha">
            {{-- Tab kategori: tiap kategori punya alamat halamannya sendiri --}}
            <div class="mb-10 tab-scroller">
                <a href="{{ route('paket-wisata') }}"
                    class="tab-orcha {{ $kategoriAktif === null ? 'tab-orcha-active' : '' }}">
                    Semua Paket
                </a>
                @foreach ($kategoriPaket as $kunci => $label)
                    <a href="{{ route('paket-wisata', str_replace('_', '-', $kunci)) }}"
                        class="tab-orcha {{ $kategoriAktif === $kunci ? 'tab-orcha-active' : '' }}">
                        {{ $label }}
                        <span class="opacity-60">({{ $jumlahPerKategori[$kunci] }})</span>
                    </a>
                @endforeach
            </div>

            @if ($packages->isEmpty())
                <div class="p-12 text-center card-orcha">
                    <x-heroicon-o-map class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Belum ada paket di kategori ini.</p>
                    <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk penawaran khusus sesuai kebutuhan Anda.</p>
                    <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                        class="mt-5 btn-orcha btn-orcha-primary">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Minta Penawaran
                    </a>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                    @foreach ($packages as $package)
                        <x-paket-wisata.kartu :paket="$package" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Panel ini dibentuk SAMA dengan "Kenapa Orcha" di Tentang
                 Kami: panel gelap berwarna logo, ubin krem, angka besar toska
                 di sudut kanan, dan cap air orca di latarnya.

                 Kunci 'gambar' bersifat pilihan: yang punya gambar tampil
                 bergambar, sisanya tetap dengan ikon garisnya. --}}
            <section class="relative overflow-hidden panel-orcha panel-orcha-gelap">
                <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true"
                    loading="lazy" width="512" height="512" class="cap-orca">

                <div class="relative max-w-2xl mb-6 sm:mb-8 reveal">
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Jenis Perjalanan</p>
                    <h2 class="mt-3 judul-panel">Tiga cara berangkat bersama kami.</h2>
                </div>

                <div class="relative grid gap-3 sm:grid-cols-3 sm:gap-4">
                    @foreach ([['Open Trip', 'o-users', 'open-trip', 'Cocok untuk solo traveler, pasangan, atau rombongan kecil. Berangkat minimal 6 orang.'], ['Private Trip', 'o-sparkles', 'private-trip', 'Cocok untuk keluarga, kantor, atau komunitas yang ingin rute dan jadwal sendiri.'], ['Study Tour', 'o-academic-cap', 'study-tour', 'Cocok untuk sekolah dan kampus yang butuh kunjungan edukatif berskala besar.']] as $urutanJenis => [$nama, $ikon, $gambar, $isi])
                        {{-- .ubin-orcha tanpa -putih: ubinnya KREM di atas panel
                             gelap, sama seperti ubin "Kenapa Orcha". --}}
                        <div class="relative ubin-orcha reveal">
                            <span aria-hidden="true"
                                class="absolute z-0 text-7xl font-black leading-none select-none top-1 right-4 font-heading text-orcha-toska-samar">
                                {{ $urutanJenis + 1 }}
                            </span>

                            @if ($gambar)
                                <img src="{{ asset('images/paket/' . $gambar . '.webp') }}"
                                    alt="" loading="lazy" decoding="async" width="440" height="440"
                                    class="relative z-10 object-contain w-20 h-20 mb-2.5 -ml-1.5">
                            @else
                                <span class="relative z-10 gelembung-ikon">
                                    <x-dynamic-component :component="'heroicon-' . $ikon" class="w-5 h-5" />
                                </span>
                            @endif

                            <h3 class="relative z-10 mt-4 text-base font-bold font-heading text-orcha-navy">{{ $nama }}</h3>
                            <p class="relative z-10 mt-2 text-sm leading-relaxed text-slate-600">{{ $isi }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

        <section class="panel-orcha panel-orcha-krem">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left sm:justify-between">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Belum ada yang pas?</p>
                    <p class="mt-1 text-sm text-slate-600">Sebutkan tujuan, tanggal, dan jumlah peserta — kami susunkan
                        paket khusus untuk Anda.</p>
                </div>
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Minta Paket Khusus
                </a>
            </div>
        </section>
    </div>
</div>
