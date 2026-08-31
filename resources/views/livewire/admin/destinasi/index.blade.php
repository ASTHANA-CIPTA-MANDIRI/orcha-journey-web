<?php

use App\Models\Etalase\DestinationPopuler;
use App\Support\GambarWebp;
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

    /**
     * Aturannya TIDAK ditulis di atribut.
     *
     * Daftar wilayah dulu ditulis keras di sini — tempat ketiga yang menyatakan
     * hal yang sama sesudah config dan halaman publik — dan langsung meleset
     * begitu "Bali & Nusa Tenggara" dipecah dua: menyimpan destinasi Bali
     * ditolak validasi tanpa sebab yang bisa dimengerti admin. Lihat rules().
     */
    public string $wilayah = 'jawa';

    #[Rule('nullable|string|max:60')]
    public $provinsi = '';

    #[Rule('nullable|string|max:500')]
    public $deskripsi = '';

    #[Rule('required')]
    public $totalVisitor = 0;

    #[Rule('nullable|image|max:5000')]
    public $mainPhoto;

    public $existingMainPhoto = null;

    #[Rule(['othersPhoto.*' => 'image|max:2048'])]
    public $othersPhoto = [];

    /**
     * Gambar tambahan yang sudah tersimpan dan MASIH dipertahankan.
     *
     * Daftar ini yang menentukan isi kolom others_photo saat disimpan, bukan
     * isi lama di basis data. Menghapus satu gambar berarti mengeluarkannya
     * dari sini; berkasnya sendiri baru dihapus ketika perubahannya disimpan,
     * supaya menutup modal tanpa menyimpan tidak meninggalkan gambar rusak.
     */
    public $existingOthersPhoto = [];

    /**
     * Kartu destinasi di halaman publik hanya menampung tiga gambar tambahan.
     * Angkanya dipakai untuk validasi DAN untuk tulisan sisa tempat, supaya
     * label dan aturannya tidak pernah berbeda.
     */
    public const BATAS_SUB_GAMBAR = 3;

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

    /**
     | Menerima ID, bukan model yang diikat otomatis.
     *
     * Sejak destinasi punya alamat publiknya sendiri, getRouteKeyName() model
     * ini mengembalikan 'slug' — supaya /destinasi/raja-ampat terbaca manusia
     * dan mesin pencari. Livewire memakai kunci yang sama untuk mengikat
     * parameter metode, sedangkan tombol di daftar ini mengirim id.
     *
     * Akibatnya tombol sunting menjawab "tidak ditemukan" untuk SETIAP
     * destinasi. Modelnya diambil di sini supaya kuncinya tidak lagi ikut
     * berubah bersama alamat publiknya.
     */
    public function edit(int $destinationId): void
    {
        $destination = DestinationPopuler::findOrFail($destinationId);

        $this->resetForm();
        $this->isEdit = true;
        $this->destinationName = $destination->destination_name;
        $this->wilayah = $destination->wilayah ?? 'jawa';
        $this->provinsi = $destination->provinsi ?? '';
        $this->deskripsi = $destination->deskripsi ?? '';
        $this->totalVisitor = $destination->total_visitor;
        $this->existingMainPhoto = $destination->main_photo;
        $this->existingOthersPhoto = $destination->others_photo ?? [];
        $this->destinationId = $destination->id;
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->destinationName = '';
        $this->wilayah = 'jawa';
        $this->provinsi = '';
        $this->deskripsi = '';
        $this->totalVisitor = 0;
        $this->mainPhoto = null;
        $this->existingMainPhoto = null;
        $this->othersPhoto = [];
        $this->existingOthersPhoto = [];
        $this->destinationId = null;
    }

    /**
     * Mengeluarkan satu gambar tambahan yang sudah tersimpan.
     *
     * Berkasnya belum dihapus di sini. Kalau modalnya ditutup tanpa disimpan,
     * tidak ada yang hilang; berkas yang benar-benar tidak dipakai lagi
     * dibersihkan di save() dengan membandingkan daftar lama dan baru.
     */
    public function hapusSubGambar(int $urutan): void
    {
        unset($this->existingOthersPhoto[$urutan]);
        $this->existingOthersPhoto = array_values($this->existingOthersPhoto);
    }

    /**
     * Membatalkan satu berkas yang baru dipilih, sebelum tersimpan.
     */
    public function hapusUnggahan(int $urutan): void
    {
        unset($this->othersPhoto[$urutan]);
        $this->othersPhoto = array_values($this->othersPhoto);
    }

    /**
     * Sisa tempat gambar tambahan, dihitung dari yang dipertahankan DAN yang
     * baru dipilih — bukan dari salah satunya saja.
     */
    public function sisaSlot(): int
    {
        return max(0, self::BATAS_SUB_GAMBAR
            - count($this->existingOthersPhoto)
            - count($this->othersPhoto ?: []));
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

    /**
     * Wilayah dibaca dari daftar yang berlaku, termasuk yang ditambahkan admin.
     */
    protected function rules(): array
    {
        return [
            'wilayah' => ['required', 'in:'.implode(',', array_keys(\App\Models\Etalase\WilayahTambahan::gabungan()))],
        ];
    }

    public function save(): void
    {
        $this->validate();

        // Label "maksimal 3" sebelumnya hanya tulisan: lima gambar pun diterima.
        // Batasnya dihitung dari total yang akan tersimpan, karena kalau hanya
        // unggahan baru yang dihitung, dua kali unggah masing-masing dua berkas
        // tetap lolos.
        $total = count($this->existingOthersPhoto) + count($this->othersPhoto ?: []);

        if ($total > self::BATAS_SUB_GAMBAR) {
            $this->addError('othersPhoto', 'Gambar tambahan maksimal '
                . self::BATAS_SUB_GAMBAR . '. Hapus dulu salah satu sebelum menambah.');

            return;
        }

        try {
            $dataToSave = [
                'destination_name' => $this->destinationName,
                'wilayah' => $this->wilayah,
                'provinsi' => $this->provinsi ?: null,
                'deskripsi' => $this->deskripsi ?: null,
                'total_visitor' => $this->totalVisitor,
            ];

            $destinationData = $this->isEdit ? DestinationPopuler::findOrFail($this->destinationId) : new DestinationPopuler();

            if ($this->mainPhoto) {
                if ($this->isEdit && $destinationData->main_photo) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $destinationData->main_photo));
                }
                $dataToSave['main_photo'] = GambarWebp::simpan($this->mainPhoto, 'destinasi_populer/utama');
            }

            // Gambar tambahan DITAMBAHKAN pada yang sudah ada, tidak menggantinya.
            //
            // Sebelumnya satu unggahan baru menghapus seluruh gambar lama:
            // menambah gambar ketiga justru menyisakan satu. Yang tersimpan
            // sekarang adalah gambar yang dipertahankan ditambah yang baru.
            $tersimpan = array_values($this->existingOthersPhoto);

            foreach ($this->othersPhoto ?: [] as $photo) {
                $tersimpan[] = GambarWebp::simpan($photo, 'destinasi_populer/tambahan');
            }

            if ($this->isEdit || $tersimpan) {
                $dataToSave['others_photo'] = $tersimpan;

                // Berkas yang benar-benar sudah tidak dirujuk lagi baru dibuang
                // di sini, sesudah pengguna menyimpan keputusannya.
                foreach (array_diff($destinationData->others_photo ?? [], $tersimpan) as $dibuang) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $dibuang));
                }
            }

            if ($this->isEdit) {
                $destinationData->update($dataToSave);
                $this->success('Perubahan destinasi tersimpan');
            } else {
                DestinationPopuler::create($dataToSave);
                $this->success('Berhasil tambah destinasi');
            }

            $this->closeModal();
        } catch (Exception $e) {
            // dump() sebelumnya membocorkan isi pengecualian ke halaman admin.
            report($e);
            $this->error('gagal menambah destinasi');
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'main_photo', 'label' => 'Gambar', 'class' => 'w-1'],
            ['key' => 'destination_name', 'label' => 'Nama Tempat'],
            ['key' => 'wilayah', 'label' => 'Wilayah', 'class' => 'w-1'],
            ['key' => 'total_visitor', 'label' => 'Total Pengunjung', 'class' => 'w-1'],
        ];
    }

    public function with(): array
    {
        return [
            'destinations' => DestinationPopuler::orderByDesc('total_visitor')->get(),
            'sisaSlot' => $this->sisaSlot(),
            'batasSubGambar' => self::BATAS_SUB_GAMBAR,
            'headers' => $this->headers(),
            'wilayahOptions' => collect(\App\Models\Etalase\WilayahTambahan::gabungan())
                ->map(fn ($label, $kunci) => ['id' => $kunci, 'name' => $label])
                ->values()
                ->all(),
        ];
    }
}; ?>

