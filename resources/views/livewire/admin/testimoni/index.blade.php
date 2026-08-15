<?php

use App\Models\Etalase\Testimoni;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
    #[Layout('components.layouts.admin')]
    #[Title('Admin | Testimoni')]
    class extends Component {
        use WithPagination, Toast;
        public $search = '';
        public array $sortBy = ['column' => 'customer_name', 'direction' => 'asc'];

        public $showDeleteModal = false;
        public $testimoniId = null;
        public $testimoniName = '';

        public function openModal(Testimoni $testimoni): void
        {
            $this->showDeleteModal = true;
            $this->testimoniId = $testimoni->id;
            $this->testimoniName = $testimoni->customer_name;
        }
        public function closeModal()
        {
            $this->showDeleteModal = false;
            $this->testimoniId = null;
            $this->testimoniName = '';
        }

        public function headers(): array
        {
            return [
                ['key' => 'avatar', 'label' => 'avatar', 'class' => 'w-1', 'sortable' => false],
                ['key' => 'rating', 'label' => 'rating', 'class' => 'w-1', 'sortable' => false],
                ['key' => 'customer_name', 'label' => 'Nama Pelanggan', 'class' => 'w-1'],
                ['key' => 'testimonial', 'label' => 'Testimoni', 'class' => 'w-1', 'sortable' => false],
            ];
        }

        public function delete(Testimoni $testimoni): void
        {
            try {
                if ($testimoni->avatar) {
                    $path = str_replace('/storage/', '', $testimoni->avatar);
                    Storage::disk('public')->delete($path);
                }
                $testimoni->delete();

                $this->warning("testimoni $testimoni->customer_name berhasil dihapus", position: 'toast-bottom');
                $this->closeModal();
            } catch (Exception $e) {
                $this->error('gagal menghapus testimoni');
            }
        }

        public function testimonials(): LengthAwarePaginator
        {
            return Testimoni::query()
                ->when($this->search, fn(Builder $query) => $query->where('customer_name', 'like', "%$this->search%"))
                ->orderBy(...array_values($this->sortBy))
                ->paginate(5);
        }

        public function with(): array
        {
            return [
                'testimonials' => $this->testimonials(),
                'headers' => $this->headers()
            ];
        }
    }; ?>

<div>
    <x-mary-header title="Testimoni Pelanggan" no-separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="cari nama customer..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Tambah Testimoni" link="/admin/testimoni/create" responsive icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$testimonials" :sort-by="$sortBy" with-pagination>
            @scope('cell_avatar', $testimonial)
            @if($testimonial->avatar)
            <x-mary-avatar image="{{$testimonial->avatar ?? '/orca-logo.jpg'  }}" />
            @else
            <x-heroicon-o-user-circle class="text-slate-700 h-7 w-7" />
            @endif
            @endscope

            @scope('actions', $testimonial)
            <x-mary-button icon="o-pencil-square" link="/admin/testimoni/{{ $testimonial->id }}/edit" class="btn-ghost btn-sm text-slate-700" />
            <x-mary-button icon="o-trash" wire:click="openModal({{$testimonial['id']}})" spinner="openModal({{$testimonial['id']}})" class="btn-ghost btn-sm text-error" />
            @endscope
            <x-slot:empty>
                <div class="text-center">
                    <x-mary-icon name="o-archive-box-x-mark" />
                    <p>data testimoni kosong</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    <!-- modal confirm delete -->
    <x-mary-modal wire:model='showDeleteModal' title="Hapus Data">
        <p>yakin ingin menghapus data testimoni <strong>{{ $testimoniName }}</strong></p>

        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
            <x-mary-button label="Ya Hapus" wire:click="delete({{$testimoniId}})" class="btn-primary"
                spinner="delete({{$testimoniId}})" />
        </x-slot:actions>
    </x-mary-modal>
</div>