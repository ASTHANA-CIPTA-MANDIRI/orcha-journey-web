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

    <section class="bg-white section-orcha">
        <div class="container-orcha">

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

            {{-- Penjelasan tiap kategori --}}
            <div class="grid gap-5 mt-16 sm:grid-cols-3 sm:gap-6">
                @foreach ([['Open Trip', 'o-users', 'Cocok untuk solo traveler, pasangan, atau rombongan kecil. Berangkat minimal 6 orang.'], ['Private Trip', 'o-sparkles', 'Cocok untuk keluarga, kantor, atau komunitas yang ingin rute dan jadwal sendiri.'], ['Study Tour', 'o-academic-cap', 'Cocok untuk sekolah dan kampus yang butuh kunjungan edukatif berskala besar.']] as [$nama, $ikon, $isi])
                    <div class="p-6 card-orcha">
                        <span
                            class="flex items-center justify-center w-12 h-12 mb-4 text-white rounded-xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                            <x-dynamic-component :component="'heroicon-' . $ikon" class="w-6 h-6" />
                        </span>
                        <h3 class="font-bold font-heading text-orcha-navy">{{ $nama }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>

            <div
                class="flex flex-col items-center gap-4 p-8 mt-12 text-center sm:flex-row sm:text-left sm:justify-between rounded-3xl bg-orcha-foam/70">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Belum ada yang pas?</p>
                    <p class="mt-1 text-sm text-slate-600">Sebutkan tujuan, tanggal, dan jumlah peserta — kami susunkan
                        paket khusus untuk Anda.</p>
                </div>
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Minta Paket Khusus
                </a>
            </div>
        </div>
    </section>
</div>
