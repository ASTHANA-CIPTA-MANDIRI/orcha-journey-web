<?php

use App\Models\TravelPackage;
use App\Support\ItineraryTeks;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Create Bundling')]   class extends Component {
    use Toast, WithFileUploads;

    public TravelPackage $package;

    #[Rule('required|string|max:255')]
    public $name;
    #[Rule('required|in:open_trip,private_trip,study_tour')]
    public $category = 'open_trip';
    #[Rule('nullable|string|max:60')]
    public $duration;
    #[Rule('nullable|date')]
    public $tanggalBerangkat;
    #[Rule('nullable|date|after_or_equal:tanggalBerangkat')]
    public $tanggalPulang;
    #[Rule('nullable|string|max:191')]
    public $titikJemput;
    #[Rule('required|integer|min:1|max:200')]
    public $minimalPeserta = 1;
    #[Rule('nullable|string|max:191')]
    public $catatanPromo;
    #[Rule('nullable|array')]
    public $fasilitas = [];
    #[Rule('nullable|string|max:4000')]
    public $itineraryTeks = '';
    #[Rule('nullable|image|max:4096')]
    public $foto;
    public $fotoLama = null;
    #[Rule('required|numeric')]
    public $price;
    #[Rule('required|numeric')]
    public $originalPrice;
    #[Rule('required|numeric')]
    public $discountPercentage;
    #[Rule('required|boolean')]
    public $isBestChoice = false;
    #[Rule('required|array')]
    public $destinationList = [];

    public $packageId = null;

    public function mount($package): void
    {
        $this->name = $package['name'];
        $this->category = $package['category'] ?? 'open_trip';
        $this->duration = $package['duration'];
        $this->tanggalBerangkat = $package['tanggal_berangkat'] ? substr((string) $package['tanggal_berangkat'], 0, 10) : null;
        $this->tanggalPulang = $package['tanggal_pulang'] ? substr((string) $package['tanggal_pulang'], 0, 10) : null;
        $this->titikJemput = $package['titik_jemput'];
        $this->minimalPeserta = $package['minimal_peserta'] ?? 1;
        $this->catatanPromo = $package['catatan_promo'];
        $this->fasilitas = $package['fasilitas'] ?? [];
        $this->itineraryTeks = ItineraryTeks::keTeks($package['itinerary'] ?? []);
        $this->fotoLama = $package['foto'];
        $this->price = $package['price'];
        $this->originalPrice = $package['original_price'];
        $this->discountPercentage = $package['discount_percentage'];
        $this->isBestChoice = $package['is_best_choice'] == 1 ? true : false;
        $this->destinationList = $package['destination_list'];

        $this->packageId = $package['id'];
    }

    public function with(): array
    {
        return [
            'kategoriOptions' => collect(config('orcha.kategori_paket'))
                ->map(fn ($label, $key) => ['id' => $key, 'name' => $label])
                ->values()
                ->all(),
        ];
    }

    public function save()
    {
        $this->validate();
        try {
            $packageSelected = TravelPackage::findOrFail($this->packageId);
            $packageSelected->update([
                'name' => $this->name,
                'category' => $this->category,
                'duration' => $this->duration,
                'tanggal_berangkat' => $this->tanggalBerangkat ?: null,
                'tanggal_pulang' => $this->tanggalPulang ?: null,
                'titik_jemput' => $this->titikJemput ?: null,
                'minimal_peserta' => $this->minimalPeserta,
                'catatan_promo' => $this->catatanPromo ?: null,
                'fasilitas' => $this->fasilitas ?: null,
                'itinerary' => ItineraryTeks::keArray($this->itineraryTeks) ?: null,
                ...($this->foto ? ['foto' => '/storage/' . $this->foto->store('paket', 'public')] : []),

                'price' => $this->price,
                'original_price' => $this->originalPrice,
                'discount_percentage' => $this->discountPercentage,
                'is_best_choice' => $this->isBestChoice,
                'destination_list' => $this->destinationList
            ]);
            $this->success('berhasil mengubah data bundling harga', redirectTo: '/admin/paket-wisata');
        } catch (Exception $e) {
            $this->error('gagal mengubah data bundling harga');
        }
    }
}; ?>

<div>
    <x-mary-header title="Edit Bundling Harga" no-separator progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Kembali" link="/admin/paket-wisata" responsive icon="o-arrow-left" class="btn-sm btn-soft btn-primary" />
        </x-slot:actions>
    </x-mary-header>
    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-mary-form wire:submit="save" no-separator>
                <x-mary-input label="Nama" wire:model="name" />
                <x-mary-select label="Kategori" wire:model="category" :options="$kategoriOptions"
                    hint="Menentukan tab tempat paket ini tampil di landing page" />
                <x-mary-input label="Durasi" wire:model="duration"
                    placeholder="Contoh: 2 hari 1 malam · min. 40 peserta" hint="Opsional" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-mary-input label="Tanggal Berangkat" wire:model="tanggalBerangkat" type="date"
                        hint="Ditetapkan admin — peserta tidak memilih tanggal sendiri" />
                    <x-mary-input label="Tanggal Pulang" wire:model="tanggalPulang" type="date" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-mary-input label="Titik Jemput" wire:model="titikJemput"
                        placeholder="Contoh: Jogja, Klaten, Surakarta" />
                    <x-mary-input label="Minimal Peserta Berangkat *" wire:model="minimalPeserta" type="number"
                        min="1" suffix="orang" />
                </div>
                <x-mary-input label="Catatan Promo" wire:model="catatanPromo"
                    placeholder="Contoh: Promo Early Bird — 5 orang pertama" hint="Opsional" />
                <x-mary-tags label="Fasilitas" wire:model="fasilitas" hint="tekan enter tiap fasilitas" clearable />
                <x-mary-file label="Foto Sampul Paket" wire:model="foto" accept="image/png, image/jpg, image/jpeg"
                    hint="Dipakai jadi latar hero halaman detail paket" />
                @if ($foto instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                    <img src="{{ $foto->temporaryUrl() }}" class="object-cover w-full h-40 rounded-lg" alt="Pratinjau">
                @elseif ($fotoLama)
                    <img src="{{ $fotoLama }}" class="object-cover w-full h-40 rounded-lg" alt="Foto saat ini">
                @endif
                <x-mary-textarea label="Itinerary" wire:model="itineraryTeks" rows="10"
                    placeholder="Day 1&#10;18.00 | Penjemputan Meeting Point&#10;19.00 | Perjalanan Banyuwangi&#10;&#10;Day 2&#10;03.00 | Tiba di Banyuwangi"
                    hint="Baris tanpa tanda | jadi judul hari, baris dengan | jadi jam &amp; kegiatan" />
                <x-mary-input label="Harga" wire:model="price" prefix="Rp" locale="id-ID" money />
                <x-mary-input label="Harga Asli" wire:model="originalPrice" prefix="Rp" locale="id-ID" money />
                <x-mary-input type="numeric" suffix="%" label="Persentase Diskon" wire:model="discountPercentage" />
                <x-mary-tags label="Daftar Destinasi" wire:model="destinationList" hint="tekan enter" clearable />
                <x-mary-toggle label="Best Choice Bundling" wire:model="isBestChoice" />

                <x-slot:actions no-separator>
                    <x-mary-button label="Simpan Data" type="submit" spinner="save" class="btn-primary" />
                </x-slot:actions>
            </x-mary-form>
        </div>
        <div></div>
    </div>
</div>