<?php

use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Bundling')] class extends Component {
    use Toast;

    public $packageName;
    public $showDeleteModal = false;
    public $packageSelectedId = null;

    public function openModal(TravelPackage $package)
    {
        $this->showDeleteModal = true;
        $this->packageSelectedId = $package->id;
        $this->packageName = $package->name;
    }
    public function closeModal()
    {
        $this->showDeleteModal = false;
        $this->packageSelectedId = null;
        $this->packageName = null;
    }

    public function delete(TravelPackage $package): void
    {
        try {
            $package->delete();
            $this->warning("$package->name berhasil dihapus", position: 'toast-bottom');
            $this->closeModal();
        } catch (Exception $e) {
            $this->error('gagal menghapus paket bundling');
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama Paket', 'class' => 'w-1', 'sortable' => false],
            ['key' => 'category', 'label' => 'Kategori', 'class' => 'w-1', 'sortable' => false],
            ['key' => 'duration', 'label' => 'Durasi', 'class' => 'w-1', 'sortable' => false],
            ['key' => 'price', 'label' => 'Harga Paket', 'class' => 'w-1', 'sortable' => false, 'format' => ['currency', '', 'Rp ']],
            ['key' => 'original_price', 'label' => 'Harga Asli Paket', 'class' => 'w-1', 'sortable' => false, 'format' => ['currency', '', 'Rp ']],
            ['key' => 'is_best_choice', 'label' => 'Pilihan Terbaik', 'class' => 'w-1', 'sortable' => false],
        ];
    }
    public function travelPackages()
    {
        return TravelPackage::query()
            ->latest()
            ->get();
    }
    public function with(): array
    {
        return [
            'travelPackages' => $this->travelPackages(),
            'headers' => $this->headers()
        ];
    }
}; ?>

<div>
    <x-mary-header title="Paket Wisata" subtitle="Open Trip, Private Trip, dan Study Tour yang tampil di landing page"
        separator progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Tambah Paket" link="/admin/paket-wisata/create" responsive icon="o-plus"
                class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$travelPackages">
            @scope('cell_category', $travelPackage)
            <x-mary-badge :value="$travelPackage->category_label" class="badge-soft badge-info" />
            @endscope

            @scope('cell_duration', $travelPackage)
            <span class="text-sm text-base-content/70">{{ $travelPackage->duration ?: '-' }}</span>
            @endscope

            @scope('actions', $travelPackage)
            <x-mary-button icon="o-pencil-square" link="/admin/paket-wisata/{{$travelPackage->id}}/edit" spinner
                class="btn-ghost btn-sm text-slate-700" />
            <x-mary-button icon="o-trash" wire:click="openModal({{$travelPackage->id}})" spinner
                class="btn-ghost btn-sm text-error" />
            @endscope
            @scope('cell_is_best_choice', $travelPackage)
            <x-mary-badge :value="$travelPackage->is_best_choice ? 'ya' : 'tidak'" class="badge-soft {{$travelPackage->is_best_choice ? 'badge-success' : ''}}" />
            @endscope

            <x-slot:empty>
                <div class="text-center">
                    <x-mary-icon name="o-archive-box-x-mark" />
                    <p>data travelPackage kosong</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>
    <!-- modal confirm delete -->
    <x-mary-modal wire:model='showDeleteModal' title="Hapus Data">
        <p>yakin ingin menghapus data <strong>{{ $packageName }}</strong></p>

        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
            <x-mary-button label="Hapus" wire:click="delete({{$packageSelectedId}})" class="btn-primary"
                spinner="delete" />
        </x-slot:actions>
    </x-mary-modal>
</div>