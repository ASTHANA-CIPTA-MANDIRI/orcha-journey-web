<?php

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\PemilikPesanan;
use App\Support\TagihanPesanan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Satu halaman untuk pertanyaan "pesanan saya sekarang bagaimana?".
 *
 * Sebelumnya pelanggan menerima surat DUA kali — saat mengirim formulir, dan
 * saat pembayarannya diperiksa. Di antara keduanya ia buta: kursinya sudah
 * dikunci belum, peserta mana yang belum mengisi formulir kesehatan, kapan
 * batas pelunasannya, buktinya yang kemarin sudah dibuka atau belum.
 *
 * Semua jawabannya sudah tersimpan; yang tidak ada cuma tempat melihatnya.
 * Akibatnya tiap pertanyaan itu berubah jadi percakapan WhatsApp yang harus
 * dijawab manusia satu per satu — beban yang tumbuh seiring jumlah pesanan,
 * padahal jawabannya selalu sama.
 *
 * Kuncinya sama dengan halaman pembatalan: kode DAN empat digit terakhir
 * nomor WhatsApp. Halaman ini menampilkan lebih banyak daripada halaman mana
 * pun, jadi justru di sini penjagaannya paling penting.
 */
new #[Layout('components.layouts.guest')] #[Title('Lacak Pesanan — Orcha Journey')] class extends Component {
    #[Url(as: 'kode', except: '')]
    public string $kode = '';

    public string $empatDigit = '';

    /** Sudah menekan "Lihat", supaya pesan gagal tidak muncul sebelum dicoba. */
    public bool $dicari = false;

    public function lihat(): void
    {
        $this->dicari = true;
    }

    public function with(): array
    {
        $pesanan = PemilikPesanan::cariTerbatas($this->kode, $this->empatDigit, request()->ip());

        if (! $pesanan) {
            return ['pesanan' => null, 'sewa' => false, 'tagihan' => [], 'pembayaran' => collect()];
        }

        return [
            'pesanan' => $pesanan,
            'sewa' => $pesanan instanceof PenyewaanKendaraan,
            'tagihan' => TagihanPesanan::untuk($pesanan),

            /*
             | Riwayat bukti yang pernah dikirim, terbaru di atas.
             |
             | Catatan admin ikut ditampilkan HANYA untuk bukti yang ditolak.
             | Itu satu-satunya keadaan yang menuntut pelanggan berbuat sesuatu,
             | dan alasannya harus terbaca olehnya — bukan menunggu ada yang
             | sempat menuliskannya lagi lewat WhatsApp.
             */
            'pembayaran' => KonfirmasiPembayaran::where('kode', $pesanan->kode)
                ->latest()
                ->get(),
        ];
    }
}; ?>

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');

    $rupaStatus = [
        'baru' => ['bg-orcha-sky/15 text-orcha-ocean', 'Pesanan Anda sudah kami terima dan sedang kami periksa ketersediaannya.'],
        'dihubungi' => ['bg-orcha-sun/25 text-orcha-navy', 'Tim kami sudah menghubungi Anda. Kursi dikunci setelah uang muka masuk.'],
        'dp_masuk' => ['bg-emerald-100 text-emerald-800', 'Uang muka sudah kami terima — kursi Anda terkunci. Tinggal pelunasan.'],
        'lunas' => ['bg-emerald-100 text-emerald-800', 'Pembayaran lunas. Jam kumpul dikirim H-1 sebelum keberangkatan.'],
        'batal' => ['bg-rose-100 text-rose-800', 'Pesanan ini sudah dibatalkan.'],
    ];
@endphp