<div>
    <x-mary-header title="Destinasi Populer" no-separator progress-indicator>
        <x-slot:actions>
            <x-mary-button spinner="openModal" label="Tambah Destinasi" wire:click="openModal" responsive icon="o-plus"
                class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$destinations">
            @scope('cell_main_photo', $destination)
                @if ($destination->main_photo)
                    <img src="{{ $destination->main_photo }}" alt="{{ $destination->destination_name }}"
                        class="object-cover w-20 h-14 rounded-lg">
                @else
                    <x-heroicon-o-photo class="w-10 h-10 text-slate-400" />
                @endif
            @endscope

            @scope('cell_destination_name', $destination)
                <div class="font-bold">{{ $destination->destination_name }}</div>
                <div class="text-xs text-gray-500">{{ $destination->provinsi ?: '-' }}</div>
            @endscope

            @scope('cell_wilayah', $destination)
                <x-mary-badge :value="$destination->wilayah_label" class="badge-soft badge-info" />
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
            <x-mary-input label="Nama Destinasi" wire:model="destinationName" placeholder="Contoh: Raja Ampat" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-mary-select label="Wilayah" wire:model="wilayah" :options="$wilayahOptions"
                    hint="Menentukan tab filter di halaman Destinasi" />
                <x-mary-input label="Provinsi" wire:model="provinsi" placeholder="Contoh: Papua Barat Daya" />
            </div>
            <x-mary-textarea label="Deskripsi Singkat" wire:model="deskripsi" rows="3"
                placeholder="Satu sampai dua kalimat tentang destinasi ini" hint="Opsional, tampil di kartu destinasi" />
            <x-mary-input label="Total Pengunjung" wire:model="totalVisitor" placeholder="0" />
            <x-mary-file label="Foto Utama (Background)" wire:model="mainPhoto"
                accept="image/png, image/jpg, image/jpeg" />
            @if ($mainPhoto)
                <img class="h-36 rounded-lg shadow-sm" src="{{ $mainPhoto->temporaryUrl() }}" alt="Preview Baru">
            @elseif($existingMainPhoto)
                <img class="h-36 rounded-lg shadow-sm" src="{{ asset($existingMainPhoto) }}" alt="Foto Lama">
            @endif


            {{-- Gambar tambahan.

                 Sebelumnya yang lama dan yang baru saling menutupi: begitu ada
                 berkas dipilih, gambar tersimpan hilang dari pandangan — dan
                 memang ikut terhapus saat disimpan. Keduanya sekarang tampil
                 berdampingan dengan penanda masing-masing, dan tiap gambar bisa
                 dihapus sendiri tanpa mengunggah ulang yang lain. --}}
            <div class="pt-2 space-y-3 border-t border-base-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold">Gambar Tambahan</span>
                    <span @class([
                        'text-xs font-medium',
                        'text-warning' => $sisaSlot === 0,
                        'text-gray-500' => $sisaSlot > 0,
                    ])>
                        {{ $batasSubGambar - $sisaSlot }} dari {{ $batasSubGambar }} terpakai
                    </span>
                </div>

                @if ($existingOthersPhoto || $othersPhoto)
                    <div class="flex flex-wrap gap-3">
                        @foreach ($existingOthersPhoto as $urutan => $oldPhoto)
                            <div class="relative">
                                <img class="object-cover w-28 h-20 border rounded-lg shadow-sm border-base-200"
                                    src="{{ asset($oldPhoto) }}" alt="Gambar tersimpan">
                                <button type="button" wire:click="hapusSubGambar({{ $urutan }})"
                                    wire:loading.attr="disabled" wire:target="hapusSubGambar({{ $urutan }})"
                                    title="Hapus gambar ini"
                                    class="absolute flex items-center justify-center w-6 h-6 text-white rounded-full shadow -top-2 -right-2 bg-error hover:brightness-110">
                                    <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach

                        @foreach ($othersPhoto ?: [] as $urutan => $photo)
                            <div class="relative">
                                <img class="object-cover w-28 h-20 rounded-lg shadow-sm ring-2 ring-primary"
                                    src="{{ $photo->temporaryUrl() }}" alt="Gambar baru">
                                <span
                                    class="absolute px-1.5 py-0.5 text-[10px] font-semibold text-white rounded bottom-1 left-1 bg-primary">
                                    Baru
                                </span>
                                <button type="button" wire:click="hapusUnggahan({{ $urutan }})"
                                    wire:loading.attr="disabled" wire:target="hapusUnggahan({{ $urutan }})"
                                    title="Batalkan gambar ini"
                                    class="absolute flex items-center justify-center w-6 h-6 text-white rounded-full shadow -top-2 -right-2 bg-error hover:brightness-110">
                                    <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($sisaSlot > 0)
                    <x-mary-file wire:model="othersPhoto" multiple accept="image/png, image/jpeg"
                        hint="Bisa pilih beberapa sekaligus — sisa {{ $sisaSlot }} gambar lagi, maksimal 2 MB per gambar" />
                @else
                    <div class="flex items-start gap-2 p-3 text-xs rounded-lg bg-base-200 text-gray-600">
                        <x-mary-icon name="o-information-circle" class="w-4 h-4 mt-px shrink-0" />
                        <span>Sudah terisi {{ $batasSubGambar }} gambar. Hapus salah satu di atas bila ingin
                            menggantinya.</span>
                    </div>
                @endif

                @error('othersPhoto')
                    <p class="text-xs text-error">{{ $message }}</p>
                @enderror
                @error('othersPhoto.*')
                    <p class="text-xs text-error">{{ $message }}</p>
                @enderror
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
