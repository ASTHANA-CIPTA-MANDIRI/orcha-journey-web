<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use App\Support\NomorTelepon;
use App\Support\SalinanPelanggan;
use App\Support\RincianBiaya;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pendaftaran Open Trip — Orcha Journey')] class extends Component {
    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $paketId = '';

    /*
     | SENGAJA tanpa tipe int.
     |
     | Isian angka di peramban mengirim string kosong saat pengguna menghapus
     | angkanya — dan itu wajar: menghapus "21" jadi "2" lalu kosong sebelum
     | mengetik angka baru. Properti bertipe int tidak bisa menerima nilai
     | kosong, dan Livewire menganggapnya properti hilang sehingga halaman
     | berhenti dengan galat. Nilainya dibersihkan saat dipakai dan ditegur
     | oleh aturan validasi.
     */
    public $jumlahPeserta = 1;

    /**
     * Nama tiap peserta. Peserta pertama adalah pemesan itu sendiri, jadi
     * namanya ikut terisi otomatis — pemesan tidak perlu mengetik dua kali.
     */
    public array $peserta = [['nama' => '', 'titik_jemput' => '']];

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
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            'email' => 'nullable|email|max:150',
            'paketId' => 'required|exists:tbl_travel_package,uuid',
            'jumlahPeserta' => 'required|integer|min:1|max:60',
            'peserta' => 'required|array|min:1',
            'peserta.*.nama' => 'required|string|min:3|max:120',
            // Titik jemput hanya ditanya bila paketnya memang menawarkan
            // lebih dari satu; nilainya wajib salah satu dari yang ditawarkan.
            'peserta.*.titik_jemput' => $this->aturanTitikJemput(),
            'catatan' => 'nullable|string|max:1000',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'paketId' => 'paket open trip',
            'jumlahPeserta' => 'jumlah peserta',
            'peserta.*.nama' => 'nama peserta',
            'peserta.*.titik_jemput' => 'titik jemput peserta',
            'setuju' => 'persetujuan syarat & ketentuan',
        ];
    }

    /** Kotak peserta mengikuti jumlahnya, tanpa menghapus yang sudah diisi. */
    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    public function updatedJumlahPeserta(): void
    {
        $this->rapikanPeserta();
    }

    public function updatedNama(): void
    {
        // Peserta pertama = pemesan
        $this->peserta[0]['nama'] = $this->nama;
    }

    /** Ganti paket berarti pilihan titik jemputnya berbeda — mulai dari kosong. */
    public function updatedPaketId(): void
    {
        foreach ($this->peserta as $urutan => $satu) {
            $this->peserta[$urutan]['titik_jemput'] = '';
        }

        $this->rapikanPeserta();
    }

    private function aturanTitikJemput(): string
    {
        $paket = $this->paketDipilih();

        if (! $paket || ! $paket->punya_pilihan_jemput) {
            return 'nullable|string|max:191';
        }

        return 'required|string|in:'.implode(',', $paket->titik_jemput_list);
    }

    private function paketDipilih(): ?TravelPackage
    {
        return $this->paketId
            ? TravelPackage::where('uuid', $this->paketId)->first()
            : null;
    }

    private function rapikanPeserta(): void
    {
        $jumlah = (int) $this->jumlahPeserta;

        // Isian sedang kosong atau belum masuk akal: biarkan kotak yang sudah
        // terisi apa adanya. Meruntuhkannya jadi satu baris hanya karena
        // pengguna sedang menghapus angka berarti nama yang telanjur diketik
        // ikut hilang. Validasi yang menegur saat dikirim.
        if ($jumlah < 1) {
            return;
        }

        $jumlah = min(60, $jumlah);
        $paket = $this->paketDipilih();

        // Paket dengan satu titik jemput tidak perlu ditanya — langsung diisikan.
        $bawaan = $paket && ! $paket->punya_pilihan_jemput
            ? ($paket->titik_jemput_list[0] ?? '')
            : '';

        $peserta = [];

        foreach (range(0, $jumlah - 1) as $urutan) {
            $lama = $this->peserta[$urutan] ?? [];

            $peserta[] = [
                'nama' => is_array($lama) ? ($lama['nama'] ?? '') : (string) $lama,
                'titik_jemput' => (is_array($lama) ? ($lama['titik_jemput'] ?? '') : '') ?: $bawaan,
            ];
        }

        $peserta[0]['nama'] = $peserta[0]['nama'] ?: $this->nama;

        $this->peserta = $peserta;
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
            'daftar_peserta' => collect($this->peserta)->map(fn ($satu) => [
                'nama' => trim($satu['nama']),
                'titik_jemput' => trim($satu['titik_jemput'] ?? ''),
            ])->all(),
            'tanggal_berangkat' => $paket->tanggal_berangkat,
            // Yang disimpan titik yang benar-benar dipakai rombongan ini,
            // bukan seluruh titik yang ditawarkan paket.
            'titik_jemput' => collect($this->peserta)->pluck('titik_jemput')
                ->filter()->unique()->implode(', ') ?: $paket->titik_jemput,
            'catatan' => $this->catatan ?: null,
        ]);

        $rincian = [
            'Paket' => $paket->name,
            'Keberangkatan' => $paket->jadwal_label ?: '—',
            'Pemesan' => $pendaftaran->nama,
            'WhatsApp' => $pendaftaran->whatsapp,
            'Email' => $pendaftaran->email,
            'Jumlah peserta' => $pendaftaran->jumlah_peserta.' orang',
            'Peserta & titik jemput' => collect($pendaftaran->peserta)
                ->map(fn ($satu) => $satu['nama'].' — '.($satu['titik_jemput'] ?: 'belum dipilih'))
                ->implode("\n"),
        ];

        // Lampirannya bukan kwitansi — belum ada uang yang masuk pada tahap
        // ini. Yang dibutuhkan pelanggan justru tagihannya: berapa DP yang
        // harus ditransfer sekarang, dan berapa sisa pelunasannya.
        $biaya = RincianBiaya::untuk($paket, (int) $pendaftaran->jumlah_peserta);

        $berkas = BerkasKwitansi::buat(
            'Rincian Biaya Pendaftaran',
            $pendaftaran->kode,
            $rincian,
            $pendaftaran->catatan,
            $biaya ? $biaya['dp_teks'] : null,
            $biaya ? 'Dibayar sekarang · DP '.$biaya['dp_persen'].'%' : null,
            'Belum Dibayar',
            biaya: $biaya,
        );

        // Angka biaya ikut ditulis di badan surat supaya terbaca tanpa perlu
        // membuka lampirannya lebih dulu.
        $rincianSurat = $biaya ? array_merge($rincian, [
            'Total biaya' => $biaya['total_teks'].' ('.$biaya['satuan_teks'].' × '.$biaya['orang'].' orang)',
            'DP '.$biaya['dp_persen'].'% — bayar sekarang' => $biaya['dp_teks'],
            'Sisa pelunasan' => $biaya['sisa_teks'].' — paling lambat H-'.$biaya['pelunasan_hari'],
        ]) : $rincian;

        // Dikirim SETELAH tersimpan, dan kegagalannya tidak membatalkan apa pun.
        KirimPemberitahuan::kirim(
            'Pendaftaran Open Trip Baru',
            $pendaftaran->kode,
            $rincianSurat,
            $pendaftaran->catatan,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('rincian-biaya', $pendaftaran->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $pendaftaran->email,
                judul: 'Pendaftaran Anda Sudah Kami Terima',
                // Tombolnya langsung ke langkah berikutnya, bukan ke beranda:
                // yang ditunggu sekarang adalah bukti transfernya.
                tautan: route('konfirmasi-pembayaran', ['kode' => $pendaftaran->kode]),
                labelTautan: 'Kirim Bukti Transfer',
                langkah: "Simpan kode {$pendaftaran->kode}. Kode inilah yang dipakai untuk mengirim bukti "
                ."transfer, mengisi riwayat kesehatan tiap peserta, sampai mengajukan pembatalan.\n\n"
                .($biaya
                    ? 'Berikutnya: transfer DP '.$biaya['dp_persen'].'% sebesar '.$biaya['dp_teks'].' paling lambat '
                        .$biaya['dp_batas_jam'].' jam sejak pendaftaran ini, lalu unggah buktinya lewat halaman '
                        .'Konfirmasi Pembayaran. Sisanya '.$biaya['sisa_teks'].' dilunasi paling lambat H-'
                        .$biaya['pelunasan_hari'].' sebelum berangkat. Rinciannya ada di lampiran surat ini.'
                        // Sebagian pelanggan lebih suka sekali bayar dan selesai. Tanpa
                        // disebutkan, mereka mengira DP itu wajib lalu mentransfer dua
                        // kali untuk sesuatu yang bisa sekali.
                        .' Boleh juga langsung lunas '.$biaya['total_teks'].' sekaligus — '
                        .'pilih jenis "Pelunasan" di formulirnya.'
                    : 'Berikutnya: tim kami menghitung biayanya lebih dulu, lalu mengabari Anda lewat WhatsApp.')
                .' Tempat duduk baru terkunci setelah DP masuk.'
                // Rombongan besar butuh pintunya sejak awal: tiap peserta mengisi
                // riwayat kesehatannya sendiri, dan halaman itu menyediakan tautan
                // pribadi per peserta yang tinggal dibagikan lewat WhatsApp.
                .($pendaftaran->jumlah_peserta > 1
                    ? "\n\nRiwayat kesehatan diisi satu formulir untuk tiap peserta karena kondisi tiap orang "
                        .'berbeda. Buka '.route('riwayat-kesehatan', ['kode' => $pendaftaran->kode])
                        .' — di sana terlihat siapa saja yang belum mengisi, lengkap dengan tautan pribadi '
                        .'yang tinggal Anda kirimkan ke masing-masing peserta.'
                    : "\n\nJangan lupa mengisi riwayat kesehatan di "
                        .route('riwayat-kesehatan', ['kode' => $pendaftaran->kode])),
            ),
        );

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
            'titikJemputPilihan' => $this->paketDipilih()?->titik_jemput_list ?? [],
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
        image="images/HERO/form-pendaftaran-trip.webp" posisi="center top" />

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

                            {{-- Disebut di sini, saat kodenya masih di layar.

                                 Pertanyaan "pesanan saya bagaimana?" baru muncul
                                 berhari-hari kemudian — dan kalau saat itu ia tidak
                                 tahu halaman ini ada, satu-satunya jalan yang
                                 terpikir adalah bertanya lewat WhatsApp. --}}
                            <p class="mt-5 text-sm text-slate-600">
                                Ingin melihat status, sisa tagihan, dan bukti yang sudah Anda kirim kapan saja?
                                <a href="{{ route('lacak-pesanan', ['kode' => $kodeTerdaftar]) }}"
                                    class="font-semibold text-orcha-ocean hover:underline">Lacak pesanan Anda</a>
                                memakai kode di atas dan 4 digit terakhir nomor WhatsApp Anda.
                            </p>

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
                                    <input id="d-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="0812-3456-7890"
                                        class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
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
                                <label class="label-orcha">
                                    Peserta &amp; titik jemput <x-wajib />
                                </label>
                                <p class="mb-3 text-sm text-slate-500">
                                    Tulis nama tiap orang sesuai kartu identitas. Peserta pertama adalah
                                    Anda sebagai pemesan.
                                </p>

                                <div class="space-y-3">
                                    @foreach ($peserta as $urutan => $satu)
                                        <div class="flex items-start gap-3" wire:key="peserta-{{ $urutan }}">
                                            <span
                                                class="flex items-center justify-center w-8 h-8 mt-2 text-sm font-bold rounded-full shrink-0 bg-orcha-foam text-orcha-navy">
                                                {{ $urutan + 1 }}
                                            </span>

                                            <div class="flex-1 grid gap-2 {{ count($titikJemputPilihan) > 1 ? 'sm:grid-cols-2' : '' }}">
                                                <div>
                                                    <input type="text" maxlength="120" required
                                                        wire:model="peserta.{{ $urutan }}.nama"
                                                        placeholder="{{ $urutan === 0 ? 'Nama Anda (pemesan)' : 'Nama peserta ke-' . ($urutan + 1) }}"
                                                        class="isian-orcha @error('peserta.' . $urutan . '.nama') isian-galat @enderror">
                                                    @error('peserta.' . $urutan . '.nama')
                                                        <p class="galat-orcha">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                {{-- Titik jemput ditanya PER PESERTA: satu rombongan sering
                                                     berangkat dari kota berbeda, dan sopir perlu tahu siapa
                                                     menunggu di mana. --}}
                                                @if (count($titikJemputPilihan) > 1)
                                                    <div>
                                                        <select required wire:model="peserta.{{ $urutan }}.titik_jemput"
                                                            class="isian-orcha @error('peserta.' . $urutan . '.titik_jemput') isian-galat @enderror">
                                                            <option value="">— Pilih titik jemput —</option>
                                                            @foreach ($titikJemputPilihan as $titik)
                                                                <option value="{{ $titik }}">{{ $titik }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('peserta.' . $urutan . '.titik_jemput')
                                                            <p class="galat-orcha">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if (count($titikJemputPilihan) > 1)
                                    <p class="mt-3 text-sm text-slate-500">
                                        Tiap peserta boleh dijemput di titik yang berbeda. Butuh titik di luar
                                        daftar? Tulis di catatan tambahan — kami kabari apakah bisa dilayani.
                                    </p>
                                @endif
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

    <x-skrip-isian />
</div>
