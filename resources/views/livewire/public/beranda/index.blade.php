<?php

use App\Models\SewaKendaraan\Car;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Galeri;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Orcha Journey — Open Trip, Private Trip, Study Tour & Sewa Kendaraan')] class extends Component {
    /**
     * Empat lini layanan Orcha Journey. Statis karena ini copy penawaran,
     * bukan data yang dikelola admin. Sengaja bukan public property supaya
     * tidak ikut dikirim ke browser lewat snapshot Livewire.
     */
    private function layanan(): array
    {
        return [
            [
                'icon' => 'users',
                'title' => 'Open Trip',
                'desc' => 'Gabung rombongan, biaya patungan, tanggal sudah kami tetapkan.',
                'tautan' => route('paket-wisata', 'open-trip'),
            ],
            [
                'icon' => 'sparkles',
                'title' => 'Private Trip',
                'desc' => 'Satu rombongan keluarga atau tim Anda sendiri. Rutenya ikut Anda.',
                'tautan' => route('paket-wisata', 'private-trip'),
            ],
            [
                'icon' => 'academic-cap',
                'title' => 'Study Tour',
                'desc' => 'Kunjungan edukatif sekolah & kampus, lengkap dengan pendampingnya.',
                'tautan' => route('paket-wisata', 'study-tour'),
            ],
            [
                'icon' => 'truck',
                'title' => 'Sewa Kendaraan',
                'desc' => 'Mobil, HiAce, sampai bus pariwisata. Lepas kunci atau dengan sopir.',
                'tautan' => route('sewa-kendaraan'),
            ],
        ];
    }

    private function bookingSteps(): array
    {
        return [
            [
                'icon' => 'chat-bubble-left-right',
                'title' => 'Konsultasi',
                'desc' => 'Chat kami di WhatsApp. Sebutkan tujuan, tanggal, dan jumlah peserta.',
            ],
            [
                'icon' => 'clipboard-document-check',
                'title' => 'Susun Rencana',
                'desc' => 'Kami kirim itinerary dan rincian biaya, lalu disesuaikan sampai cocok.',
            ],
            [
                'icon' => 'credit-card',
                'title' => 'Bayar DP',
                'desc' => 'Kursi dan armada dikunci setelah uang muka masuk. Sisa dilunasi saat berangkat.',
            ],
            [
                'icon' => 'paper-airplane',
                'title' => 'Berangkat',
                'desc' => 'Tim kami menjemput tepat waktu. Anda tinggal menikmati perjalanan.',
            ],
        ];
    }

    /**
     * Empat alasan memilih Orcha. Dipakai di kartu "Perjalanan tanpa repot" —
     * sepadan dengan blok "Travel Without the Headache" pada rancangan.
     */
    private function alasan(): array
    {
        return [
            ['icon' => 'star', 'title' => 'Itinerary disusun sendiri', 'desc' => 'Bukan jadwal salinan; disesuaikan rombongan Anda.'],
            ['icon' => 'shield-check', 'title' => 'Mitra tepercaya', 'desc' => 'Armada, hotel, dan pemandu yang sudah kami pakai sendiri.'],
            ['icon' => 'chat-bubble-left-right', 'title' => 'Didampingi orang, bukan robot', 'desc' => 'Satu nomor yang sama dari tanya-tanya sampai pulang.'],
            ['icon' => 'calendar-days', 'title' => 'Tanggal bisa menyesuaikan', 'desc' => 'Geser jadwal atau tambah peserta tanpa mengulang dari awal.'],
        ];
    }

    public function with(): array
    {
        // Beranda hanya menampilkan sorotan; daftar lengkapnya ada di
        // halaman /paket-wisata, /sewa-kendaraan, dan /destinasi.
        // Tiga terbaru saja. Enam kartu di beranda berarti menggulung dua baris
        // penuh sebelum sampai ke bagian berikutnya, dan yang di baris kedua
        // praktis tidak pernah terlihat.
        //
        // id sebagai pemutus: paket bawaan tercatat pada detik yang sama, dan
        // urutan tanpa pemutus berarti tiga yang tampil berganti-ganti sendiri
        // tiap halaman dibuka.
        $packages = TravelPackage::query()
            ->tayang()
            ->withCount('pendaftaran')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        // HANYA SATU yang disorot, dan itu inti permintaannya: mata harus
        // punya satu tujuan. Penanda "terlaris" di admin sekarang menempel di
        // tiga dari lima paket; kalau semuanya ikut disorot, tidak ada yang
        // menonjol dan sorotannya kehilangan gunanya.
        //
        // Yang dipilih di antara yang ditandai: pendaftarannya paling banyak,
        // lalu yang paling baru bila seri. Angka pendaftaran dipakai sebagai
        // PEMUTUS, bukan sebagai syarat — penandanya tetap keputusan admin,
        // dan mengabaikan penanda itu berarti mengambil alih keputusan yang
        // bukan milik halaman ini.
        //
        // Karena itu pula lencananya tidak menyebut angka. Saat ini tiga dari
        // lima paket tertandai, salah satunya tanpa satu pun pendaftaran.
        $idSorot = $packages
            ->where('is_best_choice', true)
            ->sortByDesc(fn ($paket) => [$paket->pendaftaran_count, $paket->id])
            ->first()?->id;

        // Yang disorot dipindah ke TENGAH, tidak dibiarkan di tempat yang
        // kebetulan didudukinya menurut urutan terbaru.
        //
        // Di tepi kiri ia terbaca sebagai "yang pertama", dan mata meneruskan
        // membaca ke kanan seperti daftar biasa. Di tengah ia jadi titik henti:
        // dua kartu di kiri-kanannya berlaku sebagai pembanding, dan itulah
        // yang membuat sorotannya bekerja.
        if ($idSorot) {
            $sorot = $packages->firstWhere('id', $idSorot);
            $lain = $packages->reject(fn ($paket) => $paket->id === $idSorot)->values();
            $tengah = intdiv($lain->count(), 2);

            $packages = $lain->slice(0, $tengah)
                ->push($sorot)
                ->concat($lain->slice($tengah))
                ->values();
        }

        $destinations = DestinationPopuler::query()
            ->orderByDesc('total_visitor')
            ->limit(8)
            ->get();

        // Urutan tampil mengikuti urutan tab (Mobil -> HiAce -> Bus). Diurutkan
        // di PHP, bukan lewat SQL FIELD(), supaya tetap jalan di SQLite saat tes.
        $urutanJenis = array_keys(config('orcha.jenis_kendaraan'));
        $cars = Car::where('is_available', true)
            ->orderBy('price_per_day')
            ->get()
            ->sortBy(fn ($car) => array_search($car->type, $urutanJenis, true))
            ->take(8)
            ->values();

        /*
         | Galeri punya sumbernya sendiri sekarang: menu Galeri di admin.
         |
         | Sebelumnya bagian ini meminjam foto destinasi, dan dua hal yang
         | berbeda maksudnya jadi satu — destinasi menjual TEMPAT, galeri
         | menunjukkan ORANG yang sudah berangkat. Admin tidak punya cara
         | memajang foto rombongan tanpa mengarang destinasi baru.
         |
         | Foto destinasi tetap dipakai sebagai cadangan supaya beranda yang
         | galerinya belum diisi tidak kosong melompong.
         */
        /*
         | Berbentuk [foto, keterangan], bukan jalur telanjang.
         |
         | Keterangannya dipakai dua kali: sebagai alt gambar — yang dibaca
         | pembaca layar dan mesin telusur, dan selama ini seragam "Dokumentasi
         | perjalanan" untuk SEMUA foto — dan sebagai keterangan yang muncul
         | saat tilenya disentuh. Foto cadangan tidak punya keterangan, jadi
         | kolomnya null dan tidak ada yang ditampilkan.
         */
        $galleries = Galeri::tayang()->take(12)
            ->get(['foto', 'keterangan', 'fokus_x', 'fokus_y'])
            /*
             | fokus = bagian foto yang tetap terlihat saat dipotong jadi 4:3.
             | Tanpa ini foto tegak selalu dipotong dari tengah, dan rombongan
             | yang berdiri di bagian bawah kehilangan kepalanya.
             */
            ->map(fn ($g) => [
                'foto' => $g->foto,
                'keterangan' => $g->keterangan,
                'fokus' => $g->fokus_css,
            ])
            ->all();

        /*
         | Cadangan hanya dipakai saat galerinya BENAR-BENAR kosong.
         |
         | Sebelumnya ada aturan kedua: bila jumlahnya kurang dari enam, seluruh
         | daftar diganti foto bawaan. Aturan itu masuk akal waktu sumbernya
         | foto destinasi — yang mungkin cuma ada dua — tetapi jadi merusak
         | begitu galerinya diisi admin: mengunggah lima foto berarti kelimanya
         | dibuang diam-diam, dan admin melihat beranda yang sama sekali tidak
         | berubah tanpa tahu apa yang salah.
         |
         | Jalur foto diulang sampai memenuhi pita berjalan (lihat $jalurGaleri
         | di bawah), jadi satu foto pun sudah cukup untuk menggambarnya.
         */
        if ($galleries === []) {
            $galleries = $destinations
                ->flatMap(fn ($d) => array_merge([$d->main_photo], $d->others_photo ?? []))
                ->filter()
                ->take(8)
                ->map(fn ($foto) => ['foto' => $foto, 'keterangan' => null, 'fokus' => '50% 50%'])
                ->values()
                ->all();
        }

        if ($galleries === []) {
            $galleries = array_map(
                fn ($file) => ['foto' => asset("images/$file"), 'keterangan' => null, 'fokus' => '50% 50%'],
                ['pantai-wide.webp', 'pantai-senja.webp', 'gapura.webp', 'pantai-ramai.webp', 'laut.webp', 'pantai-pinggir-laut.webp', 'pantai-atas.webp', 'pantai-pinggir.webp'],
            );
        }

        // Hero otomatis memakai video begitu berkasnya ditaruh di public/videos.
        $heroVideo = collect(['videos/hero.mp4', 'videos/hero.webm'])
            ->first(fn ($path) => file_exists(public_path($path)));

        return [
            'heroVideo' => $heroVideo,
            'kategoriPaket' => config('orcha.kategori_paket'),
            'jenisKendaraan' => config('orcha.jenis_kendaraan'),
            'packages' => $packages,
            'idSorot' => $idSorot,
            'destinations' => $destinations,
            'cars' => $cars,
            'testimonials' => Testimoni::tayang()->latest('id')->limit(12)->get(),
            'totalTestimoni' => Testimoni::tayang()->count(),
            'partners' => Partner::all(),
            'galleries' => $galleries,
            'layanan' => $this->layanan(),
            'alasan' => $this->alasan(),
            'bookingSteps' => $this->bookingSteps(),
        ];
    }
}; ?>
@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');

    /*
     | Satu paket yang disorot, sisanya berjajar di bawahnya.
     |
     | Yang disorot sudah dipilih di bagian PHP halaman ini (lihat $idSorot):
     | penanda "terlaris" dari admin, dengan jumlah pendaftaran sebagai
     | pemutus bila tertanda lebih dari satu.
     */
    $paketSorot = $idSorot ? $packages->firstWhere('id', $idSorot) : $packages->first();
    $paketLain = $packages->reject(fn ($p) => $paketSorot && $p->id === $paketSorot->id)->values();
