<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.admin')] #[Title('Admin | Pendaftaran Open Trip')] class extends Component {
    use Toast, WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $pendaftaranId = null;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public string $statusBaru = 'baru';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function buka(PendaftaranOpenTrip $pendaftaran): void
    {
        $this->pendaftaranId = $pendaftaran->id;
        $this->statusBaru = $pendaftaran->status;
        $this->showModal = true;
    }

    public function simpanStatus(): void
    {
        $this->validate([
            'statusBaru' => 'required|in:' . implode(',', array_keys(config('orcha.status_pendaftaran'))),
        ]);

        PendaftaranOpenTrip::whereKey($this->pendaftaranId)->update(['status' => $this->statusBaru]);
        $this->success('Status pendaftaran diperbarui');
    }

    public function openDeleteModal(PendaftaranOpenTrip $pendaftaran): void
    {
        $this->pendaftaranId = $pendaftaran->id;
        $this->showDeleteModal = true;
    }

    public function hapus(): void
    {
        $pendaftaran = PendaftaranOpenTrip::find($this->pendaftaranId);

        if ($pendaftaran) {
            // Data kesehatan ikut dihapus supaya tidak ada data sensitif menggantung
            $pendaftaran->riwayatKesehatan()->delete();
            $pendaftaran->delete();
        }

        $this->warning('Pendaftaran dihapus', position: 'toast-bottom');
        $this->tutup();
    }

    public function tutup(): void
    {
        $this->showModal = false;
        $this->showDeleteModal = false;
        $this->pendaftaranId = null;
    }

    public function headers(): array
    {
        return [
            ['key' => 'kode', 'label' => 'Kode', 'class' => 'w-1'],
            ['key' => 'nama', 'label' => 'Pemesan'],
            ['key' => 'nama_paket', 'label' => 'Paket'],
            ['key' => 'jumlah_peserta', 'label' => 'Peserta', 'class' => 'w-1 text-center'],
            ['key' => 'kesehatan', 'label' => 'Kesehatan', 'class' => 'w-1 text-center', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'class' => 'w-1'],
        ];
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'statusOptions' => collect(config('orcha.status_pendaftaran'))
                ->map(fn ($label, $kunci) => ['id' => $kunci, 'name' => $label])
                ->values()
                ->all(),
            'terpilih' => $this->pendaftaranId
                ? PendaftaranOpenTrip::with('riwayatKesehatan')->find($this->pendaftaranId)
                : null,
            'jumlahBaru' => PendaftaranOpenTrip::where('status', 'baru')->count(),
            'daftar' => PendaftaranOpenTrip::query()
                ->withCount('riwayatKesehatan')
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%")
                    ->orWhere('whatsapp', 'like', "%{$this->search}%")))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->latest('id')
                ->paginate(10),
        ];
    }
}; ?>

