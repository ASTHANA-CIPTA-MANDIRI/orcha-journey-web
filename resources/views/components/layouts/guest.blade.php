<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        /*
         | Keterangan halaman diambil per rute — lihat App\Support\Seo.
         |
         | $seoKeterangan dan $seoGambar boleh diisi halaman yang punya isi
         | sendiri (paket wisata, destinasi): kalimat pemasaran paket jauh
         | lebih berguna sebagai cuplikan hasil pencarian daripada kalimat
         | umum tentang Orcha.
         */
        $judulHalaman = $title ?? 'Orcha Journey — Open Trip, Private Trip, Study Tour & Sewa Kendaraan';
        $keterangan = \App\Support\Seo::keterangan(null, $seoKeterangan ?? null);
        $kanonis = \App\Support\Seo::kanonis();
        $gambarSeo = $seoGambar ?? asset('og-orcha.jpg');
        $bolehIndeks = \App\Support\Seo::bolehDiindeks();
    @endphp

    <title>{{ $judulHalaman }}</title>
    <meta name="description" content="{{ $keterangan }}">
    <meta name="theme-color" content="#16211c">

    {{-- Satu halaman bisa dicapai lewat beberapa alamat — dengan atau tanpa
         garis miring, dengan tempelan ?utm_source. Tanpa kanonis, mesin pencari
         memperlakukannya sebagai halaman berbeda dan nilai satu halaman
         terpecah ke beberapa alamat. --}}
    <link rel="canonical" href="{{ $kanonis }}">

    {{-- Bukti kepemilikan situs untuk Google Search Console.

         Ditaruh di layout, jadi melekat di SELURUH halaman publik — Google
         memeriksanya di beranda, tetapi halaman yang kebetulan diperiksa lebih
         dulu tidak boleh kehilangannya.

         Tokennya bukan rahasia: ia memang dirancang untuk tampil di sumber
         halaman. Yang perlu dijaga justru jangan sampai hilang — begitu
         lenyap, Google mencabut verifikasinya dan laporannya ikut tertutup. --}}
    @if (config('orcha.verifikasi_google'))
        <meta name="google-site-verification" content="{{ config('orcha.verifikasi_google') }}">
    @endif

    {{-- Formulir dan halaman sekali pakai dijauhkan dari hasil pencarian.
         Tidak ada yang mencarinya lewat Google, dan yang muncul justru
         menyesatkan — halaman riwayat kesehatan bahkan memuat pertanyaan
         medis peserta. --}}
    @unless ($bolehIndeks)
        <meta name="robots" content="noindex, nofollow">
    @endunless

    {{-- Pratinjau tautan saat dibagikan di WhatsApp, Facebook, dan X.
         Sebagian besar pengunjung Orcha datang dari tautan yang diteruskan di
         grup WhatsApp, dan tautan tanpa gambar serta judul hanya tampil
         sebagai alamat mentah yang jarang diketuk. --}}
    <meta property="og:type" content="{{ $seoJenis ?? 'website' }}">
    <meta property="og:site_name" content="Orcha Journey">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $judulHalaman }}">
    <meta property="og:description" content="{{ $keterangan }}">
    <meta property="og:url" content="{{ $kanonis }}">
    <meta property="og:image" content="{{ $gambarSeo }}">
    <meta property="og:image:alt" content="{{ $judulHalaman }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $judulHalaman }}">
    <meta name="twitter:description" content="{{ $keterangan }}">
    <meta name="twitter:image" content="{{ $gambarSeo }}">


    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- Foto layar muat, diminta dari <head>.

         Tanpa baris ini peramban baru mengetahui foto ini setelah membaca
         ratusan kilobita penanda halaman dan menunggu berkas tampilan tiba —
         padahal foto inilah yang pertama terlihat. Diminta dari sini, ia
         berangkat berbarengan dengan yang lain, bukan mengantre di belakang. --}}
    <link rel="preload" as="image" href="{{ asset('images/HERO/destinasi.webp') }}"
        type="image/webp" fetchpriority="high">

    {{-- Huruf dimuat TANPA menahan halaman tergambar.

         Berkas tampilan biasa menahan: peramban menolak menggambar apa pun
         sampai berkas itu tiba, dan berkas ini datang dari server lain yang
         perlu dicari alamatnya lalu disalami dulu. Diberi media="print",
         peramban tetap mengunduhnya tetapi tidak lagi menganggapnya syarat
         untuk menggambar; begitu tiba, onload mengembalikannya ke "all".

         Aman karena hurufnya sudah memakai display=swap: tulisan tampil
         seketika dengan huruf bawaan perangkat, lalu berganti sendiri. --}}
    @php
        $tautanHuruf = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Great+Vibes&display=swap';
    @endphp
    <link rel="preload" as="style" href="{{ $tautanHuruf }}">
    <link rel="stylesheet" href="{{ $tautanHuruf }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ $tautanHuruf }}"></noscript>

    @php
        /*
         | Dirakit di blok @php lalu dikirim sebagai SATU variabel ke @json.
         |
         | Menaruh senarai bertingkat langsung di dalam @json(...) membuat
         | Blade salah menghitung kurungnya dan berkas hasil kompilasinya
         | tidak lagi sah — galatnya muncul sebagai "Unclosed [", jauh dari
         | tempat sebenarnya.
         */
        $skemaSitus = [
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            'name' => 'Orcha Journey',
            'url' => url('/'),
            'image' => asset('og-orcha.jpg'),
            'description' => \App\Support\Seo::keterangan('home'),
            'telephone' => '+'.config('orcha.whatsapp'),
            'email' => config('orcha.email'),
            'areaServed' => ['Yogyakarta', 'Jawa Tengah', 'Jawa Timur'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => config('orcha.alamat'),
                'addressCountry' => 'ID',
            ],
            'sameAs' => array_values(array_filter([
                config('orcha.instagram')
                    ? 'https://instagram.com/'.ltrim(config('orcha.instagram'), '@')
                    : null,
            ])),
        ];
    @endphp

    {{-- Data terstruktur: memberi tahu mesin pencari APA situs ini, bukan
         sekadar kata-kata di halamannya. Yang dipakai TravelAgency, bukan
         Organization umum — itu yang membuat Google boleh menampilkan alamat,
         nomor telepon, dan wilayah layanan langsung di hasil pencarian.

         Ditulis di layout supaya melekat di SELURUH halaman: mesin pencari
         boleh mendarat di halaman mana pun lebih dulu, tidak selalu beranda. --}}
    <script type="application/ld+json">
        {!! \App\Support\SkemaJson::tag($skemaSitus) !!}
    </script>

    {{-- Data terstruktur khusus halaman ini, bila ada.

         Komentar ini pernah menyebut paket wisata mengirim TouristTrip dan FAQ
         mengirim daftar tanyanya, padahal tidak satu pun halaman pernah
         mengisi $seoSkema — rencana yang tidak jadi, lalu komentarnya
         tertinggal dan terbaca seperti sudah dikerjakan.

         Halaman yang punya data terstruktur sendiri sekarang menggambarnya di
         berkasnya masing-masing: paket wisata (TouristTrip berikut harganya),
         destinasi (TouristAttraction), dan artikel blog (BlogPosting). Yang
         tahu bentuk akhir datanya memang halamannya, bukan layout.

         Jalur ini dibiarkan tetap ada untuk halaman yang lebih pas
         mengirimkannya lewat sini. --}}
    @isset($seoSkema)
        <script type="application/ld+json">
            {!! \App\Support\SkemaJson::tag($seoSkema) !!}
        </script>
    @endisset

    {{-- ============ GOOGLE ANALYTICS ============

         Dijalankan hanya bila TIGA hal terpenuhi sekaligus.

         1. IDnya terisi.

         2. Aplikasinya berjalan di production. Tanpa ini, tiap kali kita
            membuka halaman di laptop sendiri angkanya ikut tercatat, dan
            laporan yang dipakai mengambil keputusan tercampur lalu lintas
            orang yang sedang mengoding.

         3. Halamannya boleh diindeks. Ini bukan soal SEO melainkan PRIVASI:
            halaman yang tidak boleh diindeks adalah formulir sekali pakai, dan
            sebagian membawa data pelanggan DI ALAMATNYA —
            /riwayat-kesehatan?kode=OT-1608-ZT8K&nama=... GA4 merekam alamat
            halaman berikut seluruh query-nya, jadi menyalakannya di sana sama
            dengan mengirim kode pesanan dan nama peserta ke Google.

            Halaman riwayat kesehatan bahkan memuat jawaban pertanyaan medis.
            Yang tidak dikirim tidak bisa bocor. --}}
    @php
        $analitik = config('orcha.analitik_google');
        $pixel = config('orcha.meta_pixel');

        /*
         | Satu penjagaan untuk KEDUA pelacak — Google Analytics dan Meta Pixel.
         |
         | Dijadikan satu variabel supaya tidak mungkin salah satunya lolos:
         | dua syarat yang ditulis terpisah lambat laun berbeda, dan yang
         | berbeda diam-diam justru yang berbahaya.
         */
        $bolehMelacak = app()->isProduction() && $bolehIndeks;
        $bolehAnalitik = filled($analitik) && $bolehMelacak;
        $bolehPixel = filled($pixel) && $bolehMelacak;
    @endphp

    @if ($bolehAnalitik)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analitik }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', @json($analitik), {
                // Alamat halaman dikirim TANPA query.
                //
                // Penjagaan lapis kedua: halaman jualan pun bisa membawa
                // tempelan ?utm_source atau ?cari=..., dan yang terakhir itu
                // ketikan pengunjung sendiri. Yang berguna bagi laporan cuma
                // jalur halamannya.
                page_location: window.location.origin + window.location.pathname,
                anonymize_ip: true,
            });
        </script>
    @endif

    {{-- ============ META PIXEL ============

         Penjagaannya sama dengan Google Analytics, dan di sini lebih penting
         lagi.

         Meta menyalakan "Otomatis sertakan info halaman dan produk yang lebih
         detail" secara bawaan. Fitur itu tidak sekadar mencatat alamat: ia
         MEMBACA ISI HALAMAN dengan AI — judul, ulasan, harga, nama entitas —
         lalu mengirimkannya ke Meta.

         Di /riwayat-kesehatan, yang dibacanya adalah jawaban pertanyaan medis
         peserta; di /konfirmasi-pembayaran, nama dan nominal transfernya.
         Karena itu Pixel tidak dimuat sama sekali di halaman-halaman itu —
         bukan sekadar dikurangi datanya. Yang tidak dimuat tidak bisa
         membaca apa pun. --}}
    @if ($bolehPixel)
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', @json($pixel));
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none" alt=""
            src="https://www.facebook.com/tr?id={{ $pixel }}&ev=PageView&noscript=1"></noscript>
    @endif

    @vite(['resources/css/new-homepage.css', 'resources/js/new-homepage.js'])

    {{-- x-cloak dipakai di beberapa tempat pada tata letak ini, tetapi aturan
         yang benar-benar menyembunyikannya tidak pernah ada — jadi selama ini
         atributnya tidak melakukan apa pun, dan menu yang seharusnya tertutup
         berkedip terbuka sepersekian detik pada tiap pemuatan halaman.

         Ditulis sebaris, bukan di berkas CSS: ia harus berlaku sebelum satu
         pun lembar gaya selesai diunduh, dan justru pada saat itulah kedipan
         terjadi. --}}
    <style>[x-cloak] { display: none !important; }</style>
