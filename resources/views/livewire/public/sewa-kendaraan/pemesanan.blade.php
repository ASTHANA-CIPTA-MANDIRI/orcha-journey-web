<?php

use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use App\Support\NomorTelepon;
use App\Support\SalinanPelanggan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pemesanan Sewa Kendaraan — Orcha Journey')] class extends Component {
    public string $unit = '';

    public string $transmisi = '';

    public string $satuan = 'hari';

    public $durasi = 1;

    public string $tanggalMulai = '';

    public string $jamMulai = '08:00';

    public string $denganSopir = 'ya';

    public string $lokasiAntar = '';

    /**
     * Lokasi pengembalian ditanya terpisah.
     *
     * Penyewa sering mengambil unit di kantor lalu mengembalikannya di bandara,
     * atau sebaliknya. Kalau hanya satu isian, sopir yang menjemput unit
     * berangkat ke alamat yang salah.
     */
    public string $lokasiKembali = '';

    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $catatan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public ?string $kodeTerkirim = null;

    public function mount(): void
    {
        $unit = (string) request()->query('unit', '');

        if ($unit !== '' && $this->armada()->contains('uuid', $unit)) {
            $this->unit = $unit;
            $this->sesuaikanPilihan();
        }
    }

    /**
     * Begitu unit berganti, transmisi dan satuan ikut disesuaikan dengan yang
     * benar-benar tersedia pada unit itu.
     */
    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    public function updatedUnit(): void
    {
        $this->sesuaikanPilihan();
    }

    private function sesuaikanPilihan(): void
    {
        $mobil = $this->kendaraanTerpilih();

        if (! $mobil) {
            return;
        }

        if (! in_array($this->transmisi, $mobil->transmisi_tersedia_list, true)) {
            $this->transmisi = $mobil->transmisi_tersedia_list[0] ?? '';
        }

        if ($mobil->tarif($this->satuan) === null) {
            $this->satuan = 'hari';
        }
    }

    protected function rules(): array
    {
        return [
            'unit' => 'required|exists:cars,uuid',
            'transmisi' => 'required|in:Manual,Matic',
            'satuan' => 'required|in:' . implode(',', array_keys(config('orcha.satuan_sewa'))),
            'durasi' => 'required|integer|min:1|max:30',
            'tanggalMulai' => 'required|date|after_or_equal:today',
            'jamMulai' => 'required|date_format:H:i',
            'denganSopir' => 'required|in:ya,tidak',
            // Wajib: tanpa alamat yang jelas, unit tidak bisa diantar maupun
            // dijemput kembali.
            'lokasiAntar' => 'required|string|min:4|max:191',
            'lokasiKembali' => 'required|string|min:4|max:191',
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            // Wajib: kwitansi dan tanda terima unit dikirim ke alamat ini, dan
            // itu berkas yang dibutuhkan penyewa bila terjadi sengketa.
            'email' => 'required|email|max:150',
            'catatan' => 'nullable|string|max:1000',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'unit' => 'kendaraan',
            'satuan' => 'satuan sewa',
            'tanggalMulai' => 'tanggal mulai',
            'jamMulai' => 'jam mulai',
            'denganSopir' => 'kebutuhan sopir',
            'lokasiAntar' => 'lokasi pengantaran unit',
            'lokasiKembali' => 'lokasi pengembalian unit',
            'setuju' => 'persetujuan ketentuan sewa',
        ];
    }

    public function pesan(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->validate();

        $mobil = Car::where('uuid', $this->unit)->firstOrFail();

        // Satuan yang tidak dijual untuk unit ini ditolak di server, bukan
        // sekadar disembunyikan di tampilan.
        if ($mobil->tarif($this->satuan) === null) {
            $this->addError('satuan', 'Unit ini tidak disewakan dengan satuan tersebut.');

            return;
        }

        if (! in_array($this->transmisi, $mobil->transmisi_tersedia_list, true)) {
            $this->addError('transmisi', 'Transmisi itu tidak tersedia untuk unit ini.');

            return;
        }

        $kunci = 'sewa-kendaraan:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('nama', 'Terlalu banyak pemesanan dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        // Tenggat pengembalian dihitung sekali lalu disimpan. Kalau dihitung
        // ulang setiap kali dibaca, mengubah aturan durasi di kemudian hari
        // akan diam-diam menggeser tenggat pesanan yang sudah berjalan — dan
        // denda keterlambatan ikut bergeser bersamanya.
        $selesai = PenyewaanKendaraan::hitungSelesai(
            $this->tanggalMulai, $this->jamMulai, $this->satuan, (int) $this->durasi
        );

        // Unit yang sudah dipesan orang lain di rentang waktu yang sama ditolak
        // di sini, bukan diketahui pagi keberangkatan saat mobilnya sudah
        // dibawa orang pertama.
        $bentrok = PenyewaanKendaraan::bentrok(
            $mobil->id,
            PenyewaanKendaraan::hitungSelesai($this->tanggalMulai, $this->jamMulai, $this->satuan, 0),
            $selesai,
        );

        if ($bentrok->isNotEmpty()) {
            $lain = $bentrok->first();

            $this->addError('tanggalMulai', 'Unit ini sudah dipesan sampai '
                .$lain->jadwal_selesai->translatedFormat('j F Y, H:i')
                .'. Pilih tanggal lain, atau unit lain yang sejenis.');

            return;
        }

        $sewa = PenyewaanKendaraan::create([
            'car_id' => $mobil->id,
            'tanggal_selesai' => $selesai->toDateString(),
            'jam_selesai' => $selesai->format('H:i'),
            'lokasi_kembali' => $this->lokasiKembali,
            'nama_kendaraan' => $mobil->name,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'transmisi' => $this->transmisi,
            'satuan' => $this->satuan,
            'durasi' => $this->durasi,
            'tanggal_mulai' => $this->tanggalMulai,
            'jam_mulai' => $this->jamMulai,
            'dengan_sopir' => $this->denganSopir === 'ya',
            'lokasi_antar' => $this->lokasiAntar ?: null,
            'estimasi_biaya' => $mobil->estimasiBiaya($this->satuan, $this->durasi, $this->denganSopir === 'ya'),
            'catatan' => $this->catatan ?: null,
        ]);

        // Formulir ini satu-satunya yang tidak pernah mengirim surat, sehingga
        // penyewa tidak memegang bukti apa pun begitu halamannya ditutup —
        // termasuk kode pesanan dan jam pengembaliannya. Padahal justru itu
        // yang dipakai saat menagih denda keterlambatan.
        $rincian = [
            'Kendaraan' => $sewa->nama_kendaraan.' ('.$sewa->transmisi.')',
            'Sopir' => $sewa->dengan_sopir ? 'Dengan sopir' : 'Lepas kunci',
            'Mulai' => $sewa->jadwal_mulai
                ? $sewa->jadwal_mulai->translatedFormat('l, j F Y').' pukul '.$sewa->jadwal_mulai->format('H:i')
                : '—',
            'Ditunggu kembali' => $selesai->translatedFormat('l, j F Y').' pukul '.$selesai->format('H:i'),
            'Durasi' => $sewa->durasi_label,
            'Lokasi pengantaran' => $sewa->lokasi_antar,
            'Lokasi pengembalian' => $sewa->lokasi_kembali,
            'Penyewa' => $sewa->nama,
            'WhatsApp' => $sewa->whatsapp,
        ];

        $berkas = BerkasKwitansi::buat(
            'Rincian Pemesanan Sewa Kendaraan',
            $sewa->kode,
            $rincian,
            $sewa->catatan,
            $sewa->estimasi_biaya ? 'Rp '.number_format($sewa->estimasi_biaya, 0, ',', '.') : null,
            'Estimasi biaya sewa',
            'Belum Dibayar',
        );

        KirimPemberitahuan::kirim(
            'Pemesanan Sewa Kendaraan Baru',
            $sewa->kode,
            $rincian,
            $sewa->catatan,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('rincian-sewa', $sewa->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $sewa->email,
                judul: 'Pemesanan Sewa Kendaraan Sudah Kami Terima',
                tautan: route('konfirmasi-pembayaran', ['kode' => $sewa->kode]),
                labelTautan: 'Kirim Bukti Transfer',
                langkah: "Simpan kode {$sewa->kode} — dipakai saat mengirim bukti transfer.\n\n"
                    .'Unit ditunggu kembali '.$selesai->translatedFormat('l, j F Y').' pukul '
                    .$selesai->format('H:i').' WIB di '.$sewa->lokasi_kembali.'. Ada tenggang '
                    .config('orcha.denda_sewa.tenggang_menit').' menit; lewat dari itu dikenakan denda '
                    .'keterlambatan '.config('orcha.denda_sewa.persen_tarif_harian_per_jam')
                    .'% tarif harian per jam.'."\n\n"
                    .'Biaya di lampiran masih perkiraan — BBM, tol, dan biaya lokasi dihitung terpisah, '
                    .'dan tim kami mengabari angka pastinya lewat WhatsApp.',
            ),
        );

        $this->kodeTerkirim = $sewa->kode;
        $this->reset(['nama', 'whatsapp', 'email', 'catatan', 'lokasiAntar', 'lokasiKembali', 'setuju']);
    }

    public function pesanLagi(): void
    {
        $this->reset(['kodeTerkirim', 'durasi']);
    }

    private function armada()
    {
        return Car::where('is_available', true)
            ->orderByRaw("case type when 'mobil' then 1 when 'hiace' then 2 else 3 end")
            ->orderBy('price_per_day')
            ->get();
    }

    private function kendaraanTerpilih(): ?Car
    {
        return $this->unit ? $this->armada()->firstWhere('uuid', $this->unit) : null;
    }

    public function with(): array
    {
        $mobil = $this->kendaraanTerpilih();

        return [
            'armada' => $this->armada(),
            'mobil' => $mobil,
            // Tenggat pengembalian ditampilkan sebelum dikirim: keterlambatan
            // didenda, jadi penyewa harus tahu jam berapa unit ditunggu kembali
            // sejak sebelum ia menekan Pesan.
            'jadwalSelesai' => $this->tanggalMulai && $this->jamMulai && (int) $this->durasi > 0
                ? PenyewaanKendaraan::hitungSelesai($this->tanggalMulai, $this->jamMulai, $this->satuan, (int) $this->durasi)
                : null,
            'satuanTersedia' => collect(config('orcha.satuan_sewa'))
                ->filter(fn ($info, $kunci) => $mobil === null || $mobil->tarif($kunci) !== null),
            'estimasi' => $mobil?->estimasiBiaya($this->satuan, $this->durasi, $this->denganSopir === 'ya'),
        ];
    }
}; ?>

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Pemesanan Sewa Kendaraan" eyebrow="Formulir Sewa"
        subtitle="Pilih unit, lama sewa, dan kebutuhan sopir. Perkiraan biayanya langsung terhitung sebelum Anda mengirim."
        image="images/kendaraan.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($kodeTerkirim)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">Pemesanan tercatat</h2>
                            <p class="mt-2 text-sm text-slate-600">Simpan kode ini untuk memudahkan komunikasi dengan tim
                                kami.</p>

                            <p
                                class="inline-block px-6 py-3 mt-5 text-2xl font-black tracking-widest rounded-2xl font-heading bg-orcha-foam text-orcha-navy">
                                {{ $kodeTerkirim }}
                            </p>

                            <p class="mt-4 text-sm text-slate-600">
                                Tim kami mengecek ketersediaan unit lalu mengirim rincian biaya final lewat WhatsApp.
                            </p>

                            <x-peringatan-pembayaran ringkas class="mt-3" />

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <a href="{{ $wa("Halo Orcha Journey, saya baru memesan sewa kendaraan dengan kode $kodeTerkirim.") }}"
                                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Konfirmasi via WhatsApp
                                </a>

                                <a href="{{ route('konfirmasi-pembayaran', ['kode' => $kodeTerkirim]) }}"
                                    class="btn-orcha btn-orcha-outline">
                                    <x-heroicon-o-banknotes class="w-5 h-5" />
                                    Konfirmasi Pembayaran
                                </a>
                                <button type="button" wire:click="pesanLagi" class="btn-orcha btn-orcha-outline">
                                    Pesan Unit Lain
                                </button>
                            </div>
                        </div>
                    @else
                        <form wire:submit="pesan" class="p-6 space-y-6 card-orcha sm:p-8">
                            <div class="hidden" aria-hidden="true">
                                <label for="sk-situs">Jangan diisi</label>
                                <input id="sk-situs" type="text" wire:model="situs" tabindex="-1" autocomplete="off">
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Pilih Kendaraan</h2>
                                <p class="mt-1 text-sm text-slate-500">Tarif per jam, per 12 jam, dan per hari berbeda —
                                    pilih yang paling sesuai kebutuhan.</p>
                            </div>

                            <div>
                                <label for="sk-unit" class="label-orcha">Kendaraan <x-wajib /></label>
                                <select id="sk-unit" wire:model.live="unit" required
                                    class="isian-orcha @error('unit') isian-galat @enderror">
                                    <option value="">— Pilih unit —</option>
                                    @foreach ($armada->groupBy('type') as $jenis => $daftar)
                                        <optgroup label="{{ config('orcha.jenis_kendaraan')[$jenis] ?? $jenis }}">
                                            @foreach ($daftar as $item)
                                                <option value="{{ $item->uuid }}">
                                                    {{ $item->name }} · {{ $item->transmisi_label }} ·
                                                    {{ $rupiah($item->price_per_day) }}/hari
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('unit')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($mobil)
                                <div class="p-5 rounded-2xl bg-orcha-foam/70">
                                    <p class="text-xs font-bold tracking-wider uppercase text-orcha-ocean">Tarif unit
                                        ini</p>
                                    <dl class="grid gap-3 mt-3 sm:grid-cols-3">
                                        @foreach ([['Per jam', $mobil->harga_per_jam], ['Paket 12 jam', $mobil->harga_12_jam], ['Per hari', $mobil->price_per_day]] as [$label, $harga])
                                            <div>
                                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                                <dd class="font-bold text-orcha-navy tabular">
                                                    {{ $harga ? $rupiah($harga) : 'Tidak tersedia' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    @if ($mobil->harga_sopir)
                                        <p class="pt-3 mt-3 text-xs border-t border-white/70 text-slate-600">
                                            Sopir {{ $rupiah($mobil->harga_sopir) }}/hari. BBM, tol, parkir, dan tiket
                                            masuk lokasi dihitung terpisah.
                                        </p>
                                    @endif
                                </div>
                            @endif

                            {{-- Transmisi: hanya yang tersedia pada unit terpilih --}}
                            <fieldset>
                                <legend class="label-orcha">Transmisi <x-wajib /></legend>
                                @if ($mobil)
                                    @if ($mobil->punya_dua_transmisi)
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($mobil->transmisi_tersedia_list as $pilihan)
                                                <label class="pilihan-centang">
                                                    <input type="radio" class="sr-only" value="{{ $pilihan }}"
                                                        wire:model="transmisi">
                                                    <span class="kotak" aria-hidden="true">
                                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                            stroke-width="3.2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M4 10.5 8 14.5 16 5.5" />
                                                        </svg>
                                                    </span>
                                                    <span>{{ $pilihan }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="px-4 py-3 text-sm font-semibold border rounded-2xl border-orcha-foam text-orcha-navy bg-orcha-foam/40">
                                            {{ $mobil->transmisi_label }}
                                            <span class="font-normal text-slate-500">— unit ini hanya tersedia dalam
                                                transmisi tersebut</span>
                                        </p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-500">Pilih kendaraan dulu untuk melihat transmisi yang
                                        tersedia.</p>
                                @endif
                                @error('transmisi')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Waktu Sewa</h2>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-satuan" class="label-orcha">Satuan sewa <x-wajib /></label>
                                    <select id="sk-satuan" wire:model.live="satuan" required
                                        class="isian-orcha @error('satuan') isian-galat @enderror">
                                        @foreach ($satuanTersedia as $kunci => $info)
                                            <option value="{{ $kunci }}">{{ $info['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('satuan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-durasi" class="label-orcha">Lama sewa <x-wajib /></label>
                                    <input id="sk-durasi" type="number" min="1" max="30" required
                                        wire:model.live="durasi"
                                        class="isian-orcha @error('durasi') isian-galat @enderror">
                                    <p class="mt-1 text-xs text-slate-500">
                                        Dihitung dalam
                                        {{ config('orcha.satuan_sewa')[$satuan]['satuan'] ?? 'hari' }}.
                                    </p>
                                    @error('durasi')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-tanggal" class="label-orcha">Tanggal mulai <x-wajib /></label>
                                    <input id="sk-tanggal" type="date" required min="{{ now()->toDateString() }}"
                                        wire:model.live="tanggalMulai"
                                        class="isian-orcha @error('tanggalMulai') isian-galat @enderror">
                                    @error('tanggalMulai')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-jam" class="label-orcha">Jam mulai <x-wajib /></label>
                                    <input id="sk-jam" type="time" required wire:model.live="jamMulai"
                                        class="isian-orcha @error('jamMulai') isian-galat @enderror">
                                    @error('jamMulai')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <fieldset>
                                <legend class="label-orcha">Kebutuhan sopir <x-wajib /></legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ([['ya', 'Dengan sopir'], ['tidak', 'Lepas kunci']] as [$nilai, $label])
                                        @if ($nilai === 'tidak' && $mobil && $mobil->type !== 'mobil')
                                            <span class="pilihan-centang opacity-50 cursor-not-allowed">
                                                <span class="kotak" aria-hidden="true"></span>
                                                <span>{{ $label }}</span>
                                            </span>
                                        @else
                                            <label class="pilihan-centang">
                                                <input type="radio" class="sr-only" value="{{ $nilai }}"
                                                    wire:model.live="denganSopir">
                                                <span class="kotak" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                        stroke-width="3.2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M4 10.5 8 14.5 16 5.5" />
                                                    </svg>
                                                </span>
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                @if ($mobil && $mobil->type !== 'mobil')
                                    <p class="mt-2 text-xs text-slate-500">{{ $mobil->type_label }} hanya disewakan
                                        bersama sopir kami.</p>
                                @endif
                                @error('denganSopir')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            {{-- Dua alamat, bukan satu: penyewa sering mengambil unit di
                                 kantor lalu mengembalikannya di bandara. Kalau hanya satu
                                 isian, sopir yang menjemput unit berangkat ke alamat yang
                                 salah. --}}
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-lokasi" class="label-orcha">Lokasi pengantaran unit <x-wajib /></label>
                                    <input id="sk-lokasi" type="text" wire:model="lokasiAntar" required minlength="4" maxlength="191"
                                        placeholder="Contoh: Bandara YIA, atau alamat lengkap"
                                        class="isian-orcha @error('lokasiAntar') isian-galat @enderror">
                                    @error('lokasiAntar')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-lokasi-kembali" class="label-orcha">Lokasi pengembalian unit <x-wajib /></label>
                                    <input id="sk-lokasi-kembali" type="text" wire:model="lokasiKembali" required minlength="4" maxlength="191"
                                        placeholder="Boleh sama dengan lokasi pengantaran"
                                        class="isian-orcha @error('lokasiKembali') isian-galat @enderror">
                                    @error('lokasiKembali')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- ============ TENGGAT PENGEMBALIAN ============
                                 Ditampilkan sebelum dikirim, bukan setelahnya: keterlambatan
                                 didenda, jadi penyewa harus tahu jam berapa unit ditunggu
                                 kembali sejak sebelum ia menekan Pesan. --}}
                            @if ($jadwalSelesai)
                                <div class="flex items-start gap-3 p-4 rounded-2xl bg-orcha-foam/70">
                                    <x-heroicon-s-clock class="w-5 h-5 mt-0.5 shrink-0 text-orcha-ocean" />
                                    <div>
                                        <p class="text-sm font-bold text-orcha-navy">
                                            Unit ditunggu kembali
                                            {{ $jadwalSelesai->translatedFormat('l, d F Y') }} pukul
                                            {{ $jadwalSelesai->format('H:i') }} WIB
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Ada tenggang {{ config('orcha.denda_sewa.tenggang_menit') }} menit.
                                            Lewat dari itu dikenakan denda keterlambatan
                                            {{ config('orcha.denda_sewa.persen_tarif_harian_per_jam') }}% tarif harian
                                            per jam.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Data Penyewa</h2>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-nama" class="label-orcha">Nama lengkap <x-wajib /></label>
                                    <input id="sk-nama" type="text" wire:model="nama" required minlength="3"
                                        maxlength="120" placeholder="Nama penyewa"
                                        class="isian-orcha @error('nama') isian-galat @enderror">
                                    @error('nama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                    <input id="sk-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="0812-3456-7890"
                                        class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
                                    @error('whatsapp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="sk-email" class="label-orcha">Email <x-wajib /></label>
                                <input id="sk-email" type="email" wire:model="email" required maxlength="150"
                                    placeholder="nama@email.com"
                                    class="isian-orcha @error('email') isian-galat @enderror">
                                @error('email')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sk-catatan" class="label-orcha">Catatan tambahan <span
                                        class="font-normal text-slate-400">(opsional)</span></label>
                                <textarea id="sk-catatan" rows="3" wire:model="catatan" maxlength="1000"
                                    placeholder="Rencana rute, jumlah penumpang, atau permintaan khusus."
                                    class="isian-orcha @error('catatan') isian-galat @enderror"></textarea>
                                @error('catatan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <label class="flex items-start gap-3 text-sm cursor-pointer text-slate-600">
                                <input type="checkbox" wire:model="setuju" required
                                    class="mt-0.5 w-5 h-5 rounded border-orcha-foam text-orcha-ocean focus:ring-orcha-sky">
                                <span>
                                    <x-wajib /> Saya menyetujui
                                    <a href="{{ route('syarat-ketentuan') }}#sewa-kendaraan"
                                        class="font-semibold text-orcha-ocean hover:underline">ketentuan sewa
                                        kendaraan</a>
                                    dan memahami bahwa BBM, tol, parkir, serta tiket masuk lokasi dihitung terpisah.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">Kolom bertanda <x-wajib /> wajib diisi.</p>

                            <button type="submit" class="w-full btn-orcha btn-orcha-primary"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="pesan">Kirim Pemesanan</span>
                                <span wire:loading wire:target="pesan">Mengirim…</span>
                            </button>
                        </form>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        {{-- Perkiraan biaya ikut berubah saat pilihan diubah --}}
                        <div class="overflow-hidden card-orcha">
                            <div class="p-6 text-white bg-gradient-to-br from-orcha-navy to-orcha-abyss sm:p-7">
                                <p class="text-xs font-bold tracking-wider uppercase text-orcha-sun">Perkiraan biaya</p>
                                <p class="mt-2 text-3xl font-black font-heading tabular">
                                    {{ $estimasi ? $rupiah($estimasi) : '—' }}
                                </p>
                                @if ($mobil)
                                    <p class="mt-1 text-sm text-slate-300">
                                        {{ $mobil->name }} · {{ $durasi }}
                                        {{ config('orcha.satuan_sewa')[$satuan]['satuan'] ?? 'hari' }}
                                        · {{ $denganSopir === 'ya' ? 'dengan sopir' : 'lepas kunci' }}
                                    </p>
                                @endif
                            </div>

                            <div class="p-6 text-xs sm:p-7 text-slate-500">
                                <p>Angka di atas baru perkiraan. Biaya final dikonfirmasi tim kami setelah ketersediaan
                                    unit dicek, dan belum termasuk BBM, tol, parkir, serta tiket masuk lokasi.</p>
                            </div>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <x-peringatan-pembayaran class="mb-5" />

                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Cara kerjanya</h2>
                            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                                @foreach (['Anda kirim pemesanan lewat formulir ini.', 'Kami cek ketersediaan unit pada tanggal tersebut.', 'Rincian biaya final dikirim lewat WhatsApp.', 'Unit dikunci setelah uang muka masuk.'] as $i => $langkah)
                                    <li class="flex gap-3">
                                        <span
                                            class="flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full shrink-0 bg-orcha-ocean">{{ $i + 1 }}</span>
                                        <span>{{ $langkah }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">Butuh beberapa unit sekaligus atau rute khusus?</p>
                            <a href="{{ $wa('Halo Orcha Journey, saya ingin menyewa beberapa unit kendaraan sekaligus.') }}"
                                target="_blank" rel="noopener noreferrer"
                                class="w-full mt-3 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                <x-bi-whatsapp class="w-4 h-4" />
                                Tanya via WhatsApp
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <x-skrip-isian />
</div>
