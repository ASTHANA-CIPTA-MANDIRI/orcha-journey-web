<?php

use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Partner')] class extends Component {
    use WithPagination, Toast, WithFileUploads;

    public $search = '';
    public $sortBy = ['column' => 'partner_name', 'direction' => 'asc'];
    public $showModal = false;
    public $showDeleteModal = false;
    public $isEdit = false;
    public $partnerId = null;

    // property data
    #[Rule('required|string|max:100')]
    public string $partnerName = '';
    #[Rule('nullable|image|max:2048')]
    public $partnerLogo;

    public $existingLogo = null;

    // method crud data
    public function save(): void
    {
        $this->validate();
        try {
            if ($this->isEdit) {
                $partnerData = Partner::findOrFail($this->partnerId);
                if ($this->partnerLogo) {
                    if ($partnerData->foto) {
                        $oldPath = str_replace('/storage/', '', $partnerData->foto);
                        Storage::disk('public')->delete($oldPath);
                    }
                    $url = $this->partnerLogo->store('partner', 'public');
                    $this->partnerLogo = "/storage/$url";
                }
                $partnerData->update([
                    'partner_name' => $this->partnerName,
                    'foto' => $this->partnerLogo,
                ]);
                $this->success('berhasil edit data partner');
            } else {
                if ($this->partnerLogo) {
                    $url = $this->partnerLogo->store('partner', 'public');
                    $this->partnerLogo = "/storage/$url";
                }
                Partner::create([
                    'partner_name' => $this->partnerName,
                    'foto' => $this->partnerLogo,
                ]);
                $this->success('berhasil tambah data partner');
            }
            $this->closeModal();
        } catch (Exception $e) {
            dump($e->getMessage());
            if ($this->isEdit) {
                $this->error('gagal edit data partner');
            } else {
                $this->error('gagal tambah data partner');
            }
        }
    }

    public function openModal()
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }
    public function openDeleteModal(Partner $partner)
    {
        $this->showDeleteModal = true;
        $this->partnerName = $partner->partner_name;
        $this->partnerId = $partner->id;
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->partnerId = null;
        $this->showModal = false;
        $this->showDeleteModal = false;
    }

    public function edit(Partner $partner): void
    {
        $this->resetForm();
        $this->isEdit = true;
        $this->partnerName = $partner->partner_name;
        $this->existingLogo = $partner->foto;
        $this->partnerId = $partner->id;

        $this->showModal = true;
    }
    public function resetForm()
    {
        $this->partnerName = '';
        $this->partnerLogo = null;
        $this->existingLogo = null;
        $this->partnerId = null;
    }

    public function delete(Partner $partner): void
    {
        try {
            if ($partner->foto) {
                $path = str_replace('/storage/', '', $partner->foto);
                Storage::disk('public')->delete($path);
            }
            $partner->delete();
            $this->warning("$partner->partner_name berhasil di hapus", position: 'toast-bottom');
            $this->closeModal();
        } catch (Exception $e) {
            $this->error('gagal menghapus data');
        }
    }

    // method untuk kebutuhan menampilkan list data
    public function headers(): array
    {
        return [['key' => 'foto', 'label' => 'Logo Partner', 'class' => 'w-1', 'sortable' => false], ['key' => 'partner_name', 'label' => 'Nama Partner', 'class' => 'w-1']];
    }

    public function partners(): LengthAwarePaginator
    {
        return Partner::query()->latest()->when($this->search, fn(Builder $query) => $query->where('partner_name', 'like', "%$this->search%"))->orderBy(...array_values($this->sortBy))->paginate(5);
    }

    public function with(): array
    {
        return [
            'partners' => $this->partners(),
            'headers' => $this->headers(),
        ];
    }
}; ?>

<div>
    <x-mary-header title="Partner Usaha" no-separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="cari nama partner..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button spinner="openModal" label="Tambah Partner" wire:click="openModal" responsive icon="o-plus"
                class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$partners" :sort-by="$sortBy" with-pagination>
            @scope('cell_foto', $partner)
            @if($partner->foto)
            <x-mary-avatar image="{{ $partner->foto}}" />
            @else
            <x-heroicon-o-user-circle class="text-slate-700 h-7 w-7" />
            @endif
            @endscope

            @scope('actions', $partner)
            <x-mary-button icon="o-pencil-square" wire:click="edit({{ $partner['id'] }})" spinner="edit({{$partner['id']}})"
                class="btn-ghost btn-sm text-slate-700" />
            <x-mary-button icon="o-trash" wire:click="openDeleteModal({{ $partner['id'] }})" spinner="openDeleteModal({{$partner['id']}})"
                class="btn-ghost btn-sm text-error" />
            @endscope

            <x-slot:empty>
                <div class="text-center">
                    <x-mary-icon name="o-archive-box-x-mark" />
                    <p>data partner kosong</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>


    <!-- modal confirm delete -->
    <x-mary-modal wire:model='showDeleteModal' title="Hapus Data">
        <p>yakin ingin menghapus data <strong>{{ $partnerName }}</strong></p>

        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
            <x-mary-button label="Ya Hapus" wire:click="delete({{$partnerId}})" class="btn-primary"
                spinner="delete({{$partnerId}})" />
        </x-slot:actions>
    </x-mary-modal>

    <!-- modal -->
    <x-mary-modal wire:model="showModal" title="{{ $isEdit ? 'Edit Data' : 'Tambah Data' }}">
        <x-mary-form no-separator wire:submit="save">
            <x-mary-input label="Name" wire:model="partnerName" placeholder="The full name" />
            <x-mary-file label="Logo Partner" wire:model="partnerLogo" accept="image/png, image/jpg, image/jpeg">
            </x-mary-file>
            @if ($partnerLogo)
            <img class="h-36 rounded-lg shadow-sm" src="{{ $partnerLogo->temporaryUrl() }}" alt="">
            @elseif($existingLogo)
            <img class="h-36 rounded-lg shadow-sm" src="{{ $existingLogo }}" alt="">
            @else
            <x-heroicon-o-user-circle class="text-slate-700 h-24 w-24" />
            @endif

            <x-slot:actions>
                <x-mary-button label="Batal" @click="$wire.closeModal()" spinner="closeModal" />
                <x-mary-button label="{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Data' }}" type="submit"
                    class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>