<div>
    <x-page-hero title="Lacak Pesanan" eyebrow="Open Trip & Sewa Kendaraan"
        subtitle="Lihat status pesanan, sisa tagihan, dan bukti pembayaran yang sudah Anda kirim — tanpa perlu bertanya lewat WhatsApp."
        image="images/HERO/form-konfirmasi-pembayaran.webp" />

    <section class="py-12 sm:py-16">
        <div class="max-w-3xl px-4 mx-auto sm:px-6">

            <div class="p-6 sm:p-8 card-orcha">
                <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div>
                        <label for="lp-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
                        <input id="lp-kode" type="text" wire:model="kode" maxlength="30"
                            placeholder="OT-3108-K7QMXV atau SK-3108-B2MQXV" class="uppercase isian-orcha">
                    </div>

                    <div>
                        <label for="lp-digit" class="label-orcha">4 digit WhatsApp <x-wajib /></label>
                        <input id="lp-digit" type="text" inputmode="numeric" maxlength="4" wire:model="empatDigit"
                            placeholder="7890" class="isian-orcha tracking-[.4em] font-bold sm:w-32">
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Kode ada di email dan tanda terima yang Anda terima saat memesan. Empat digitnya diambil dari
                    nomor WhatsApp yang Anda pakai waktu itu.
                </p>

                <button type="button" wire:click="lihat" class="w-full mt-5 btn-orcha btn-orcha-primary sm:w-auto">
                    <span wire:loading.remove wire:target="lihat">Lihat Pesanan</span>
                    <span wire:loading wire:target="lihat">Mencari…</span>
                </button>
            </div>

            @if ($dicari && ! $pesanan)
                {{-- Satu pesan untuk kedua kegagalan. Pesan yang membedakan
                     "kode tidak ada" dari "nomor salah" akan mengubah halaman ini
                     jadi alat pemeriksa kode. --}}
                <div class="p-5 mt-6 text-sm rounded-2xl bg-rose-50 text-rose-800">
                    Kode pesanan dan empat digit WhatsApp tidak cocok. Periksa kembali keduanya — keduanya ada di
                    email yang Anda terima saat memesan.
                </div>
            @endif

            @if ($pesanan)
                @php
                    $status = $pesanan->status ?? 'baru';
                    [$rupa, $penjelasan] = $rupaStatus[$status] ?? ['bg-slate-100 text-slate-700', ''];
                @endphp

                <div class="p-6 mt-6 sm:p-8 card-orcha">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-3 py-1 text-xs font-bold rounded-full {{ $rupa }}">
                            {{ $pesanan->status_label ?? 'Baru' }}
                        </span>
                        <span class="font-mono text-sm text-slate-500">{{ $pesanan->kode }}</span>
                    </div>

                    @if ($penjelasan)
                        <p class="mt-3 leading-relaxed text-slate-700">{{ $penjelasan }}</p>
                    @endif

                    <dl class="grid gap-4 mt-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">Pemesan</dt>
                            <dd class="font-bold text-orcha-navy">{{ $pesanan->nama }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                {{ $sewa ? 'Kendaraan' : 'Trip' }}
                            </dt>
                            <dd class="font-bold text-orcha-navy">
                                {{ $sewa ? ($pesanan->nama_kendaraan ?: 'Belum ditentukan') : ($pesanan->nama_paket ?: 'Belum ditentukan') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                {{ $sewa ? 'Mulai sewa' : 'Tanggal berangkat' }}
                            </dt>
                            <dd class="font-bold text-orcha-navy">
                                @if ($sewa)
                                    {{ $pesanan->jadwal_mulai?->translatedFormat('j F Y, H:i') ?: 'Menyusul' }}
                                @else
                                    {{ $pesanan->paket?->jadwal_label ?: ($pesanan->tanggal_berangkat?->translatedFormat('j F Y') ?: 'Menyusul') }}
                                @endif
                            </dd>
                        </div>
                        @unless ($sewa)
                            <div>
                                <dt class="text-xs font-semibold tracking-wider uppercase text-slate-500">Peserta</dt>
                                <dd class="font-bold text-orcha-navy">{{ $pesanan->jumlah_peserta }} orang</dd>
                            </div>
                        @endunless
                    </dl>
                </div>

                @if ($tagihan)
                    <div class="p-6 mt-6 sm:p-8 card-orcha">
                        <h2 class="text-lg font-bold font-heading text-orcha-navy">Tagihan</h2>

                        <dl class="mt-4 divide-y divide-slate-100">
                            @foreach ([['Total', $tagihan['total']], ['Sudah dibayar', $tagihan['sudah']], ['Sisa', $tagihan['sisa']]] as [$label, $angka])
                                <div class="flex items-center justify-between py-3">
                                    <dt class="text-slate-600">{{ $label }}</dt>
                                    <dd
                                        class="font-bold {{ $label === 'Sisa' && $angka > 0 ? 'text-orcha-ocean' : 'text-orcha-navy' }}">
                                        {{ $rupiah($angka) }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($tagihan['sisa'] > 0 && $status !== 'batal')
                            <a href="{{ route('konfirmasi-pembayaran', ['kode' => $pesanan->kode]) }}" wire:navigate
                                class="w-full mt-5 btn-orcha btn-orcha-primary sm:w-auto">
                                Kirim Bukti Pembayaran
                            </a>
                        @endif
                    </div>
                @endif

                @if ($pembayaran->isNotEmpty())
                    <div class="p-6 mt-6 sm:p-8 card-orcha">
                        <h2 class="text-lg font-bold font-heading text-orcha-navy">Bukti yang Anda Kirim</h2>

                        <ul class="mt-4 divide-y divide-slate-100">
                            @foreach ($pembayaran as $bayar)
                                <li class="py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-bold text-orcha-navy">{{ $rupiah($bayar->nominal) }}</span>
                                        <span
                                            class="px-2.5 py-1 text-xs font-bold rounded-full @class([
                                                'bg-emerald-100 text-emerald-800' => $bayar->status === 'diterima',
                                                'bg-rose-100 text-rose-800' => $bayar->status === 'ditolak',
                                                'bg-orcha-sun/25 text-orcha-navy' => !in_array($bayar->status, ['diterima', 'ditolak']),
                                            ])">
                                            {{ config('orcha.status_pembayaran')[$bayar->status] ?? 'Menunggu Dicek' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Dikirim {{ $bayar->created_at?->translatedFormat('j F Y, H:i') }}
                                    </p>

                                    {{-- Alasan penolakan ditampilkan apa adanya: itu
                                         satu-satunya keadaan yang menuntut pelanggan
                                         berbuat sesuatu, dan tanpa alasannya ia cuma
                                         tahu "ditolak" tanpa tahu harus bagaimana. --}}
                                    @if ($bayar->status === 'ditolak' && $bayar->catatan_admin)
                                        <p class="p-3 mt-2 text-sm rounded-xl bg-rose-50 text-rose-800">
                                            {{ $bayar->catatan_admin }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @unless ($sewa)
                    <div class="p-6 mt-6 sm:p-8 card-orcha">
                        <h2 class="text-lg font-bold font-heading text-orcha-navy">Formulir Kesehatan Peserta</h2>

                        <p class="mt-2 text-slate-600">
                            {{ $pesanan->kesehatan_terisi }} dari {{ $pesanan->jumlah_peserta }} peserta sudah mengisi.
                        </p>

                        @if (!$pesanan->kesehatan_lengkap)
                            {{-- Nama yang BELUM mengisi disebut satu per satu.
                                 "3 dari 5 sudah" memaksa ketua rombongan menanyai
                                 kelimanya lagi untuk tahu dua yang mana. --}}
                            @if ($pesanan->peserta_belum_isi)
                                <p class="mt-3 text-sm text-slate-600">Belum mengisi:</p>
                                <ul class="flex flex-wrap gap-2 mt-2">
                                    @foreach ($pesanan->peserta_belum_isi as $nama)
                                        <li
                                            class="px-3 py-1 text-sm font-semibold rounded-full bg-orcha-foam text-orcha-navy">
                                            {{ $nama }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <a href="{{ route('riwayat-kesehatan', ['kode' => $pesanan->kode]) }}" wire:navigate
                                class="mt-5 btn-orcha btn-orcha-outline">
                                Isi Formulir Kesehatan
                            </a>
                        @endif
                    </div>
                @endunless

                @if ($status !== 'batal')
                    <p class="mt-6 text-sm text-center text-slate-500">
                        Perlu membatalkan?
                        <a href="{{ route('pembatalan', ['kode' => $pesanan->kode]) }}" wire:navigate
                            class="font-semibold text-orcha-ocean hover:underline">Ajukan pembatalan</a>.
                    </p>
                @endif
            @endif
        </div>
    </section>
</div>
