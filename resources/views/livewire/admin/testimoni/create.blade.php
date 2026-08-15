<?php

use App\Models\Etalase\Testimoni;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new
    #[Layout('components.layouts.admin')]
    #[Title('Admin | Tambah Testimoni')]
    class extends Component {
        use Toast, WithFileUploads;

        #[Rule('nullable|image|max:2048')]
        public $avatar;
        #[Rule('required')]
        public string $customerName = '';
        #[Rule('required')]
        public string $testimoniCustomer = '';
        #[Rule('required')]
        public ?int $rating = 5;

        public function save(): void
        {
            $this->validate();
            try {
                if ($this->avatar) {
                    $url = $this->avatar->store('testimoni', 'public');
                    $this->avatar = "/storage/$url";
                }

                Testimoni::create([
                    'customer_name' => $this->customerName,
                    'avatar' => $this->avatar,
                    'testimonial' => $this->testimoniCustomer,
                    'rating' => $this->rating,
                ]);
                $this->success('berhasil tambah data testimoni', redirectTo: '/admin/testimoni');
            } catch (Exception $e) {
                dump($e->getMessage());
                $this->error('gagal tambah data testimoni');
            }
        }
    }; ?>

<div>
    <x-mary-header title="Tambah Testimoni Pelanggan" no-separator progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Kembali" link="/admin/testimoni" responsive icon="o-arrow-left" class="btn-sm btn-soft btn-primary" />
        </x-slot:actions>
    </x-mary-header>
    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-mary-form wire:submit="save" no-separator>
                <x-mary-file label="Avatar" wire:model="avatar" accept="image/png, image/jpg, image/jpeg">
                    @if($this->avatar)
                    <img wire:model="avatar" class="h-36 rounded-lg shadow-sm" src="{{$this->avatar ?? '/orca-logo.jpg'}}" alt="">
                    @else
                    <x-heroicon-o-user-circle class="text-slate-700 h-24 w-24 hover:bg-slate-200 rounded-lg" />
                    @endif
                </x-mary-file>
                <x-mary-input label="Nama" wire:model="customerName" />
                <x-mary-textarea label="Testimoni" wire:model="testimoniCustomer" rows="5" />
                <p class="text-sm text-slate-900 font-normal">Rating</p>
                <x-mary-rating wire:model="rating" class="bg-warning" total="5" />

                <x-slot:actions>
                    <x-mary-button label="Tambah Data" type="submit" spinner="save" class="btn-primary" />
                </x-slot:actions>
            </x-mary-form>
        </div>
        <div></div>
    </div>
</div>