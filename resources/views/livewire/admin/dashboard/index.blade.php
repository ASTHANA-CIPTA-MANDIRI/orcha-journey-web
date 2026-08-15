<?php

use App\Models\SewaKendaraan\Car;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Models\Kontak\PesanKontak;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Orcha Journey | Dashboard')] class extends Component {
    public function with(): array
    {
        return [
            'ringkasan' => [
                [
                    'label' => 'Pendaftaran baru',
                    'nilai' => PendaftaranOpenTrip::where('status', 'baru')->count(),
                    'icon' => 'o-clipboard-document-list',
                    'link' => '/admin/pendaftaran',
                    'warna' => 'from-orcha-sun to-orange-500',
                ],
                [
                    'label' => 'Sewa kendaraan baru',
                    'nilai' => PenyewaanKendaraan::where('status', 'baru')->count(),
                    'icon' => 'o-truck',
                    'link' => '/admin/penyewaan',
                    'warna' => 'from-orcha-ocean to-orcha-abyss',
                ],
                [
                    'label' => 'Pesan belum dibaca',
                    'nilai' => PesanKontak::belumDibaca()->count(),
                    'icon' => 'o-inbox',
                    'link' => '/admin/pesan',
                    'warna' => 'from-orcha-sky to-orcha-ocean',
                ],
                [
                    'label' => 'Paket Wisata',
                    'nilai' => TravelPackage::count(),
                    'icon' => 'o-map',
                    'link' => '/admin/paket-wisata',
                    'warna' => 'from-orcha-sky to-orcha-ocean',
                ],
                [
                    'label' => 'Kendaraan',
                    'nilai' => Car::count(),
                    'icon' => 'o-truck',
                    'link' => '/admin/sewa-kendaraan',
                    'warna' => 'from-orcha-ocean to-orcha-abyss',
                ],
                [
                    'label' => 'Destinasi Populer',
                    'nilai' => DestinationPopuler::count(),
                    'icon' => 'o-map-pin',
                    'link' => '/admin/destinasi',
                    'warna' => 'from-orcha-abyss to-orcha-navy',
                ],
            ],
            'paketPerKategori' => collect(config('orcha.kategori_paket'))
                ->map(fn ($label, $key) => [
                    'label' => $label,
                    'jumlah' => TravelPackage::where('category', $key)->count(),
                ])
                ->values(),
            'kendaraanPerJenis' => collect(config('orcha.jenis_kendaraan'))
                ->map(fn ($label, $key) => [
                    'label' => $label,
                    'jumlah' => Car::where('type', $key)->count(),
                    'tersedia' => Car::where('type', $key)->where('is_available', true)->count(),
                ])
                ->values(),
            'partnerCount' => Partner::count(),
            'kendaraanTersedia' => Car::where('is_available', true)->count(),
            'testimoniTerbaru' => Testimoni::latest()->limit(4)->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-mary-header title="Dashboard" subtitle="Ringkasan isi website Orcha Journey" separator />

    {{-- Kartu ringkasan --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($ringkasan as $item)
            <a href="{{ $item['link'] }}"
                class="p-5 transition shadow-sm bg-base-100 rounded-2xl hover:shadow-lg hover:-translate-y-0.5">
                <div class="flex items-center gap-4">
                    <span
                        class="flex items-center justify-center text-white w-12 h-12 rounded-xl bg-gradient-to-br {{ $item['warna'] }}">
                        <x-mary-icon :name="$item['icon']" class="w-6 h-6" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold tracking-wider uppercase text-base-content/50">
                            {{ $item['label'] }}</p>
                        <p class="text-2xl font-black font-heading text-orcha-navy">{{ $item['nilai'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Paket per kategori --}}
        <x-mary-card title="Paket per Kategori" shadow class="lg:col-span-1">
            <div class="space-y-3">
                @foreach ($paketPerKategori as $baris)
                    <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-base-200">
                        <span class="text-sm font-semibold text-orcha-navy">{{ $baris['label'] }}</span>
                        <span class="badge badge-primary badge-soft">{{ $baris['jumlah'] }} paket</span>
                    </div>
                @endforeach
            </div>
        </x-mary-card>

        {{-- Armada per jenis --}}
        <x-mary-card title="Armada per Jenis" shadow class="lg:col-span-1">
            <div class="space-y-3">
                @foreach ($kendaraanPerJenis as $baris)
                    <div class="px-4 py-3 rounded-xl bg-base-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-orcha-navy">{{ $baris['label'] }}</span>
                            <span class="text-sm text-base-content/60">{{ $baris['tersedia'] }} /
                                {{ $baris['jumlah'] }} siap</span>
                        </div>
                        <progress class="w-full mt-2 progress progress-primary"
                            value="{{ $baris['tersedia'] }}" max="{{ max($baris['jumlah'], 1) }}"></progress>
                    </div>
                @endforeach
            </div>
        </x-mary-card>

        {{-- Testimoni terbaru --}}
        <x-mary-card title="Testimoni Terbaru" shadow class="lg:col-span-1">
            @forelse ($testimoniTerbaru as $testimoni)
                <div class="py-3 border-b last:border-0 border-base-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-orcha-navy">{{ $testimoni->customer_name }}</span>
                        <span class="text-xs text-orcha-sun">{{ str_repeat('★', (int) $testimoni->rating) }}</span>
                    </div>
                    <p class="mt-1 text-xs line-clamp-2 text-base-content/60">{{ $testimoni->testimonial }}</p>
                </div>
            @empty
                <p class="text-sm text-base-content/50">Belum ada testimoni.</p>
            @endforelse
        </x-mary-card>
    </div>

    {{-- Catatan cepat --}}
    <x-mary-card shadow>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-bold admin-title">{{ $kendaraanTersedia }} kendaraan siap disewa</p>
                <p class="text-sm text-base-content/60">
                    Kendaraan berstatus tidak tersedia otomatis disembunyikan dari landing page.
                    Saat ini ada {{ $partnerCount }} partner yang tampil.
                </p>
            </div>
            <a href="/" target="_blank" rel="noopener" class="btn btn-primary">
                <x-mary-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                Buka Website
            </a>
        </div>
    </x-mary-card>
</div>
