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
                'gambar' => 'open-trip',
                'title' => 'Open Trip',
                'desc' => 'Gabung rombongan, biaya patungan.',
                'tautan' => route('paket-wisata', 'open-trip'),
            ],
            [
                'gambar' => 'private-trip',
                'title' => 'Private Trip',
                'desc' => 'Rombongan sendiri, rute ikut Anda.',
                'tautan' => route('paket-wisata', 'private-trip'),
            ],
            [
                'gambar' => 'study-tour',
                'title' => 'Study Tour',
                'desc' => 'Edukatif untuk sekolah & kampus.',
                'tautan' => route('paket-wisata', 'study-tour'),
            ],
            [
                'gambar' => 'sewa-kendaraan',
                'title' => 'Sewa Kendaraan',
                'desc' => 'Mobil, HiAce, sampai bus.',
                'tautan' => route('sewa-kendaraan'),
            ],
        ];
    }

    private function bookingSteps(): array
    {
        return [
            [
                // Langkah pertama memakai GAMBAR, bukan ikon garis. Kuncinya
                // 'gambar'; markahnya memilih sendiri mana yang digambar, jadi
                // tiga langkah lain tetap berjalan dengan ikonnya sampai
                // gambarnya tersedia juga.
                'gambar' => 'konsultasi',
                'icon' => 'chat-bubble-left-right',
                'title' => 'Konsultasi',
                'desc' => 'Chat kami di WhatsApp. Sebutkan tujuan, tanggal, dan jumlah peserta.',
            ],
            [
                'gambar' => 'susun-rencana',
                'icon' => 'clipboard-document-check',
                'title' => 'Susun Rencana',
                'desc' => 'Kami kirim itinerary dan rincian biaya, lalu disesuaikan sampai cocok.',
            ],
            [
                'gambar' => 'bayar-dp',
                'icon' => 'credit-card',
                'title' => 'Bayar DP',
                'desc' => 'Kursi dan armada dikunci setelah uang muka masuk. Sisa dilunasi saat berangkat.',
            ],
            [
                'gambar' => 'berangkat',
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
            /*
             * Keempatnya memakai gambar, bukan ikon garis. Cabang @else yang
             * menggambar ikon di markah masih ada sebagai jaring pengaman —
             * ia terpakai kalau suatu saat ada poin baru tanpa 'gambar'.
             */
            ['icon' => 'star', 'gambar' => 'images/ikon-itinerary.webp', 'title' => 'Itinerary disusun sendiri', 'desc' => 'Bukan jadwal salinan; disesuaikan rombongan Anda.'],
            ['icon' => 'shield-check', 'gambar' => 'images/ikon-mitra.webp', 'title' => 'Mitra tepercaya', 'desc' => 'Armada, hotel, dan pemandu yang sudah kami pakai sendiri.'],
            ['icon' => 'chat-bubble-left-right', 'gambar' => 'images/ikon-didampingi.webp', 'title' => 'Didampingi orang, bukan robot', 'desc' => 'Satu nomor yang sama dari tanya-tanya sampai pulang.'],
            ['icon' => 'calendar-days', 'gambar' => 'images/ikon-tanggal.webp', 'title' => 'Tanggal bisa menyesuaikan', 'desc' => 'Geser jadwal atau tambah peserta tanpa mengulang dari awal.'],
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
            /*
             | LIMA, bukan tiga: satu dipakai kartu sorotan dan EMPAT sisanya
             | mengisi penggeser di bawahnya. Dengan tiga, penggesernya hanya
             | berisi dua kartu dan tidak ada yang bisa digeser.
             */
            ->limit(5)
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

            {{-- Gelembung "Tujuan berikutnya" — satu-satunya yang melayang di
                 atas foto, sama seperti rancangan acuan. Disembunyikan di layar
                 sempit: di sana fotonya sendiri sudah kecil, dan gelembungnya
                 menutupi jendela yang justru jadi isi fotonya. --}}
            <div class="hero-kabin-kartu">
                {{-- Pesawat tampak atas yang dimiringkan, bukan pesawat kertas:
                     itu bentuk ikon pada rancangan acuan. --}}
                <span class="gelembung-ikon">
                    <x-bi-airplane-fill class="w-5 h-5 rotate-45" />
                </span>
                <span class="block mt-4 text-sm text-[#9a9289]">Tujuan berikutnya</span>
                <span class="block mt-0.5 text-[1.15rem] font-extrabold leading-[1.15] font-heading text-orcha-navy">
                    Pengalaman yang Lebih Baik
                </span>
                <span class="flex justify-end mt-3 text-orcha-navy">
                    <x-heroicon-o-arrow-right class="w-5 h-5" />
                </span>
            </div>
        </div>

        <div class="hero-kabin-tirai"></div>

        <div class="relative container-orcha hero-kabin-isi">
            <div class="max-w-xl reveal">
                <p class="text-[0.7rem] font-bold tracking-[0.22em] uppercase text-slate-400">
                    {{ config('orcha.slogan') }}
                </p>

                {{-- Jarak huruf dan barisnya dirapatkan: huruf tebal sebesar ini
                     terbaca renggang dan lemas dengan jarak bawaannya. --}}
                <h1 class="mt-4 font-heading font-extrabold text-orcha-navy leading-[1.02] tracking-[-0.018em]"
                    style="font-size: clamp(1.85rem, 5vw, 3.45rem); font-variation-settings: 'SOFT' 40, 'WONK' 1;">
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
                        <x-heroicon-o-arrow-right class="w-5 h-5" />
                    </a>
                    <a href="{{ $wa('Halo Orcha Journey, saya ingin merencanakan perjalanan.') }}"
                        target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-gelembung">
                        Rencanakan Perjalanan
                    </a>
                </div>

            </div>

            {{-- Barisan jaminan berdiri DI LUAR kolom tulisan.

                 Di dalamnya ia terkurung selebar 36rem dan butir ketiga turun
                 ke baris kedua; pada acuan ketiganya berjajar dalam satu baris
                 yang lebih lebar daripada judulnya.

                 Ikonnya duduk di kotak kecil dan tulisannya DI LUAR kotak —
                 bukan pil yang membungkus keduanya. --}}
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3 mt-9 max-w-3xl reveal">
                @foreach ([['paper-airplane', 'Destinasi pilihan'], ['user-group', 'Itinerary per rombongan'], ['shield-check', 'Berangkat tanpa repot']] as [$ikon, $teks])
                    <span class="flex items-center gap-2.5 text-sm text-slate-500">
                        <span class="gelembung-ikon !w-9 !h-9 !rounded-xl">
                            <x-dynamic-component :component="'heroicon-s-' . $ikon" class="w-4 h-4" />
                        </span>
                        {{ $teks }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        {{-- ==================================================================
             DESTINASI POPULER
        =================================================================== --}}
        @if ($destinations->isNotEmpty())
            <section id="destinasi" class="panel-orcha scroll-mt-28">
                <div class="flex flex-wrap items-end justify-between gap-4 mb-6 sm:mb-8 reveal">
                    <div class="max-w-2xl">
                        <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Destinasi Populer</p>
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
                        <a href="{{ route('destinasi') }}" class="kartu-destinasi reveal">
                            <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.webp') }}"
                                alt="{{ $dest->destination_name }}" loading="lazy">

                            {{-- Panel tulisan MENUMPANG di atas foto, menempel ke tepi
                                 bawah kartu. Ditumpuk ke bawah dalam satu kolom,
                                 kartunya terbaca seperti baris tabel bergambar. --}}
                            <span class="kartu-destinasi-isi">
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold sm:text-base text-orcha-navy">
                                        {{ $dest->destination_name }}
                                    </span>
                                    {{-- Dipangkas dua baris: tanpa itu tinggi panelnya ikut
                                         panjang keterangan dan deretan kartunya bergerigi. --}}
                                    <span class="mt-0.5 text-xs leading-snug text-slate-500 line-clamp-2">
                                        {{ $ketDestinasi }}
                                    </span>
                                </span>
                                {{-- Di layar sempit kartunya cuma selebar 130px; keping
                                     panah di sampingnya menyisakan lajur tulisan selebar
                                     tiga kata. Seluruh kartunya memang sudah berupa tautan. --}}
                                <span class="hidden sm:block">
                                    <span class="tombol-bulat !w-9 !h-9">
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
        {{-- Panel GELAP, salah satu dari dua di beranda (satunya #cara-pesan).

             Beranda terukur 73% krem/putih dan cuma 8,7% warna gelap merek, jadi
             warnanya tidak pernah terbaca sebagai warna perusahaan. Dua panel
             gelap ini memotong deretan krem di sepertiga atas dan sepertiga
             bawah, memakai warna yang SUDAH dipakai hero dan ajakan penutup —
             jadi bukan warna baru, melainkan warna yang sama dipakai lebih
             sering. --}}
        <section id="layanan" class="panel-orcha panel-orcha-gelap scroll-mt-28">
            <div class="grid gap-4 mb-6 lg:grid-cols-2 lg:items-end sm:mb-8 reveal">
                <div>
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Cara Berangkat</p>
                    <h2 class="mt-3 judul-panel">Jalan-jalan dengan cara Anda.</h2>
                </div>
                <p class="text-sm leading-relaxed text-slate-300 sm:text-base lg:text-right">
                    Mau berangkat bareng orang baru, satu rombongan keluarga sendiri, satu sekolah,
                    atau cuma butuh kendaraannya saja &mdash; semuanya kami layani.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
                @foreach ($layanan as $item)
                    {{-- !flex-row, pakai tanda seru: .ubin-orcha menetapkan
                         flex-direction: column di berkas CSS ini, dan di luar lapisan
                         utilitas Tailwind — tanpa !important ia selalu menang.

                         Kartunya SATU baris: gambar di kiri, lajur tulisan di kanan,
                         dan panahnya ikut di dasar lajur tulisan. Sebelumnya panah
                         punya barisnya sendiri di bawah; karena gambarnya cuma setinggi
                         barisnya, kolom di bawah gambar jadi kosong melompong. --}}
                    {{-- !px-4: .ubin-orcha memberi padding 1,2rem di kiri-kanan. Di
                         kartu selebar 287px (4 kolom), tiap piksel padding diambil dari
                         lajur tulisan — dan lajur yang sempit membuat kalimatnya pecah
                         jadi tiga baris timpang. Dipotong ke 1rem. --}}
                    <a href="{{ $item['tautan'] }}" class="ubin-orcha ubin-orcha-putih reveal !flex-row items-stretch gap-2 !px-3.5">
                        {{-- Gambar layanan, bukan ikon garis. Tanpa ubin krem di
                             belakangnya: gambarnya berlatar tembus pandang dan
                             punya langit sendiri, jadi ubin berwarna justru
                             bertabrakan dengannya.
                             alt sengaja KOSONG — nama layanannya sudah tertulis
                             persis di sebelahnya, jadi gambarnya hiasan dan tidak
                             perlu dibacakan dua kali oleh pembaca layar. --}}
                        {{-- -ml-2: gambarnya MENEROBOS padding kiri kartu. Ia jadi
                             terlihat besar sampai ke tepi tanpa memakan lebar lajur
                             tulisan — padding yang diterobos itu ruang mati. --}}
                        <img src="{{ asset('images/layanan/' . $item['gambar'] . '.webp') }}"
                            alt="" loading="lazy" width="400" height="320"
                            class="self-center object-contain shrink-0 w-28 h-auto -ml-3 sm:w-32">
                        {{-- justify-center: tulisannya ditaruh rata-tengah terhadap
                             gambar. Gambar 102px vs tulisan 64px — selisih 38px itu
                             kalau ditumpuk di satu sisi terbaca sebagai lubang, kalau
                             dibagi dua terbaca sebagai padding. --}}
                        <span class="flex flex-col justify-center flex-1 min-w-0">
                            {{-- 14px, bukan 16px: nama layanan memakai Fraunces — huruf
                                 berkait yang lebar. Pada 15,2px "Sewa Kendaraan" makan
                                 123px, persis selebar lajurnya, jadi ia patah dua baris
                                 sementara tiga kartu lain tidak. Diukur di halaman uji,
                                 bukan ditaksir. --}}
                            <span class="block text-[0.95rem] font-bold leading-tight font-heading text-orcha-navy">{{ $item['title'] }}</span>
                            {{-- text-pretty: melarang satu kata berdiri sendirian di
                                 baris terakhir. 13px, bukan 14px, supaya keterangan
                                 tiap layanan sama-sama jatuh di dua baris. --}}
                            <span class="block mt-1 text-[0.8125rem] leading-relaxed text-pretty text-slate-500">{{ $item['desc'] }}</span>
                            {{-- TANPA keping panah. Di ubin selebar 287px, panah yang
                                 rata-kanan di bawah tulisan yang rata-kiri menyisakan
                                 bidang kosong ±100x32px di sebelah kirinya, dan itu yang
                                 terbaca "kosong terlalu banyak". Seluruh ubin ini sudah
                                 berupa tautan dan masih terangkat saat disentuh kursor,
                                 jadi penanda "ada lanjutannya" tidak hilang sama sekali. --}}
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
                {{-- Pembungkus TANPA pemangkasan. Section di dalamnya ber-overflow:
                     hidden supaya foto sampulnya terpangkas sudut membulat, jadi paku
                     yang kepalanya menyembul di luar pojok TIDAK BISA ditaruh di dalam
                     section — ia akan terpangkas. Yang menjorok keluar (kepala paku)
                     dipasang di pembungkus ini; yang harus ikut terpangkas (kerutan
                     kertas & lubangnya) tetap di dalam section.

                     h-full pada section: pembungkus ini yang kini jadi anak grid dan
                     meregang setinggi baris, jadi section-nya perlu disuruh ikut
                     setinggi pembungkusnya. --}}
                <div class="relative">
                <section id="paket-sorotan" class="relative h-full overflow-hidden panel-orcha !p-0 !flex !flex-col scroll-mt-28 reveal">
                    {{-- Paku payung yang MENANCAP ke kartu ini.

                         DI DALAM kartu, bukan menjorok keluar pojoknya: section ini
                         ber-overflow:hidden supaya foto sampulnya terpangkas rapi oleh
                         sudut membulat, dan apa pun yang keluar batas ikut terpangkas.

                         aria-hidden + alt kosong: ini hiasan murni. Pembaca layar tidak
                         perlu mendengar "paku payung" sebelum nama paketnya.

                         EMPAT lapis, dari belakang ke depan:

                         1. Cekungan — bayangan lebar yang melebur di sekelilingnya,
                            seolah permukaan kartu tertekan ke bawah di titik tancap.
                         2. Gambar pakunya, diputar pada UJUNG JARUM (origin 49% 100%,
                            diukur dari berkasnya: piksel terbawah ada di 48,9% lebar).
                            Diputar pada pusatnya, ujungnya ikut bergeser dan pakunya
                            terbaca melayang, bukan menancap di satu titik.
                         3. Bayangan jatuh condong ke kiri-bawah, searah kemiringannya —
                            sama arah dengan bayangan kartu-kartu lain di halaman ini.
                         4. BIBIR LUBANG: elips kecil sewarna latar kartu (#fffdfb) yang
                            menimpa ujung jarum, dengan bayangan DI DALAM di sisi atas.
                            Inilah yang bikin ia terbaca mencoblos: ujung jarumnya
                            tertutup permukaan kartu, bukan berhenti di atasnya. Tanpa
                            lapis ini, jarum utuh sampai ke ujung runcingnya selalu
                            terbaca sebagai stiker yang ditempel. --}}
                    {{-- BEKAS TUSUKAN — di dalam section, jadi ikut terpangkas sudut
                         membulat kartu dan tidak pernah melimpah ke latar halaman.

                         Dipusatkan di titik tusuk yang sama dengan pakunya: 36px dari
                         tepi kanan, 36px dari tepi atas.

                         Dua lapis:
                         1. Kerutan — repeating-conic-gradient: jari-jari gelap tipis
                            yang memancar dari titik tusuk, ditutup topeng radial supaya
                            memudar ke luar. Inilah kerut kertas yang tertarik jarum;
                            tanpa ini permukaannya terbaca rata dan pakunya cuma
                            menempel.
                         2. Lubang + cekungannya di tengah.

                         Pekatnya dinaikkan dari percobaan pertama (jari-jari .16 ->
                         .24, pusat .30 -> .38): pada paku sebesar ini efeknya nyaris
                         tidak terlihat di layar biasa, hanya kelihatan saat potretnya
                         diperbesar. --}}
                    <span aria-hidden="true" class="absolute z-10 block rounded-full pointer-events-none"
                        style="top: 8px; right: 8px; width: 56px; height: 56px;
                               background:
                                 radial-gradient(circle at center, rgb(60 40 25 / 0.38) 0%, rgb(60 40 25 / 0.14) 16%, transparent 54%),
                                 repeating-conic-gradient(from 8deg, rgb(60 40 25 / 0.24) 0deg 1.2deg, transparent 1.2deg 13deg);
                               -webkit-mask-image: radial-gradient(circle at center, #000 10%, rgb(0 0 0 / 0.55) 36%, transparent 70%);
                               mask-image: radial-gradient(circle at center, #000 10%, rgb(0 0 0 / 0.55) 36%, transparent 70%);"></span>
                    <span aria-hidden="true" class="absolute z-10 block rounded-[50%] pointer-events-none bg-[#fffdfb]"
                        style="top: 33px; right: 31px; width: 10px; height: 5px;
                               box-shadow: inset 0 2px 2.5px rgb(30 18 8 / 0.65);"></span>

                    <div class="grid flex-1 gap-0 p-2 sm:grid-cols-2 sm:p-2.5">
                        {{-- Berbingkai, bukan rata tepi: kartu unggulan pada rancangan
                             acuan menyisakan pias putih tipis mengelilingi fotonya. --}}
                        {{-- object-CONTAIN, bukan cover — dan di sini itu bukan selera.

                             Sampul paket sering berupa POSTER dengan tulisan yang sudah
                             menyatu di gambarnya: judul besar, "Rp 350.000 /pax · All In",
                             titik jemput. object-cover memotong tepinya, dan yang pertama
                             hilang justru harga dan titik jemput karena keduanya duduk di
                             pinggir. Memotong foto berarti kehilangan pemandangan;
                             memotong poster berarti kehilangan kalimat.

                             Pita kosong yang muncul karena contain ditutup SALINAN
                             gambar yang sama, diburamkan dan dibesarkan sedikit. Dengan
                             begitu panelnya tetap terisi penuh tanpa satu huruf pun
                             terpotong, dan warnanya selalu cocok dengan posternya sendiri
                             — latar polos akan terlihat seperti gambar yang gagal dimuat.

                             scale-110 pada lapis buram itu wajib: blur menarik piksel dari
                             luar tepi, dan tanpa dibesarkan sisinya menipis jadi garis
                             pucat di keempat pinggir. --}}
                        <div class="relative min-h-[14rem] overflow-hidden rounded-[1.1rem] bg-orcha-navy">
                            <img src="{{ $paketSorot->sampul }}" alt="" aria-hidden="true"
                                loading="lazy" decoding="async"
                                class="absolute inset-0 object-cover w-full h-full scale-110 blur-xl opacity-70">
                            <img src="{{ $paketSorot->sampul }}" alt="{{ $paketSorot->name }}" loading="lazy"
                                class="absolute inset-0 object-contain w-full h-full">
                        </div>

                        <div class="flex flex-col p-5 sm:p-6">
                            <p class="label-bagian">Paket Sorotan</p>
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

                    {{-- KEPALA PAKU — di luar section, boleh menjorok lewat pojok.

                         Ujung jarum pada berkasnya ada di sudut KIRI-BAWAH (terukur:
                         1,3% lebar, 99,9% tinggi), jadi gambarnya tidak perlu diputar:
                         letakkan saja sudut kiri-bawah kotaknya di titik tusuk.

                         Lebar 42px, bukan 64px seperti percobaan pertama: paku sebesar
                         itu lebih besar daripada tombol "Lihat Paket" di kartu yang
                         sama, dan hiasan tidak boleh lebih besar daripada tindakan
                         utamanya.

                         Titik tusuk dipatok 40px dari tepi kanan, 44px dari tepi atas
                         kartu. Dari situ: kotak gambar selebar 42px -> right -2px,
                         setinggi 62px -> top -18px. Kepalanya menyembul 18px di atas
                         tepi kartu.

                         JENDELA pemotong: dasarnya 8px di atas ujung jarum, jadi 8px
                         terakhir jarumnya tidak pernah digambar dan sisanya berakhir
                         tumpul — terbaca masuk ke dalam kertas, bukan berdiri di
                         atasnya. Melebar 16px ke kiri-kanan supaya kepala pakunya tidak
                         ikut terpotong tegak lurus. drop-shadow dipasang di jendela,
                         bukan di gambar, supaya bayangannya tidak ikut terpotong. --}}
                    <span aria-hidden="true" class="absolute z-20 pointer-events-none select-none"
                        style="top: -18px; right: -2px; width: 42px; height: 62px;">
                        <span class="absolute block overflow-hidden"
                            style="left: -16px; right: -16px; bottom: 8px; height: 80px; filter: drop-shadow(-5px 7px 5px rgb(60 40 25 / 0.4));">
                            <img src="{{ asset('images/paku-payung.webp') }}" alt="" width="256" height="377"
                                loading="lazy" decoding="async"
                                class="absolute h-auto" style="left: 16px; bottom: -8px; width: 42px;">
                        </span>
                    </span>
                                </div>
            @endif

            <section class="panel-orcha !flex !flex-col reveal">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Kenapa Orcha</p>
                <h2 class="mt-3 judul-panel !text-[clamp(1.35rem,2.2vw,1.9rem)]">Jalan-jalan tanpa repot.</h2>

                <div class="grid flex-1 gap-3 mt-5 sm:grid-cols-2">
                    @foreach ($alasan as $poin)
                        {{-- BERDAMPINGAN di semua lebar: gambar di lajur kiri,
                             judul di lajur kanan.

                             Dulu ubin ini BERTUMPUK di layar lebar, karena ubinnya
                             memang sempit — terukur 205,4px di jendela 1440 — dan
                             gambar 96px di sana menyisakan lajur judul 60px sehingga
                             "Didampingi orang, bukan robot" pecah jadi empat baris
                             satu-kata.

                             Sekarang muat berdampingan karena tiga angkanya diperkecil
                             bersamaan: gambar 96 -> 80px, judul 14 -> 13px, padding
                             19,2 -> 14px. Diukur di halaman uji pada lebar 205,4px yang
                             sama: tinggi keempat ubin jadi PERSIS SAMA (103px), sebab
                             tingginya ditentukan gambar (64px) yang selalu lebih tinggi
                             daripada judulnya — jadi judul yang jumlah barisnya
                             berbeda-beda tidak lagi membuat barisnya bergerigi.

                             !px-3.5: .ubin-orcha memberi padding 1,2rem di kiri-kanan,
                             dan di ubin 205px tiap piksel padding diambil dari lajur
                             judul. --}}
                        <div class="items-center gap-2 ubin-orcha !flex-row !px-3.5">
                            @isset($poin['gambar'])
                                {{-- TANPA ubin putih di belakangnya, dan ukurannya
                                     mengikuti kartu layanan "cara berangkat".

                                     Bentuk pertamanya memakai kotak putih 40px berisi
                                     gambar 32px, dan dua-duanya salah: kotaknya memaksa
                                     gambar mendatar dipotong jadi persegi, dan 32px
                                     terlalu kecil untuk gambar seramai ini — terukur,
                                     ia terbaca sebagai gumpalan warna.

                                     Gambarnya berlatar tembus pandang dan punya langit
                                     sendiri, jadi ubin berwarna di belakangnya justru
                                     bertabrakan — alasan yang sama seperti di kartu
                                     layanan.

                                     -ml-2: gambarnya MENEROBOS padding kiri ubin,
                                     sehingga terlihat besar sampai ke tepi tanpa
                                     memakan lebar lajur tulisan. Padding yang diterobos
                                     itu ruang mati.

                                     w-20/sm:w-24, bukan w-28 seperti kartu layanan:
                                     ubin ini 205px di layar lebar, sementara kartu
                                     layanan 288px. Gambar selebar 112px di sana
                                     menyisakan lajur tulisan 60px, dan judul seperti
                                     "Itinerary disusun sendiri" pecah jadi empat baris.

                                     self-start/md:self-center/lg:self-start — satu
                                     kata yang BERGANTI ARTI mengikuti susunannya. Pada
                                     baris (md) `self-center` menengahkan TEGAK terhadap
                                     tulisan; pada tumpukan ia memusatkan MENDATAR, dan
                                     gambar yang terpusat di atas judul yang rata kiri
                                     terbaca terputus. Jadi dipusatkan hanya saat
                                     berdampingan.

                                     alt KOSONG: judulnya ada tepat di sebelahnya. --}}
                                <img src="{{ asset($poin['gambar']) }}" alt=""
                                    loading="lazy" decoding="async" width="400" height="320"
                                    class="self-center object-contain shrink-0 w-20 h-auto -ml-3">
                            @else
                                <span class="flex items-center justify-center w-10 h-10 bg-white shrink-0 rounded-xl text-orcha-amber">
                                    <x-dynamic-component :component="'heroicon-s-' . $poin['icon']" class="w-5 h-5" />
                                </span>
                            @endisset
                            {{-- 13px, bukan 14px: pada lajur selebar ±111px, 14px
                                 membuat "Itinerary disusun sendiri" pecah tiga baris. --}}
                            <h3 class="text-[0.8125rem] font-bold leading-snug text-orcha-navy">{{ $poin['title'] }}</h3>
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
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Paket Wisata</p>
                    <h2 class="mt-3 judul-panel">Pilih paketnya, sisanya kami urus.</h2>
                </div>

                {{-- "Semua Paket", bukan "Lihat Semua Paket".

                     Terukur di jendela 1440: judulnya 659px dan empat tabnya 556px,
                     sedangkan lebar kepala cuma 1198px — kurang 33px, dan deret tabnya
                     turun ke baris kedua. Kata "Lihat" memakan 47px dan tidak menambah
                     arti apa pun di antara tiga tab yang semuanya nama kategori.
                     Bagian Armada tidak kena karena tabnya tiga, bukan empat. --}}
                <div class="tab-scroller">
                    @foreach ($kategoriPaket as $key => $label)
                        <a href="{{ route('paket-wisata', str_replace('_', '-', $key)) }}" class="tab-orcha">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('paket-wisata') }}" class="tab-orcha tab-orcha-active">Semua Paket</a>
                </div>
            </div>

            @if ($paketLain->isEmpty() && ! $paketSorot)
                <div class="p-10 text-center ubin-orcha">
                    <x-heroicon-o-map class="w-12 h-12 mx-auto text-orcha-toska-samar" />
                    <p class="mt-3 font-semibold text-orcha-navy">Paket wisata sedang disiapkan.</p>
                    <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk penawaran khusus.</p>
                </div>
            @else
                {{-- DERET YANG DIGESER, bukan kisi.

                     Kisinya dulu membungkus barisnya ke bawah dan jumlah
                     lajurnya ikut jumlah paket, supaya tidak ada sel menganga.
                     Sekarang kartunya berjajar satu baris dan digeser mendatar:
                     empat paket terbaru saja, selebihnya lewat "Semua Paket".

                     Lebarnya dipatok di kartunya (lihat .geser-paket-item),
                     bukan dibiarkan ikut lebar panel — kartu selebar panel
                     membuat sampul 16/10-nya menjulang, persis yang ditahan
                     max-h-64 di komponen kartunya.

                     Tanpa JavaScript pun tetap bisa digeser: overflow-x milik
                     peramban sudah menanganinya lewat trackpad, roda tetikus
                     mendatar, dan sentuhan. Skrip penyeretnya hanya menambah
                     seret-dengan-tetikus, yang tidak dipunyai peramban. --}}
                <div class="geser-paket" data-geser
                    role="region" aria-label="Paket wisata terbaru, bisa digeser mendatar" tabindex="0">
                    @foreach ($paketLain->take(4) as $package)
                        <x-paket-wisata.kartu :paket="$package" :lencana-unggulan="false"
                            class="reveal geser-paket-item" />
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
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Sewa Kendaraan</p>
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
                    <x-heroicon-o-truck class="w-12 h-12 mx-auto text-orcha-toska-samar" />
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
        <section id="cara-pesan" class="relative overflow-hidden panel-orcha panel-orcha-gelap scroll-mt-28">
            {{-- Lambang orca sebagai cap air.

                 Di beranda lambangnya cuma muncul dua kali dan kecil-kecil —
                 56px di bilah atas dan 48px di kaki halaman. Di sini ia tampil
                 besar sekali, samar, jadi bentuknya sempat terbaca tanpa
                 mengganggu isinya.

                 aria-hidden + alt kosong: ini hiasan. Nama perusahaannya sudah
                 dibacakan pembaca layar di bilah atas, tidak perlu diulang. --}}
            <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true"
                loading="lazy" width="512" height="512" class="cap-orca">

            <div class="relative max-w-2xl mb-6 reveal">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Cara Pesan</p>
                <h2 class="mt-3 judul-panel">Empat langkah, selesai.</h2>
            </div>

            <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
                @foreach ($bookingSteps as $index => $step)
                    <li class="relative ubin-orcha reveal">
                        {{-- Angka langkah: krem-jingga muda, BUKAN putih.

                             Ubin ini berlatar #fbf7f1 (krem), jadi putih 70% di
                             atasnya praktis tidak terlihat — angkanya ada di markah
                             sejak awal tetapi tidak pernah terbaca. Warna mist
                             (#f8cba8) cukup beda dari latarnya untuk terlihat, dan
                             cukup muda untuk tetap jadi hiasan, bukan saingan judul.
                             Ukurannya 8xl (96px), bukan 5xl: angka ini mengisi sisi
                             kanan ubin yang memang kosong — di sebelah kiri cuma ada
                             gambar 80px dan dua baris tulisan pendek. Sebagai angka
                             kecil ia malah menegaskan kekosongan itu. --}}
                        <span class="absolute text-8xl font-black leading-none select-none top-1 right-4 font-heading text-orcha-toska-samar">
                            {{ $index + 1 }}
                        </span>
                        @isset($step['gambar'])
                            {{-- Tanpa ubin jingga di belakangnya: gambarnya berlatar
                                 tembus pandang dan sudah punya bidang kuning sendiri,
                                 jadi ubin berwarna justru bertabrakan dengannya —
                                 alasan yang sama seperti di kartu layanan.

                                 w-20 (80px): adegan seramai ini — orang, kendaraan,
                                 ponsel, kalender — masih terbaca sebagai gumpalan
                                 warna di 56px, apalagi di 44px seperti ubin ikon.
                                 alt KOSONG, judul langkahnya ada tepat di bawahnya. --}}
                            <img src="{{ asset('images/langkah/' . $step['gambar'] . '.webp') }}"
                                alt="" loading="lazy" decoding="async" width="256" height="256"
                                class="relative object-contain w-20 h-20 mb-2.5 -ml-1.5">
                        @else
                            <span class="relative flex items-center justify-center mb-4 w-11 h-11 rounded-xl bg-orcha-sun text-orcha-navy">
                                <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-6 h-6" />
                            </span>
                        @endisset
                        <h3 class="relative text-base font-bold font-heading text-orcha-navy">{{ $step['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- ==================================================================
             GALERI — korsel 3D, tulisan di samping foto
        =================================================================== --}}
        <section id="galeri" class="overflow-hidden panel-orcha !px-0 scroll-mt-28">
            {{-- DUA LAJUR: tulisan di kiri, foto di kanan.

                 Sebelumnya semuanya rata tengah dan bertumpuk ke bawah — label,
                 judul, foto, lalu kendali — sehingga bagian ini terbaca sama
                 seperti enam bagian lain di halaman yang juga berjudul di atas
                 dan berisi di bawah.

                 Dengan tulisan di samping, keterangan fotonya punya tempat
                 sendiri yang lega: ia tidak lagi menumpang di atas foto sebagai
                 pita kecil, dan ganti foto berarti ganti kalimat di kiri. Foto
                 di kanan boleh menerobos tepi panel, jadi deretnya terbaca
                 berlanjut keluar layar, bukan berhenti di pinggir. --}}
            <div class="grid items-center gap-x-10 gap-y-8 lg:grid-cols-[minmax(0,21rem)_minmax(0,1fr)] px-[clamp(1.35rem,3.4vw,3rem)] lg:pr-0">
                <div class="reveal">
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Galeri</p>
                    <h2 class="mt-3 judul-panel">Momen perjalanan pelanggan kami.</h2>

                    {{-- Keterangan foto yang sedang di tengah, DIBERI PENANDA.

                         Sebagai kalimat polos di bawah judul, ia terbaca sebagai
                         anak kalimat judulnya — pembaca tidak punya cara tahu
                         bahwa kata itu milik foto yang sedang di tengah, apalagi
                         karena ia berganti sendiri tiap empat detik.

                         Penandanya dua lapis: garis tegak di kiri yang
                         memisahkannya dari judul, dan label kecil "FOTO 02 DARI
                         03" di atasnya. Labelnya sekaligus menggantikan
                         penghitung yang tadinya berdiri sendiri di baris kendali
                         — angka yang sama tidak perlu ditulis dua kali.

                         Diisi skrip; kalau skripnya gagal, blok ini tetap kosong
                         dan keterangannya tampil di atas fotonya seperti pada
                         tata letak dasar. --}}
                    <div class="galeri-keterangan" data-korsel-blok hidden>
                        <p class="galeri-keterangan-label" data-korsel-hitung></p>
                        <p class="galeri-keterangan-teks" data-korsel-keterangan aria-live="polite"></p>
                    </div>

                    <div class="galeri-kendali">
                        <button type="button" class="galeri-tombol" data-korsel-mundur aria-label="Foto sebelumnya">
                            <x-heroicon-o-arrow-left class="w-5 h-5" />
                        </button>
                        <button type="button" class="galeri-tombol" data-korsel-maju aria-label="Foto berikutnya">
                            <x-heroicon-o-arrow-right class="w-5 h-5" />
                        </button>
                        <div class="galeri-titik" data-korsel-titik></div>
                    </div>
                </div>

                <div class="galeri-panggung" data-korsel="kipas" aria-roledescription="korsel"
                    aria-label="Galeri momen perjalanan">
                    <div class="galeri-jalur" data-korsel-jalur>
                        @foreach ($galleries as $index => $img)
                            <figure class="galeri-kartu" data-korsel-kartu>
                                <img src="{{ $img['foto'] }}"
                                    alt="{{ $img['keterangan'] ?: 'Dokumentasi perjalanan Orcha Journey' }}"
                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async"
                                    style="object-position: {{ $img['fokus'] ?? '50% 50%' }}">

                                @if ($img['keterangan'])
                                    <figcaption><span>{{ $img['keterangan'] }}</span></figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ==================================================================
             TESTIMONI + AJAKAN
        =================================================================== --}}
        <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr] sm:gap-5">
            @if ($testimonials->isNotEmpty())
                {{-- panel-orcha-krem: latar panel dibuat krem supaya kartu putih
                     di dalamnya punya beda warna yang cukup. --}}
                <section id="testimoni" class="panel-orcha panel-orcha-krem scroll-mt-28">
                    <div class="flex flex-wrap items-end justify-between gap-4 mb-7 reveal">
                        <div class="flex-1 min-w-0">
                            <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Testimoni</p>
                            <h2 class="mt-3 judul-panel">Cerita asli, pelanggan yang senang.</h2>
                        </div>
                        {{-- Toska, warna logo: tautan sekunder di seluruh beranda memakai warna
                             ini supaya warna perusahaan muncul berulang, bukan sekali di
                             sudut kiri atas. --}}
                        <a href="{{ route('testimoni') }}" class="text-sm font-bold text-orcha-toska hover:underline">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    {{-- Korsel bermode TUMPUK, bukan tiga kartu berjajar.

                         Berjajar, ketiganya harus dipendekkan supaya muat — dan
                         testimoni yang dipotong di tengah kalimat ("...saat trip
                         j...") justru melemahkan gunanya: pembaca tidak pernah
                         membaca satu cerita pun sampai habis. Satu kartu besar
                         bergantian memberi ruang untuk kalimat utuh.

                         Mesinnya sama dengan galeri; yang membedakan cuma atribut
                         data-korsel. --}}
                    <div class="galeri-panggung reveal" data-korsel="tumpuk"
                        aria-roledescription="korsel" aria-label="Testimoni pelanggan">
                        <div class="galeri-jalur" data-korsel-jalur>
                            @php
                                $pilihan = $testimonials->take(6);
                                // Kartu pengukur memakai testimoni TERPANJANG — lihat
                                // catatan di CSS: tinggi panggung dipatok ke kartu
                                // terpanjang supaya halaman di bawahnya tidak melompat
                                // tiap kali testimoni berganti.
                                $terpanjang = $pilihan->sortByDesc(fn ($t) => mb_strlen((string) $t->testimonial))->first();
                            @endphp

                            @if ($terpanjang)
                                {{-- Susunannya HARUS sama persis dengan kartu asli —
                                     tanda kutip, kalimat, lalu blok nama — karena
                                     tinggi panggung diukur dari sini. Sempat memakai
                                     kotak kosong 64px sebagai pengganti blok nama, dan
                                     tingginya kurang ±50px sehingga kartu aslinya
                                     menimpa tombol panah di bawahnya. --}}
                                <figure class="galeri-kartu is-pengukur" aria-hidden="true">
                                    <div class="p-5 sm:p-6">
                                        <span class="block font-serif text-4xl leading-none select-none text-orcha-toska-samar">&ldquo;</span>
                                        <p class="mt-1 text-[0.95rem] leading-relaxed sm:text-base text-orcha-navy">
                                            {{ $terpanjang->testimonial }}
                                        </p>
                                        <div class="flex items-center gap-3 pt-4 mt-5 border-t border-orcha-foam">
                                            <span class="w-10 h-10 rounded-full shrink-0 bg-orcha-toska"></span>
                                            <span class="min-w-0">
                                                <span class="block font-bold text-orcha-navy">{{ $terpanjang->customer_name }}</span>
                                                <span class="block w-20 h-4 mt-0.5"></span>
                                            </span>
                                        </div>
                                    </div>
                                </figure>
                            @endif

                            @foreach ($pilihan as $testimoni)
                                <figure class="galeri-kartu" data-korsel-kartu>
                                    <div class="p-5 sm:p-6">
                                        {{-- Tanda kutip besar sebagai jangkar mata.
                                             Digambar sebagai huruf, bukan gambar, jadi
                                             ikut berganti warna dan tidak menambah
                                             permintaan berkas. --}}
                                        <span aria-hidden="true"
                                            class="block font-serif text-4xl leading-none select-none text-orcha-toska-samar">&ldquo;</span>

                                        <p class="mt-1 text-[0.95rem] leading-relaxed sm:text-base text-orcha-navy">
                                            {{ $testimoni->testimonial }}
                                        </p>

                                        <figcaption class="flex items-center gap-3 pt-4 mt-5 border-t border-orcha-foam">
                                            {{-- Inisial SELALU digambar, foto ditumpangkan di
                                                 atasnya. Kalau berkas fotonya hilang —  dan di
                                                 basis data ini ada barisnya yang berkasnya
                                                 sudah tidak ada — ikon rusak bawaan peramban
                                                 yang muncul. Dengan onerror, fotonya
                                                 menyingkir dan inisialnya yang terlihat. --}}
                                            <span class="relative grid w-10 h-10 font-bold text-white rounded-full shrink-0 place-items-center bg-orcha-toska">
                                                {{ mb_strtoupper(mb_substr($testimoni->customer_name, 0, 1)) }}
                                                @if ($testimoni->avatar)
                                                    <img src="{{ $testimoni->avatar }}" alt="{{ $testimoni->customer_name }}"
                                                        loading="lazy" onerror="this.remove()"
                                                        class="absolute inset-0 object-cover w-full h-full rounded-full">
                                                @endif
                                            </span>

                                            <span class="min-w-0">
                                                <span class="block font-bold truncate text-orcha-navy">{{ $testimoni->customer_name }}</span>
                                                <span class="flex items-center gap-0.5 mt-0.5">
                                                    @for ($i = 0; $i < 5; $i++)
                                                        <x-heroicon-s-star class="w-3.5 h-3.5 {{ $i < $testimoni->rating ? 'text-orcha-sun' : 'text-slate-200' }}" />
                                                    @endfor
                                                </span>
                                            </span>
                                        </figcaption>
                                    </div>
                                </figure>
                            @endforeach
                        </div>

                        <div class="galeri-kendali">
                            <button type="button" class="galeri-tombol" data-korsel-mundur aria-label="Testimoni sebelumnya">
                                <x-heroicon-o-arrow-left class="w-5 h-5" />
                            </button>
                            <div class="galeri-titik" data-korsel-titik></div>
                            <button type="button" class="galeri-tombol" data-korsel-maju aria-label="Testimoni berikutnya">
                                <x-heroicon-o-arrow-right class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Pembungkus TANPA pemangkasan. Panel di dalamnya ber-overflow:hidden
                 supaya foto latarnya terpangkas sudut membulat kartu; pohon yang
                 harus MENYEMBUL sedikit ke luar tepi tidak bisa ditaruh di
                 dalamnya. Pola yang sama dipakai paku payung di kartu Paket
                 Sorotan. --}}
            <div class="relative">
                <section class="relative h-full overflow-hidden panel-orcha panel-orcha-gelap reveal">
                {{-- Panorama sebagai LATAR panel, dengan tirai hijau tua di atasnya.

                     Panel ini sebelumnya berlatar foto pantai beropasitas 25% —
                     cukup untuk tekstur, tetapi gambarnya sendiri tidak pernah
                     terbaca. Gambar tegak ini dipakai penuh dan ditutup tirai yang
                     PEKAT DI ATAS, mengikuti letak isinya: label, judul, kalimat,
                     dan tombol semuanya duduk di bagian atas panel. Tirainya
                     menipis ke bawah, tempat panel memang kosong — di situlah
                     perahu dan lautnya terlihat.

                     Dicoba terbalik lebih dulu (pekat di bawah) dan langsung
                     terlihat salah: label jingga "Siap Berangkat?" jatuh tepat di
                     bagian paling terang fotonya dan nyaris tidak terbaca.

                     Bingkai krem bawaan ilustrasinya sudah dipangkas di berkasnya:
                     dipakai sebagai latar ber-object-cover, bingkai itu terpotong
                     dan terbaca sebagai jalur menyambung di tepi panel, bukan
                     sebagai bingkai. --}}
                {{-- object-COVER: panel terisi penuh, dan yang tidak muat dipotong.

                     Terukur: panel 491x495 (rasio 0,99), gambar 900x1072 (0,84),
                     jadi ±15% tingginya terbuang. Sempat dicoba object-contain
                     supaya tidak ada yang terpotong sama sekali, tetapi akibatnya
                     panel tidak terisi penuh dan tersisa pita kosong — dan yang
                     diminta adalah terisi penuh.

                     Bingkai krem bawaan ilustrasinya dipangkas di berkasnya, dan
                     pohon yang dulu menjulur keluar bingkai itu TIDAK lagi diambil
                     dari sini: ia dipasang terpisah di bawah sebagai hiasan sudut,
                     supaya bisa benar-benar keluar dari tepi kartu. --}}
                <img src="{{ asset('images/panorama-tegak.webp') }}" alt="" aria-hidden="true"
                    loading="lazy" decoding="async" width="900" height="1072"
                    class="absolute inset-0 object-cover w-full h-full">
                <div class="absolute inset-0"
                    style="background: linear-gradient(to bottom, rgb(22 33 28 / 0.9) 0%, rgb(22 33 28 / 0.88) 50%, rgb(22 33 28 / 0.35) 100%);"></div>

                {{-- LAPIS DALAM: pohon utuh. Ada DI DALAM section, jadi terpangkas
                     oleh overflow:hidden kartunya — itulah yang membuat batangnya
                     berhenti rapi di garis pinggir kartu. Pasangannya (daun saja)
                     dipasang di luar section, sesudah </section>. --}}
                <img src="{{ asset('images/pohon-sudut.webp') }}" alt="" aria-hidden="true"
                    loading="lazy" decoding="async" width="820" height="552"
                    class="absolute z-10 w-3/4 pointer-events-none select-none -top-7 -left-10 sm:w-4/5 sm:-top-9">


                {{-- pt-24/32: tulisannya dimulai DI BAWAH tajuk pohon.

                     Tanpa itu label "Siap Berangkat?" dan baris pertama judul
                     jatuh tepat di dedaunan paling terang, dan dua-duanya nyaris
                     tidak terbaca. Menurunkan tulisannya lebih baik daripada
                     meredupkan pohonnya: yang diminta justru warnanya hidup. --}}
                <div class="relative pt-24 sm:pt-32">
                    <p class="label-bagian !text-orcha-sun">Siap Berangkat?</p>
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

                {{-- LAPIS LUAR: daun saja, tidak dipangkas apa pun.

                     Pasangannya ada di dalam <section> (pohon utuh). Dua lapis
                     karena dua tuntutan yang berlawanan: batang harus terpangkas
                     tepi kartu — kalau tidak, ujung batang yang rata itu
                     menggantung di latar halaman — sedangkan daun yang menyembul
                     tidak boleh terpangkas apa pun, karena garis pangkasnya
                     sendiri yang terlihat.

                     Berkas pohon-daun.webp dibuat dari berkas yang sama dengan
                     membuang piksel BERONA KAYU (10°-58° dengan kepekatan di atas
                     0,18) — 41.744 piksel batang dan dahan hilang, 162.719 piksel
                     daun tinggal. Celah bekas dahan di lapis ini tidak terlihat:
                     di dalam kartu, lapis pohon utuh di bawahnya yang mengisinya.

                     Letaknya SAMA PERSIS dengan lapis dalam supaya daunnya
                     bertumpuk tepat, bukan bergeser jadi bayangan ganda.

                     Geserannya 40px, bukan 16px. Diukur dari berkasnya: 40 piksel
                     paling kiri hampir kosong (0-263 piksel berisi per pita 10px,
                     dibandingkan 1.500-2.500 di pita selanjutnya), jadi geseran
                     16px tidak mengeluarkan daun apa pun — hanya ruang kosong
                     yang menyembul. --}}
                <img src="{{ asset('images/pohon-daun.webp') }}" alt="" aria-hidden="true"
                    loading="lazy" decoding="async" width="820" height="552"
                    class="absolute z-20 w-3/4 pointer-events-none select-none -top-7 -left-10 sm:w-4/5 sm:-top-9">
            </div>
        </div>

        {{-- ==================================================================
             PARTNER
        =================================================================== --}}
        @if ($partners->isNotEmpty())
            <section class="overflow-hidden panel-orcha !px-0 !py-7">
                {{-- Label disamakan dengan label bagian lain (amber, berjarak huruf,
                     berapit garis). Sebelumnya abu-abu slate-400 — satu-satunya
                     tulisan di halaman ini yang memakai warna itu, dan terbaca
                     seperti keterangan sistem, bukan bagian dari halaman. --}}
                {{-- Pembungkus text-center, BUKAN justify-center di labelnya:
                     .label-bagian itu inline-flex, jadi justify-content hanya
                     menata isi di dalamnya dan tidak pernah memindahkan
                     elemennya sendiri ke tengah. --}}
                <div class="mb-5 text-center">
                    <p class="label-bagian">
                        <span class="w-6 h-px bg-orcha-sun"></span>
                        Didukung mitra tepercaya
                        <span class="w-6 h-px bg-orcha-sun"></span>
                    </p>
                </div>

                @php
                    $jalurPartner = $partners->all();
                    while (count($jalurPartner) < 12) {
                        $jalurPartner = array_merge($jalurPartner, $partners->all());
                    }
                    $jalurPartner = array_merge($jalurPartner, $jalurPartner);
                @endphp

                {{-- Pita berjalan DIBERI TEPI MEMUDAR (pita-mitra).

                     Tanpa itu kedua ujungnya terpotong lurus di tepi panel, dan
                     kartu yang sedang lewat tampak terpenggal — terbaca seperti
                     isi yang kebetulan kepotong, bukan deret yang berjalan terus.

                     Pita ini TIDAK dijadikan korsel 3D seperti galeri dan
                     testimoni: isinya nama mitra yang hanya perlu lewat, bukan
                     dibaca satu per satu. Korsel menuntut orang menekan tombol
                     untuk melihat mitra berikutnya — untuk daftar nama, itu
                     kerja tambahan tanpa imbalan. --}}
                <div class="flex overflow-hidden marquee-wrap pita-mitra">
                    <div class="marquee marquee-slow">
                        @foreach ($jalurPartner as $partner)
                            <div class="flex items-center gap-3 px-5 py-3 bg-white border shrink-0 rounded-2xl border-orcha-foam kartu-mitra">
                                {{-- Inisial SELALU digambar, fotonya ditumpangkan di
                                     atasnya; kalau berkasnya hilang, onerror
                                     menyingkirkan foto dan inisialnya yang terlihat.
                                     Di basis data ini ada mitra yang berkas fotonya
                                     memang sudah tidak ada, dan yang muncul ikon
                                     rusak bawaan peramban. --}}
                                <span class="relative grid w-10 h-10 overflow-hidden font-bold text-white rounded-full shrink-0 place-items-center bg-orcha-toska">
                                    {{ $partner->initials() }}
                                    @if ($partner->foto)
                                        <img src="{{ $partner->foto }}" alt="{{ $partner->partner_name }}"
                                            loading="lazy" onerror="this.remove()"
                                            class="absolute inset-0 object-cover w-full h-full">
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

            {{-- Tirai DUA lapis, bukan satu gradasi mendatar.

                 Sebelumnya satu gradasi navy 100% -> 40% menutup seluruh lebar
                 foto, dan cahaya matahari di sisi kanan ikut teredam sampai
                 fotonya terbaca kelabu. Sekarang: lapis mendatar hanya
                 menggelapkan sisi KIRI tempat tulisan berada lalu hampir habis
                 di tengah, dan lapis tegak menggelapkan tepi bawah tipis saja
                 supaya tombol tetap berpijak. Sisi kanan dibiarkan terang. --}}
            <div class="absolute inset-0 tirai-ajakan"></div>
            <div class="absolute inset-0 tirai-ajakan-bawah"></div>

            <div class="relative grid items-center gap-6 p-7 sm:p-10 lg:grid-cols-[1.4fr_1fr] lg:p-14">
                <div class="reveal">
                    <p class="label-bagian !text-orcha-sun">
                        <span class="w-6 h-px bg-orcha-sun"></span> Siap kapan saja
                    </p>
                    <h2 class="mt-3 text-2xl font-extrabold leading-tight text-white font-heading sm:text-3xl lg:text-4xl">
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

                    {{-- Tiga janji yang MENGULANG isi halaman ini, bukan klaim baru:
                         jam balas dan "dibalas orang" sudah tertulis di kartu
                         ajakan atas, tiga lini layanan sudah jadi kartu sendiri.
                         Sengaja tidak menambah klaim semacam "armada milik
                         sendiri" — kendaraannya datang dari vendor. --}}
                    <ul class="flex flex-wrap gap-x-5 gap-y-2 mt-6 text-xs text-slate-300">
                        @foreach (['Dibalas orang, bukan mesin', 'Rencana & biaya di hari yang sama', 'Open trip, private, study tour'] as $janji)
                            <li class="flex items-center gap-1.5">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-orcha-toska-muda shrink-0" />
                                {{ $janji }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- SELURUH kartu jadi tautan, bukan cuma baris "Baca di blog
                     kami". Sasaran klik sebesar kartu jauh lebih mudah dikenai,
                     dan tidak ada lagi bagian kartu yang terlihat bisa diklik
                     tetapi ternyata mati. --}}
                {{-- Kartu MIRING, mengikuti contoh yang diminta: kotak ikon di
                     kiri, tulisan bertingkat di kanan, baris terakhir berwarna
                     jingga. Miringnya lewat transform 3D, bukan gambar, jadi
                     tulisannya tetap tulisan sungguhan — masih bisa dipilih,
                     dibaca pembaca layar, dan tajam di layar beresolusi apa
                     pun. Saat disentuh kursor ia menegak, menandakan bisa
                     diklik.

                     SELURUH kartu jadi tautan, bukan cuma barisnya. --}}
                <a href="{{ route('blog') }}"
                    class="hidden lg:flex items-center gap-3.5 justify-self-end w-64 kartu-melayang kartu-blog">
                    {{-- Kotak ikon memakai .gelembung-ikon, kelas yang sama dengan
                         kartu di hero: ikut punya sisi tebal di kanan-bawah,
                         jadi seluruh permukaan timbul di halaman ini satu
                         keluarga. Pesawatnya pun ikon yang sama. --}}
                    <span class="gelembung-ikon">
                        <x-bi-airplane-fill class="w-5 h-5 rotate-45" />
                    </span>
                    <span class="min-w-0 leading-tight">
                        <span class="block text-[0.6rem] font-bold tracking-widest uppercase text-slate-400">Yang baru</span>
                        <span class="block mt-1 text-[0.95rem] font-bold text-orcha-navy">Destinasi baru</span>
                        <span class="flex items-center gap-1.5 text-[0.95rem] font-bold text-orcha-sunset">
                            Cerita perjalanan
                            <x-heroicon-o-arrow-right class="w-4 h-4 panah-blog shrink-0" />
                        </span>
                    </span>
                </a>
            </div>
        </section>
    </div>
</div>