<div>
    <x-mary-header title="Pendaftaran Open Trip" subtitle="Pendaftaran yang masuk lewat formulir di website" separator
        progress-indicator>
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
            @scope('cell_kode', $item)
                <span class="font-mono text-sm font-bold whitespace-nowrap">{{ $item->kode }}</span>
                <div class="text-xs whitespace-nowrap text-gray-500">{{ $item->created_at->translatedFormat("d M Y") }}</div>
            @endscope

            @scope('cell_nama', $item)
                <div class="font-bold">{{ $item->nama }}</div>
                <div class="text-xs text-gray-500">{{ $item->whatsapp }}</div>
            @endscope

            @scope('cell_nama_paket', $item)
                <span class="text-sm">{{ $item->nama_paket ?: 'Belum menentukan' }}</span>
                @if ($item->tanggal_berangkat)
                    <div class="text-xs text-gray-500">{{ $item->tanggal_berangkat->translatedFormat('d M Y') }}</div>
                @endif
            @endscope

            @scope('cell_kesehatan', $item)
                <span
                    class="badge badge-soft {{ $item->riwayat_kesehatan_count >= $item->jumlah_peserta ? 'badge-success' : 'badge-warning' }}">
                    {{ $item->riwayat_kesehatan_count }}/{{ $item->jumlah_peserta }}
                </span>
            @endscope

            @scope('cell_status', $item)
                <x-mary-badge :value="$item->status_label"
                    class="badge-soft {{ $item->status === 'lunas' ? 'badge-success' : ($item->status === 'batal' ? 'badge-error' : 'badge-info') }}" />
            @endscope

            @scope('actions', $item)
                <x-mary-button icon="o-eye" wire:click="buka({{ $item->id }})" spinner="buka({{ $item->id }})"
                    class="btn-ghost btn-sm text-slate-700" />
                <x-mary-button icon="o-trash" wire:click="openDeleteModal({{ $item->id }})"
                    spinner="openDeleteModal({{ $item->id }})" class="btn-ghost btn-sm text-error" />
            @endscope

            <x-slot:empty>
                <div class="py-10 text-center">
                    <x-mary-icon name="o-clipboard-document-list" class="w-12 h-12 text-gray-400 opacity-50" />
                    <p class="mt-2 text-gray-500">Belum ada pendaftaran masuk.</p>
                </div>
            </x-slot:empty>
        </x-mary-table>
    </x-mary-card>

    {{-- Detail pendaftaran + riwayat kesehatan --}}
    <x-mary-modal wire:model="showModal" title="Detail Pendaftaran" separator box-class="max-w-3xl">
        @if ($terpilih)
            <div class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Kode</p>
                        <p class="font-mono font-bold">{{ $terpilih->kode }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Pemesan</p>
                        <p class="font-bold">{{ $terpilih->nama }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">WhatsApp</p>
                        <a href="https://api.whatsapp.com/send?phone={{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $terpilih->whatsapp)) }}"
                            target="_blank" rel="noopener"
                            class="font-bold text-primary hover:underline">{{ $terpilih->whatsapp }}</a>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Paket</p>
                        <p class="font-bold">{{ $terpilih->nama_paket ?: 'Belum menentukan' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Tanggal berangkat</p>
                        <p class="font-bold">
                            {{ $terpilih->tanggal_berangkat?->translatedFormat('d F Y') ?: 'Belum ditentukan' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wider uppercase text-base-content/50">Jumlah peserta</p>
                        <p class="font-bold">{{ $terpilih->jumlah_peserta }} orang</p>
                    </div>
                </div>

                @if ($terpilih->titik_jemput || $terpilih->catatan)
                    <div class="p-4 text-sm rounded-2xl bg-base-200">
                        @if ($terpilih->titik_jemput)
                            <p><strong>Titik jemput:</strong> {{ $terpilih->titik_jemput }}</p>
                        @endif
                        @if ($terpilih->catatan)
                            <p class="mt-1 whitespace-pre-line"><strong>Catatan:</strong> {{ $terpilih->catatan }}</p>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-48">
                        <x-mary-select label="Status pendaftaran" wire:model="statusBaru" :options="$statusOptions" />
                    </div>
                    <x-mary-button label="Simpan Status" wire:click="simpanStatus" spinner="simpanStatus"
                        class="btn-primary" />
                </div>

                <div>
                    <h3 class="flex items-center gap-2 mb-3 font-bold admin-title">
                        <x-mary-icon name="o-heart" class="w-5 h-5 text-primary" />
                        Riwayat Kesehatan Peserta
                        <span class="badge badge-soft badge-info">{{ $terpilih->riwayatKesehatan->count() }} /
                            {{ $terpilih->jumlah_peserta }}</span>
                    </h3>

                    @forelse ($terpilih->riwayatKesehatan as $peserta)
                        <div class="p-4 mb-3 border rounded-2xl border-base-300">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-bold">
                                    {{ $peserta->nama_peserta }}
                                    <span class="ml-1 text-sm font-normal text-base-content/60">
                                        {{ $peserta->usia ? $peserta->usia . ' tahun' : '' }}
                                        {{ $peserta->golongan_darah ? '· Gol. ' . $peserta->golongan_darah : '' }}
                                    </span>
                                </p>
                                @if ($peserta->ada_catatan_khusus)
                                    <span class="badge badge-warning badge-soft">Ada catatan kesehatan</span>
                                @else
                                    <span class="badge badge-success badge-soft">Tanpa catatan khusus</span>
                                @endif
                            </div>

                            @if (! empty($peserta->kondisi_khusus))
                                <div class="flex flex-wrap gap-1.5 mt-3">
                                    @foreach ($peserta->kondisi_khusus as $kondisi)
                                        <span class="badge badge-sm badge-warning badge-soft">
                                            {{ config('orcha.kondisi_kesehatan')[$kondisi] ?? $kondisi }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <dl class="grid gap-2 mt-3 text-sm sm:grid-cols-2">
                                @foreach ([['Jenis kelamin', $peserta->jenis_kelamin], ['Tinggi / berat', $peserta->tinggi_badan || $peserta->berat_badan ? trim(($peserta->tinggi_badan ? $peserta->tinggi_badan . ' cm' : '') . ' / ' . ($peserta->berat_badan ? $peserta->berat_badan . ' kg' : ''), ' /') : null], ['Riwayat penyakit', $peserta->riwayat_penyakit], ['Riwayat operasi / rawat inap', $peserta->riwayat_operasi], ['Alergi', $peserta->alergi], ['Pantangan makanan', $peserta->pantangan_makanan], ['Obat rutin', $peserta->obat_rutin], ['Pantangan kegiatan', $peserta->pantangan_kegiatan], ['Kemampuan berenang', $peserta->kemampuan_renang ? config('orcha.kemampuan_renang')[$peserta->kemampuan_renang] ?? $peserta->kemampuan_renang : null], ['Asuransi / BPJS', $peserta->asuransi], ['Catatan tambahan', $peserta->catatan_tambahan]] as [$label, $isi])
                                    <div>
                                        <dt class="text-xs tracking-wider uppercase text-base-content/50">
                                            {{ $label }}</dt>
                                        <dd class="whitespace-pre-line">{{ $isi ?: '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <p class="mt-3 text-sm">
                                <span class="text-xs tracking-wider uppercase text-base-content/50">Kontak darurat:</span>
                                <strong>{{ $peserta->kontak_darurat_nama }}</strong>
                                ({{ $peserta->kontak_darurat_hubungan ?: 'tidak disebutkan' }}) —
                                {{ $peserta->kontak_darurat_hp }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-base-content/60">Belum ada peserta yang mengisi formulir kesehatan.
                            Kirimkan kode <strong>{{ $terpilih->kode }}</strong> ke pemesan.</p>
                    @endforelse
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Tutup" @click="$wire.tutup()" spinner="tutup" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Konfirmasi hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Pendaftaran">
        <p>Menghapus pendaftaran ini sekaligus menghapus seluruh data riwayat kesehatan pesertanya. Lanjutkan?</p>
        <x-slot:actions>
            <x-mary-button label="Batal" @click="$wire.tutup()" spinner="tutup" />
            <x-mary-button label="Ya, Hapus" wire:click="hapus" spinner="hapus" class="btn-error" />
        </x-slot:actions>
    </x-mary-modal>
</div>
