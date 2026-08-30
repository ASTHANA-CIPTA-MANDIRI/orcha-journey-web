<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('FAQ — Pertanyaan yang Sering Diajukan | Orcha Journey')] class extends Component {
    public function with(): array
    {
        $dp = config('orcha.pembayaran.dp_persen');
        $dpStudy = config('orcha.pembayaran.dp_persen_study_tour');
        $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
        $batasDp = config('orcha.pembayaran.dp_batas_jam');

        $kelompok = [
                [
                    'judul' => 'Pemesanan',
                    'icon' => 'o-clipboard-document-check',
                    'tanya' => [
                        [
                            'q' => 'Bagaimana cara memesan paket wisata?',
                            'a' => 'Hubungi kami lewat WhatsApp, sebutkan tujuan, tanggal, dan jumlah peserta. Kami kirimkan itinerary beserta rincian biaya, lalu pemesanan dikunci setelah uang muka masuk.',
                        ],
                        [
                            'q' => 'Berapa lama sebelum keberangkatan sebaiknya memesan?',
                            'a' => 'Untuk open trip cukup 3–7 hari sebelumnya selama kursi masih ada dan kuota minimal 6 orang terpenuhi. Untuk private trip dan study tour kami sarankan 2–4 minggu sebelumnya, terutama pada musim liburan sekolah dan akhir tahun.',
                        ],
                        [
                            'q' => 'Apakah bisa memesan untuk rombongan besar?',
                            'a' => 'Bisa. Kami biasa menangani rombongan sekolah dan instansi hingga ratusan peserta dengan beberapa bus sekaligus, lengkap dengan pendamping di tiap armada.',
                        ],
                        [
                            'q' => 'Apa bedanya open trip, private trip, dan study tour?',
                            'a' => 'Open trip digabung dengan peserta lain sehingga biayanya patungan, jadwalnya sudah ditentukan, dan berangkat setelah kuota minimal 6 orang terpenuhi. Private trip hanya diisi rombongan Anda sendiri dengan rute yang bisa diatur. Study tour adalah paket kunjungan edukatif untuk sekolah dan kampus, lengkap dengan dokumentasi dan surat jalan.',
                        ],
                    ],
                ],
                [
                    'judul' => 'Pembayaran & DP',
                    'icon' => 'o-credit-card',
                    'tanya' => [
                        [
                            'q' => 'Berapa uang muka (DP) yang harus dibayar?',
                            'a' => "Uang muka standar sebesar {$dp}% dari total biaya, khusus study tour {$dpStudy}%. Uang muka dibayarkan paling lambat {$batasDp} jam setelah konfirmasi pemesanan — itulah yang mengunci kursi Anda.",
                        ],
                        [
                            'q' => 'Kapan sisa pembayaran harus dilunasi?',
                            'a' => "Pelunasan paling lambat H-{$pelunasan} — yaitu {$pelunasan} hari sebelum tanggal keberangkatan. Untuk sewa kendaraan tanpa paket, pelunasan dilakukan " . config('orcha.pembayaran.pelunasan_sewa_kendaraan') . '.',
                        ],
                        [
                            'q' => 'Metode pembayaran apa saja yang diterima?',
                            'a' => 'Pembayaran hanya lewat transfer bank, dan hanya sah ke rekening atas nama ' . config('orcha.pembayaran.atas_nama') . ' — nama selain itu bukan kami. Nomor rekeningnya dikirim tim kami lewat WhatsApp saat konfirmasi pemesanan, sengaja tidak dipajang di situs supaya tidak disalin penipu. Setelah transfer, kirim buktinya lewat <a href="' . route('konfirmasi-pembayaran') . '">formulir Konfirmasi Pembayaran</a>.',
                        ],
                        [
                            'q' => 'Apakah harga yang tertera sudah final?',
                            'a' => 'Harga di halaman paket adalah harga per orang untuk jumlah peserta minimum yang tertulis. Perubahan jumlah peserta, tanggal, atau permintaan tambahan dapat mengubah harga, dan selalu kami konfirmasikan lebih dulu.',
                        ],
                    ],
                ],
                [
                    'judul' => 'Sewa Kendaraan',
                    'icon' => 'o-truck',
                    'tanya' => [
                        [
                            'q' => 'Apakah sewa kendaraan sudah termasuk sopir dan BBM?',
                            'a' => 'Harga yang tertera adalah harga sewa unit. Sopir, BBM, tol, parkir, dan biaya masuk lokasi dihitung terpisah kecuali disebutkan lain pada penawaran Anda.',
                        ],
                        [
                            'q' => 'Bisakah menyewa lepas kunci?',
                            'a' => 'Untuk kategori mobil bisa, dengan jaminan dan verifikasi identitas. HiAce dan bus pariwisata hanya disewakan bersama sopir kami.',
                        ],
                        [
                            'q' => 'Berapa lama minimal sewa?',
                            'a' => 'Minimal sewa adalah 12 jam untuk mobil dan 1 hari penuh untuk HiAce serta bus. Pemakaian lebih dari batas jam dikenakan biaya kelebihan waktu.',
                        ],
                        [
                            'q' => 'Bagaimana jika kendaraan mogok di jalan?',
                            'a' => 'Laporkan segera ke tim kami. Kami mengupayakan unit pengganti secepat mungkin, dan waktu yang hilang karena kerusakan unit tidak dihitung sebagai masa sewa.',
                        ],
                    ],
                ],
                [
                    'judul' => 'Pembatalan & Perubahan',
                    'icon' => 'o-arrow-path',
                    'tanya' => [
                        [
                            'q' => 'Bagaimana jika saya membatalkan perjalanan?',
                            'a' => 'Besar pengembalian dana mengikuti jarak waktu pembatalan terhadap tanggal keberangkatan. Rinciannya ada di halaman Kebijakan Pengembalian Dana.',
                        ],
                        [
                            'q' => 'Bisakah mengganti tanggal keberangkatan?',
                            'a' => 'Bisa, satu kali tanpa biaya administrasi bila diajukan lebih dari 14 hari sebelum keberangkatan dan tanggal pengganti masih tersedia. Selisih harga musim ramai tetap menjadi tanggungan pemesan.',
                        ],
                        [
                            'q' => 'Bagaimana jika perjalanan dibatalkan oleh Orcha Journey?',
                            'a' => 'Bila pembatalan berasal dari pihak kami (misalnya kuota open trip tidak terpenuhi), Anda dapat memilih penjadwalan ulang atau pengembalian dana 100% tanpa potongan.',
                        ],
                        [
                            'q' => 'Bagaimana kalau cuaca buruk atau bencana alam?',
                            'a' => 'Keselamatan didahulukan. Jika lokasi ditutup atau tidak aman, kami tawarkan destinasi pengganti setara atau penjadwalan ulang. Biaya yang sudah dibayarkan ke pihak ketiga dan tidak dapat ditarik kembali akan kami jelaskan terbuka.',
                        ],
                    ],
                ],
                [
                    'judul' => 'Selama Perjalanan',
                    'icon' => 'o-map',
                    'tanya' => [
                        [
                            'q' => 'Apakah peserta mendapat asuransi perjalanan?',
                            'a' => 'Paket study tour sudah termasuk asuransi perjalanan. Untuk paket lain, asuransi dapat ditambahkan atas permintaan dengan biaya tambahan.',
                        ],
                        [
                            'q' => 'Apakah ada pemandu yang mendampingi?',
                            'a' => 'Ya. Setiap keberangkatan didampingi tim kami, dan untuk rombongan besar kami menempatkan satu pendamping di tiap armada.',
                        ],
                        [
                            'q' => 'Bagaimana dengan peserta anak-anak atau lansia?',
                            'a' => 'Sampaikan sejak awal agar kami dapat menyesuaikan rute, jam istirahat, dan posisi tempat duduk. Beri tahu juga bila ada peserta dengan kondisi kesehatan khusus.',
                        ],
                    ],
            ],
        ];

        // Slug dipakai untuk anchor daftar isi di sisi kiri.
        return [
            'kelompok' => collect($kelompok)
                ->map(fn ($grup) => [...$grup, 'slug' => Str::slug($grup['judul'])])
                ->all(),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya punya pertanyaan yang belum ada di FAQ.');
@endphp

<div>
    <x-page-hero title="Pertanyaan yang Sering Diajukan" eyebrow="FAQ"
        subtitle="Jawaban singkat soal pemesanan, pembayaran, sewa kendaraan, sampai pembatalan perjalanan."
        image="images/HERO/faq.webp" posisi="center 85%" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">

                {{-- Daftar isi --}}
                <aside class="hidden lg:block lg:col-span-3">
                    <nav class="toc-orcha" aria-label="Daftar kategori">
                        <p class="px-3 mb-2 text-xs font-bold tracking-widest uppercase text-slate-400">Kategori</p>
                        @foreach ($kelompok as $grup)
                            <a href="#{{ $grup['slug'] }}">{{ $grup['judul'] }}</a>
                        @endforeach
                    </nav>
                </aside>

                <div class="lg:col-span-9">
                    @foreach ($kelompok as $grup)
                        <section id="{{ $grup['slug'] }}" class="mb-12 scroll-mt-28 last:mb-0">
                            <div class="flex items-center gap-3 mb-5">
                                <span
                                    class="flex items-center justify-center w-10 h-10 text-white rounded-xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                                    <x-dynamic-component :component="'heroicon-' . $grup['icon']" class="w-5 h-5" />
                                </span>
                                <h2 class="text-xl font-bold font-heading text-orcha-navy sm:text-2xl">
                                    {{ $grup['judul'] }}</h2>
                            </div>

                            <div class="space-y-3">
                                @foreach ($grup['tanya'] as $item)
                                    <details class="p-5 group card-orcha sm:p-6">
                                        <summary
                                            class="flex items-start justify-between gap-4 font-bold list-none cursor-pointer text-orcha-navy marker:hidden">
                                            <span>{{ $item['q'] }}</span>
                                            <x-heroicon-o-chevron-down
                                                class="w-5 h-5 mt-0.5 shrink-0 text-orcha-wave transition-transform duration-300 group-open:rotate-180" />
                                        </summary>
                                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $item['a'] }}</p>
                                    </details>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <div
                        class="flex flex-col items-center gap-4 p-8 mt-12 text-center sm:flex-row sm:text-left sm:justify-between rounded-3xl bg-orcha-foam/70">
                        <div>
                            <p class="text-lg font-bold font-heading text-orcha-navy">Pertanyaan Anda belum terjawab?
                            </p>
                            <p class="mt-1 text-sm text-slate-600">Tim kami membalas setiap hari pukul 08.00–21.00 WIB.
                            </p>
                        </div>
                        <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-primary">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Tanya via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
