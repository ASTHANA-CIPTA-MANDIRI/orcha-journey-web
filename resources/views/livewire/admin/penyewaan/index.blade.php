<?php

use App\Models\PenyewaanKendaraan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Sewa Kendaraan Masuk')] class extends Component {
    use Toast, WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $sewaId = null;

    public bool $showModal = false;

    public string $statusBaru = 'baru';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function buka(PenyewaanKendaraan $penyewaan): void
    {
        $this->sewaId = $penyewaan->id;
        $this->statusBaru = $penyewaan->status;
        $this->showModal = true;
    }

    public function simpanStatus(): void
    {
        $this->validate([
            'statusBaru' => 'required|in:' . implode(',', array_keys(config('orcha.status_penyewaan'))),
        ]);

        PenyewaanKendaraan::whereKey($this->sewaId)->update(['status' => $this->statusBaru]);
        $this->success('Status pemesanan diperbarui');
    }

    public function tutup(): void
    {
        $this->showModal = false;
        $this->sewaId = null;
    }

    public function headers(): array
    {
        return [
            ['key' => 'kode', 'label' => 'Kode', 'class' => 'w-1'],
            ['key' => 'nama', 'label' => 'Penyewa'],
            ['key' => 'nama_kendaraan', 'label' => 'Kendaraan'],
            ['key' => 'tanggal_mulai', 'label' => 'Mulai', 'class' => 'w-1'],
            ['key' => 'estimasi_biaya', 'label' => 'Estimasi', 'class' => 'w-1'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'w-1'],
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'statusOptions' => collect(config('orcha.status_penyewaan'))
                ->map(fn ($label, $kunci) => ['id' => $kunci, 'name' => $label])
                ->values()
                ->all(),
            'terpilih' => $this->sewaId ? PenyewaanKendaraan::with('kendaraan')->find($this->sewaId) : null,
            'jumlahBaru' => PenyewaanKendaraan::where('status', 'baru')->count(),
            'daftar' => PenyewaanKendaraan::query()
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%")
                    ->orWhere('whatsapp', 'like', "%{$this->search}%")
                    ->orWhere('nama_kendaraan', 'like', "%{$this->search}%")))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->latest('id')
                ->paginate(10),
        ];
    }
}; ?>

@php
    $rupiah = fn ($angka) => $angka ? 'Rp ' . number_format((float) $angka, 0, ',', '.') : '—';
@endphp

<div>
    <x-mary-header title="Sewa Kendaraan Masuk" subtitle="Pemesanan yang dikirim lewat formulir sewa di website"
        separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Cari kode, nama, atau unit..." wire:model.live.debounce="search" clearable
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
            @scope('cell_kode', $item)
                <span class="font-mono text-sm font-bold whitespace-nowrap">{{ $item->kode }}</span>
                <div class="text-xs whitespace-nowrap text-gray-500">{{ $item->created_at->translatedFormat('d M Y') }}
                </div>
            @endscope

            @scope('cell_nama', $item)
                <div class="font-bold">{{ $item->nama }}</div>
                <div class="text-xs text-gray-500">{{ $item->whatsapp }}</div>
            @endscope

            @scope('cell_nama_kendaraan', $item)
                <div class="text-sm">{{ $item->nama_kendaraan }}</div>
                <div class="text-xs text-gray-500">{{ $item->transmisi }} · {{ $item->durasi_label }} ·
                    {{ $item->dengan_sopir ? 'dengan sopir' : 'lepas kunci' }}</div>
            @endscope

            @scope('cell_tanggal_mulai', $item)
                <div class="text-sm whitespace-nowrap">{{ $item->tanggal_mulai->translatedFormat('d M Y') }}</div>
                <div class="text-xs text-gray-500">{{ substr((string) $item->jam_mulai, 0, 5) }}</div>
            @endscope

            @scope('cell_estimasi_biaya', $item)
                <span class="font-semibold whitespace-nowrap">
                    {{ $item->estimasi_biaya ? 'Rp ' . number_format($item->estimasi_biaya, 0, ',', '.') : '—' }}
                </span>
            @endscope

            @scope('cell_status', $item)
                <x-mary-badge :value="$item->status_label"
                    class="badge-soft {{ $item->status === 'selesai' ? 'badge-success' : ($item->status === 'batal' ? 'badge-error' : 'badge-info') }}" />
            @endscope

            @scope('actions', $item)
                <x-mary-button icon="o-eye" wire:click="buka({{ $item->id }})" spinner="buka({{ $item->id }})"
                    class="btn-ghost btn-sm text-slate-700" />
            @endscope

            <x-slot:empty>
                <div class="py-10 text-center">
                    <x-mary-icon name="o-truck" class="w-12 h-12 text-gray-400 opacity-50" />
                    <p class="mt-2 text-gray-500">Belum ada pemesanan sewa kendaraan.</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" title="Detail Pemesanan Sewa" separator box-class="max-w-2xl">
        @if ($terpilih)
            <div class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([['Kode', $terpilih->kode], ['Penyewa', $terpilih->nama], ['WhatsApp', $terpilih->whatsapp], ['Email', $terpilih->email ?: '—'], ['Kendaraan', $terpilih->nama_kendaraan], ['Transmisi', $terpilih->transmisi], ['Lama sewa', $terpilih->durasi_label], ['Sopir', $terpilih->dengan_sopir ? 'Dengan sopir' : 'Lepas kunci'], ['Mulai', $terpilih->tanggal_mulai->translatedFormat('j F Y') . ' pukul ' . substr((string) $terpilih->jam_mulai, 0, 5)], ['Lokasi antar/jemput', $terpilih->lokasi_antar ?: '—'], ['Estimasi biaya', $rupiah($terpilih->estimasi_biaya)], ['Tarif unit saat ini', $terpilih->kendaraan ? $rupiah($terpilih->kendaraan->tarif($terpilih->satuan)) . ' / ' . ($terpilih->satuan_label) : '—']] as [$label, $nilai])
                        <div>
                            <p class="text-xs tracking-wider uppercase text-base-content/50">{{ $label }}</p>
                            <p class="font-bold">{{ $nilai }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($terpilih->catatan)
                    <div>
                        <p class="mb-1 text-xs tracking-wider uppercase text-base-content/50">Catatan penyewa</p>
                        <div class="p-4 text-sm whitespace-pre-line rounded-2xl bg-base-200">{{ $terpilih->catatan }}
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-48">
                        <x-mary-select label="Status pemesanan" wire:model="statusBaru" :options="$statusOptions" />
                    </div>
                    <x-mary-button label="Simpan Status" wire:click="simpanStatus" spinner="simpanStatus"
                        class="btn-primary" />
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Tutup" @click="$wire.tutup()" spinner="tutup" />
        </x-slot:actions>
    </x-mary-modal>
</div>
