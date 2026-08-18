<?php

use App\Models\Kontak\PesanKontak;
use App\Support\NomorTelepon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Kontak Kami — Orcha Journey')] class extends Component {
    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $keperluan = 'open_trip';

    public string $pesan = '';

    /** Perangkap bot: manusia tidak akan pernah mengisi kolom tersembunyi ini. */
    public string $situs = '';

    public bool $terkirim = false;

    protected function rules(): array
    {
        return [
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            'email' => 'nullable|email|max:150',
            'keperluan' => 'required|in:' . implode(',', array_keys(config('orcha.keperluan_kontak'))),
            'pesan' => 'required|string|min:10|max:2000',
        ];
    }

    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    public function kirim(): void
    {
        // Bot mengisi semua kolom termasuk yang tersembunyi — diam-diam diabaikan.
        if (filled($this->situs)) {
            $this->terkirim = true;

            return;
        }

        $this->validate();

        $kunci = 'kontak:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('pesan', 'Terlalu banyak pesan dikirim dari perangkat ini. Coba lagi satu jam lagi atau hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        PesanKontak::create([
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email ?: null,
            'keperluan' => $this->keperluan,
            'pesan' => $this->pesan,
            'ip' => request()->ip(),
        ]);

        $this->reset(['nama', 'whatsapp', 'email', 'pesan']);
        $this->terkirim = true;
    }

    public function kirimLagi(): void
    {
        $this->terkirim = false;
    }

    public function with(): array
    {
        $wa = config('orcha.whatsapp');

        return [
            'kanal' => [
                [
                    'icon' => 'o-chat-bubble-left-right',
                    'judul' => 'WhatsApp',
                    'nilai' => '+' . $wa,
                    'catatan' => 'Paling cepat dibalas, setiap hari 08.00–21.00 WIB',
                    'tautan' => 'https://api.whatsapp.com/send?phone=' . $wa . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya.'),
                    'aksi' => 'Chat sekarang',
                ],
                [
                    'icon' => 'o-envelope',
                    'judul' => 'Email',
                    'nilai' => config('orcha.email'),
                    'catatan' => 'Untuk penawaran instansi, sekolah, dan dokumen penagihan',
                    'tautan' => 'mailto:' . config('orcha.email'),
                    'aksi' => 'Kirim email',
                ],
                [
                    'icon' => 'o-camera',
                    'judul' => 'Instagram',
                    'nilai' => '@' . config('orcha.instagram'),
                    'catatan' => 'Dokumentasi perjalanan dan jadwal open trip terbaru',
                    'tautan' => 'https://www.instagram.com/' . config('orcha.instagram') . '/',
                    'aksi' => 'Buka Instagram',
                ],
                [
                    'icon' => 'o-map-pin',
                    'judul' => 'Kantor',
                    'nilai' => config('orcha.alamat'),
                    'catatan' => 'Datang langsung? Kabari dulu supaya tim kami siap menerima',
                    'tautan' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode(config('orcha.alamat')),
                    'aksi' => 'Buka di peta',
                ],
            ],
            'jamOperasional' => [
                ['hari' => 'Senin – Jumat', 'jam' => '08.00 – 21.00 WIB'],
                ['hari' => 'Sabtu – Minggu', 'jam' => '08.00 – 21.00 WIB'],
                ['hari' => 'Hari libur nasional', 'jam' => 'Tetap melayani perjalanan berjalan'],
            ],
            'daftarKeperluan' => config('orcha.keperluan_kontak'),
            'pesanCepat' => [
                'Halo Orcha Journey, saya mau tanya jadwal open trip terdekat.',
                'Halo Orcha Journey, saya ingin private trip untuk keluarga.',
                'Halo Orcha Journey, saya butuh penawaran study tour sekolah.',
                'Halo Orcha Journey, saya ingin menyewa kendaraan.',
            ],
        ];
    }
}; ?>

@php
    $waLink = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
    $peta = 'https://www.google.com/maps?q=' . urlencode(config('orcha.alamat')) . '&output=embed';
@endphp

