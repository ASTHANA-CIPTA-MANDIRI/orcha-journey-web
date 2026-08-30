<?php

use App\Models\OpenTrip\Pembatalan;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use App\Support\SalinanPelanggan;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\NomorTelepon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pengajuan Pembatalan — Orcha Journey')] class extends Component {
    public string $kode = '';

    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $alasan = '';

    public string $penjelasan = '';

    public $jumlahDibatalkan = 1;

    public string $bank = '';

    public string $nomorRekening = '';

    public string $atasNama = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public bool $terkirim = false;

    public function mount(): void
    {
        $this->kode = strtoupper((string) request()->query('kode', ''));

        // Tautan dari email dan WhatsApp membawa kodenya sendiri. Tanpa ini,
        // pengisian otomatis baru jalan kalau kodenya diketik ulang — padahal
        // justru orang yang datang lewat tautan yang paling tidak perlu
        // mengetik apa pun.
        $this->updatedKode();
    }

    protected function rules(): array
    {
        return [
            // Kodenya boleh menunjuk open trip maupun sewa kendaraan. Dulu
            // hanya tabel pendaftaran yang diperiksa, jadi penyewa kendaraan
            // yang ingin membatalkan selalu ditolak "kode tidak ditemukan"
            // padahal kodenya benar.
            'kode' => ['required', 'string', 'max:20', fn ($atribut, $nilai, $gagal) => Pembatalan::milik($nilai)
                ? null
                : $gagal('Kode pesanan tidak ditemukan. Periksa kembali kode yang Anda terima saat memesan.')],
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            'email' => 'nullable|email|max:150',
            'alasan' => 'required|in:' . implode(',', array_keys(config('orcha.alasan_pembatalan'))),
            'penjelasan' => 'nullable|string|max:1000',
            'jumlahDibatalkan' => 'required|integer|min:1|max:60',
            'bank' => 'required|string|max:60',
            'nomorRekening' => 'required|string|min:6|max:40|regex:/^[0-9\-\s]+$/',
            'atasNama' => 'required|string|min:3|max:120',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'kode' => 'kode pesanan',
            'jumlahDibatalkan' => 'jumlah peserta yang dibatalkan',
            'nomorRekening' => 'nomor rekening',
            'atasNama' => 'nama pemilik rekening',
            'setuju' => 'persetujuan kebijakan pembatalan',
        ];
    }

    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    /**
     * Begitu kodenya cocok, isian yang sudah kami ketahui diisikan sendiri.
     *
     * Orang yang sedang membatalkan biasanya sedang kesal atau terburu-buru —
     * menyuruhnya mengetik ulang nama, nomor, dan email yang sudah ada di
     * pesanannya hanya menambah peluang salah ketik, dan nomor yang salah
     * ketik berarti perhitungan pengembaliannya tidak sampai ke siapa pun.
     *
     * Yang terisi tetap bisa diubah: rekening pengembalian kadang memang atas
     * nama orang lain, dan nomor WhatsApp bisa saja sudah berganti.
     */
    public function updatedKode(): void
    {
        $pesanan = Pembatalan::milik($this->kode);

        if (! $pesanan) {
            return;
        }

        $this->nama = $this->nama ?: (string) $pesanan->nama;
        $this->whatsapp = $this->whatsapp ?: NomorTelepon::rapi((string) $pesanan->whatsapp);
        $this->email = $this->email ?: (string) $pesanan->email;

        // Pemesan dan pemilik rekening hampir selalu orang yang sama; kalau
        // tidak, tinggal diganti.
        $this->atasNama = $this->atasNama ?: (string) $pesanan->nama;

        // Sewa kendaraan dibatalkan utuh — tidak ada "dua dari lima peserta"
        // pada satu unit mobil. Isiannya pun disembunyikan di layar.
        $this->jumlahDibatalkan = $pesanan instanceof PenyewaanKendaraan
            ? 1
            : ($pesanan->jumlah_peserta ?: 1);
    }

    public function ajukan(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->kode = strtoupper(trim($this->kode));
        $this->validate();

        $kunci = 'pembatalan:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('nama', 'Terlalu banyak pengajuan dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $pembatalan = Pembatalan::create([
            'kode_pendaftaran' => $this->kode,
            'nama_pemohon' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email ?: null,
            'alasan' => $this->alasan,
            'penjelasan' => $this->penjelasan ?: null,
            'jumlah_dibatalkan' => $this->jumlahDibatalkan,
            'bank' => $this->bank,
            'nomor_rekening' => $this->nomorRekening,
            'atas_nama_rekening' => $this->atasNama,
        ]);

        $pesanan = $pembatalan->pesanan();
        $sewa = $pesanan instanceof PenyewaanKendaraan;

        $rincian = array_filter([
            'Jenis pesanan' => $sewa ? 'Sewa Kendaraan' : 'Open Trip',
            'Yang dibatalkan' => $sewa
                ? ($pesanan->nama_kendaraan ?: 'Unit kendaraan')
                : ($pesanan?->nama_paket ?: 'Open Trip'),
            'Pemohon' => $pembatalan->nama_pemohon,
            'WhatsApp' => $pembatalan->whatsapp,
            'Email' => $pembatalan->email,
            'Alasan' => $pembatalan->alasan_label,
            // Jumlah peserta hanya bermakna pada open trip; pada sewa kendaraan
            // yang dibatalkan adalah unitnya, dan "1 orang" hanya membingungkan.
            'Peserta dibatalkan' => $sewa ? null : $pembatalan->jumlah_dibatalkan.' orang',
            'Rekening pengembalian' => $pembatalan->bank.' · '.$pembatalan->nomor_rekening
                .' a.n. '.$pembatalan->atas_nama_rekening,
        ]);

        // Tanda terima pengajuan — bukan tanda pengembalian dana. Capnya
        // "Diajukan" supaya tidak terbaca sebagai janji dana sudah dikirim.
        $tandaTerima = BerkasKwitansi::buat(
            'Tanda Terima Pengajuan Pembatalan',
            $pembatalan->kode_pendaftaran,
            $rincian,
            $pembatalan->penjelasan,
            null,
            null,
            'Diajukan',
        );

        KirimPemberitahuan::kirim(
            'Pengajuan Pembatalan',
            $pembatalan->kode_pendaftaran,
            $rincian,
            $pembatalan->penjelasan,
            [],
            $tandaTerima
                ? [BerkasKwitansi::namaBerkas('pengajuan-pembatalan', $pembatalan->kode_pendaftaran) => $tandaTerima]
                : [],
            pelanggan: new SalinanPelanggan(
                email: $pembatalan->email,
                judul: 'Pengajuan Pembatalan Sudah Kami Terima',
                langkah: 'Pengajuan Anda tercatat. Tim kami menghitung besaran pengembalian sesuai kebijakan '
                    ."yang berlaku, lalu mengirim rinciannya lewat WhatsApp untuk Anda setujui.\n\n"
                    .'Dana dikirim paling lambat '.config('orcha.pengembalian.proses_hari_kerja').' hari kerja '
                    .'setelah perhitungan itu disetujui. Tanda terima terlampir adalah bukti pengajuan — bukan '
                    .'tanda dana sudah dikirim.',
            ),
        );

        $this->terkirim = true;
    }

    public function with(): array
    {
        $pesanan = Pembatalan::milik($this->kode);
        $sewa = $pesanan instanceof PenyewaanKendaraan;

        // Dua jenis pesanan diringkas jadi satu bentuk yang sama, supaya
        // tampilannya tidak bercabang dua di layar. Yang berbeda hanya isi
        // barisnya — unit dan jadwal ambil untuk sewa, trip dan tanggal
        // berangkat untuk open trip.
        $ringkas = match (true) {
            ! $pesanan => [],
            $sewa => [
                ['Pemesan', $pesanan->nama],
                ['Kendaraan', $pesanan->nama_kendaraan ?: 'Belum ditentukan'],
                ['Mulai sewa', $pesanan->jadwal_mulai?->translatedFormat('j F Y, H:i') ?: 'Menyusul'],
                ['Lama sewa', $pesanan->durasi_label],
            ],
            default => [
                ['Pemesan', $pesanan->nama],
                ['Trip', $pesanan->nama_paket ?: 'Belum ditentukan'],
                ['Tanggal berangkat', $pesanan->paket?->jadwal_label
                    ?: $pesanan->tanggal_berangkat?->translatedFormat('j F Y') ?: 'Menyusul'],
                ['Jumlah peserta', $pesanan->jumlah_peserta.' orang'],
            ],
        };

        return [
            'pesanan' => $pesanan,
            'sewa' => $sewa,
            'ringkas' => $ringkas,
            // "Kalau saya batal sekarang, kembali berapa?" adalah pertanyaan
            // pertama pada tiap pembatalan. Dijawab di layar ini, bukan sehari
            // kemudian lewat WhatsApp.
            'perkiraan' => \App\Support\PerkiraanPotongan::untuk($pesanan),
            'daftarAlasan' => config('orcha.alasan_pembatalan'),
            // Tangga yang ditampilkan mengikuti jenis pesanannya. Menampilkan
            // tangga open trip kepada penyewa kendaraan bukan sekadar keliru
            // di layar — orang membuat keputusan membatalkan berdasarkan angka
            // yang ia baca di sini.
            'tanggaPengembalian' => $sewa
                ? config('orcha.pengembalian.tangga_sewa')
                : config('orcha.pengembalian.tangga'),
            // Dua aturan pengikatnya tampil untuk kedua jenis pesanan. Yang
            // paling perlu dibaca justru pelanggan yang sudah melunasi: sejak
            // aturan ini, melunasi lebih awal tidak lagi menghapus potongan.
            'aturanDasar' => config('orcha.pengembalian.aturan_dasar', []),
            'catatanSewa' => $sewa ? config('orcha.pengembalian.catatan_sewa', []) : [],
            'prosesHari' => config('orcha.pengembalian.proses_hari_kerja'),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Pengajuan Pembatalan" eyebrow="Formulir Pembatalan"
        subtitle="Ajukan pembatalan open trip maupun sewa kendaraan di sini. Besaran pengembalian dana mengikuti jarak waktu pembatalan terhadap tanggal keberangkatan atau tanggal mulai sewa."
        image="images/HERO/pengajuan-pembatalan.webp" posisi="center 100%" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($terkirim)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">Pengajuan pembatalan
                                diterima</h2>
                            <p class="mt-2 text-sm text-slate-600">
                                Tim kami menghitung besaran pengembalian sesuai kebijakan, lalu mengirimkan rinciannya
                                lewat WhatsApp untuk Anda setujui. Dana dikirim paling lambat {{ $prosesHari }} hari
                                kerja setelah perhitungan disetujui.
                            </p>

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <a href="{{ $wa("Halo Orcha Journey, saya baru mengajukan pembatalan untuk kode $kode.") }}"
                                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Konfirmasi via WhatsApp
                                </a>
                                <a href="{{ route('kebijakan-pengembalian') }}" class="btn-orcha btn-orcha-outline">
                                    Baca Kebijakan Pengembalian
                                </a>
                            </div>
                        </div>
                    @else
                        <form wire:submit="ajukan" class="p-6 space-y-6 card-orcha sm:p-8">
                            <div class="hidden" aria-hidden="true">
                                <label for="pb-situs">Jangan diisi</label>
                                <input id="pb-situs" type="text" wire:model="situs" tabindex="-1" autocomplete="off">
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Data Pemesanan</h2>
                                <p class="mt-1 text-sm text-slate-500">Berlaku untuk open trip maupun sewa kendaraan.
                                    Pembatalan hanya bisa diajukan oleh pemesan yang terdaftar.</p>
                            </div>

                            <div>
                                <label for="pb-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
                                <input id="pb-kode" type="text" wire:model.live.debounce.500ms="kode" required
                                    maxlength="20" placeholder="OT-1408-A7K3 atau SK-1608-ZGAN"
                                    class="uppercase isian-orcha @error('kode') isian-galat @enderror">
                                @error('kode')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-slate-500">
                                    Kode ada di email dan tanda terima yang Anda terima saat memesan — diawali
                                    <strong>OT-</strong> untuk open trip, <strong>SK-</strong> untuk sewa kendaraan.
                                </p>

                                @if ($pesanan)
                                    <div class="p-5 mt-3 rounded-2xl bg-orcha-foam/70">
                                        <p
                                            class="flex items-center gap-2 text-xs font-bold tracking-wider uppercase text-orcha-ocean">
                                            <x-heroicon-s-check-badge class="w-4 h-4" />
                                            {{ $sewa ? 'Pesanan sewa ditemukan' : 'Pemesanan ditemukan' }}
                                        </p>
                                        <dl class="grid gap-4 mt-4 sm:grid-cols-2">
                                            @foreach ($ringkas as [$label, $nilai])
                                                <div>
                                                    <dt
                                                        class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                                        {{ $label }}</dt>
                                                    <dd class="font-bold text-orcha-navy">{{ $nilai }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                        {{-- Isian di bawah sudah terisi dari pesanan ini. Disebutkan
                                             supaya pengguna tahu itu bukan tebakan sistem, dan tahu
                                             bahwa ia boleh mengubahnya. --}}
                                        <p class="mt-4 text-xs text-slate-500">
                                            Data pemohon di bawah kami isikan dari pesanan ini. Silakan ubah bila
                                            ada yang sudah berganti.
                                        </p>
                                    </div>

                                    {{-- Perkiraan pengembalian.

                                         Angka ini yang dicari orang sebelum memutuskan, dan selama
                                         ini baru diketahuinya sehari kemudian lewat WhatsApp. Disebut
                                         perkiraan apa adanya: tim masih memeriksa hal yang tidak
                                         diketahui sistem, misalnya biaya yang sudah terlanjur
                                         dibayarkan ke pihak ketiga. --}}
                                    @if ($perkiraan)
                                        <div class="p-5 mt-3 border rounded-2xl border-orcha-foam">
                                            <p class="text-xs font-bold tracking-wider uppercase text-slate-500">
                                                Perkiraan bila dibatalkan hari ini
                                            </p>

                                            <div class="grid gap-4 mt-3 sm:grid-cols-3">
                                                @foreach ([
                                                    ['Sudah dibayar', $perkiraan['dibayar_teks'], 'text-orcha-navy'],
                                                    ['Potongan (' . $perkiraan['persen'] . '%)', '− ' . $perkiraan['potongan_teks'], 'text-rose-600'],
                                                    ['Perkiraan kembali', $perkiraan['kembali_teks'], 'text-emerald-700'],
                                                ] as [$label, $nilai, $warna])
                                                    <div>
                                                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                                        <dd class="text-lg font-bold {{ $warna }}">{{ $nilai }}</dd>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <p class="mt-3 text-xs text-slate-500">
                                                Dasarnya: <strong>{{ $perkiraan['batas'] }}</strong> —
                                                potongan {{ $perkiraan['persen'] }}% dari total biaya
                                                {{ $perkiraan['total_teks'] }}.
                                                @if ($perkiraan['potongan'] >= $perkiraan['dibayar'] && $perkiraan['persen'] > 0)
                                                    Potongan dibatasi sebesar pembayaran Anda; sisanya tidak ditagihkan.
                                                @endif
                                            </p>

                                            <p class="mt-2 text-xs text-slate-500">
                                                Angka ini perkiraan. Tim kami memeriksa dulu biaya yang sudah
                                                terlanjur dikeluarkan, lalu mengirim perhitungan resminya untuk
                                                Anda setujui.
                                            </p>
                                        </div>
                                    @endif
                                @elseif (filled($kode))
                                    <p class="mt-3 text-sm text-slate-500">Kode belum cocok. Periksa kembali huruf dan
                                        angkanya.</p>
                                @endif
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="pb-nama" class="label-orcha">Nama pemohon <x-wajib /></label>
                                    <input id="pb-nama" type="text" wire:model="nama" required minlength="3"
                                        maxlength="120" placeholder="Nama sesuai pendaftaran"
                                        class="isian-orcha @error('nama') isian-galat @enderror">
                                    @error('nama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="pb-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                    <input id="pb-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="0812-3456-7890"
                                        class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
                                    @error('whatsapp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="pb-email" class="label-orcha">Email <span
                                            class="font-normal text-slate-400">(opsional)</span></label>
                                    <input id="pb-email" type="email" wire:model="email" maxlength="150"
                                        placeholder="nama@email.com"
                                        class="isian-orcha @error('email') isian-galat @enderror">
                                    @error('email')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                {{-- Satu unit kendaraan tidak bisa dibatalkan sebagian —
                                     tidak ada "dua dari lima peserta" pada satu mobil. Isiannya
                                     disembunyikan supaya tidak ada yang perlu dijawab, bukan
                                     ditampilkan terkunci. --}}
                                @unless ($sewa)
                                    <div>
                                        <label for="pb-jumlah" class="label-orcha">Jumlah peserta yang dibatalkan
                                            <x-wajib /></label>
                                        <input id="pb-jumlah" type="number" min="1" max="60" required
                                            wire:model="jumlahDibatalkan"
                                            class="isian-orcha @error('jumlahDibatalkan') isian-galat @enderror">
                                        @error('jumlahDibatalkan')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-2 text-xs text-slate-500">
                                            Terisi sesuai jumlah peserta pada pesanan Anda. Kurangi bila hanya
                                            sebagian yang batal ikut.
                                        </p>
                                    </div>
                                @endunless
                            </div>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Alasan Pembatalan</h2>
                            </div>

                            <div>
                                <label for="pb-alasan" class="label-orcha">Alasan <x-wajib /></label>
                                <select id="pb-alasan" wire:model="alasan" required
                                    class="isian-orcha @error('alasan') isian-galat @enderror">
                                    <option value="">— Pilih alasan —</option>
                                    @foreach ($daftarAlasan as $kunci => $label)
                                        <option value="{{ $kunci }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('alasan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pb-penjelasan" class="label-orcha">Penjelasan singkat <span
                                        class="font-normal text-slate-400">(opsional)</span></label>
                                <textarea id="pb-penjelasan" rows="4" wire:model="penjelasan" maxlength="1000"
                                    placeholder="Ceritakan situasinya agar tim kami bisa mempertimbangkan dengan tepat."
                                    class="isian-orcha @error('penjelasan') isian-galat @enderror"></textarea>
                                @error('penjelasan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <hr class="border-orcha-foam">

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Rekening Pengembalian</h2>

                                <x-peringatan-pembayaran ringkas class="mt-3" />
                                <p class="mt-1 text-sm text-slate-500">Dana hanya dikirim ke rekening atas nama pemesan
                                    yang melakukan pembayaran.</p>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <label for="pb-bank" class="label-orcha">Bank <x-wajib /></label>
                                    <input id="pb-bank" type="text" wire:model="bank" required maxlength="60"
                                        placeholder="Contoh: BCA"
                                        class="isian-orcha @error('bank') isian-galat @enderror">
                                    @error('bank')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="pb-rek" class="label-orcha">Nomor rekening <x-wajib /></label>
                                    <input id="pb-rek" type="text" wire:model="nomorRekening" required minlength="6"
                                        maxlength="40" placeholder="0000000000"
                                        class="isian-orcha @error('nomorRekening') isian-galat @enderror">
                                    @error('nomorRekening')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="pb-an" class="label-orcha">Atas nama <x-wajib /></label>
                                    <input id="pb-an" type="text" wire:model="atasNama" required minlength="3"
                                        maxlength="120" placeholder="Nama pemilik rekening"
                                        class="isian-orcha @error('atasNama') isian-galat @enderror">
                                    @error('atasNama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <label class="flex items-start gap-3 text-sm cursor-pointer text-slate-600">
                                <input type="checkbox" wire:model="setuju" required
                                    class="mt-0.5 w-5 h-5 rounded border-orcha-foam text-orcha-ocean focus:ring-orcha-sky">
                                <span>
                                    <x-wajib /> Saya memahami bahwa besaran pengembalian mengikuti
                                    <a href="{{ route('kebijakan-pengembalian') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">Kebijakan Pembatalan
                                        &amp; Pengembalian Dana</a>, dan perhitungannya dihitung dari tanggal
                                    pengajuan ini diterima.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">Kolom bertanda <x-wajib /> wajib diisi.</p>

                            <button type="submit" class="w-full btn-orcha btn-orcha-primary"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="ajukan">Ajukan Pembatalan</span>
                                <span wire:loading wire:target="ajukan">Mengirim…</span>
                            </button>
                        </form>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    {{-- Ikut menggulung bersama halaman, berhenti di atas layar --}}
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Besaran pengembalian</h2>
                            <div class="mt-4 space-y-3">
                                {{-- Yang ditebalkan adalah POTONGANNYA, bukan kalimat
                                     "pembayaran dikurangi potongan". Angka itu yang dicari
                                     mata orang yang sedang menimbang batal atau tidak;
                                     kalimatnya sendiri tidak memberi tahu apa-apa. --}}
                                @foreach ($tanggaPengembalian as $baris)
                                    <div class="p-3 rounded-2xl bg-orcha-foam/60">
                                        <p class="text-xs text-slate-500">{{ $baris['batas'] }}</p>
                                        <p class="text-sm font-bold text-orcha-navy">
                                            Potongan {{ strtolower($baris['potongan']) }}
                                        </p>
                                        <p class="text-xs text-slate-500">Kembali: {{ $baris['kembali'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Dua aturan pengikatnya ikut tampil di sini, bukan hanya di
                                 halaman kebijakan. Orang membuat keputusan membatalkan di
                                 layar ini, dan yang paling perlu membacanya adalah
                                 pelanggan yang sudah melunasi. --}}
                            <ul class="mt-4 space-y-2 text-xs text-slate-600">
                                @foreach ($aturanDasar as $aturan)
                                    <li class="flex gap-2">
                                        <span class="font-bold text-orcha-ocean">•</span>
                                        <span>{{ $aturan }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($catatanSewa)
                                <ul class="mt-3 space-y-2 text-xs text-slate-500">
                                    @foreach ($catatanSewa as $catatan)
                                        <li class="flex gap-2">
                                            <span class="text-orcha-ocean">•</span>
                                            <span>{{ $catatan }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <p class="mt-4 text-xs text-slate-500">
                                Dihitung dari tanggal pengajuan diterima, bukan tanggal pengajuan lisan.
                                {{ $sewa ? 'Jaraknya dihitung ke waktu mulai sewa.' : '' }}
                            </p>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Yang terjadi setelah ini</h2>
                            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                                @foreach (['Tim kami memeriksa data pemesanan Anda.', 'Kami kirim perhitungan pengembalian lewat WhatsApp.', 'Setelah Anda setujui, dana diproses ke rekening yang dicantumkan.', "Dana masuk paling lambat $prosesHari hari kerja."] as $i => $langkah)
                                    <li class="flex gap-3">
                                        <span
                                            class="flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full shrink-0 bg-orcha-ocean">{{ $i + 1 }}</span>
                                        <span>{{ $langkah }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">Ingin mengubah tanggal saja, bukan membatalkan?</p>
                            <a href="{{ $wa('Halo Orcha Journey, saya ingin menanyakan penjadwalan ulang trip saya.') }}"
                                target="_blank" rel="noopener noreferrer"
                                class="w-full mt-3 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                <x-bi-whatsapp class="w-4 h-4" />
                                Tanya Penjadwalan Ulang
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <x-skrip-isian />
</div>
