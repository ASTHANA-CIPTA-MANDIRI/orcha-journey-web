<?php

use App\Models\DestinationPopuler;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | destination')] class extends Component {
    use Toast, WithFileUploads;

    public $showModal = false;

    public $showDeleteModal = false;

    public $isEdit = false;

    public $destinationId = null;

    #[Rule('required|string|max:191')]
    public string $destinationName = '';

    #[Rule('required')]
    public $totalVisitor = 0;

    #[Rule('nullable|image|max:5000')]
    public $mainPhoto;

    public $existingMainPhoto = null;

    #[Rule(['othersPhoto.*' => 'image|max:2048'])]
    public $othersPhoto = [];

    public $existingOthersPhoto = [];

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
        $this->isEdit = false;
    }

    public function openDeleteModal(DestinationPopuler $destination): void
    {
        $this->resetForm();
        $this->showDeleteModal = true;
        $this->destinationName = $destination->destination_name;
        $this->destinationId = $destination->id;
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->showModal = false;
        $this->showDeleteModal = false;
    }

    public function edit(DestinationPopuler $destination): void
    {
        $this->resetForm();
        $this->isEdit = true;
        $this->destinationName = $destination->destination_name;
        $this->totalVisitor = $destination->total_visitor;
        $this->existingMainPhoto = $destination->main_photo;
        $this->existingOthersPhoto = $destination->others_photo;
        $this->destinationId = $destination->id;
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->destinationName = '';
        $this->totalVisitor = 0;
        $this->mainPhoto = null;
        $this->existingMainPhoto = null;
        $this->othersPhoto = [];
        $this->existingOthersPhoto = [];
        $this->destinationId = null;
    }

    public function delete(DestinationPopuler $destination): void
    {
        try {
            if ($destination->main_photo) {
                $path = str_replace('/storage/', '', $destination->main_photo);
                Storage::disk('public')->delete($path);
            }
            if ($destination->others_photo) {
                foreach ($destination->others_photo as $photo) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $photo));
                }
            }
            $destination->delete();
            $this->warning("$destination->destination_name berhasil dihapus", position: 'toast-bottom');
            $this->closeModal();
        } catch (Exception $e) {
            $this->error('gagal menghapus data destinasi populer');
        }
    }

    public function save(): void
    {
        $this->validate();
        try {
            $dataToSave = [
                'destination_name' => $this->destinationName,
                'total_visitor' => $this->totalVisitor,
            ];

            $destinationData = $this->isEdit ? DestinationPopuler::findOrFail($this->destinationId) : new DestinationPopuler();

            if ($this->mainPhoto) {
                if ($this->isEdit && $destinationData->main_photo) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $destinationData->main_photo));
                }
                $dataToSave['main_photo'] = '/storage/' . $this->mainPhoto->store('destinasi_populer/utama', 'public');
            }

            if (!empty($this->othersPhoto)) {
                if ($this->isEdit && $destinationData->others_photo) {
                    foreach ($destinationData->others_photo as $oldPhoto) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $oldPhoto));
                    }
                }
                $paths = [];
                foreach ($this->othersPhoto as $photo) {
                    $paths[] = '/storage/' . $photo->store('destinasi_populer/tambahan', 'public');
                }
                $dataToSave['others_photo'] = $paths;
            }

            if ($this->isEdit) {
                $destinationData->update($dataToSave);
                $this->success('Berhasil tambah destinasi');
            } else {
                DestinationPopuler::create($dataToSave);
                $this->success('Berhasil tambah destinasi');
            }

            $this->closeModal();
        } catch (Exception $e) {
            dump($e->getMessage());
            $this->error('gagal menambah destinasi');
        }
    }

    public function headers(): array
    {
        return [['key' => 'destination_name', 'label' => 'Nama Tempat', 'class' => 'w-1'], ['key' => 'total_visitor', 'label' => 'Total Pengunjung', 'class' => 'w-1'], ['key' => 'foto', 'label' => 'Foto', 'class' => 'w-1']];
    }

    public function with(): array
    {
        return [
            'destinations' => DestinationPopuler::all(),
            'headers' => $this->headers(),
        ];
    }
}; ?>

<div class="mt-10">
    <x-mary-header title="Destinasi Populer" no-separator progress-indicator>
        <x-slot:actions>
            <x-mary-button spinner="openModal" label="Tambah Destinasi" wire:click="openModal" responsive icon="o-plus"
                class="btn-primary"></x-button>
        </x-slot:actions>
    </x-mary-header>
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$destinations">
            @scope('cell_foto', $destination)
                @if ($destination->foto)
                    <x-mary-avatar image="{{ $destination->foto }}" />
                @else
                    <x-heroicon-o-user-circle class="text-slate-700 h-7 w-7" />
                @endif
            @endscope

            @scope('actions', $destination)
                <x-mary-button icon="o-pencil-square" wire:click="edit({{ $destination['id'] }})"
                    spinner="edit({{ $destination['id'] }})" class="btn-ghost btn-sm text-slate-700" />
                <x-mary-button icon="o-trash" wire:click="openDeleteModal({{ $destination['id'] }})"
                    spinner="openDeleteModal({{ $destination->id }})" class="btn-ghost btn-sm text-error" />
            @endscope

            <x-slot:empty>
                <div class="text-center">
                    <x-mary-icon name="o-archive-box-x-mark" />
                    <p>data destinasi populer kosong</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>
    <!-- modal -->
    <x-mary-modal wire:model="showModal" title="{{ $isEdit ? 'Edit Data' : 'Tambah Data' }}">
        <x-mary-form no-separator wire:submit="save">
            <x-mary-input label="Name" wire:model="destinationName" placeholder="nama tempat destinasi" />
            <x-mary-input label="Total Pengunjung" wire:model="totalVisitor" placeholder="0" />
            <x-mary-file label="Foto Utama (Background)" wire:model="mainPhoto"
                accept="image/png, image/jpg, image/jpeg" />
            @if ($mainPhoto)
                <img class="h-36 rounded-lg shadow-sm" src="{{ $mainPhoto->temporaryUrl() }}" alt="Preview Baru">
            @elseif($existingMainPhoto)
                <img class="h-36 rounded-lg shadow-sm" src="{{ asset($existingMainPhoto) }}" alt="Foto Lama">
            @endif


            <x-mary-file label="Foto Tambahan (Maksimal 3)" wire:model="othersPhoto" multiple
                accept="image/png, image/jpg, image/jpeg" />

            <div class="flex gap-2 mt-2">
                @if ($othersPhoto)
                    @foreach ($othersPhoto as $photo)
                        <img class="h-20 rounded-lg shadow-sm" src="{{ $photo->temporaryUrl() }}" alt="Preview Baru">
                    @endforeach
                @elseif($existingOthersPhoto)
                    @foreach ($existingOthersPhoto as $oldPhoto)
                        <img class="h-20 rounded-lg shadow-sm" src="{{ asset($oldPhoto) }}" alt="Foto Lama">
                    @endforeach
                @endif
            </div>

            <x-slot:actions>
                <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
                <x-mary-button label="{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Data' }}" type="submit"
                    class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    <!-- modal confirm delete -->
    <x-mary-modal wire:model='showDeleteModal' title="Hapus Data">
        <p>yakin ingin menghapus data destinasi <strong>{{ $destinationName }}</strong></p>

        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
            <x-mary-button label="Ya Hapus" wire:click="delete({{ $destinationId }})" class="btn-primary"
                spinner="delete({{ $destinationId }})" />
        </x-slot:actions>
    </x-mary-modal>
</div>
