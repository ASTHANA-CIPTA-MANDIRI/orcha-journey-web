<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pendaftaran Open Trip — Orcha Journey')] class extends Component {
    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $paketId = '';

    public int $jumlahPeserta = 1;

    /**
     * Nama tiap peserta. Peserta pertama adalah pemesan itu sendiri, jadi
     * namanya ikut terisi otomatis — pemesan tidak perlu mengetik dua kali.
     */
    public array $peserta = [''];

    public string $catatan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public ?string $kodeTerdaftar = null;

    public function mount(): void
    {
        // Pra-isi paket bila pengunjung datang dari kartu paket, mis. ?paket=<uuid>
        $paket = (string) request()->query('paket', '');

        if ($paket !== '' && $this->paketTersedia()->contains('uuid', $paket)) {
            $this->paketId = $paket;
        }
    }

    /**
     * Aturan validasi ini berjalan di server. Atribut `required` di HTML hanya
     * mempercepat umpan balik; data tetap ditolak di sini bila kosong atau
     * tidak sah, termasuk bila pengunjung mengakalinya dari sisi peramban.
     */
    protected function rules(): array
    {
        return [
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => 'required|string|min:8|max:30|regex:/^[0-9+()\-\s]+$/',
            'email' => 'nullable|email|max:150',
            'paketId' => 'required|exists:tbl_travel_package,uuid',
            'jumlahPeserta' => 'required|integer|min:1|max:60',
            'peserta' => 'required|array|min:1',
            'peserta.*' => 'required|string|min:3|max:120',
            'catatan' => 'nullable|string|max:1000',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'paketId' => 'paket open trip',
            'jumlahPeserta' => 'jumlah peserta',
            'peserta.*' => 'nama peserta',
            'setuju' => 'persetujuan syarat & ketentuan',
        ];
    }

    /** Kotak nama mengikuti jumlah peserta, tanpa menghapus yang sudah diketik. */
    public function updatedJumlahPeserta(): void
    {
        $this->rapikanPeserta();
    }

    public function updatedNama(): void
    {
        // Peserta pertama = pemesan
        $this->peserta[0] = $this->nama;
    }

    private function rapikanPeserta(): void
    {
        $jumlah = max(1, min(60, (int) $this->jumlahPeserta));
        $peserta = array_values($this->peserta);

        while (count($peserta) < $jumlah) {
            $peserta[] = '';
        }

        $this->peserta = array_slice($peserta, 0, $jumlah);
        $this->peserta[0] = $this->peserta[0] ?: $this->nama;
    }

    public function daftar(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->rapikanPeserta();
        $this->validate();

        $kunci = 'daftar-open-trip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('nama', 'Terlalu banyak pendaftaran dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $paket = TravelPackage::where('uuid', $this->paketId)->firstOrFail();

        // Tanggal dan titik jemput diambil dari paket — bukan dari isian
        // pengunjung — supaya tidak ada pendaftaran dengan jadwal karangan.
        $pendaftaran = PendaftaranOpenTrip::create([
            'travel_package_id' => $paket->id,
            'nama_paket' => $paket->name,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email ?: null,
            'jumlah_peserta' => $this->jumlahPeserta,
            'daftar_peserta' => array_map('trim', $this->peserta),
            'tanggal_berangkat' => $paket->tanggal_berangkat,
            'titik_jemput' => $paket->titik_jemput,
            'catatan' => $this->catatan ?: null,
        ]);

        $this->kodeTerdaftar = $pendaftaran->kode;
        $this->reset(['nama', 'whatsapp', 'email', 'catatan', 'setuju', 'peserta']);
    }

    public function daftarLagi(): void
    {
        $this->reset(['kodeTerdaftar', 'jumlahPeserta', 'peserta']);
    }

    /**
     * Hanya paket open trip yang tanggalnya belum lewat.
     */
    private function paketTersedia()
    {
        return TravelPackage::tayang()->where('category', 'open_trip')
            ->where(fn ($q) => $q->whereNull('tanggal_berangkat')->orWhereDate('tanggal_berangkat', '>=', today()))
            ->orderByRaw('tanggal_berangkat is null')
            ->orderBy('tanggal_berangkat')
            ->get();
    }

    public function with(): array
    {
        $daftarPaket = $this->paketTersedia();

        return [
            'paketOpenTrip' => $daftarPaket,
            'paketTerpilih' => $this->paketId ? $daftarPaket->firstWhere('uuid', $this->paketId) : null,
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $dp = config('orcha.pembayaran.dp_persen');
    $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
    $batasDp = config('orcha.pembayaran.dp_batas_jam');
@endphp

<div>
    <x-page-hero title="Pendaftaran Open Trip" eyebrow="Formulir Pendaftaran"
        subtitle="Pilih trip yang jadwalnya sudah kami tetapkan, isi data Anda, dan kami balas dengan ketersediaan kursi di hari yang sama."
        image="images/pantai-ramai.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                {{-- Formulir --}}
                <div class="lg:col-span-8">
                    @if ($kodeTerdaftar)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">Pendaftaran Anda tercatat
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">Simpan kode di bawah ini. Kode dipakai untuk mengisi
                                formulir riwayat kesehatan peserta.</p>

                            <p
                                class="inline-block px-6 py-3 mt-5 text-2xl font-black tracking-widest rounded-2xl font-heading bg-orcha-foam text-orcha-navy">
                                {{ $kodeTerdaftar }}
                            </p>

                            <div class="p-4 mt-6 text-sm text-left rounded-2xl bg-orcha-foam/60 text-slate-600">
                                <p class="font-bold text-orcha-navy">Langkah berikutnya</p>
                                <ol class="mt-2 space-y-1 list-decimal list-inside">
                                    <li>Isi <strong>formulir riwayat kesehatan</strong> untuk tiap peserta.</li>
                                    <li>Tim kami menghubungi Anda untuk konfirmasi kursi.</li>
                                    <li>Bayar uang muka {{ $dp }}% paling lambat {{ $batasDp }} jam setelah
                                        konfirmasi, lalu pelunasan paling lambat
                                        <strong>H-{{ $pelunasan }}</strong> sebelum keberangkatan.</li>
                                </ol>
                            </div>

                            <x-peringatan-pembayaran class="mt-4 text-left" />

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <a href="{{ route('riwayat-kesehatan', ['kode' => $kodeTerdaftar]) }}"
                                    class="btn-orcha btn-orcha-primary">
                                    <x-heroicon-o-heart class="w-5 h-5" />
                                    Isi Riwayat Kesehatan
                                </a>
                                <a href="{{ route('konfirmasi-pembayaran', ['kode' => $kodeTerdaftar]) }}"
                                    class="btn-orcha btn-orcha-outline">
                                    <x-heroicon-o-banknotes class="w-5 h-5" />
                                    Konfirmasi Pembayaran
                                </a>
                                <a href="{{ $wa("Halo Orcha Journey, saya sudah mendaftar open trip dengan kode $kodeTerdaftar.") }}"
                                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-outline">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Konfirmasi via WhatsApp
                                </a>
                            </div>

                            <button type="button" wire:click="daftarLagi"
                                class="mt-5 text-sm font-semibold text-orcha-ocean hover:underline">
                                Daftarkan rombongan lain
                            </button>
                        </div>
                    @elseif ($paketOpenTrip->isEmpty())
                        <div class="p-10 text-center card-orcha">
                            <x-heroicon-o-calendar-days class="w-12 h-12 mx-auto text-orcha-mist" />
                            <p class="mt-3 font-semibold text-orcha-navy">Belum ada jadwal open trip yang dibuka.</p>
                            <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk menanyakan jadwal berikutnya.</p>
                            <a href="{{ $wa('Halo Orcha Journey, kapan jadwal open trip berikutnya dibuka?') }}"
                                target="_blank" rel="noopener noreferrer" class="mt-5 btn-orcha btn-orcha-primary">
                                <x-bi-whatsapp class="w-5 h-5" />
                                Tanya Jadwal
                            </a>
                        </div>
                    @else
                        <form wire:submit="daftar" class="p-6 space-y-6 card-orcha sm:p-8">
                            <div class="hidden" aria-hidden="true">
                                <label for="daftar-situs">Jangan diisi</label>
                                <input id="daftar-situs" type="text" wire:model="situs" tabindex="-1"
                                    autocomplete="off">
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Pilih Trip</h2>
                                <p class="mt-1 text-sm text-slate-500">Tanggal keberangkatan dan titik jemput sudah kami
                                    tetapkan untuk tiap trip.</p>
                            </div>

                            <div>
                                <label for="d-paket" class="label-orcha">Paket open trip <x-wajib /></label>
                                <select id="d-paket" wire:model.live="paketId" required
                                    class="isian-orcha @error('paketId') isian-galat @enderror">
                                    <option value="">— Pilih trip —</option>
                                    @foreach ($paketOpenTrip as $paket)
                                        <option value="{{ $paket->uuid }}">
                                            {{ $paket->name }}{{ $paket->jadwal_label ? ' · ' . $paket->jadwal_label : '' }}
                                            — {{ $rupiah($paket->price) }}/orang
                                        </option>
                                    @endforeach
                                </select>
                                @error('paketId')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Jadwal & titik jemput: ditetapkan, tidak bisa dipilih peserta --}}
                            @if ($paketTerpilih)
                                <div class="p-5 rounded-2xl bg-orcha-foam/70">
                                    <p class="flex items-center gap-2 text-xs font-bold tracking-wider uppercase text-orcha-ocean">
                                        <x-heroicon-s-lock-closed class="w-4 h-4" />
                                        Sudah ditetapkan oleh Orcha Journey
                                    </p>

                                    <dl class="grid gap-4 mt-4 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                Tanggal berangkat</dt>
                                            <dd class="font-bold text-orcha-navy">
                                                {{ $paketTerpilih->jadwal_label ?? 'Menyusul, akan kami kabari' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                Titik jemput</dt>
                                            <dd class="font-bold text-orcha-navy">
                                                {{ $paketTerpilih->titik_jemput ?? 'Dikonfirmasi tim kami' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                Durasi</dt>
                                            <dd class="font-bold text-orcha-navy">
                                                {{ $paketTerpilih->duration ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                Minimal berangkat</dt>
                                            <dd class="font-bold text-orcha-navy">
                                                {{ $paketTerpilih->minimal_peserta }} orang</dd>
                                        </div>
                                    </dl>

                                    @if ($paketTerpilih->batas_pelunasan)
                                        <p class="pt-4 mt-4 text-xs border-t border-white/70 text-slate-600">
                                            Uang muka {{ $dp }}% saat pemesanan, pelunasan paling lambat
                                            <strong class="text-orcha-navy">
                                                {{ $paketTerpilih->batas_pelunasan->translatedFormat('j F Y') }}
                                            </strong> (H-{{ $pelunasan }}).
                                        </p>
                                    @endif

                                    <a href="{{ route('paket-detail', $paketTerpilih->uuid) }}"
                                        class="inline-block mt-3 text-sm font-semibold text-orcha-ocean hover:underline">
                                        Lihat itinerary lengkap
                                    </a>
                                </div>
                            @endif

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Data Pemesan</h2>
                                <p class="mt-1 text-sm text-slate-500">Satu orang sebagai penanggung jawab rombongan.
                                </p>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="d-nama" class="label-orcha">Nama lengkap <x-wajib /></label>
                                    <input id="d-nama" type="text" wire:model="nama" required minlength="3"
                                        maxlength="120" placeholder="Nama pemesan"
                                        class="isian-orcha @error('nama') isian-galat @enderror">
                                    @error('nama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="d-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                    <input id="d-wa" type="tel" wire:model="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="08xxxxxxxxxx"
                                        class="isian-orcha @error('whatsapp') isian-galat @enderror">
                                    @error('whatsapp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="d-email" class="label-orcha">Email <span
                                            class="font-normal text-slate-400">(opsional)</span></label>
                                    <input id="d-email" type="email" wire:model="email" maxlength="150"
                                        placeholder="nama@email.com"
                                        class="isian-orcha @error('email') isian-galat @enderror">
                                    @error('email')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="d-jumlah" class="label-orcha">Jumlah peserta <x-wajib /></label>
                                    <input id="d-jumlah" type="number" min="1" max="60" required
                                        wire:model.live="jumlahPeserta"
                                        class="isian-orcha @error('jumlahPeserta') isian-galat @enderror">
                                    @error('jumlahPeserta')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Nama tiap peserta ditulis sejak awal.

                                 Riwayat kesehatan diisi per orang, dan tanpa daftar ini
                                 identitas peserta lain baru diketahui setelah mereka
                                 mengisi formulir itu — terlalu terlambat untuk menyiapkan
                                 kursi, kamar, dan konsumsi. --}}
                            <div>
                                <label class="label-orcha">Nama peserta <x-wajib /></label>
                                <p class="mb-3 text-sm text-slate-500">
                                    Tulis nama tiap orang sesuai kartu identitas. Peserta pertama adalah
                                    Anda sebagai pemesan.
                                </p>

                                <div class="space-y-2">
                                    @foreach ($peserta as $urutan => $satu)
                                        <div class="flex items-center gap-3" wire:key="peserta-{{ $urutan }}">
                                            <span
                                                class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full shrink-0 bg-orcha-foam text-orcha-navy">
                                                {{ $urutan + 1 }}
                                            </span>
                                            <input type="text" maxlength="120" required
                                                wire:model="peserta.{{ $urutan }}"
                                                placeholder="{{ $urutan === 0 ? 'Nama Anda (pemesan)' : 'Nama peserta ke-' . ($urutan + 1) }}"
                                                class="isian-orcha @error('peserta.' . $urutan) isian-galat @enderror">
                                        </div>
                                        @error('peserta.' . $urutan)
                                            <p class="galat-orcha ms-11">{{ $message }}</p>
                                        @enderror
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label for="d-catatan" class="label-orcha">Catatan tambahan <span
                                        class="font-normal text-slate-400">(opsional)</span></label>
                                <textarea id="d-catatan" rows="4" wire:model="catatan" maxlength="1000"
                                    placeholder="Misalnya ada peserta anak-anak, lansia, atau permintaan khusus."
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
                                    <a href="{{ route('syarat-ketentuan') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">syarat &amp;
                                        ketentuan</a>,
                                    <a href="{{ route('ketentuan-pembayaran') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">ketentuan pembayaran</a>,
                                    dan
                                    <a href="{{ route('kebijakan-pengembalian') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">kebijakan
                                        pengembalian dana</a>.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">
                                Kolom bertanda <x-wajib /> wajib diisi.
                            </p>

                            <button type="submit" class="w-full btn-orcha btn-orcha-primary"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="daftar">Kirim Pendaftaran</span>
                                <span wire:loading wire:target="daftar">Mengirim…</span>
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Sisi kanan --}}
                <aside class="lg:col-span-4">
                    {{-- Ikut menggulung bersama halaman, berhenti di atas layar --}}
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <x-peringatan-pembayaran />

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Setelah mendaftar</h2>
                            <ol class="mt-4 space-y-4">
                                @foreach ([['Konfirmasi kursi', 'Kami cek ketersediaan lalu menghubungi Anda lewat WhatsApp.'], ['Riwayat kesehatan', 'Tiap peserta mengisi formulir kesehatan memakai kode pendaftaran.'], ['Uang muka & pelunasan', "Kursi dikunci setelah DP $dp% masuk. Pelunasan paling lambat H-$pelunasan sebelum keberangkatan."], ['Berangkat', 'Jam kumpul di titik jemput dikirim H-1 sebelum keberangkatan.']] as $i => [$judul, $isi])
                                    <li class="flex gap-3">
                                        <span
                                            class="flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full shrink-0 bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                                            {{ $i + 1 }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-bold text-orcha-navy">{{ $judul }}</p>
                                            <p class="mt-0.5 text-sm text-slate-600">{{ $isi }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Sudah pernah daftar?</h2>
                            <p class="mt-2 text-sm text-slate-600">Kalau Anda tinggal mengisi data kesehatan peserta,
                                langsung saja ke formulirnya.</p>
                            <a href="{{ route('riwayat-kesehatan') }}"
                                class="w-full mt-4 btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                                <x-heroicon-o-heart class="w-4 h-4" />
                                Formulir Riwayat Kesehatan
                            </a>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">
                                Ingin bertanya dulu sebelum mendaftar?
                            </p>
                            <a href="{{ $wa('Halo Orcha Journey, saya ingin bertanya soal open trip sebelum mendaftar.') }}"
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
</div>