<div>
    <x-page-hero title="Kontak Kami" eyebrow="Hubungi Orcha Journey"
        subtitle="Pilih kanal yang paling nyaman untuk Anda. Pertanyaan lewat WhatsApp biasanya dibalas dalam hitungan menit."
        image="images/HERO/kontak.jpg" posisi="center 30%" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">

            {{-- Kanal kontak --}}
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 sm:gap-6">
                @foreach ($kanal as $item)
                    <div class="flex flex-col p-6 card-orcha reveal sm:p-7">
                        <span
                            class="flex items-center justify-center mb-5 text-white w-14 h-14 rounded-2xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                            <x-dynamic-component :component="'heroicon-' . $item['icon']" class="w-7 h-7" />
                        </span>
                        <h2 class="text-lg font-bold font-heading text-orcha-navy">{{ $item['judul'] }}</h2>
                        <p class="mt-2 text-sm font-semibold break-words text-orcha-ocean">{{ $item['nilai'] }}</p>
                        <p class="flex-1 mt-2 text-sm leading-relaxed text-slate-500">{{ $item['catatan'] }}</p>
                        <a href="{{ $item['tautan'] }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 mt-5 text-sm font-bold text-orcha-ocean hover:underline">
                            {{ $item['aksi'] }}
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 mt-12 lg:grid-cols-12">

                {{-- Formulir kontak --}}
                <div class="lg:col-span-7">
                    <div class="p-6 card-orcha sm:p-8">
                        <h2 class="text-xl font-bold font-heading text-orcha-navy">Kirim Pesan</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Isi formulir ini bila Anda lebih nyaman menulis panjang. Pesan masuk ke panel kami dan
                            dibalas lewat WhatsApp atau email yang Anda cantumkan.
                        </p>

                        @if ($terkirim)
                            <div class="p-6 mt-6 text-center rounded-2xl bg-orcha-foam/70">
                                <x-heroicon-s-check-circle class="w-12 h-12 mx-auto text-orcha-sky" />
                                <p class="mt-3 font-bold font-heading text-orcha-navy">Pesan Anda sudah kami terima.</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    Tim kami membalas setiap hari pukul 08.00–21.00 WIB. Kalau mendesak, chat WhatsApp
                                    saja supaya lebih cepat.
                                </p>
                                <div class="flex flex-col justify-center gap-3 mt-5 sm:flex-row">
                                    <a href="{{ $waLink('Halo Orcha Journey, saya baru saja mengirim pesan lewat website.') }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                        <x-bi-whatsapp class="w-4 h-4" />
                                        Chat WhatsApp
                                    </a>
                                    <button type="button" wire:click="kirimLagi"
                                        class="btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                                        Kirim Pesan Lain
                                    </button>
                                </div>
                            </div>
                        @else
                            <form wire:submit="kirim" class="mt-6 space-y-5">
                                {{-- Perangkap bot, disembunyikan dari pengguna --}}
                                <div class="hidden" aria-hidden="true">
                                    <label for="situs">Jangan diisi</label>
                                    <input id="situs" type="text" wire:model="situs" tabindex="-1"
                                        autocomplete="off">
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="kontak-nama" class="label-orcha">Nama <x-wajib /></label>
                                        <input id="kontak-nama" type="text" wire:model="nama" required minlength="3" maxlength="120"
                                            placeholder="Nama lengkap Anda"
                                            class="isian-orcha @error('nama') isian-galat @enderror">
                                        @error('nama')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="kontak-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                        <input id="kontak-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8" maxlength="30"
                                            placeholder="0812-3456-7890"
                                            class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
                                        @error('whatsapp')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="kontak-email" class="label-orcha">Email <span
                                                class="font-normal text-slate-400">(opsional)</span></label>
                                        <input id="kontak-email" type="email" wire:model="email"
                                            placeholder="nama@email.com"
                                            class="isian-orcha @error('email') isian-galat @enderror">
                                        @error('email')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="kontak-keperluan" class="label-orcha">Keperluan <x-wajib /></label>
                                        <select id="kontak-keperluan" wire:model="keperluan" required
                                            class="isian-orcha @error('keperluan') isian-galat @enderror">
                                            @foreach ($daftarKeperluan as $kunci => $label)
                                                <option value="{{ $kunci }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('keperluan')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div>
                                    <label for="kontak-pesan" class="label-orcha">Pesan <x-wajib /></label>
                                    <textarea id="kontak-pesan" rows="5" wire:model="pesan" required minlength="10" maxlength="2000"
                                        placeholder="Ceritakan tujuan, tanggal, dan jumlah peserta bila sudah ada."
                                        class="isian-orcha @error('pesan') isian-galat @enderror"></textarea>
                                    @error('pesan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-slate-500">
                                        Kolom bertanda <x-wajib /> wajib diisi. Data Anda hanya dipakai untuk membalas pesan ini —
                                        <a href="{{ route('kebijakan-privasi') }}"
                                            class="font-semibold text-orcha-ocean hover:underline">kebijakan privasi</a>.
                                    </p>
                                    <button type="submit" class="btn-orcha btn-orcha-primary" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="kirim">Kirim Pesan</span>
                                        <span wire:loading wire:target="kirim">Mengirim…</span>
                                        <x-heroicon-o-paper-airplane class="w-5 h-5" wire:loading.remove
                                            wire:target="kirim" />
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Pesan cepat + jam operasional --}}
                <div class="lg:col-span-5">
                    <div class="p-6 card-orcha sm:p-8">
                        <h2 class="text-xl font-bold font-heading text-orcha-navy">Atau pakai pesan siap kirim</h2>
                        <p class="mt-2 text-sm text-slate-600">Pilih salah satu, WhatsApp terbuka dengan pesan yang
                            sudah terisi.</p>

                        <div class="mt-6 space-y-3">
                            @foreach ($pesanCepat as $pesanSiap)
                                <a href="{{ $waLink($pesanSiap) }}" target="_blank" rel="noopener noreferrer"
                                    class="flex items-start gap-3 p-4 text-sm transition border rounded-2xl border-orcha-foam text-slate-600 hover:border-orcha-sky hover:bg-orcha-foam/50">
                                    <x-bi-whatsapp class="w-5 h-5 mt-0.5 shrink-0 text-green-600" />
                                    <span>{{ $pesanSiap }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-6 mt-6 card-orcha sm:p-8">
                        <h2 class="flex items-center gap-2 text-xl font-bold font-heading text-orcha-navy">
                            <x-heroicon-o-clock class="w-5 h-5 text-orcha-wave" />
                            Jam Operasional
                        </h2>
                        <dl class="mt-5 divide-y divide-orcha-foam">
                            @foreach ($jamOperasional as $baris)
                                <div class="flex items-center justify-between gap-4 py-3 text-sm">
                                    <dt class="text-slate-600">{{ $baris['hari'] }}</dt>
                                    <dd class="font-semibold text-right text-orcha-navy">{{ $baris['jam'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Peta --}}
            <div class="mt-6 overflow-hidden card-orcha">
                <iframe src="{{ $peta }}" title="Peta lokasi kantor Orcha Journey" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                    class="w-full border-0 h-80 sm:h-[26rem]"></iframe>
            </div>

            {{-- Tautan bantuan --}}
            <div class="grid gap-5 mt-12 sm:grid-cols-3 sm:gap-6">
                @foreach ([['FAQ', 'Jawaban cepat soal pemesanan dan pembayaran', route('faq')], ['Ketentuan Pembayaran & DP', 'Besaran uang muka dan tenggat pelunasan', route('ketentuan-pembayaran')], ['Kebijakan Pengembalian', 'Aturan pembatalan dan refund', route('kebijakan-pengembalian')]] as [$judul, $isi, $tautan])
                    <a href="{{ $tautan }}" class="p-6 card-orcha group">
                        <h3
                            class="flex items-center justify-between gap-2 font-bold font-heading text-orcha-navy group-hover:text-orcha-ocean">
                            {{ $judul }}
                            <x-heroicon-o-arrow-right
                                class="w-4 h-4 transition-transform text-orcha-wave group-hover:translate-x-1" />
                        </h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $isi }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <x-skrip-isian />
</div>
