<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Support\KirimPemberitahuan;
use App\Support\SalinanPelanggan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Riwayat Kesehatan Peserta — Orcha Journey')] class extends Component {
    public string $kode = '';

    public string $namaPeserta = '';

    public $usia = '';

    public string $jenisKelamin = '';

    public $tinggiBadan = '';

    public $beratBadan = '';

    public string $golonganDarah = '';

    public array $kondisiKhusus = [];

    public string $riwayatOperasi = '';

    public string $pantanganMakanan = '';

    public string $kemampuanRenang = '';

    public string $asuransi = '';

    public string $catatanTambahan = '';

    public string $riwayatPenyakit = '';

    public string $alergi = '';

    public string $obatRutin = '';

    public string $pantangan = '';

    public string $kontakNama = '';

    public string $kontakHp = '';

    public string $kontakHubungan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public bool $terkirim = false;

    public ?string $namaTerkirim = null;

    /**
     * Mengetik nama sendiri, karena namanya tidak ada di daftar peserta.
     *
     * Perlu ada jalan keluarnya: rombongan sering bertambah orang setelah
     * mendaftar, dan nama yang tertulis saat pendaftaran kadang cuma panggilan.
     */
    public bool $namaBebas = false;

    public function mount(): void
    {
        $this->kode = strtoupper((string) request()->query('kode', ''));

        // Tautan yang dibagikan ketua rombongan sudah membawa nama pesertanya,
        // jadi yang membuka tinggal mengisi data kesehatannya sendiri.
        $this->namaPeserta = trim((string) request()->query('peserta', ''));

        if (blank($this->namaPeserta) || blank($this->kode)) {
            return;
        }

        // Ejaannya disamakan dengan yang terdaftar. Kalau tidak, "budi santoso"
        // dari tautan dianggap orang lain oleh penanda "sudah diisi", dan
        // rombongannya tidak pernah terlihat lengkap.
        $terdaftar = collect(PendaftaranOpenTrip::where('kode', $this->kode)->first()?->peserta ?? [])
            ->pluck('nama')
            ->first(fn ($nama) => mb_strtolower(trim($nama)) === mb_strtolower($this->namaPeserta));

        // Nama di luar daftar tetap bisa mengisi — rombongan sering bertambah
        // orang setelah pendaftarannya masuk.
        $this->namaBebas = blank($terdaftar);
        $this->namaPeserta = $terdaftar ?: $this->namaPeserta;
    }

    protected function rules(): array
    {
        return [
            'kode' => 'required|string|max:20|exists:tbl_pendaftaran_open_trip,kode',
            'namaPeserta' => 'required|string|min:3|max:120',
            'usia' => 'required|integer|min:1|max:110',
            'jenisKelamin' => 'required|in:Laki-laki,Perempuan',
            'tinggiBadan' => 'nullable|integer|min:50|max:250',
            'beratBadan' => 'nullable|integer|min:10|max:250',
            'golonganDarah' => 'nullable|in:A,B,AB,O',
            'kondisiKhusus' => 'nullable|array',
            'kondisiKhusus.*' => 'in:' . implode(',', array_keys(config('orcha.kondisi_kesehatan'))),
            'riwayatOperasi' => 'nullable|string|max:1000',
            'pantanganMakanan' => 'nullable|string|max:500',
            'kemampuanRenang' => 'required|in:' . implode(',', array_keys(config('orcha.kemampuan_renang'))),
            'asuransi' => 'nullable|string|max:120',
            'catatanTambahan' => 'nullable|string|max:1000',
            'riwayatPenyakit' => 'nullable|string|max:1000',
            'alergi' => 'nullable|string|max:1000',
            'obatRutin' => 'nullable|string|max:1000',
            'pantangan' => 'nullable|string|max:191',
            'kontakNama' => 'required|string|min:3|max:120',
            'kontakHp' => 'required|string|min:8|max:30|regex:/^[0-9+()\-\s]+$/',
            'kontakHubungan' => 'required|string|max:60',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'kode' => 'kode pendaftaran',
            'namaPeserta' => 'nama peserta',
            'jenisKelamin' => 'jenis kelamin',
            'tinggiBadan' => 'tinggi badan',
            'beratBadan' => 'berat badan',
            'golonganDarah' => 'golongan darah',
            'kondisiKhusus' => 'kondisi kesehatan',
            'riwayatOperasi' => 'riwayat operasi',
            'pantanganMakanan' => 'pantangan makanan',
            'kemampuanRenang' => 'kemampuan berenang',
            'asuransi' => 'asuransi / BPJS',
            'catatanTambahan' => 'catatan tambahan',
            'riwayatPenyakit' => 'riwayat penyakit',
            'obatRutin' => 'obat rutin',
            'pantangan' => 'pantangan kegiatan',
            'kontakNama' => 'nama kontak darurat',
            'kontakHp' => 'nomor kontak darurat',
            'kontakHubungan' => 'hubungan kontak darurat',
            'setuju' => 'persetujuan penggunaan data kesehatan',
        ];
    }

    protected function messages(): array
    {
        return [
            'kode.exists' => 'Kode pendaftaran tidak ditemukan. Periksa kembali kode yang Anda terima saat mendaftar.',
        ];
    }

    public function simpan(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->kode = strtoupper(trim($this->kode));
        $this->validate();

        $kunci = 'riwayat-kesehatan:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 30)) {
            $this->addError('namaPeserta', 'Terlalu banyak pengiriman dari perangkat ini. Coba lagi nanti.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $adaCatatanKhusus = filled($this->riwayatPenyakit) || filled($this->alergi)
            || filled($this->obatRutin) || filled($this->pantangan)
            || ! empty($this->kondisiKhusus);

        RiwayatKesehatan::create([
            'kode_pendaftaran' => $this->kode,
            'nama_peserta' => $this->namaPeserta,
            'usia' => $this->usia ?: null,
            'jenis_kelamin' => $this->jenisKelamin,
            'tinggi_badan' => $this->tinggiBadan ?: null,
            'berat_badan' => $this->beratBadan ?: null,
            'golongan_darah' => $this->golonganDarah ?: null,
            'kondisi_khusus' => $this->kondisiKhusus ?: null,
            'riwayat_operasi' => $this->riwayatOperasi ?: null,
            'pantangan_makanan' => $this->pantanganMakanan ?: null,
            'kemampuan_renang' => $this->kemampuanRenang,
            'asuransi' => $this->asuransi ?: null,
            'catatan_tambahan' => $this->catatanTambahan ?: null,
            'riwayat_penyakit' => $this->riwayatPenyakit ?: null,
            'alergi' => $this->alergi ?: null,
            'obat_rutin' => $this->obatRutin ?: null,
            'pantangan_kegiatan' => $this->pantangan ?: null,
            'kontak_darurat_nama' => $this->kontakNama,
            'kontak_darurat_hp' => $this->kontakHp,
            'kontak_darurat_hubungan' => $this->kontakHubungan ?: null,
            'setuju_data_kesehatan' => true,
        ]);

        // Isi kesehatannya sengaja TIDAK dirinci di surat: itu data pribadi,
        // dan kotak masuk bukan tempat yang tepat untuk menyimpannya. Yang
        // dikirim cukup penanda bahwa formulirnya sudah masuk.
        KirimPemberitahuan::kirim(
            'Riwayat Kesehatan Peserta Masuk',
            $this->kode,
            [
                'Peserta' => $this->namaPeserta,
                'Kontak darurat' => trim($this->kontakNama.' ('.$this->kontakHubungan.') '.$this->kontakHp),
                'Ada catatan khusus' => $adaCatatanKhusus ? 'Ya — periksa di dashboard' : 'Tidak',
            ],
            'Rincian kesehatannya tidak disertakan di surat ini karena bersifat pribadi. '
                .'Bukalah lewat dashboard Orcha.',
            pelanggan: new SalinanPelanggan(
                email: PendaftaranOpenTrip::where('kode', $this->kode)->value('email'),
                judul: 'Riwayat Kesehatan Sudah Kami Terima',
                tautan: route('riwayat-kesehatan', ['kode' => $this->kode]),
                labelTautan: 'Lihat Peserta yang Belum Mengisi',
                langkah: "Data kesehatan atas nama {$this->namaPeserta} sudah tercatat untuk pendaftaran "
                    ."{$this->kode}.\n\n"
                    .'Peserta lain mengisi formulirnya masing-masing karena riwayat kesehatannya berbeda. '
                    .'Halaman di atas menampilkan siapa saja yang belum mengisi, lengkap dengan tautan '
                    .'pribadi yang tinggal dikirimkan lewat WhatsApp.',
                // Kontak darurat sengaja tidak diulang di salinan pelanggan: yang
                // perlu memegangnya tim kami, bukan kotak masuk pelanggan.
                rincian: ['Peserta' => $this->namaPeserta],
            ),
        );

        $this->namaTerkirim = $this->namaPeserta;
        $this->terkirim = true;

        $this->reset(['namaPeserta', 'usia', 'jenisKelamin', 'tinggiBadan', 'beratBadan', 'golonganDarah', 'kondisiKhusus', 'riwayatPenyakit', 'riwayatOperasi', 'alergi', 'pantanganMakanan', 'obatRutin', 'pantangan', 'kemampuanRenang', 'asuransi', 'catatanTambahan', 'kontakNama', 'kontakHp', 'kontakHubungan', 'setuju']);
    }

    public function isiPesertaLain(): void
    {
        $this->terkirim = false;
        $this->namaTerkirim = null;
        $this->namaBebas = false;
    }

    public function with(): array
    {
        // Identitas pendaftaran & trip langsung tampil begitu kodenya cocok.
        $pendaftaran = filled($this->kode)
            ? PendaftaranOpenTrip::with('paket')->where('kode', strtoupper(trim($this->kode)))->first()
            : null;

        // Daftar peserta beserta status pengisiannya. Inilah yang dipakai
        // ketua rombongan untuk tahu siapa yang masih perlu ditagih — sebelum
        // ini yang tampil cuma angka "2 dari 5", tanpa keterangan siapanya.
        $daftarPeserta = [];

        if ($pendaftaran) {
            $sudah = $pendaftaran->riwayatKesehatan()->pluck('nama_peserta')
                ->map(fn ($nama) => mb_strtolower(trim($nama)))
                ->all();

            $daftarPeserta = collect($pendaftaran->peserta)
                ->pluck('nama')
                ->filter()
                ->map(fn ($nama) => [
                    'nama' => $nama,
                    'sudah' => in_array(mb_strtolower(trim($nama)), $sudah, true),
                ])
                ->values()
                ->all();
        }

        return [
            'daftarKondisi' => config('orcha.kondisi_kesehatan'),
            'daftarRenang' => config('orcha.kemampuan_renang'),
            'pendaftaran' => $pendaftaran,
            'daftarPeserta' => $daftarPeserta,
            'jumlahTerisi' => $pendaftaran
                ? RiwayatKesehatan::where('kode_pendaftaran', $pendaftaran->kode)->count()
                : 0,
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);

    // Tautan pribadi tiap peserta: kode dan namanya sudah terbawa, sehingga
    // yang membukanya tinggal mengisi kondisi kesehatannya sendiri. Ini yang
    // membuat rombongan besar bisa jalan sendiri — ketua rombongan cukup
    // membagikan tautannya, tidak perlu menyalin data kesehatan orang lain.
    $tautanPeserta = fn ($kode, $nama) => route('riwayat-kesehatan', ['kode' => $kode, 'peserta' => $nama]);

    // Tanpa nomor tujuan: WhatsApp yang menanyakan mau dikirim ke siapa.
    $bagikan = fn (string $pesan) => 'https://api.whatsapp.com/send?text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Riwayat Kesehatan Peserta" eyebrow="Formulir Kesehatan"
        subtitle="Diisi satu formulir untuk tiap peserta. Data ini membantu tim kami menyiapkan penanganan bila terjadi sesuatu di perjalanan."
        image="images/HERO/form-kesehatan.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($terkirim)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                                Data {{ $namaTerkirim }} tersimpan
                            </h2>
                            @php $belum = collect($daftarPeserta)->reject(fn ($s) => $s['sudah'])->values(); @endphp

                            <p class="mt-2 text-sm text-slate-600">
                                @if ($belum->isEmpty())
                                    Terima kasih. Seluruh peserta rombongan ini sudah mengisi formulir kesehatannya.
                                @else
                                    Terima kasih. Masih ada {{ $belum->count() }} peserta yang belum mengisi —
                                    tiap orang mengisi sendiri karena riwayat kesehatannya berbeda-beda.
                                @endif
                            </p>

                            {{-- Menagih yang belum mengisi paling mudah dilakukan justru sekarang,
                                 saat orangnya baru selesai dan masih memegang ponselnya. --}}
                            @if ($belum->isNotEmpty())
                                <ul class="max-w-md mx-auto mt-5 space-y-2 text-left">
                                    @foreach ($belum as $satu)
                                        @php
                                            $tautan = $tautanPeserta($kode, $satu['nama']);
                                            $pesan = "Halo {$satu['nama']}, tolong isi data kesehatan untuk trip "
                                                . ($pendaftaran?->nama_paket ?: 'Orcha Journey')
                                                . ". Formulirnya di sini: {$tautan}";
                                        @endphp

                                        <li class="flex items-center gap-2 p-3 rounded-xl bg-orcha-foam/60">
                                            <x-heroicon-o-clock class="w-5 h-5 shrink-0 text-slate-400" />
                                            <span class="flex-1 text-sm font-semibold text-orcha-navy">{{ $satu['nama'] }}</span>
                                            <a href="{{ $bagikan($pesan) }}" target="_blank" rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-full text-orcha-ocean bg-white">
                                                <x-bi-whatsapp class="w-3.5 h-3.5" />
                                                Kirim tautan
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <button type="button" wire:click="isiPesertaLain" class="btn-orcha btn-orcha-primary">
                                    <x-heroicon-o-user-plus class="w-5 h-5" />
                                    Isi Peserta Berikutnya
                                </button>
                                <a href="{{ route('home') }}" class="btn-orcha btn-orcha-outline">Kembali ke Beranda</a>
                            </div>
                        </div>
                    @else
                        <form wire:submit="simpan" class="p-6 space-y-6 card-orcha sm:p-8">
                            <div class="hidden" aria-hidden="true">
                                <label for="rk-situs">Jangan diisi</label>
                                <input id="rk-situs" type="text" wire:model="situs" tabindex="-1"
                                    autocomplete="off">
                            </div>

                            <div>
                                <label for="rk-kode" class="label-orcha">Kode pendaftaran <x-wajib /></label>
                                <input id="rk-kode" type="text" wire:model.live.debounce.500ms="kode" required maxlength="20" placeholder="OT-1408-A7K3"
                                    class="uppercase isian-orcha @error('kode') isian-galat @enderror">
                                @error('kode')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @else
                                    <p class="mt-1 text-xs text-slate-500">Kode yang Anda terima setelah mengisi
                                        formulir pendaftaran open trip.</p>
                                @enderror

                                @if ($pendaftaran)
                                    {{-- Identitas pendaftaran & trip tampil otomatis begitu kode cocok --}}
                                    <div class="p-5 mt-3 rounded-2xl bg-orcha-foam/70">
                                        <p class="flex items-center gap-2 text-xs font-bold tracking-wider uppercase text-orcha-ocean">
                                            <x-heroicon-s-check-badge class="w-4 h-4" />
                                            Kode ditemukan
                                        </p>

                                        <dl class="grid gap-4 mt-4 sm:grid-cols-2">
                                            @foreach ([['Pemesan', $pendaftaran->nama], ['Trip', $pendaftaran->nama_paket ?: 'Belum ditentukan'], ['Tanggal berangkat', $pendaftaran->paket?->jadwal_label ?: $pendaftaran->tanggal_berangkat?->translatedFormat('j F Y') ?: 'Menyusul'], ['Titik jemput', $pendaftaran->titik_jemput ?: 'Dikonfirmasi tim kami'], ['Jumlah peserta', $pendaftaran->jumlah_peserta . ' orang'], ['Formulir kesehatan terisi', $jumlahTerisi . ' dari ' . $pendaftaran->jumlah_peserta]] as [$label, $nilai])
                                                <div>
                                                    <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                        {{ $label }}</dt>
                                                    <dd class="font-bold text-orcha-navy">{{ $nilai }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>

                                        {{-- ============ DAFTAR PESERTA ============
                                             Sebelumnya yang tampil hanya angka "2 dari 5", jadi ketua rombongan
                                             tahu ada yang kurang tetapi tidak tahu siapa. Di sini namanya
                                             disebut satu per satu berikut tombol untuk menagihnya. --}}
                                        @if (count($daftarPeserta) > 1)
                                            <div class="pt-4 mt-4 border-t border-white/70">
                                                <p class="text-xs font-bold tracking-wider uppercase text-orcha-ocean">
                                                    Peserta rombongan ini
                                                </p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    Tiap peserta mengisi formulirnya sendiri karena riwayat
                                                    kesehatannya berbeda-beda. Kirimkan tautannya lewat WhatsApp —
                                                    yang membuka langsung terisi kode dan namanya.
                                                </p>

                                                <ul class="mt-3 space-y-2">
                                                    @foreach ($daftarPeserta as $satu)
                                                        @php
                                                            $tautan = $tautanPeserta($pendaftaran->kode, $satu['nama']);
                                                            $pesan =
                                                                "Halo {$satu['nama']}, tolong isi data kesehatan untuk trip " .
                                                                ($pendaftaran->nama_paket ?: 'Orcha Journey') .
                                                                ". Formulirnya di sini: {$tautan}";
                                                        @endphp

                                                        <li
                                                            class="flex flex-wrap items-center gap-2 p-3 bg-white sm:flex-nowrap rounded-xl">
                                                            @if ($satu['sudah'])
                                                                <x-heroicon-s-check-circle
                                                                    class="w-5 h-5 shrink-0 text-orcha-sky" />
                                                            @else
                                                                <x-heroicon-o-clock class="w-5 h-5 shrink-0 text-slate-300" />
                                                            @endif

                                                            <span
                                                                class="flex-1 text-sm font-semibold text-orcha-navy">{{ $satu['nama'] }}</span>

                                                            @if ($satu['sudah'])
                                                                <span
                                                                    class="px-2.5 py-1 text-[0.68rem] font-bold tracking-wide uppercase rounded-full text-orcha-ocean bg-orcha-foam">
                                                                    Sudah diisi
                                                                </span>
                                                            @else
                                                                <a href="{{ $bagikan($pesan) }}" target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-full text-orcha-ocean bg-orcha-sky/15 hover:bg-orcha-sky/25">
                                                                    <x-bi-whatsapp class="w-3.5 h-3.5" />
                                                                    Kirim tautan
                                                                </a>

                                                                {{-- Disalin lewat atribut onclick, bukan berkas skrip:
                                                                     tata letak tamu tidak menyediakan tumpukan skrip. --}}
                                                                <button type="button" title="Salin tautannya"
                                                                    onclick="navigator.clipboard.writeText('{{ $tautan }}');this.firstElementChild.classList.add('text-orcha-sky');this.title='Tautan tersalin'"
                                                                    class="p-1.5 rounded-full text-slate-400 hover:bg-orcha-foam">
                                                                    <x-heroicon-o-link class="w-4 h-4" />
                                                                </button>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($jumlahTerisi >= $pendaftaran->jumlah_peserta)
                                            <p class="pt-4 mt-4 text-sm border-t border-white/70 text-slate-600">
                                                Semua peserta sudah mengisi. Anda tetap bisa menambah bila jumlah
                                                pesertanya bertambah.
                                            </p>
                                        @endif
                                    </div>
                                @elseif (filled($kode))
                                    <p class="mt-3 text-sm text-slate-500">Kode belum cocok. Periksa kembali huruf dan
                                        angkanya.</p>
                                @endif
                            </div>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Data Peserta</h2>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <label for="rk-nama" class="label-orcha">Nama peserta <x-wajib /></label>

                                    {{-- Dipilih dari daftar, bukan diketik ulang. Nama yang salah ketik
                                         membuat penanda "sudah diisi" meleset, dan rombongan besar jadi
                                         tidak pernah terlihat lengkap padahal semua sudah mengisi. --}}
                                    @if (count($daftarPeserta) && ! $namaBebas)
                                        @php
                                            $dipilihSudah = collect($daftarPeserta)->firstWhere('nama', $namaPeserta)['sudah'] ?? false;
                                        @endphp

                                        <select id="rk-nama" wire:model.live="namaPeserta" required
                                            class="isian-orcha @error('namaPeserta') isian-galat @enderror">
                                            <option value="">— Pilih peserta —</option>
                                            @foreach ($daftarPeserta as $satu)
                                                <option value="{{ $satu['nama'] }}">
                                                    {{ $satu['nama'] }}{{ $satu['sudah'] ? ' — sudah diisi' : '' }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('namaPeserta')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror

                                        {{-- Sudah pernah masuk tetap boleh dikirim ulang: kondisi kesehatan
                                             bisa berubah, dan pengisi pertama kadang keliru orang. --}}
                                        @if ($dipilihSudah)
                                            <p class="mt-1 text-xs font-semibold text-amber-600">
                                                Data atas nama ini sudah pernah masuk. Mengirim lagi menambah
                                                catatan baru, bukan mengganti yang lama.
                                            </p>
                                        @endif

                                        <button type="button" wire:click="$set('namaBebas', true)"
                                            class="mt-1 text-xs font-semibold underline text-orcha-ocean underline-offset-2">
                                            Nama saya tidak ada di daftar
                                        </button>
                                    @else
                                        <input id="rk-nama" type="text" wire:model="namaPeserta" required minlength="3" maxlength="120"
                                            placeholder="Nama sesuai identitas"
                                            class="isian-orcha @error('namaPeserta') isian-galat @enderror">
                                        @error('namaPeserta')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror

                                        @if (count($daftarPeserta))
                                            <button type="button" wire:click="$set('namaBebas', false)"
                                                class="mt-1 text-xs font-semibold underline text-orcha-ocean underline-offset-2">
                                                Kembali memilih dari daftar peserta
                                            </button>
                                        @endif
                                    @endif
                                </div>
                                <div>
                                    <label for="rk-usia" class="label-orcha">Usia <x-wajib /></label>
                                    <input id="rk-usia" type="number" min="1" max="110" required
                                        wire:model="usia" placeholder="Tahun"
                                        class="isian-orcha @error('usia') isian-galat @enderror">
                                    @error('usia')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-4">
                                <div>
                                    <label for="rk-jk" class="label-orcha">Jenis kelamin <x-wajib /></label>
                                    <select id="rk-jk" wire:model="jenisKelamin" required
                                        class="isian-orcha @error('jenisKelamin') isian-galat @enderror">
                                        <option value="">— Pilih —</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select>
                                    @error('jenisKelamin')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-goldar" class="label-orcha">Golongan darah</label>
                                    <select id="rk-goldar" wire:model="golonganDarah"
                                        class="isian-orcha @error('golonganDarah') isian-galat @enderror">
                                        <option value="">Tidak tahu</option>
                                        @foreach (['A', 'B', 'AB', 'O'] as $gol)
                                            <option value="{{ $gol }}">{{ $gol }}</option>
                                        @endforeach
                                    </select>
                                    @error('golonganDarah')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-tinggi" class="label-orcha">Tinggi badan</label>
                                    <input id="rk-tinggi" type="number" min="50" max="250" wire:model="tinggiBadan"
                                        placeholder="cm" class="isian-orcha @error('tinggiBadan') isian-galat @enderror">
                                    @error('tinggiBadan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-berat" class="label-orcha">Berat badan</label>
                                    <input id="rk-berat" type="number" min="10" max="250" wire:model="beratBadan"
                                        placeholder="kg" class="isian-orcha @error('beratBadan') isian-galat @enderror">
                                    @error('beratBadan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Kondisi Kesehatan</h2>
                                <p class="mt-1 text-sm text-slate-500">Kosongkan bila tidak ada. Tulis apa adanya —
                                    tidak memengaruhi diterima atau tidaknya pendaftaran.</p>
                            </div>

                            <div>
                                <span class="label-orcha">Kondisi yang pernah / sedang dialami</span>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach ($daftarKondisi as $kunci => $label)
                                        <label class="pilihan-centang">
                                            <input type="checkbox" class="sr-only" value="{{ $kunci }}"
                                                wire:model="kondisiKhusus">
                                            <span class="kotak" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                    stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M4 10.5 8 14.5 16 5.5" />
                                                </svg>
                                            </span>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Centang yang sesuai. Kosongkan bila tidak ada.
                                </p>
                                @error('kondisiKhusus')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-penyakit" class="label-orcha">Riwayat penyakit lain</label>
                                <textarea id="rk-penyakit" rows="3" wire:model="riwayatPenyakit"
                                    placeholder="Contoh: asma, maag kronis, hipertensi, vertigo, epilepsi."
                                    class="isian-orcha @error('riwayatPenyakit') isian-galat @enderror"></textarea>
                                @error('riwayatPenyakit')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-alergi" class="label-orcha">Alergi</label>
                                <textarea id="rk-alergi" rows="2" wire:model="alergi"
                                    placeholder="Contoh: alergi seafood, alergi obat tertentu, alergi debu."
                                    class="isian-orcha @error('alergi') isian-galat @enderror"></textarea>
                                @error('alergi')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-obat" class="label-orcha">Obat yang rutin diminum</label>
                                <textarea id="rk-obat" rows="2" wire:model="obatRutin"
                                    placeholder="Nama obat dan jadwal minumnya, bila ada."
                                    class="isian-orcha @error('obatRutin') isian-galat @enderror"></textarea>
                                @error('obatRutin')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-operasi" class="label-orcha">Riwayat operasi / rawat inap 2 tahun
                                    terakhir</label>
                                <textarea id="rk-operasi" rows="2" wire:model="riwayatOperasi" maxlength="1000"
                                    placeholder="Sebutkan jenis dan perkiraan waktunya, bila ada."
                                    class="isian-orcha @error('riwayatOperasi') isian-galat @enderror"></textarea>
                                @error('riwayatOperasi')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-makanan" class="label-orcha">Pantangan makanan</label>
                                <input id="rk-makanan" type="text" wire:model="pantanganMakanan" maxlength="500"
                                    placeholder="Contoh: tidak makan seafood, vegetarian, tanpa pedas"
                                    class="isian-orcha @error('pantanganMakanan') isian-galat @enderror">
                                @error('pantanganMakanan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="rk-pantangan" class="label-orcha">Kegiatan yang sebaiknya dihindari</label>
                                <input id="rk-pantangan" type="text" wire:model="pantangan"
                                    placeholder="Contoh: tidak boleh berenang, tidak kuat mendaki"
                                    class="isian-orcha @error('pantangan') isian-galat @enderror">
                                @error('pantangan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="rk-renang" class="label-orcha">Kemampuan berenang <x-wajib /></label>
                                    <select id="rk-renang" wire:model="kemampuanRenang" required
                                        class="isian-orcha @error('kemampuanRenang') isian-galat @enderror">
                                        <option value="">— Pilih —</option>
                                        @foreach ($daftarRenang as $kunci => $label)
                                            <option value="{{ $kunci }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">Penting untuk kegiatan snorkeling dan
                                        penyeberangan laut.</p>
                                    @error('kemampuanRenang')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-asuransi" class="label-orcha">Asuransi / BPJS</label>
                                    <input id="rk-asuransi" type="text" wire:model="asuransi" maxlength="120"
                                        placeholder="Nama penjamin dan nomor kartu, bila ada"
                                        class="isian-orcha @error('asuransi') isian-galat @enderror">
                                    @error('asuransi')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Kontak Darurat</h2>
                                <p class="mt-1 text-sm text-slate-500">Orang yang kami hubungi bila terjadi sesuatu
                                    dengan peserta ini.</p>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <label for="rk-knama" class="label-orcha">Nama <x-wajib /></label>
                                    <input id="rk-knama" type="text" wire:model="kontakNama" required minlength="3" maxlength="120"
                                        placeholder="Nama kontak darurat"
                                        class="isian-orcha @error('kontakNama') isian-galat @enderror">
                                    @error('kontakNama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-khp" class="label-orcha">Nomor HP <x-wajib /></label>
                                    <input id="rk-khp" type="tel" wire:model="kontakHp" required minlength="8" maxlength="30"
                                        placeholder="08xxxxxxxxxx"
                                        class="isian-orcha @error('kontakHp') isian-galat @enderror">
                                    @error('kontakHp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="rk-khub" class="label-orcha">Hubungan <x-wajib /></label>
                                    <input id="rk-khub" type="text" wire:model="kontakHubungan" required maxlength="60"
                                        placeholder="Orang tua / pasangan"
                                        class="isian-orcha @error('kontakHubungan') isian-galat @enderror">
                                    @error('kontakHubungan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="rk-catatan" class="label-orcha">Catatan tambahan untuk tim kami</label>
                                <textarea id="rk-catatan" rows="3" wire:model="catatanTambahan" maxlength="1000"
                                    placeholder="Hal lain yang perlu kami tahu soal kondisi peserta ini."
                                    class="isian-orcha @error('catatanTambahan') isian-galat @enderror"></textarea>
                                @error('catatanTambahan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <label class="flex items-start gap-3 text-sm cursor-pointer text-slate-600">
                                <input type="checkbox" wire:model="setuju" required
                                    class="mt-0.5 w-5 h-5 rounded border-orcha-foam text-orcha-ocean focus:ring-orcha-sky">
                                <span>
                                    Saya menyatakan data di atas benar dan setuju data kesehatan ini dipakai
                                    Orcha Journey hanya untuk keperluan keselamatan selama perjalanan, sesuai
                                    <a href="{{ route('kebijakan-privasi') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">kebijakan privasi</a>.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">Kolom bertanda <x-wajib /> wajib diisi.</p>

                            <button type="submit" class="w-full btn-orcha btn-orcha-primary"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="simpan">Simpan Data Kesehatan</span>
                                <span wire:loading wire:target="simpan">Menyimpan…</span>
                            </button>
                        </form>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    {{-- Ikut menggulung bersama halaman, berhenti di atas layar --}}
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="flex items-center gap-2 text-lg font-bold font-heading text-orcha-navy">
                                <x-heroicon-o-lock-closed class="w-5 h-5 text-orcha-wave" />
                                Data ini dijaga
                            </h2>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                                @foreach (['Hanya dibuka tim yang menangani perjalanan Anda.', 'Tidak pernah ditayangkan di halaman publik.', 'Tidak dikirim lewat WhatsApp maupun grup peserta.', 'Dapat dihapus atas permintaan Anda setelah perjalanan selesai.'] as $poin)
                                    <li class="flex items-start gap-2">
                                        <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-orcha-sky" />
                                        <span>{{ $poin }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Belum punya kode?</h2>
                            <p class="mt-2 text-sm text-slate-600">Kode pendaftaran muncul setelah Anda mengisi formulir
                                pendaftaran open trip.</p>
                            <a href="{{ route('pendaftaran-open-trip') }}"
                                class="w-full mt-4 btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                                Daftar Open Trip Dulu
                            </a>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">Kode Anda hilang atau ada kondisi kesehatan yang perlu
                                dibicarakan langsung?</p>
                            <a href="{{ $wa('Halo Orcha Journey, saya perlu bantuan soal formulir riwayat kesehatan open trip.') }}"
                                target="_blank" rel="noopener noreferrer"
                                class="w-full mt-3 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                <x-bi-whatsapp class="w-4 h-4" />
                                Hubungi Tim Kami
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