</head>

@php
    $waNumber = config('orcha.whatsapp');
    $waLink = 'https://api.whatsapp.com/send?phone=' . $waNumber . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya soal paket wisata.');
    // Menu utama. Tiap butir menyimpan sendiri kapan dianggap "sedang dibuka",
    // sehingga penanda halaman aktif ikut menyala di menu desktop dan mobile.
    // 'ikon' hanya dipakai menu mobile. Di layar sempit menunya berupa daftar
    // tegak yang panjang, dan tanpa ikon tiap barisnya terlihat sama — mata
    // harus membaca satu per satu. Menu desktop tetap teks saja: berjajar
    // mendatar, ikon di situ justru menambah keramaian tanpa menambah petunjuk.
    $navLinks = [
        ['label' => 'Beranda', 'url' => route('home'), 'aktif' => request()->routeIs('home'), 'ikon' => 'heroicon-o-home'],
        ['label' => 'Tentang Kami', 'url' => route('tentang-kami'), 'aktif' => request()->routeIs('tentang-kami'), 'ikon' => 'heroicon-o-users'],
        ['label' => 'Paket Wisata', 'url' => route('paket-wisata'), 'aktif' => request()->routeIs('paket-wisata'), 'ikon' => 'heroicon-o-map'],
        ['label' => 'Sewa Kendaraan', 'url' => route('sewa-kendaraan'), 'aktif' => request()->routeIs('sewa-kendaraan'), 'ikon' => 'heroicon-o-truck'],
        ['label' => 'Destinasi', 'url' => route('destinasi'), 'aktif' => request()->routeIs('destinasi'), 'ikon' => 'heroicon-o-map-pin'],
        ['label' => 'Blog', 'url' => route('blog'), 'aktif' => request()->routeIs('blog*'), 'ikon' => 'heroicon-o-newspaper'],
        ['label' => 'Kontak', 'url' => route('kontak'), 'aktif' => request()->routeIs('kontak'), 'ikon' => 'heroicon-o-chat-bubble-left-right'],
    ];
