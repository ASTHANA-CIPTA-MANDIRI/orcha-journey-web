<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\GambarWebp;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.guest')] #[Title('Konfirmasi Pembayaran — Orcha Journey')] class extends Component {
    use WithFileUploads;

    public string $kode = '';

    public string $jenis = 'dp';

    /*
     | Dua properti untuk satu isian: yang dilihat pengguna bertitik
     | ("500.000"), yang divalidasi dan disimpan angka polosnya. Memaksakan
     | satu properti berarti aturan `numeric` menolak titik pemisahnya.
     */
    public string $nominalTeks = '';

    public $nominal = '';

    public string $tanggalTransfer = '';

    public string $bankPengirim = '';

    public string $atasNamaPengirim = '';

    public $bukti;

    public string $catatan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public bool $terkirim = false;

    public function mount(): void
    {
        $this->kode = strtoupper(trim((string) request()->query('kode', '')));
        $this->tanggalTransfer = now()->toDateString();
    }

    /**
     * Semua aturan ini berjalan di server. Kode pesanan sengaja TIDAK
     * diwajibkan cocok dengan data: pelanggan bisa salah ketik, dan bila itu
     * terjadi buktinya tetap masuk untuk diperiksa admin — lebih baik daripada
     * ditolak mentah padahal uangnya sudah terlanjur berpindah.
     */
    protected function rules(): array
    {
        return [
            'kode' => 'required|string|min:6|max:30',
            'jenis' => 'required|in:'.implode(',', array_keys(config('orcha.jenis_pembayaran'))),
            'nominal' => 'required|numeric|min:1000',
            'tanggalTransfer' => 'required|date|before_or_equal:today',
            'bankPengirim' => 'required|string|min:2|max:60',
            'atasNamaPengirim' => 'required|string|min:3|max:120',
            'bukti' => 'required|image|max:4096',
            'catatan' => 'nullable|string|max:500',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'kode' => 'kode pesanan',
            'jenis' => 'jenis pembayaran',
            'tanggalTransfer' => 'tanggal transfer',
            'bankPengirim' => 'bank pengirim',
            'atasNamaPengirim' => 'nama pemilik rekening pengirim',
            'bukti' => 'bukti transfer',
            'setuju' => 'pernyataan kebenaran data',
        ];
    }

    protected function messages(): array
    {
        return [
            'bukti.required' => 'Bukti transfer wajib diunggah — tanpa itu pembayaran tidak bisa kami cek.',
            'tanggalTransfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
        ];
    }

    public function updatedKode(): void
    {
        $this->kode = strtoupper(trim($this->kode));
    }

    /** Ketikan apa pun jadi angka polos, lalu ditampilkan kembali bertitik. */
    public function updatedNominalTeks(): void
    {
        $angka = (int) preg_replace('/\D/', '', $this->nominalTeks);

        $this->nominal = $angka > 0 ? (string) $angka : '';
        $this->nominalTeks = $angka > 0 ? number_format($angka, 0, ',', '.') : '';
    }

    public function kirim(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->validate();

        $kunci = 'konfirmasi-bayar:'.request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 8)) {
            $this->addError('kode', 'Terlalu banyak pengiriman dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $bayar = KonfirmasiPembayaran::create([
            'kode' => $this->kode,
            'jenis' => $this->jenis,
            'nominal' => (int) $this->nominal,
            'tanggal_transfer' => $this->tanggalTransfer,
            'bank_pengirim' => $this->bankPengirim,
            'atas_nama_pengirim' => $this->atasNamaPengirim,
            'bukti' => GambarWebp::simpan($this->bukti, 'bukti-bayar'),
            'catatan' => $this->catatan ?: null,
        ]);

        // Bukti transfernya ikut dilampirkan supaya bisa dicocokkan langsung
        // dari kotak masuk, tanpa membuka dashboard.
        $rincian = [
            'Jenis' => $bayar->jenis_label,
            'Nominal' => $bayar->nominal_formatted,
            'Tanggal transfer' => $bayar->tanggal_transfer->translatedFormat('j F Y'),
            'Bank pengirim' => $bayar->bank_pengirim,
            'Atas nama' => $bayar->atas_nama_pengirim,
            'Pemesan' => $bayar->pesanan()?->nama ?? '— kode tidak dikenal —',
        ];

        // Kwitansi PDF dilampirkan supaya ada berkas yang bisa disimpan dan
        // dicetak — capnya "Menunggu Dicek", bukan "Lunas", karena pada tahap
        // ini pembayarannya memang belum diperiksa tim.
        $kwitansi = BerkasKwitansi::buat(
            'Tanda Terima Pembayaran',
            $bayar->kode,
            $rincian,
            $bayar->catatan,
            $bayar->nominal_formatted,
            'Nominal dilaporkan',
            'Menunggu Dicek',
        );

        KirimPemberitahuan::kirim(
            'Bukti Pembayaran Masuk',
            $bayar->kode,
            $rincian,
            $bayar->catatan,
            [$bayar->bukti],
            $kwitansi ? [BerkasKwitansi::namaBerkas('tanda-terima', $bayar->kode) => $kwitansi] : [],
            // Alamatnya diambil dari pendaftaran yang kodenya dicantumkan —
            // formulir ini sendiri tidak menanyakan email. Kalau kodenya salah
            // ketik, salinannya memang tidak terkirim; buktinya tetap tercatat.
            emailPelanggan: $bayar->pesanan()?->email,
            judulPelanggan: 'Bukti Transfer Anda Sudah Kami Terima',
            langkahPelanggan: "Bukti transfer Anda masuk dan akan dicek tim kami pada jam kerja, lalu hasilnya "
                ."dikabarkan lewat WhatsApp.\n\n"
                .'Perlu diketahui: kwitansi terlampir masih bertanda "Menunggu Dicek", jadi belum berarti lunas. '
                .'Simpan bukti transfer aslinya sampai pembayaran dinyatakan diterima.',
        );

        $this->terkirim = true;
        $this->reset(['nominal', 'nominalTeks', 'bankPengirim', 'atasNamaPengirim', 'bukti', 'catatan', 'setuju']);
    }

    public function kirimLagi(): void
    {
        $this->reset(['terkirim', 'kode']);
        $this->tanggalTransfer = now()->toDateString();
    }

    public function with(): array
    {
        $kode = trim($this->kode);

        return [
            'pesanan' => $kode === '' ? null : (str_starts_with($kode, 'SK-')
                ? PenyewaanKendaraan::where('kode', $kode)->first()
                : PendaftaranOpenTrip::where('kode', $kode)->first()),
            'pilihanJenis' => config('orcha.jenis_pembayaran'),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp');
@endphp

<div>
    <x-page-hero title="Konfirmasi Pembayaran" eyebrow="Sudah Transfer?"
        subtitle="Kirim bukti transfer di sini supaya pembayaran Anda tercatat dan segera kami cek."
        image="images/pantai-senja.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($terkirim)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">Bukti transfer terkirim</h2>
                            <p class="max-w-lg mx-auto mt-2 text-slate-600">
                                Tim kami mengeceknya pada jam kerja, lalu mengabari Anda lewat WhatsApp.
                                Simpan bukti transfer aslinya sampai pembayaran dinyatakan diterima.
                            </p>

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <a href="{{ $wa }}" target="_blank" rel="noopener"
                                    class="btn-orcha btn-orcha-primary">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Hubungi Kami
                                </a>
                                <button type="button" wire:click="kirimLagi" class="btn-orcha btn-orcha-outline">
                                    Kirim Bukti Lain
                                </button>
                            </div>
                        </div>
                    @else
                        <form wire:submit="kirim" class="p-6 card-orcha sm:p-8 space-y-7">
                            {{-- Perangkap bot: manusia tidak melihat kolom ini --}}
                            <div class="hidden" aria-hidden="true">
                                <label>Situs<input type="text" wire:model="situs" tabindex="-1"
                                        autocomplete="off"></label>
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Pesanan yang Dibayar</h2>

                                <div class="mt-4">
                                    <label for="kb-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
                                    <input id="kb-kode" type="text" required maxlength="30"
                                        wire:model.live.debounce.500ms="kode" placeholder="OT-1508-A7K3 atau SK-1508-B2M9"
                                        class="isian-orcha uppercase @error('kode') isian-galat @enderror">
                                    <p class="mt-1.5 text-sm text-slate-500">
                                        Kode yang Anda terima saat mendaftar open trip atau memesan sewa kendaraan.
                                    </p>
                                    @error('kode')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($pesanan)
                                    <div class="p-4 mt-4 border rounded-2xl border-orcha-sky/40 bg-orcha-foam/50">
                                        <p class="text-sm font-bold text-orcha-ocean">Pesanan ditemukan</p>
                                        <p class="mt-1 font-bold text-orcha-navy">{{ $pesanan->nama }}</p>
                                        <p class="text-sm text-slate-600">
                                            @if ($pesanan instanceof App\Models\OpenTrip\PendaftaranOpenTrip)
                                                {{ $pesanan->nama_paket }} · {{ $pesanan->jumlah_peserta }} peserta
                                            @else
                                                {{ $pesanan->nama_kendaraan }} · {{ $pesanan->durasi_label }}
                                            @endif
                                        </p>
                                    </div>
                                @elseif (strlen(trim($kode)) >= 6)
                                    <div class="p-4 mt-4 text-sm border rounded-2xl border-orcha-sun/50 bg-orcha-sun/10 text-slate-700">
                                        Kode ini belum kami temukan. Bukti tetap boleh dikirim — tim kami akan
                                        mencocokkannya, tapi periksa lagi kodenya supaya lebih cepat.
                                    </div>
                                @endif
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Rincian Transfer</h2>

                                <div class="grid gap-5 mt-4 sm:grid-cols-2">
                                    <div>
                                        <label for="kb-jenis" class="label-orcha">Jenis pembayaran <x-wajib /></label>
                                        <select id="kb-jenis" required wire:model="jenis"
                                            class="isian-orcha @error('jenis') isian-galat @enderror">
                                            @foreach ($pilihanJenis as $kunci => $label)
                                                <option value="{{ $kunci }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('jenis')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="kb-nominal" class="label-orcha">Nominal transfer <x-wajib /></label>
                                        <div class="relative">
                                            <span
                                                class="absolute inset-y-0 left-0 flex items-center pl-4 font-bold pointer-events-none text-orcha-navy">Rp</span>
                                            <input id="kb-nominal" type="text" inputmode="numeric" required
                                                wire:model.blur="nominalTeks" value="{{ $nominalTeks }}"
                                                placeholder="500.000"
                                                class="isian-orcha orcha-uang !pl-12 @error('nominal') isian-galat @enderror">
                                        </div>
                                        <p class="mt-1.5 text-sm text-slate-500">Tulis apa adanya sesuai yang tertera
                                            di bukti transfer.</p>
                                        @error('nominal')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="kb-tanggal" class="label-orcha">Tanggal transfer <x-wajib /></label>
                                        <input id="kb-tanggal" type="date" required wire:model="tanggalTransfer"
                                            max="{{ now()->toDateString() }}"
                                            class="isian-orcha @error('tanggalTransfer') isian-galat @enderror">
                                        @error('tanggalTransfer')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="kb-bank" class="label-orcha">Bank pengirim <x-wajib /></label>
                                        <input id="kb-bank" type="text" required maxlength="60"
                                            wire:model="bankPengirim" placeholder="BCA / Mandiri / BRI"
                                            class="isian-orcha @error('bankPengirim') isian-galat @enderror">
                                        @error('bankPengirim')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label for="kb-atas-nama" class="label-orcha">Nama pemilik rekening pengirim
                                            <x-wajib /></label>
                                        <input id="kb-atas-nama" type="text" required maxlength="120"
                                            wire:model="atasNamaPengirim" placeholder="Sesuai buku tabungan"
                                            class="isian-orcha @error('atasNamaPengirim') isian-galat @enderror">
                                        @error('atasNamaPengirim')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy">Bukti Transfer</h2>

                                <div class="mt-4">
                                    <label for="kb-bukti" class="label-orcha">Foto atau tangkapan layar <x-wajib /></label>
                                    <input id="kb-bukti" type="file" required accept="image/*" wire:model="bukti"
                                        class="isian-orcha @error('bukti') isian-galat @enderror">
                                    <p class="mt-1.5 text-sm text-slate-500">
                                        Maksimal 4 MB. Pastikan nominal, tanggal, dan nama penerima terbaca jelas.
                                    </p>
                                    @error('bukti')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror

                                    <div wire:loading wire:target="bukti" class="mt-2 text-sm text-orcha-ocean">
                                        Mengunggah…
                                    </div>

                                    @if ($bukti)
                                        <img src="{{ $bukti->temporaryUrl() }}" alt="Pratinjau bukti transfer"
                                            class="mt-3 border rounded-2xl border-orcha-foam max-h-64">
                                    @endif
                                </div>

                                <div class="mt-5">
                                    <label for="kb-catatan" class="label-orcha">Catatan <span
                                            class="font-normal text-slate-400">(opsional)</span></label>
                                    <textarea id="kb-catatan" rows="3" maxlength="500" wire:model="catatan"
                                        placeholder="Misalnya: pembayaran untuk 2 peserta atas nama Budi dan Sari."
                                        class="isian-orcha @error('catatan') isian-galat @enderror"></textarea>
                                    @error('catatan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="flex items-start gap-3 text-sm text-slate-600">
                                    <input type="checkbox" required wire:model="setuju"
                                        class="w-5 h-5 mt-0.5 rounded border-orcha-mist text-orcha-ocean">
                                    <span>
                                        <x-wajib /> Saya menyatakan bukti transfer ini benar dan saya kirim sendiri.
                                        Bukti palsu dapat membatalkan pesanan.
                                    </span>
                                </label>
                                @error('setuju')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror

                                <p class="mt-3 text-xs text-slate-400">Kolom bertanda <span
                                        class="text-red-500">*</span> wajib diisi.</p>

                                <button type="submit" class="w-full mt-4 btn-orcha btn-orcha-primary"
                                    wire:loading.attr="disabled" wire:target="kirim,bukti">
                                    <span wire:loading.remove wire:target="kirim">Kirim Bukti Transfer</span>
                                    <span wire:loading wire:target="kirim">Mengirim…</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <x-peringatan-pembayaran />

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Setelah bukti dikirim</h2>
                            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                                @foreach (['Bukti masuk ke daftar pembayaran kami.', 'Tim mengecek nominal dan tanggalnya.', 'Anda dikabari lewat WhatsApp bila sudah diterima.', 'Kursi atau unit dikunci setelah pembayaran diterima.'] as $i => $langkah)
                                    <li class="flex gap-3">
                                        <span
                                            class="flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full shrink-0 bg-orcha-ocean">{{ $i + 1 }}</span>
                                        <span>{{ $langkah }}</span>
                                    </li>
                                @endforeach
                            </ol>

                            <p class="mt-5 text-sm text-slate-500">
                                Simpan bukti transfer aslinya sampai pembayaran dinyatakan diterima.
                            </p>

                            <a href="{{ route('ketentuan-pembayaran') }}"
                                class="inline-block mt-3 text-sm font-semibold text-orcha-ocean hover:underline">
                                Lihat ketentuan pembayaran
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    {{-- Angka bertitik SAMBIL diketik. Server tetap yang memegang nilainya
         (wire:model.blur memformat ulang dengan aturan yang sama); ini hanya
         supaya pengguna tidak menunggu pindah kolom untuk melihat "500.000".
         Ditulis inline karena berkas Vite tidak ikut ter-deploy. --}}

    <x-skrip-isian />
</div>
