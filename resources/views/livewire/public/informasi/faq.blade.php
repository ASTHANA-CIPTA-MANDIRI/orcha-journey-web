<?php

use App\Services\DokuCheckout;
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

        // Jawaban tentang cara membayar harus mengikuti jalur yang benar-benar
        // berlaku. FAQ yang menyuruh orang menunggu nomor rekening lewat
        // WhatsApp, padahal pembayarannya sudah bisa diselesaikan sendiri
        // dalam dua menit, membuat orang menunggu untuk sesuatu yang tidak
        // akan datang.
        $gerbang = app(DokuCheckout::class)->aktif();

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
                            'a' => $gerbang
                                ? 'Pembayaran diselesaikan di <a href="' . route('konfirmasi-pembayaran') . '">halaman pembayaran</a> kami: masukkan kode pesanan dan 4 digit terakhir nomor WhatsApp Anda, pilih uang muka atau lunas, lalu bayar lewat transfer bank, virtual account, QRIS, atau dompet digital. Pembayarannya tercatat sendiri — Anda tidak perlu mengirim bukti apa pun. Di aplikasi bank Anda, penerimanya tertulis ' . config('orcha.pembayaran.atas_nama_gerbang') . ' — itu kami; bila Anda transfer langsung ke rekening kami, namanya ' . config('orcha.pembayaran.atas_nama') . '. Kami tidak pernah meminta transfer ke rekening pribadi atas nama perorangan.'
                                : 'Pembayaran hanya lewat transfer bank, dan hanya sah ke rekening atas nama ' . config('orcha.pembayaran.atas_nama') . ' — nama selain itu bukan kami. Nomor rekeningnya dikirim tim kami lewat WhatsApp saat konfirmasi pemesanan, sengaja tidak dipajang di situs supaya tidak disalin penipu. Setelah transfer, kirim buktinya lewat <a href="' . route('konfirmasi-pembayaran') . '">formulir Konfirmasi Pembayaran</a>.',
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

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, blog, testimoni, dan halaman paket.

         Sebelumnya halaman ini memakai .section-orcha di atas latar putih,
         sehingga ada pita putih kosong setinggi hampir 150px antara foto hero
         dan isinya. --}}
    <div class="container-orcha rak-panel pb-6">
        <div class="grid gap-4 sm:gap-5 lg:grid-cols-12">

            {{-- Daftar isi --}}
            <aside class="hidden lg:block lg:col-span-3">
                <nav class="toc-orcha panel-orcha reveal !p-5" aria-label="Daftar kategori">
                    <p class="mb-3 label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Kategori</p>
                    @foreach ($kelompok as $grup)
                        <a href="#{{ $grup['slug'] }}">{{ $grup['judul'] }}</a>
                    @endforeach
                </nav>
            </aside>

            <div class="space-y-4 sm:space-y-5 lg:col-span-9">
                @foreach ($kelompok as $grup)
                    <section id="{{ $grup['slug'] }}" class="panel-orcha reveal scroll-mt-28">
                        <div class="flex items-start gap-3">
                            {{-- .ubin-toska: gradasi sky->ocean di sini peninggalan
                                 palet biru, dan pada palet hangat sekarang ia
                                 membentang dari emas ke abu — warna lumpur, tanpa
                                 satu pun galat yang menyebutnya. --}}
                            <span class="grid w-11 h-11 shrink-0 place-items-center rounded-xl ubin-toska">
                                <x-dynamic-component :component="'heroicon-' . $grup['icon']" class="w-5 h-5" />
                            </span>

                            <div>
                                {{-- Nomor bagian, bukan kata "Kategori" berulang lima
                                     kali: label yang sama di tiap panel berhenti
                                     memberi tahu apa pun. --}}
                                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span>
                                    Bagian {{ $loop->iteration }} dari {{ count($kelompok) }}</p>
                                <h2 class="mt-2 judul-panel-kecil">{{ $grup['judul'] }}.</h2>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3">
                            @foreach ($grup['tanya'] as $item)
                                <details class="group tanya-orcha">
                                    <summary
                                        class="flex items-start justify-between gap-4 font-bold list-none cursor-pointer text-orcha-navy marker:hidden">
                                        <span>{{ $item['q'] }}</span>
                                        <x-heroicon-o-chevron-down
                                            class="w-5 h-5 mt-0.5 shrink-0 text-orcha-toska transition-transform duration-300 group-open:rotate-180" />
                                    </summary>
                                    <p class="jawab-orcha">{{ $item['a'] }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="panel-orcha panel-orcha-krem reveal">
                    <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="grid w-11 h-11 shrink-0 place-items-center rounded-xl ubin-toska">
                                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5" />
                            </span>

                            <div>
                                <p class="judul-panel-kecil">Pertanyaan Anda belum terjawab?</p>
                                <p class="mt-1.5 text-sm text-slate-600">Tim kami membalas setiap hari pukul
                                    08.00–21.00 WIB.</p>
                            </div>
                        </div>

                        <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                            class="shrink-0 btn-orcha btn-orcha-sun">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Tanya via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