@endphp

<body class="antialiased text-slate-700 bg-white overflow-x-hidden">

    {{-- ============ LAYAR MUAT ============
         Bentuknya mengikuti loader Phoenix Digital; rinciannya beserta
         alasan setiap penyesuaian ada di dalam partial-nya. --}}
    @include('partials.orcha-loader')

    {{-- ============ NAVBAR ============ --}}
    {{-- $navbarPadat: halaman yang TIDAK punya hero gelap memaksanya padat.

         Bilah ini transparan sebelum digulung karena hampir semua halaman
         dibuka oleh foto hero gelap. Halaman artikel blog tidak — isinya
         langsung putih, dan tulisan putih di atas putih membuat menunya
         lenyap sama sekali.

         Bawaannya false, jadi seluruh halaman lain berperilaku persis seperti
         sebelumnya. --}}
    @php $navbarPadat = $navbarSolid ?? false; @endphp

    <nav x-data="{ open: false, scrolled: false }"
        x-init="scrolled = window.scrollY > 20"
        @scroll.window="scrolled = window.scrollY > 20"
        @keydown.escape.window="open = false"
        class="fixed inset-x-0 top-0 z-[900] pt-3 sm:pt-4 transition-all duration-300">

        <div class="container-orcha">
            {{-- TIGA kelompok yang melayang terpisah, bukan satu pita selebar
                 layar: lambang di kiri, tautan di kapsul tengah, tindakan di
                 kanan. Bentuk inilah yang dipakai rancangan acuan.

                 Masing-masing berlatar putih sendiri, jadi terbaca di atas apa
                 pun — foto hero gelap maupun latar krem terang. Sebelumnya
                 bilah ini tembus pandang sebelum digulung, dan di halaman
                 berlatar terang tulisannya lenyap sama sekali. --}}
            <div class="flex items-center justify-between gap-2 sm:gap-3">

                {{-- 1. LAMBANG --}}
                <a href="{{ route('home') }}"
                    class="flex items-center gap-2 py-2 pl-2 pr-4 transition rounded-full shrink-0 sm:pr-5 gelembung group">
                    <img src="{{ asset('orcha-logo-only.png') }}" alt="Logo Orcha Journey" width="56" height="56"
                        fetchpriority="high" decoding="async"
                        class="object-contain transition-transform duration-300 w-9 h-9 group-hover:scale-105">
                    <span class="text-base font-extrabold tracking-tight sm:text-lg font-heading text-orcha-navy">
                        Orcha <span class="text-orcha-amber">Journey</span>
                    </span>
                </a>

                {{-- 2. TAUTAN.

                     Ambangnya xl (1280px), bukan lg (1024px). Pada 1024px —
                     lebar iPad Pro tegak — menu lengkap ini muncul padahal
                     ruangnya tidak cukup: tujuh tautan dan satu tombol ajakan
                     melipat jadi dua baris lalu menabrak logo.

                     flex-nowrap dan whitespace-nowrap menjaga janji itu: bila
                     tautannya bertambah dan tetap tidak muat, ia meluber keluar
                     dan langsung terlihat saat diuji — bukan melipat diam-diam
                     jadi dua baris yang tampak "hampir rapi". --}}
                <div class="items-center hidden px-2 py-1.5 rounded-full xl:flex flex-nowrap gelembung">
                    {{-- Halaman yang sedang dibuka ditandai lewat WARNA tulisannya
                         saja, bukan bidang berwarna di belakangnya: pada rancangan
                         acuan seluruh tautan duduk rata di dalam satu kapsul. --}}
                    @foreach ($navLinks as $item)
                        <a href="{{ $item['url'] }}" @if ($item['aktif']) aria-current="page" @endif
                            class="px-3.5 py-2 text-sm transition rounded-full whitespace-nowrap {{ $item['aktif'] ? 'font-bold text-orcha-navy' : 'font-semibold text-slate-500 hover:text-orcha-navy' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                {{-- 3. TINDAKAN --}}
                <div class="flex items-center gap-2 shrink-0">
                    {{-- Kaca pembesar pada rancangan acuan: di sini ia menuju
                         pencarian yang memang ada — daftar destinasi. Ikon yang
                         tidak membuka apa pun lebih buruk daripada tidak ada. --}}
                    <a href="{{ route('destinasi') }}" aria-label="Cari destinasi"
                        class="items-center justify-center hidden transition rounded-full w-11 h-11 sm:flex gelembung text-orcha-navy hover:text-orcha-amber">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                    </a>

                    {{-- Disembunyikan lewat PEMBUNGKUS, bukan kelas hidden di
                         tombolnya sendiri.

                         .btn-orcha menetapkan display: inline-flex di berkas
                         gaya yang tidak berada di dalam @layer, sedangkan
                         utilitas hidden milik Tailwind v4 ada di dalamnya —
                         dan CSS tanpa layer selalu menang. Ditempel langsung,
                         tombolnya tetap tampil di ponsel dan menghimpit tombol
                         menu sampai terpotong tepi layar. --}}
                    <span class="hidden xl:block">
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-sun !py-2.5 !px-6 !text-sm whitespace-nowrap">
                            Rencanakan Trip
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </a>
                    </span>

                {{-- Tiga garis yang BERUBAH jadi silang, bukan dua gambar yang
                     bertukar tempat. Bertukar gambar, perubahannya terjadi
                     seketika dan tidak menerangkan apa-apa; bergerak, ia
                     menunjukkan bahwa tombol yang sama kini menutup apa yang
                     tadi dibukanya. --}}
                    <button type="button" @click="open = !open"
                        :aria-label="open ? 'Tutup menu' : 'Buka menu'" :aria-expanded="open"
                        :class="open ? 'menu-tombol-buka' : ''"
                        class="flex items-center justify-center transition rounded-full menu-tombol xl:hidden gelembung text-orcha-navy">
                        <span class="menu-garis"></span>
                        <span class="menu-garis"></span>
                        <span class="menu-garis"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ============ MENU MOBILE ============
             Panel bertirai, bukan panel yang menempel begitu saja di bawah
             kepala halaman.

             Sebelumnya halaman di belakangnya tetap terlihat penuh dan tetap
             bisa digulung, jadi menunya terbaca sebagai potongan halaman —
             bukan sebagai lapisan yang sedang dibuka. Tirai gelap memisahkan
             keduanya, dan gulirnya dikunci selama menu terbuka. --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="open = false"
            {{-- Dimulai di bawah bilah kepala (top-16 = tinggi bilah saat menu
                 terbuka), bukan dari puncak layar.

                 Menutupi seluruh layar, tirainya duduk di belakang latar navbar
                 yang tembus 95% dan ikut mengusamkan logonya — terlihat kusam
                 tanpa sebab yang jelas. --}}
            class="fixed inset-x-0 bottom-0 top-20 bg-orcha-navy/45 backdrop-blur-[2px] xl:hidden"
            aria-hidden="true">
        </div>

        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-3"
            x-effect="document.body.style.overflow = open ? 'hidden' : ''"
            class="menu-mobile xl:hidden">
            <div class="container-orcha">
                <div class="py-4">
                    {{-- Tiap butir jadi ubin, bukan baris bergaris tipis. Ikonnya
                         memberi mata titik pegangan, dan yang sedang dibuka
                         ditandai dengan bidang berwarna — bukan cuma titik kecil
                         di ujung kanan yang mudah terlewat. --}}
                    <div class="grid gap-1.5">
                        @foreach ($navLinks as $item)
                            <a href="{{ $item['url'] }}" @click="open = false"
                                @if ($item['aktif']) aria-current="page" @endif
                                class="menu-ubin {{ $item['aktif'] ? 'menu-ubin-aktif' : '' }}">
                                <span class="menu-ubin-ikon">
                                    <x-dynamic-component :component="$item['ikon']" class="w-5 h-5" />
                                </span>
                                <span class="menu-ubin-teks">{{ $item['label'] }}</span>
                                @if ($item['aktif'])
                                    <span class="menu-ubin-tanda">Sedang dibuka</span>
                                @else
                                    <x-heroicon-o-chevron-right class="w-4 h-4 menu-ubin-panah" />
                                @endif
                            </a>
                        @endforeach
                    </div>

                    {{-- Cara menghubungi ditaruh di dasar panel, terpisah garis:
                         itu tindakan, bukan halaman, dan menaruhnya sebaris
                         dengan menu membuat keduanya terlihat setara. --}}
                    <div class="menu-kaki">
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-sun w-full">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Hubungi via WhatsApp
                        </a>

                        <p class="menu-kaki-jam">
                            <x-heroicon-s-clock class="w-4 h-4" />
                            Setiap hari, 08.00 &ndash; 21.00 WIB
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </nav>

    <main>
        {{ $slot }}
    </main>

    {{-- ============ FOOTER ============ --}}
    <footer id="kontak" class="relative overflow-hidden border-t text-slate-600 bg-orcha-sand border-black/5">
        <div class="absolute inset-0 pointer-events-none opacity-70"
            style="background-image: radial-gradient(60% 50% at 15% 0%, rgba(255,122,61,.10), transparent 70%), radial-gradient(50% 40% at 90% 100%, rgba(255,122,61,.07), transparent 70%);">
        </div>

        <div class="relative container-orcha py-14 sm:py-20">
            {{-- Di layar sempit isinya dipusatkan, dari sm ke atas kembali rata kiri.

                 Satu kolom selebar layar dengan teks rata kiri menyisakan pias kosong
                 yang lebar di kanan tiap baris — terbaca seperti tata letak yang
                 belum selesai. Dipusatkan, tiap blok punya sumbu yang sama dan
                 footernya terbaca sebagai satu kesatuan.

                 Begitu kolomnya berdampingan (sm ke atas), rata kiri justru yang
                 benar: mata menyusuri daftar tautan dari satu tepi yang tetap. --}}
            <div class="grid gap-10 text-center sm:text-left sm:grid-cols-2 lg:grid-cols-12 lg:gap-8">

                <div class="lg:col-span-4">
                    <div class="flex items-center justify-center gap-3 mb-5 sm:justify-start">
                        <img src="{{ asset('orcha-logo-only.png') }}" alt="Orcha Journey" width="48" height="48"
                            loading="lazy" decoding="async" class="object-contain w-12 h-12">
                        <span class="text-xl font-extrabold tracking-tight text-orcha-navy font-heading">Orcha <span
                                class="text-orcha-amber">Journey</span></span>
                    </div>
                    <p class="max-w-sm mx-auto text-sm leading-relaxed text-slate-500 sm:mx-0">
                        Travel agent Yogyakarta untuk open trip, private trip, study tour, dan sewa kendaraan
                        pariwisata. Harga transparan, armada terawat, dan pemandu yang paham medan.
                    </p>
                    <div class="flex justify-center gap-3 mt-6 sm:justify-start">
                        <a href="https://www.instagram.com/{{ config('orcha.instagram') }}/" target="_blank"
                            rel="noopener noreferrer" aria-label="Instagram Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-black/5 bg-white text-orcha-navy hover:bg-orcha-sun hover:text-white hover:border-orcha-sun">
                            <x-bi-instagram class="w-5 h-5" />
                        </a>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                            aria-label="WhatsApp Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-black/5 bg-white text-orcha-navy hover:bg-orcha-sun hover:text-white hover:border-orcha-sun">
                            <x-bi-whatsapp class="w-5 h-5" />
                        </a>
                        <a href="mailto:{{ config('orcha.email') }}" aria-label="Email Orcha Journey"
                            class="flex items-center justify-center transition border rounded-full w-11 h-11 border-black/5 bg-white text-orcha-navy hover:bg-orcha-sun hover:text-white hover:border-orcha-sun">
                            <x-heroicon-o-envelope class="w-5 h-5" />
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-xs font-bold tracking-[0.2em] uppercase text-orcha-amber">Layanan</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('paket-wisata', 'open-trip') }}"
                                class="transition hover:text-orcha-amber">Open Trip</a></li>
                        <li><a href="{{ route('paket-wisata', 'private-trip') }}"
                                class="transition hover:text-orcha-amber">Private Trip</a></li>
                        <li><a href="{{ route('paket-wisata', 'study-tour') }}"
                                class="transition hover:text-orcha-amber">Study Tour</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'mobil') }}"
                                class="transition hover:text-orcha-amber">Sewa Mobil</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'hiace') }}"
                                class="transition hover:text-orcha-amber">Sewa HiAce</a></li>
                        <li><a href="{{ route('sewa-kendaraan', 'bus') }}"
                                class="transition hover:text-orcha-amber">Sewa Bus</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-xs font-bold tracking-[0.2em] uppercase text-orcha-amber">Jelajahi</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('home') }}" class="transition hover:text-orcha-amber">Beranda</a></li>
                        <li><a href="{{ route('tentang-kami') }}" class="transition hover:text-orcha-amber">Tentang
                                Kami</a></li>
                        <li><a href="{{ route('destinasi') }}" class="transition hover:text-orcha-amber">Destinasi</a>
                        </li>
                        <li><a href="{{ route('testimoni') }}" class="transition hover:text-orcha-amber">Testimoni</a>
                        </li>
                        <li><a href="{{ route('blog') }}" class="transition hover:text-orcha-amber">Blog</a></li>
                        <li><a href="{{ route('pendaftaran-open-trip') }}"
                                class="transition hover:text-orcha-amber">Daftar Open Trip</a></li>
                        <li><a href="{{ route('kontak') }}" class="transition hover:text-orcha-amber">Kontak</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-xs font-bold tracking-[0.2em] uppercase text-orcha-amber">Informasi</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="{{ route('faq') }}" class="transition hover:text-orcha-amber">FAQ</a></li>
                        <li><a href="{{ route('syarat-ketentuan') }}" class="transition hover:text-orcha-amber">Syarat &
                                Ketentuan</a></li>
                        <li><a href="{{ route('ketentuan-pembayaran') }}"
                                class="transition hover:text-orcha-amber">Pembayaran & DP</a></li>
                        <li><a href="{{ route('kebijakan-pengembalian') }}"
                                class="transition hover:text-orcha-amber">Pengembalian Dana</a></li>
                        <li><a href="{{ route('kebijakan-privasi') }}" class="transition hover:text-orcha-amber">Kebijakan
                                Privasi</a></li>
                        {{-- Paling atas di kelompok ini: "pesanan saya bagaimana?"
                             ditanyakan jauh lebih sering daripada ketiga formulir di
                             bawahnya, dan halaman ini juga pintu menuju ketiganya. --}}
                        <li><a href="{{ route('lacak-pesanan') }}" class="transition hover:text-orcha-amber">Lacak
                                Pesanan Saya</a></li>
                        <li><a href="{{ route('riwayat-kesehatan') }}" class="transition hover:text-orcha-amber">Riwayat
                                Kesehatan Peserta</a></li>
                        <li><a href="{{ route('konfirmasi-pembayaran') }}"
                                class="transition hover:text-orcha-amber">Konfirmasi Pembayaran</a></li>
                        <li><a href="{{ route('pembatalan') }}" class="transition hover:text-orcha-amber">Ajukan
                                Pembatalan</a></li>
                    </ul>
                </div>

                <div class="lg:col-span-2">
                    <h4 class="mb-4 text-xs font-bold tracking-[0.2em] uppercase text-orcha-amber">Kontak</h4>
                    <ul class="space-y-4 text-sm">
                        <li class="flex items-start justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-map-pin class="w-5 h-5 shrink-0 mt-0.5 text-orcha-amber" />
                            {{-- Lebarnya dibatasi di layar sempit supaya ikon dan teks
                                 terbaca sebagai satu kelompok yang dipusatkan. Dibiarkan
                                 selebar baris, alamat yang panjang mendorong ikonnya
                                 menempel ke tepi kiri layar sementara teksnya di tengah —
                                 keduanya terlihat tidak berhubungan. --}}
                            <span class="max-w-[15rem] sm:max-w-none">{{ config('orcha.alamat') }}</span>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-phone class="w-5 h-5 shrink-0 text-orcha-amber" />
                            <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                                class="transition hover:text-orcha-amber">+{{ config('orcha.whatsapp') }}</a>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-envelope class="w-5 h-5 shrink-0 text-orcha-amber" />
                            <a href="mailto:{{ config('orcha.email') }}"
                                class="transition hover:text-orcha-amber">{{ config('orcha.email') }}</a>
                        </li>
                        <li class="flex items-center justify-center gap-3 sm:justify-start">
                            <x-heroicon-s-clock class="w-5 h-5 shrink-0 text-orcha-amber" />
                            <span>Setiap hari, 08.00 – 21.00 WIB</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="flex flex-col items-center gap-2 pt-8 mt-12 text-xs text-center border-t border-black/5 text-slate-400 sm:flex-row sm:justify-between sm:text-left">
                <p>&copy; {{ date('Y') }} Orcha Journey &mdash; {{ config('orcha.perusahaan') }}.
                    Seluruh hak cipta dilindungi.</p>
                {{-- Slogannya, bukan nama kota: kotanya sudah tersebut di blok
                     alamat tepat di atas baris ini. --}}
                <p>{{ config('orcha.slogan') }}</p>
            </div>
        </div>
    </footer>

    {{-- ============ TOMBOL WHATSAPP MELAYANG ============ --}}
    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer" aria-label="Chat WhatsApp Orcha Journey"
        class="fixed z-[800] flex items-center gap-2 px-4 py-3 font-bold text-white transition-transform duration-300 bg-green-600 rounded-full shadow-2xl bottom-5 right-5 sm:bottom-8 sm:right-8 hover:scale-105">
        <x-bi-whatsapp class="w-6 h-6" />
        <span class="hidden text-sm sm:inline">Chat Kami</span>
    </a>
</body>

</html>
