<?php

use App\Models\Pembatalan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Pengajuan Pembatalan')] class extends Component {
    use Toast, WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $pembatalanId = null;

    public bool $showModal = false;

    public string $statusBaru = 'diajukan';

    public string $catatanAdmin = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function buka(Pembatalan $pembatalan): void
    {
        $this->pembatalanId = $pembatalan->id;
        $this->statusBaru = $pembatalan->status;
        $this->catatanAdmin = (string) $pembatalan->catatan_admin;
        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->validate([
            'statusBaru' => 'required|in:' . implode(',', array_keys(config('orcha.status_pembatalan'))),
            'catatanAdmin' => 'nullable|string|max:1000',
        ]);

        Pembatalan::whereKey($this->pembatalanId)->update([
            'status' => $this->statusBaru,
            'catatan_admin' => $this->catatanAdmin ?: null,
        ]);

        $this->success('Pengajuan pembatalan diperbarui');
    }

    public function tutup(): void
    {
        $this->showModal = false;
        $this->pembatalanId = null;
    }

    public function headers(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Diajukan', 'class' => 'w-1'],
            ['key' => 'kode_pendaftaran', 'label' => 'Kode', 'class' => 'w-1'],
            ['key' => 'nama_pemohon', 'label' => 'Pemohon'],
            ['key' => 'alasan', 'label' => 'Alasan', 'class' => 'w-1'],
            ['key' => 'jumlah_dibatalkan', 'label' => 'Peserta', 'class' => 'w-1 text-center'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'w-1'],
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'statusOptions' => collect(config('orcha.status_pembatalan'))
                ->map(fn ($label, $kunci) => ['id' => $kunci, 'name' => $label])
                ->values()
                ->all(),
            'terpilih' => $this->pembatalanId ? Pembatalan::with('pendaftaran')->find($this->pembatalanId) : null,
            'jumlahBaru' => Pembatalan::where('status', 'diajukan')->count(),
            'daftar' => Pembatalan::query()
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('nama_pemohon', 'like', "%{$this->search}%")
                    ->orWhere('kode_pendaftaran', 'like', "%{$this->search}%")
                    ->orWhere('whatsapp', 'like', "%{$this->search}%")))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->latest('id')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-mary-header title="Pengajuan Pembatalan" subtitle="Permintaan pembatalan yang masuk lewat formulir website"
        separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Cari kode, nama, atau nomor..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            @if ($jumlahBaru > 0)
                <span class="badge badge-error badge-lg">{{ $jumlahBaru }} baru</span>
            @endif
            <x-mary-select placeholder="Semua status" :options="$statusOptions" wire:model.live="filterStatus"
                icon="o-funnel" class="w-full sm:w-48" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$daftar" with-pagination>
            @scope('cell_created_at', $item)
                <div class="text-sm whitespace-nowrap">{{ $item->created_at->translatedFormat('d M Y') }}</div>
                <div class="text-xs text-gray-500">{{ $item->created_at->format('H:i') }}</div>
            @endscope

            @scope('cell_kode_pendaftaran', $item)
                <span class="font-mono text-sm font-bold whitespace-nowrap">{{ $item->kode_pendaftaran }}</span>
            @endscope

            @scope('cell_nama_pemohon', $item)
                <div class="font-bold">{{ $item->nama_pemohon }}</div>
                <div class="text-xs text-gray-500">{{ $item->whatsapp }}</div>
            @endscope

            @scope('cell_alasan', $item)
                <x-mary-badge :value="$item->alasan_label" class="badge-soft badge-info" />
            @endscope

            @scope('cell_status', $item)
                <x-mary-badge :value="$item->status_label"
                    class="badge-soft {{ $item->status === 'dana_dikirim' ? 'badge-success' : ($item->status === 'ditolak' ? 'badge-error' : 'badge-warning') }}" />
            @endscope

            @scope('actions', $item)
                <x-mary-button icon="o-eye" wire:click="buka({{ $item->id }})" spinner="buka({{ $item->id }})"
                    class="btn-ghost btn-sm text-slate-700" />
            @endscope

            <x-slot:empty>
                <div class="py-10 text-center">
                    <x-mary-icon name="o-arrow-uturn-left" class="w-12 h-12 text-gray-400 opacity-50" />
                    <p class="mt-2 text-gray-500">Belum ada pengajuan pembatalan.</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" title="Detail Pengajuan Pembatalan" separator box-class="max-w-2xl">
        @if ($terpilih)
            <div class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([['Kode pendaftaran', $terpilih->kode_pendaftaran], ['Pemohon', $terpilih->nama_pemohon], ['WhatsApp', $terpilih->whatsapp], ['Email', $terpilih->email ?: '—'], ['Alasan', $terpilih->alasan_label], ['Peserta dibatalkan', $terpilih->jumlah_dibatalkan . ' orang'], ['Trip', $terpilih->pendaftaran?->nama_paket ?: '—'], ['Tanggal berangkat', $terpilih->pendaftaran?->tanggal_berangkat?->translatedFormat('j F Y') ?: '—']] as [$label, $nilai])
                        <div>
                            <p class="text-xs tracking-wider uppercase text-base-content/50">{{ $label }}</p>
                            <p class="font-bold">{{ $nilai }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($terpilih->penjelasan)
                    <div>
                        <p class="mb-1 text-xs tracking-wider uppercase text-base-content/50">Penjelasan pemohon</p>
                        <div class="p-4 text-sm whitespace-pre-line rounded-2xl bg-base-200">
                            {{ $terpilih->penjelasan }}</div>
                    </div>
                @endif

                <div class="p-4 rounded-2xl bg-base-200">
                    <p class="mb-2 text-xs tracking-wider uppercase text-base-content/50">Rekening pengembalian</p>
                    <p class="font-bold">{{ $terpilih->bank }} · {{ $terpilih->nomor_rekening }}</p>
                    <p class="text-sm text-base-content/70">a.n. {{ $terpilih->atas_nama_rekening }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-mary-select label="Status" wire:model="statusBaru" :options="$statusOptions" />
                    <x-mary-input label="Catatan internal" wire:model="catatanAdmin"
                        placeholder="Mis. potongan 50%, sudah dikonfirmasi pemesan" />
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Tutup" @click="$wire.tutup()" spinner="tutup" />
            <x-mary-button label="Simpan" wire:click="simpan" spinner="simpan" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>
</div>
