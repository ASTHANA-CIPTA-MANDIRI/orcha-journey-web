<?php

use App\Models\SewaKendaraan\Car;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Sewa Kendaraan — Mobil, HiAce & Bus Pariwisata | Orcha Journey')] class extends Component {
    public ?string $jenis = null;

    public function mount(?string $jenis = null): void
    {
        if ($jenis !== null) {
            abort_unless(array_key_exists($jenis, config('orcha.jenis_kendaraan')), 404);

            $this->jenis = $jenis;
        }
    }

    public function with(): array
    {
        $keterangan = [
            'mobil' => 'Mobil keluarga dan city car untuk perjalanan harian. Bisa lepas kunci maupun dengan sopir.',
            'hiace' => 'HiAce untuk rombongan 11–15 orang. Nyaman untuk perjalanan luar kota, selalu disertai sopir kami.',
            'bus' => 'Bus sedang sampai big bus 59 kursi untuk study tour dan rombongan besar, lengkap dengan pendamping.',
        ];

        $label = $this->jenis ? config('orcha.jenis_kendaraan')[$this->jenis] : null;

        $tersedia = Car::where('is_available', true);

        return [
            'judul' => $label ? "Sewa $label" : 'Sewa Kendaraan',
            'keterangan' => $this->jenis
                ? $keterangan[$this->jenis]
                : 'Mobil harian, HiAce untuk rombongan kecil, sampai bus pariwisata besar. Semua unit dicek berkala dan bisa dipesan dengan sopir.',
            'jenisAktif' => $this->jenis,
            'jenisKendaraan' => config('orcha.jenis_kendaraan'),
            'cars' => (clone $tersedia)
                ->when($this->jenis, fn ($q) => $q->where('type', $this->jenis))
                ->orderBy('price_per_day')
                ->get(),
            'jumlahPerJenis' => collect(config('orcha.jenis_kendaraan'))
                ->map(fn ($nama, $kunci) => Car::where('is_available', true)->where('type', $kunci)->count()),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin menyewa kendaraan. Boleh dibantu cek ketersediaannya?');
@endphp

<div>
    <x-page-hero :title="$judul" eyebrow="Sewa Kendaraan" :subtitle="$keterangan"
        image="images/HERO/sewa-kendaraan.jpg" posisi="center bottom" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">

            <div class="mb-10 tab-scroller">
                <a href="{{ route('sewa-kendaraan') }}"
                    class="tab-orcha {{ $jenisAktif === null ? 'tab-orcha-active' : '' }}">
                    Semua Armada
                </a>
                @foreach ($jenisKendaraan as $kunci => $label)
                    <a href="{{ route('sewa-kendaraan', $kunci) }}"
                        class="tab-orcha {{ $jenisAktif === $kunci ? 'tab-orcha-active' : '' }}">
                        {{ $label }}
                        <span class="opacity-60">({{ $jumlahPerJenis[$kunci] }})</span>
                    </a>
                @endforeach
            </div>

            @if ($cars->isEmpty())
                <div class="p-12 text-center card-orcha">
                    <x-heroicon-o-truck class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Belum ada unit tersedia di kategori ini.</p>
                    <p class="mt-1 text-sm text-slate-500">Tanyakan ketersediaan langsung — kami juga punya unit mitra.
                    </p>
                    <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                        class="mt-5 btn-orcha btn-orcha-primary">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Cek Ketersediaan
                    </a>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 sm:gap-6">
                    @foreach ($cars as $car)
                        <x-sewa-kendaraan.kartu :kendaraan="$car" />
                    @endforeach
                </div>
            @endif

            {{-- Yang termasuk dan tidak termasuk --}}
            <div class="grid gap-5 mt-16 lg:grid-cols-2 sm:gap-6">
                <div class="p-6 card-orcha sm:p-8">
                    <h3 class="flex items-center gap-2 mb-4 text-lg font-bold font-heading text-orcha-navy">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-orcha-sky" />
                        Harga sewa sudah termasuk
                    </h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        @foreach (['Unit dalam kondisi bersih dan siap jalan', 'Perawatan dan pengecekan berkala', 'Asuransi kendaraan', 'Konsultasi rute perjalanan'] as $poin)
                            <li class="flex items-start gap-2">
                                <span class="mt-2 w-1.5 h-1.5 rounded-full bg-orcha-sky shrink-0"></span>
                                {{ $poin }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="p-6 card-orcha sm:p-8">
                    <h3 class="flex items-center gap-2 mb-4 text-lg font-bold font-heading text-orcha-navy">
                        <x-heroicon-s-information-circle class="w-5 h-5 text-orcha-sun" />
                        Belum termasuk
                    </h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        @foreach (['Sopir (bila memilih paket dengan sopir)', 'Bahan bakar selama perjalanan', 'Tol, parkir, dan tiket masuk lokasi', 'Penginapan dan makan sopir untuk perjalanan luar kota'] as $poin)
                            <li class="flex items-start gap-2">
                                <span class="mt-2 w-1.5 h-1.5 rounded-full bg-orcha-sun shrink-0"></span>
                                {{ $poin }}
                            </li>
                        @endforeach
                    </ul>

                    {{-- Daftar di atas berlaku umum, tetapi sebagian unit memang
                         ditawarkan all-in. Tanpa catatan ini, halaman menyatakan
                         hal yang bertentangan dengan kartu unitnya sendiri — dan
                         yang benar adalah yang tertulis di kartu, karena itu
                         dibaca dari datanya. --}}
                    <p class="pt-3 mt-3 text-xs border-t text-slate-500 border-orcha-foam">
                        Sebagian unit ditawarkan all-in. Keterangan BBM, tol, dan parkir untuk tiap
                        unit tertulis di kartunya masing-masing.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col items-center gap-4 p-8 mt-12 text-center sm:flex-row sm:text-left sm:justify-between rounded-3xl bg-orcha-foam/70">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Butuh beberapa unit sekaligus?</p>
                    <p class="mt-1 text-sm text-slate-600">Untuk rombongan besar kami siapkan konvoi beberapa armada
                        beserta pendampingnya.</p>
                </div>
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Tanya Ketersediaan
                </a>
            </div>
        </div>
    </section>
</div>