@endphp

{{--
    BERANDA ORCHA JOURNEY

    Seluruh halaman berdiri di atas satu latar krem hangat, dan tiap bagian
    adalah PANEL putih membulat di atasnya — bukan pita warna yang berganti
    gelap-terang seperti sebelumnya. Mata tidak perlu menyetel ulang tiap kali
    menggulung, dan tulisan gelap di atas putih punya kontras yang sama dari
    atas sampai bawah halaman.

    Warnanya diambil dari logo: hijau pinus untuk tulisan dan bidang gelap,
    tosca untuk aksen, kuning pasir untuk ajakan. Kuning logo hanya dipakai
    sebagai LATAR (tulisannya hijau tua di atasnya); untuk tulisan kuning
    dipakai versi gelapnya — lihat --color-orcha-amber di new-homepage.css.
--}}
<div id="beranda" class="latar-krem">

    {{-- ==================================================================
         HERO — selebar kanvas

         Fotonya menempel ke tepi atas dan tepi kanan halaman, persis seperti
         rancangan acuan: kabin pesawat dengan jendela menghadap destinasi.
         Bilah menu melayang DI ATASNYA, jadi hero ini sengaja tidak duduk di
         dalam .container-orcha seperti panel-panel di bawahnya.
    =================================================================== --}}
    <section class="hero-kabin">
        <div class="hero-kabin-media">
            <img src="{{ asset('images/HERO/kabin-pesawat.webp') }}"
                alt="Pemandangan pantai dari jendela pesawat, dengan paspor dan boarding pass di meja kabin"
                fetchpriority="high" decoding="async" width="1672" height="941">

            {{-- Kartu melayang di atas foto, seperti pada acuan. Disembunyikan
                 di layar sempit: di sana fotonya sendiri sudah sempit, dan
                 kartunya menutupi jendela yang justru jadi isi fotonya. --}}
            <div class="hero-kabin-kartu hero-kabin-kartu-atas">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-orcha-foam text-orcha-amber shrink-0">
                    <x-heroicon-s-paper-airplane class="w-4 h-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[0.6rem] font-bold tracking-widest uppercase text-slate-400">Tujuan berikutnya</span>
                    <span class="block text-sm font-bold leading-snug text-orcha-navy">Pengalaman yang lebih baik</span>
                </span>
            </div>

            <div class="hero-kabin-kartu hero-kabin-kartu-bawah">
                <span class="min-w-0">
                    <span class="flex items-center gap-0.5">
                        @for ($i = 0; $i < 5; $i++)
                            <x-heroicon-s-star class="w-3.5 h-3.5 text-orcha-sun" />
                        @endfor
                    </span>
                    <span class="block mt-1 text-sm font-bold text-orcha-navy">{{ $totalTestimoni ?: 50 }}+ ulasan pelanggan</span>
                </span>
            </div>
        </div>

        <div class="hero-kabin-tirai"></div>

        <div class="relative container-orcha hero-kabin-isi">
            <div class="max-w-xl reveal">
                <p class="text-[0.7rem] font-bold tracking-[0.22em] uppercase text-slate-400">
                    {{ config('orcha.slogan') }}
                </p>

                <h1 class="mt-4 font-heading font-extrabold text-orcha-navy leading-[1.06] tracking-tight"
                    style="font-size: clamp(1.8rem, 5.2vw, 3.5rem);">
                    Pergi ke tempat yang
                    <span class="sorot-kata">layak dikenang.</span>
                </h1>

                <p class="max-w-lg mt-5 text-base leading-relaxed text-slate-600 sm:text-lg">
                    Open trip, private trip, study tour, sampai sewa mobil, HiAce, dan bus.
                    Sebutkan tujuan dan tanggalnya &mdash; rute, armada, sampai sopirnya kami yang siapkan.
                </p>

                <div class="flex flex-col gap-3 mt-8 sm:flex-row sm:items-center">
                    <a href="{{ route('paket-wisata') }}" class="btn-orcha btn-orcha-sun">
                        Jelajahi Perjalanan
                        <span class="btn-panah"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                    </a>
                    <a href="{{ $wa('Halo Orcha Journey, saya ingin merencanakan perjalanan.') }}"
                        target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-outline">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Rencanakan Perjalanan
                    </a>
                </div>

                <div class="flex flex-wrap gap-2 mt-8">
                    @foreach ([['map-pin', 'Destinasi pilihan'], ['user-group', 'Itinerary per rombongan'], ['shield-check', 'Berangkat tanpa repot']] as [$ikon, $teks])
                        <span class="chip-orcha">
                            <x-dynamic-component :component="'heroicon-s-' . $ikon" class="w-4 h-4 text-orcha-amber" />
                            {{ $teks }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <div class="container-orcha pt-4 pb-6 space-y-4 sm:space-y-5">

        {{-- ==================================================================
             DESTINASI POPULER
        =================================================================== --}}
        @if ($destinations->isNotEmpty())
            <section id="destinasi" class="panel-orcha scroll-mt-28">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-6 sm:mb-8 reveal">
                    <div class="max-w-2xl">
                        <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Destinasi Populer</p>
                        <h2 class="mt-3 judul-panel">Ke mana Anda berikutnya?</h2>
                    </div>
                    <a href="{{ route('destinasi') }}" class="btn-orcha btn-orcha-outline !py-1.5 !pl-5 !pr-1.5 !text-sm">
                        Lihat Semua Destinasi
                        <span class="btn-panah !w-7 !h-7"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 sm:gap-4">
                    @foreach ($destinations->take(5) as $dest)
                        @php
                            // Dua baris keterangan, seperti kartu pada rancangan acuan.
                            // Dipotong di sini, bukan lewat CSS: deskripsi destinasi
                            // bisa sepanjang satu paragraf, dan kartu yang tingginya
                            // ikut isi membuat barisnya bergerigi.
                            $ketDestinasi = \Illuminate\Support\Str::limit(
                                strip_tags((string) $dest->deskripsi) ?: trim(($dest->daerah ? $dest->daerah.', ' : '').$dest->provinsi, ', '),
                                52,
                            );
                        @endphp
                        <a href="{{ route('destinasi') }}" class="kartu-destinasi group reveal">
                            <span class="relative block overflow-hidden rounded-[1rem] aspect-[4/5]">
                                <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.webp') }}"
                                    alt="{{ $dest->destination_name }}" loading="lazy"
                                    class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">
                            </span>
                            <span class="flex items-end gap-2 px-2 pt-3 pb-1 sm:px-2.5">
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold sm:text-base text-orcha-navy">
                                        {{ $dest->destination_name }}
                                    </span>
                                    {{-- Dipangkas dua baris: tanpa itu tinggi tiap kartu
                                         ikut panjang keterangannya dan deretannya jadi
                                         bergerigi. --}}
                                    <span class="mt-1 text-xs leading-snug text-slate-500 line-clamp-2">
                                        {{ $ketDestinasi }}
                                    </span>
                                </span>
                                {{-- Di layar sempit kartunya cuma selebar 130px; keping
                                     panah di sampingnya menyisakan lajur tulisan selebar
                                     tiga kata, dan keterangannya pecah jadi enam baris
                                     pendek. Seluruh kartunya memang sudah berupa tautan. --}}
                                <span class="hidden sm:block">
                                    <span class="tombol-bulat !w-8 !h-8">
                                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                                    </span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ==================================================================
             LAYANAN
        =================================================================== --}}
        <section id="layanan" class="panel-orcha panel-orcha-krem scroll-mt-28">
            <div class="grid gap-4 mb-6 lg:grid-cols-2 lg:items-end sm:mb-8 reveal">
                <div>
                    <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Cara Berangkat</p>
                    <h2 class="mt-3 judul-panel">Jalan-jalan dengan cara Anda.</h2>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 sm:text-base lg:text-right">
                    Mau berangkat bareng orang baru, satu rombongan keluarga sendiri, satu sekolah,
                    atau cuma butuh kendaraannya saja &mdash; semuanya kami layani.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
                @foreach ($layanan as $item)
                    <a href="{{ $item['tautan'] }}" class="ubin-orcha ubin-orcha-putih reveal">
                        <span class="flex items-start gap-3">
                            <span class="flex items-center justify-center shrink-0 w-14 h-14 rounded-2xl bg-orcha-foam text-orcha-amber">
                                <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-7 h-7" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-base font-bold font-heading text-orcha-navy">{{ $item['title'] }}</span>
                                <span class="block mt-1 text-sm leading-snug text-slate-500">{{ $item['desc'] }}</span>
                            </span>
                        </span>
                        <span class="flex justify-end mt-4">
                            <span class="tombol-bulat !w-8 !h-8"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ==================================================================
             PAKET SOROTAN + ALASAN MEMILIH ORCHA
        =================================================================== --}}
        <div class="grid gap-4 lg:grid-cols-[1.45fr_1fr] sm:gap-5">
            @if ($paketSorot)
                {{-- !flex, bukan flex biasa: .panel-orcha menetapkan display di
                     luar lapisan CSS dan selalu menang atas utilitas Tailwind.

                     Perlu flex supaya isi kartunya IKUT TINGGI barisnya. Tanpa
                     itu panel ini memang meregang setinggi kartu di sebelahnya,
                     tetapi isinya tidak — fotonya berhenti di tengah dan
                     separuh bawah kartu menganga putih. --}}
                <section id="paket-sorotan" class="overflow-hidden panel-orcha !p-0 !flex !flex-col scroll-mt-28 reveal">
                    <div class="grid flex-1 gap-0 p-2 sm:grid-cols-2 sm:p-2.5">
                        {{-- Berbingkai, bukan rata tepi: kartu unggulan pada rancangan
                             acuan menyisakan pias putih tipis mengelilingi fotonya. --}}
                        <div class="relative min-h-[14rem] overflow-hidden rounded-[1.1rem]">
                            <img src="{{ $paketSorot->sampul }}" alt="{{ $paketSorot->name }}" loading="lazy"
                                class="absolute inset-0 object-cover w-full h-full">
                        </div>

                        <div class="flex flex-col p-5 sm:p-6">
                            <p class="label-orcha">Paket Sorotan</p>
                            <h2 class="mt-2 text-xl font-bold leading-snug font-heading text-orcha-navy sm:text-2xl">
                                {{ $paketSorot->name }}
                            </h2>

                            <div class="flex flex-wrap gap-3 mt-3 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-heroicon-s-clock class="w-4 h-4 text-orcha-wave" />
                                    {{ $paketSorot->duration }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-heroicon-s-user-group class="w-4 h-4 text-orcha-wave" />
                                    Min. {{ $paketSorot->minimal_peserta ?: 6 }} orang
                                </span>
                            </div>

                            <p class="mt-4 text-sm leading-relaxed text-slate-600">
                                @php $tujuan = collect($paketSorot->destination_list ?? [])->filter()->take(3); @endphp
                                @if ($tujuan->isNotEmpty())
                                    Mampir ke {{ $tujuan->join(', ', ' dan ') }}.
                                @endif
                                Transportasi, pemandu, dan tiket masuk yang tertera sudah termasuk.
                            </p>

                            {{-- mt-auto: harga & tombolnya turun ke dasar kartu saat
                                 barisnya lebih tinggi, bukan menggantung di tengah
                                 dengan ruang kosong di bawahnya. --}}
                            <div class="flex flex-wrap items-end justify-between gap-3 pt-5 mt-auto border-t border-orcha-foam">
                                <span>
                                    <span class="block text-xs text-slate-500">Mulai dari</span>
                                    <span class="text-lg font-black font-heading text-orcha-amber sm:text-xl">
                                        {{ $rupiah($paketSorot->price) }}
                                    </span>
                                    <span class="text-xs text-slate-500">/orang</span>
                                </span>
                                <a href="{{ route('paket-detail', $paketSorot->uuid) }}"
                                    class="btn-orcha btn-orcha-sun !py-1.5 !pl-5 !pr-1.5 !text-sm">
                                    Lihat Paket
                                    <span class="btn-panah !w-7 !h-7"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            <section class="panel-orcha !flex !flex-col reveal">
                <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Kenapa Orcha</p>
                <h2 class="mt-3 judul-panel !text-[clamp(1.35rem,2.2vw,1.9rem)]">Jalan-jalan tanpa repot.</h2>

                <div class="grid flex-1 gap-3 mt-5 sm:grid-cols-2">
                    @foreach ($alasan as $poin)
                        <div class="!flex-row items-center gap-3 ubin-orcha">
                            <span class="flex items-center justify-center w-10 h-10 bg-white shrink-0 rounded-xl text-orcha-amber">
                                <x-dynamic-component :component="'heroicon-s-' . $poin['icon']" class="w-5 h-5" />
                            </span>
                            <h3 class="text-sm font-bold leading-snug text-orcha-navy">{{ $poin['title'] }}</h3>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ==================================================================
             PAKET LAIN
        =================================================================== --}}
        <section id="paket" class="panel-orcha scroll-mt-28">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-6 reveal">
                <div class="max-w-2xl">
                    <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Paket Wisata</p>
                    <h2 class="mt-3 judul-panel">Pilih paketnya, sisanya kami urus.</h2>
                </div>

                <div class="tab-scroller">
                    @foreach ($kategoriPaket as $key => $label)
                        <a href="{{ route('paket-wisata', str_replace('_', '-', $key)) }}" class="tab-orcha">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('paket-wisata') }}" class="tab-orcha tab-orcha-active">Lihat Semua Paket</a>
                </div>
            </div>

            @if ($paketLain->isEmpty() && ! $paketSorot)
                <div class="p-10 text-center ubin-orcha">
                    <x-heroicon-o-map class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Paket wisata sedang disiapkan.</p>
                    <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk penawaran khusus.</p>
                </div>
            @else
                {{-- Jumlah lajurnya ikut jumlah paketnya: dua paket di dalam
                     kisi tiga lajur menyisakan satu sel menganga selebar
                     sepertiga panel. --}}
                <div class="grid gap-4 sm:grid-cols-2 {{ $paketLain->count() >= 3 ? 'xl:grid-cols-3' : '' }}">
                    @foreach ($paketLain as $package)
                        <x-paket-wisata.kartu :paket="$package" :lencana-unggulan="false" class="reveal" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ==================================================================
             ARMADA
        =================================================================== --}}
        <section id="armada" class="panel-orcha panel-orcha-krem scroll-mt-28">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-6 reveal">
                <div class="max-w-2xl">
                    <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Sewa Kendaraan</p>
                    <h2 class="mt-3 judul-panel">Armada untuk rombongan berapa pun.</h2>
                </div>

                <div class="tab-scroller">
                    @foreach ($jenisKendaraan as $key => $label)
                        <a href="{{ route('sewa-kendaraan', $key) }}" class="tab-orcha">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('sewa-kendaraan') }}" class="tab-orcha tab-orcha-active">Lihat Semua Armada</a>
                </div>
            </div>

            @if ($cars->isEmpty())
                <div class="p-10 text-center ubin-orcha ubin-orcha-putih">
                    <x-heroicon-o-truck class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Data armada belum tersedia.</p>
                    <p class="mt-1 text-sm text-slate-500">Silakan tanyakan ketersediaan lewat WhatsApp.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($cars->take(4) as $car)
                        <x-sewa-kendaraan.kartu :kendaraan="$car" class="reveal" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ==================================================================
             CARA PESAN
        =================================================================== --}}
        <section id="cara-pesan" class="panel-orcha scroll-mt-28">
            <div class="max-w-2xl mb-6 reveal">
                <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Cara Pesan</p>
                <h2 class="mt-3 judul-panel">Empat langkah, selesai.</h2>
            </div>

            <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
                @foreach ($bookingSteps as $index => $step)
                    <li class="relative ubin-orcha reveal">
                        <span class="absolute text-5xl font-black leading-none select-none top-3 right-4 font-heading text-white/70">
                            {{ $index + 1 }}
                        </span>
                        <span class="relative flex items-center justify-center mb-4 w-11 h-11 rounded-xl bg-orcha-sun text-orcha-navy">
                            <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-6 h-6" />
                        </span>
                        <h3 class="relative text-base font-bold font-heading text-orcha-navy">{{ $step['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- ==================================================================
             GALERI — pita berjalan, selebar panel
        =================================================================== --}}
        <section id="galeri" class="overflow-hidden panel-orcha !px-0 scroll-mt-28">
            <div class="max-w-2xl mx-auto mb-6 text-center px-[clamp(1.35rem,3.4vw,3rem)] reveal">
                <p class="justify-center label-orcha">Galeri</p>
                <h2 class="mt-3 judul-panel">Momen perjalanan pelanggan kami.</h2>
            </div>

            @php
                // Digandakan sampai jalurnya lebih panjang dari layar terlebar,
                // supaya perulangannya tidak pernah terlihat putus.
                $jalurGaleri = $galleries;
                while (count($jalurGaleri) < 10) {
                    $jalurGaleri = array_merge($jalurGaleri, $galleries);
                }
                $jalurGaleri = array_merge($jalurGaleri, $jalurGaleri);
            @endphp

            <div class="space-y-3 marquee-wrap">
                @foreach ([$jalurGaleri, array_reverse($jalurGaleri)] as $baris => $jalur)
                    <div class="flex overflow-hidden">
                        <div class="marquee {{ $baris === 1 ? 'marquee-reverse marquee-slow' : '' }}">
                            @foreach ($jalur as $img)
                                <figure class="relative overflow-hidden rounded-2xl w-56 sm:w-72 lg:w-80 aspect-[4/3] shrink-0 group">
                                    <img src="{{ $img['foto'] }}"
                                        alt="{{ $img['keterangan'] ?: 'Dokumentasi perjalanan Orcha Journey' }}"
                                        loading="lazy" style="object-position: {{ $img['fokus'] ?? '50% 50%' }}"
                                        class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">

                                    @if ($img['keterangan'])
                                        {{-- Muncul saat tilenya disentuh; pita berjalannya memang
                                             berhenti saat itu, jadi kalimatnya sempat terbaca. --}}
                                        <figcaption class="galeri-selubung">
                                            <span class="galeri-keterangan"><span>{{ $img['keterangan'] }}</span></span>
                                        </figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ==================================================================
             TESTIMONI + AJAKAN
        =================================================================== --}}
        <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr] sm:gap-5">
            @if ($testimonials->isNotEmpty())
                <section id="testimoni" class="panel-orcha scroll-mt-28">
                    <div class="flex flex-wrap items-end justify-between gap-4 mb-6 reveal">
                        <div class="flex-1 min-w-0">
                            <p class="label-orcha"><span class="w-6 h-px bg-orcha-sun"></span> Testimoni</p>
                            <h2 class="mt-3 judul-panel">Cerita asli, pelanggan yang senang.</h2>
                        </div>
                        <a href="{{ route('testimoni') }}" class="text-sm font-bold text-orcha-ocean hover:underline">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
                        @foreach ($testimonials->take(3) as $testimoni)
                            <figure class="ubin-orcha reveal">
                                <blockquote class="flex-1 text-sm leading-relaxed text-slate-600">
                                    &ldquo;{{ \Illuminate\Support\Str::limit($testimoni->testimonial, 150) }}&rdquo;
                                </blockquote>
                                <figcaption class="flex items-center gap-3 pt-4 mt-4 border-t border-orcha-foam">
                                    @if ($testimoni->avatar)
                                        <img src="{{ $testimoni->avatar }}" alt="{{ $testimoni->customer_name }}"
                                            loading="lazy" class="object-cover w-10 h-10 rounded-full">
                                    @else
                                        <span class="flex items-center justify-center w-10 h-10 font-bold text-white rounded-full bg-orcha-wave">
                                            {{ mb_strtoupper(mb_substr($testimoni->customer_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold truncate text-orcha-navy">{{ $testimoni->customer_name }}</span>
                                        <span class="flex items-center gap-0.5">
                                            @for ($i = 0; $i < 5; $i++)
                                                <x-heroicon-s-star class="w-3.5 h-3.5 {{ $i < $testimoni->rating ? 'text-orcha-sun' : 'text-slate-200' }}" />
                                            @endfor
                                        </span>
                                    </span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="relative overflow-hidden panel-orcha panel-orcha-gelap reveal">
                <img src="{{ asset('images/pantai-senja.webp') }}" alt="" loading="lazy"
                    class="absolute inset-0 object-cover w-full h-full opacity-25">
                <div class="relative">
                    <p class="label-orcha !text-orcha-sun">Siap Berangkat?</p>
                    <h2 class="mt-3 text-xl font-bold leading-snug text-white font-heading sm:text-2xl">
                        Perjalanan Anda berikutnya cuma sejauh satu pesan.
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-200">
                        Ceritakan tujuan, tanggal, dan jumlah pesertanya. Kami balas dengan rencana
                        perjalanan beserta rincian biaya di hari yang sama.
                    </p>
                    <a href="{{ $wa('Halo Orcha Journey, saya mau tanya jadwal dan harga.') }}" target="_blank"
                        rel="noopener noreferrer" class="w-full mt-6 btn-orcha btn-orcha-sun">
                        <x-bi-whatsapp class="w-5 h-5" />
                        Chat via WhatsApp
                    </a>
                    <p class="mt-3 text-xs text-center text-slate-300">
                        Dibalas orang, bukan mesin. Setiap hari 08.00&ndash;21.00 WIB.
                    </p>
                </div>
            </section>
        </div>

        {{-- ==================================================================
             PARTNER
        =================================================================== --}}
        @if ($partners->isNotEmpty())
            <section class="overflow-hidden panel-orcha !px-0 !py-6">
                <p class="mb-4 text-xs font-semibold tracking-wider text-center uppercase text-slate-400">
                    Didukung mitra tepercaya
                </p>

                @php
                    $jalurPartner = $partners->all();
                    while (count($jalurPartner) < 12) {
                        $jalurPartner = array_merge($jalurPartner, $partners->all());
                    }
                    $jalurPartner = array_merge($jalurPartner, $jalurPartner);
                @endphp

                <div class="flex overflow-hidden marquee-wrap">
                    <div class="marquee marquee-slow">
                        @foreach ($jalurPartner as $partner)
                            <div class="flex items-center gap-3 px-5 py-3 border shrink-0 rounded-2xl border-orcha-foam bg-orcha-foam/50">
                                <span class="flex items-center justify-center w-10 h-10 overflow-hidden font-bold text-white rounded-full shrink-0 bg-orcha-wave">
                                    @if ($partner->foto)
                                        <img src="{{ $partner->foto }}" alt="{{ $partner->partner_name }}"
                                            loading="lazy" class="object-cover w-full h-full">
                                    @else
                                        {{ $partner->initials() }}
                                    @endif
                                </span>
                                <span class="text-sm font-semibold whitespace-nowrap text-orcha-navy">{{ $partner->partner_name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ==================================================================
             AJAKAN PENUTUP
        =================================================================== --}}
        <section class="relative overflow-hidden panel-orcha panel-orcha-gelap !p-0">
            <img src="{{ asset('images/FOOTER/beranda.webp') }}" alt="" loading="lazy"
                class="absolute inset-0 object-cover w-full h-full">
            <div class="absolute inset-0 bg-gradient-to-r from-orcha-navy via-orcha-navy/85 to-orcha-navy/40"></div>

            <div class="relative grid items-center gap-6 p-7 sm:p-10 lg:grid-cols-[1.4fr_1fr] lg:p-14">
                <div class="reveal">
                    <h2 class="text-2xl font-extrabold leading-tight text-white font-heading sm:text-3xl lg:text-4xl">
                        Sudah punya tanggalnya? Kami siap berangkat.
                    </h2>
                    <p class="max-w-xl mt-3 text-sm leading-relaxed text-slate-200 sm:text-base">
                        Dari satu keluarga sampai satu sekolah, kami yang menyiapkan rute, armada, dan pendampingnya.
                    </p>
                    <div class="flex flex-col gap-3 mt-7 sm:flex-row">
                        <a href="{{ $wa('Halo Orcha Journey, saya mau tanya jadwal dan harga.') }}" target="_blank"
                            rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Rencanakan Perjalanan
                        </a>
                        <a href="{{ route('paket-wisata') }}" class="btn-orcha btn-orcha-ghost">
                            Lihat Paket Wisata
                            <span class="btn-panah"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                        </a>
                    </div>
                </div>

                <div class="hidden lg:block justify-self-end kartu-melayang w-60">
                    <p class="text-[0.65rem] font-bold tracking-wider uppercase text-slate-400">Yang baru</p>
                    <p class="mt-1 text-sm font-bold text-orcha-navy">Destinasi &amp; cerita perjalanan</p>
                    <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 mt-3 text-xs font-bold text-orcha-ocean hover:underline">
                        Baca di blog kami
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
