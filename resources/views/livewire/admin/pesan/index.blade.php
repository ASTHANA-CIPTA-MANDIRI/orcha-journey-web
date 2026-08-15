<?php

use App\Models\Kontak\PesanKontak;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Pesan Masuk')] class extends Component {
    use Toast, WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $pesanId = null;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function buka(PesanKontak $pesan): void
    {
        $this->pesanId = $pesan->id;
        $this->showModal = true;

        if (! $pesan->dibaca_pada) {
            $pesan->update(['dibaca_pada' => now()]);
        }
    }

    public function tandaiBelumDibaca(): void
    {
        PesanKontak::whereKey($this->pesanId)->update(['dibaca_pada' => null]);
        $this->success('Ditandai belum dibaca');
        $this->tutup();
    }

    public function openDeleteModal(PesanKontak $pesan): void
    {
        $this->pesanId = $pesan->id;
        $this->showDeleteModal = true;
    }

    public function hapus(): void
    {
        PesanKontak::whereKey($this->pesanId)->delete();
        $this->warning('Pesan dihapus', position: 'toast-bottom');
        $this->tutup();
    }

    public function tutup(): void
    {
        $this->showModal = false;
        $this->showDeleteModal = false;
        $this->pesanId = null;
    }

    public function headers(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Masuk', 'class' => 'w-1'],
            ['key' => 'nama', 'label' => 'Pengirim'],
            ['key' => 'keperluan', 'label' => 'Keperluan', 'class' => 'w-1'],
            ['key' => 'pesan', 'label' => 'Pesan'],
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'pesanTerpilih' => $this->pesanId ? PesanKontak::find($this->pesanId) : null,
            'belumDibaca' => PesanKontak::belumDibaca()->count(),
            'daftarPesan' => PesanKontak::query()
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('whatsapp', 'like', "%{$this->search}%")
                    ->orWhere('pesan', 'like', "%{$this->search}%")))
                ->when($this->filterStatus === 'belum', fn ($q) => $q->whereNull('dibaca_pada'))
                ->when($this->filterStatus === 'sudah', fn ($q) => $q->whereNotNull('dibaca_pada'))
                ->latest('id')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-mary-header title="Pesan Masuk" subtitle="Pesan yang dikirim lewat formulir di halaman Kontak" separator
        progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Cari nama, nomor, atau isi pesan..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            @if ($belumDibaca > 0)
                <span class="badge badge-error badge-lg">{{ $belumDibaca }} belum dibaca</span>
            @endif
            <x-mary-select placeholder="Semua status" :options="[['id' => 'belum', 'name' => 'Belum dibaca'], ['id' => 'sudah', 'name' => 'Sudah dibaca']]"
                wire:model.live="filterStatus" icon="o-funnel" class="w-full sm:w-48" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$daftarPesan" with-pagination>
            @scope('cell_created_at', $pesan)
                <div class="text-sm">{{ $pesan->created_at->translatedFormat('d M Y') }}</div>
                <div class="text-xs text-gray-500">{{ $pesan->created_at->format('H:i') }}</div>
            @endscope

            @scope('cell_nama', $pesan)
                <div class="flex items-center gap-2 font-bold">
                    @unless ($pesan->sudah_dibaca)
                        <span class="w-2 h-2 rounded-full bg-error shrink-0" title="Belum dibaca"></span>
                    @endunless
                    {{ $pesan->nama }}
                </div>
                <div class="text-xs text-gray-500">{{ $pesan->whatsapp }}</div>
            @endscope

            @scope('cell_keperluan', $pesan)
                <x-mary-badge :value="$pesan->keperluan_label" class="badge-soft badge-info" />
            @endscope

            @scope('cell_pesan', $pesan)
                <p class="max-w-md text-sm line-clamp-2 text-base-content/70">{{ $pesan->pesan }}</p>
            @endscope

            @scope('actions', $pesan)
                <x-mary-button icon="o-eye" wire:click="buka({{ $pesan->id }})" spinner="buka({{ $pesan->id }})"
                    class="btn-ghost btn-sm text-slate-700" />
                <x-mary-button icon="o-trash" wire:click="openDeleteModal({{ $pesan->id }})"
                    spinner="openDeleteModal({{ $pesan->id }})" class="btn-ghost btn-sm text-error" />
            @endscope

            <x-slot:empty>
                <div class="py-10 text-center">
                    <x-mary-icon name="o-inbox" class="w-12 h-12 text-gray-400 opacity-50" />
                    <p class="mt-2 text-gray-500">Belum ada pesan masuk.</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    {{-- Detail pesan --}}
    <x-mary-modal wire:model="showModal" title="Detail Pesan" separator>
        @if ($pesanTerpilih)
            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Pengirim</p>
                        <p class="font-bold">{{ $pesanTerpilih->nama }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Masuk</p>
                        <p class="font-bold">{{ $pesanTerpilih->created_at->translatedFormat('d F Y, H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">WhatsApp</p>
                        <a href="https://api.whatsapp.com/send?phone={{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $pesanTerpilih->whatsapp)) }}"
                            target="_blank" rel="noopener" class="font-bold text-primary hover:underline">
                            {{ $pesanTerpilih->whatsapp }}
                        </a>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Email</p>
                        @if ($pesanTerpilih->email)
                            <a href="mailto:{{ $pesanTerpilih->email }}"
                                class="font-bold text-primary hover:underline">{{ $pesanTerpilih->email }}</a>
                        @else
                            <p class="text-base-content/50">—</p>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="text-xs tracking-wider uppercase text-base-content/50">Keperluan</p>
                    <x-mary-badge :value="$pesanTerpilih->keperluan_label" class="mt-1 badge-soft badge-info" />
                </div>

                <div>
                    <p class="mb-1 text-xs tracking-wider uppercase text-base-content/50">Pesan</p>
                    <div class="p-4 text-sm whitespace-pre-line rounded-2xl bg-base-200">{{ $pesanTerpilih->pesan }}
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Tandai belum dibaca" wire:click="tandaiBelumDibaca" spinner class="btn-ghost" />
            <x-mary-button label="Tutup" @click="$wire.tutup()" spinner="tutup" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Konfirmasi hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Pesan">
        <p>Yakin ingin menghapus pesan ini? Tindakan ini tidak bisa dibatalkan.</p>
        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.tutup()" spinner="tutup" />
            <x-mary-button label="Ya, Hapus" wire:click="hapus" spinner="hapus" class="btn-error" />
        </x-slot:actions>
    </x-mary-modal>
</div>
