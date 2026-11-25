<?php

use App\Models\SewaKendaraan\Car;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\PaketWisata\TravelPackage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Tentang Kami — Orcha Journey')] class extends Component {
    public function with(): array
    {
        return [
            'angka' => [
                ['label' => 'Destinasi dilayani', 'nilai' => DestinationPopuler::count()],
                ['label' => 'Armada siap jalan', 'nilai' => Car::where('is_available', true)->count()],
                ['label' => 'Paket wisata', 'nilai' => TravelPackage::count()],
                ['label' => 'Ulasan pelanggan', 'nilai' => Testimoni::tayang()->count()],
            ],
            'nilaiKami' => [
                [
                    // Bergambar, bukan ikon garis. Markahnya memilih sendiri mana
                    // yang digambar lewat kunci 'gambar', jadi tiga nilai lain
                    // tetap berjalan dengan ikonnya sampai gambarnya tersedia juga
                    // — pola yang sama dengan kartu layanan & langkah di beranda.
                    'gambar' => 'harga-transparan',
                    'icon' => 'o-banknotes',
                    'judul' => 'Harga Transparan',
                    'isi' => 'Rincian biaya kami tulis apa adanya sejak penawaran pertama. Tidak ada biaya kejutan di tengah perjalanan.',
                ],
                [
                    'gambar' => 'armada-terawat',
                    'icon' => 'o-shield-check',
                    'judul' => 'Armada Terawat',
                    'isi' => 'Setiap unit dicek berkala sebelum jalan. Bila ada kendala di jalan, kami siapkan unit pengganti.',
                ],
                [
                    'gambar' => 'pemandu-paham-medan',
                    'icon' => 'o-map',
                    'judul' => 'Pemandu Paham Medan',
                    'isi' => 'Tim kami hafal jalur, jam ramai, dan spot terbaik — sehingga waktu Anda tidak habis di jalan.',
                ],
                [
                    'gambar' => 'dibalas-cepat',
                    'icon' => 'o-chat-bubble-left-right',
                    'judul' => 'Dibalas Cepat',
                    'isi' => 'Pertanyaan dibalas setiap hari pukul 08.00–21.00 WIB, termasuk saat perjalanan berlangsung.',
                ],
            ],
            'perjalanan' => [
                ['tahun' => 'Awal', 'judul' => 'Berangkat dari hobi jalan-jalan', 'isi' => 'Orcha Journey lahir dari kebiasaan mengatur perjalanan bareng teman dan keluarga di sekitar Yogyakarta.'],
                ['tahun' => 'Tumbuh', 'judul' => 'Melayani rombongan sekolah', 'isi' => 'Permintaan study tour membuat kami menambah armada bus dan menyiapkan pendamping di setiap kendaraan.'],
                ['tahun' => 'Kini', 'judul' => 'Satu tim untuk semua kebutuhan', 'isi' => 'Open trip, private trip, study tour, sampai sewa mobil, HiAce, dan bus — semuanya bisa diurus dalam satu pintu.'],
            ],
            'partners' => Partner::all(),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin tahu lebih banyak tentang layanan Anda.');
@endphp

<div>
    <x-page-hero title="Tentang Kami" eyebrow="Kenali Orcha Journey"
        subtitle="{{ config('orcha.slogan') }} Kami mengurus perjalanan dari rencana sampai pulang, supaya Anda tinggal menikmatinya."
        image="images/HERO/tentang-kami.webp" posisi="center 62%" />

    {{-- ======================================================================
         Halaman ini memakai SISTEM PANEL yang sama dengan beranda: panel
         bersudut tumpul yang ditumpuk di dalam satu rak, bukan bagian
         selebar layar yang berganti warna latar.

         Yang ikut berpindah bersamanya: .label-bagian (label amber berapit
         garis), .judul-panel, ubin .ubin-orcha yang terangkat saat disentuh,
         panel gelap sebagai pemecah irama, dan pita mitra bertepi memudar.
         Tulisannya tidak diubah sama sekali — yang berganti wadahnya.
    ====================================================================== --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        {{-- ==============================================================
             CERITA KAMI
        =============================================================== --}}
        <section class="panel-orcha">
            <div class="grid items-center gap-8 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-6 reveal">
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Cerita Kami</p>
                    <h2 class="mt-3 judul-panel">Travel agent Yogyakarta yang tidak bikin repot.</h2>

                    {{-- Paragraf pembuka sengaja lebih besar. Empat paragraf
                         sepanjang ini dengan ukuran huruf yang sama persis
                         terbaca sebagai satu dinding tulisan — mata tidak punya
                         tempat memulai. --}}
                    <p class="mt-5 text-base leading-relaxed sm:text-lg text-slate-600">
                        Liburan sering sudah melelahkan sebelum berangkat. Membandingkan harga sewa, menawar armada,
                        menyusun rute yang masuk akal, memastikan tidak ada yang tertinggal di titik jemput — semua itu
                        memakan malam-malam yang seharusnya dipakai untuk membayangkan tujuannya.
                        <strong class="text-orcha-navy">Orcha Journey</strong> berdiri dari keluhan yang selalu sama
                        itu.
                    </p>
                    <p class="mt-4 text-sm leading-relaxed sm:text-base text-slate-600">
                        Bagian yang merepotkan kami yang ambil. Anda cukup menyebutkan tujuan, tanggal, dan berapa orang
                        yang ikut; sisanya kami susun — itinerary, armada, pemandu, sampai titik jemput yang paling
                        masuk akal untuk rombongan Anda. Yang sampai ke tangan Anda sudah berupa rencana yang tinggal
                        dijalani, bukan setumpuk pilihan yang masih harus dipilah sendiri.
                    </p>
                    {{-- Cerita nama dikeluarkan jadi KUTIPAN, bukan paragraf
                         biasa. Ini bagian paling kuat di halaman — ia menjelaskan
                         kenapa perusahaan ini bernama Orcha dan apa janjinya —
                         tetapi sebagai paragraf ketiga dari empat ia terlewat
                         begitu saja. --}}
                    <figure class="relative p-5 mt-6 border-l-4 rounded-r-2xl border-orcha-sun bg-orcha-sun/10">
                        <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true" loading="lazy"
                            width="512" height="512"
                            class="absolute w-16 h-16 opacity-10 pointer-events-none select-none -bottom-1 right-2">
                        <blockquote class="relative text-sm leading-relaxed sm:text-base text-orcha-navy">
                            Nama <strong>Orcha</strong> diambil dari orca — penjelajah samudra yang menempuh ribuan
                            kilometer, selalu bergerak dalam kelompok, dan tidak pernah meninggalkan satu pun
                            anggotanya.
                        </blockquote>
                        <figcaption class="relative mt-2 text-sm font-bold text-orcha-amber">
                            Rombongan berangkat utuh, pulang utuh.
                        </figcaption>
                    </figure>

                    {{-- Badan hukumnya disebut di sini, bukan sekadar di baris hak cipta.

                         Calon peserta menyerahkan uang muka ke pihak yang baru
                         ditemuinya lewat internet, dan penipuan berkedok open trip
                         cukup sering terjadi. Nama badan hukum yang bisa ditelusuri
                         sendiri adalah salah satu dari sedikit hal yang bisa ia
                         periksa sebelum mentransfer — jadi ia ditaruh di halaman
                         yang memang dibuka orang saat menimbang, tepat sebelum dua
                         tombol ajakan di bawahnya, lengkap dengan tautan ke situs
                         resminya supaya bisa dicek tanpa perlu percaya begitu saja.

                         Dibaca dari config supaya sekali ubah berlaku juga di
                         footer; ejaan penerima transfer sengaja tetap terpisah,
                         alasannya ada di config/orcha.php. --}}
                    {{-- Dijadikan KARTU berikon, bukan paragraf. Isinya penanda
                         kepercayaan — nama badan hukum yang bisa ditelusuri
                         sendiri sebelum orang mentransfer — dan sebagai paragraf
                         keempat ia tenggelam di dinding tulisan. Alasan
                         keberadaannya ada di catatan bawah. --}}
                    <div class="flex gap-3 p-4 mt-6 border rounded-2xl border-orcha-foam bg-white/70">
                        <span class="grid w-10 h-10 shrink-0 place-items-center rounded-xl bg-orcha-toska/10 text-orcha-toska">
                            <x-heroicon-o-shield-check class="w-5 h-5" />
                        </span>
                        <p class="text-sm leading-relaxed text-slate-600">
                        Kami bukan usaha yang berdiri sendiri. Orcha Journey berada di bawah naungan
                        <a href="{{ config('orcha.perusahaan_situs') }}" target="_blank" rel="noopener"
                            class="font-semibold underline text-orcha-toska decoration-orcha-mist underline-offset-4 hover:text-orcha-amber">{{ config('orcha.perusahaan') }}</a>,
                        perusahaan yang bergerak di bidang pariwisata dan teknologi — di sisi digitalnya ada
                        Reka Teknologi, saudara satu atap kami. Badan hukum itu pula yang tercantum sebagai penerima
                        setiap pembayaran, jadi nama yang Anda baca di halaman ini sama dengan nama yang Anda temukan
                        saat mentransfer.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 mt-7 sm:flex-row">
                        <a href="{{ route('paket-wisata') }}" class="btn-orcha btn-orcha-sun">
                            <x-heroicon-o-map class="w-5 h-5" />
                            Lihat Paket Wisata
                        </a>
                        <a href="{{ route('kontak') }}" class="btn-orcha btn-orcha-outline">
                            Hubungi Kami
                            <span class="btn-panah"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-6 reveal">
                    {{-- Susunannya seperti semula: tinggi berselang dan kolom
                         kanan berundak. Yang berganti jari-jari sudutnya, kini
                         mengikuti ubin beranda (1,4rem), bukan rounded-3xl —
                         supaya foto di sini sekeluarga dengan kartu di halaman
                         lain.

                         Berkasnya sendiri sudah dipotong lebih dulu — pita logo
                         di atas dan pita tulisan di bawah dibuang dari berkasnya,
                         karena aslinya poster media sosial. --}}
                    {{-- Susunan BERSELANG-SELING: kolom kiri tinggi-pendek,
                         kolom kanan pendek-tinggi. Sebelumnya empat foto
                         berukuran hampir sama dalam kisi 2x2, dan empat kotak
                         sebangun berjajar terbaca sebagai tabel, bukan koleksi
                         perjalanan. --}}
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div class="grid gap-3 sm:gap-4">
                            <img src="{{ asset('images/tentang-kami/web1.webp') }}" alt="Menyusuri pantai bersama rombongan"
                                loading="lazy" class="object-cover w-full h-64 sm:h-[23rem] rounded-[1.4rem] shadow-orcha">
                            <img src="{{ asset('images/tentang-kami/web10.webp') }}" alt="Tebing dan laut di pagi hari"
                                loading="lazy" class="object-cover w-full h-36 sm:h-52 rounded-[1.4rem] shadow-orcha">
                        </div>
                        <div class="grid gap-3 sm:gap-4 mt-7 sm:mt-10">
                            <img src="{{ asset('images/tentang-kami/web9.webp') }}" alt="Pantai yang kami kunjungi"
                                loading="lazy" class="object-cover w-full h-36 sm:h-52 rounded-[1.4rem] shadow-orcha">
                            <img src="{{ asset('images/tentang-kami/web11.webp') }}" alt="Destinasi budaya"
                                loading="lazy" class="object-cover w-full h-64 sm:h-[23rem] rounded-[1.4rem] shadow-orcha">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ==============================================================
             ANGKA — panel gelap

             Satu dari dua panel gelap di halaman ini (satunya ajakan
             penutup), memakai warna gelap logo seperti di beranda. Angka
             memang pantas berlatar gelap: tidak ada yang perlu dibaca
             panjang di sini, jadi kontras tinggi justru membantu.
        =============================================================== --}}
        {{-- Panel angka ini TERANG, bukan gelap.

             Panel gelap di halaman ini dipegang bagian "Kenapa Orcha" di
             bawahnya, mengikuti bentuk "Cara Pesan" di beranda. Kalau panel
             angka ikut gelap, keduanya berdampingan dan terbaca sebagai satu
             blok gelap panjang — jarak antar panel cuma 16px, tidak cukup
             untuk memisahkan dua bidang segelap itu. --}}
        <section class="panel-orcha panel-orcha-krem">
            <dl class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                @foreach ($angka as $item)
                    <div class="text-center reveal">
                        <dd class="text-3xl font-black font-heading text-orcha-navy sm:text-4xl">{{ $item['nilai'] }}+</dd>
                        <dt class="mt-2 text-xs font-semibold tracking-wider uppercase text-orcha-amber">
                            {{ $item['label'] }}
                        </dt>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- ==============================================================
             NILAI
        =============================================================== --}}
        {{-- Bentuknya mengikuti "Cara Pesan" di beranda: panel gelap, ubin
             krem, dan angka besar di sudut kanan tiap ubin. --}}
        <section class="relative overflow-hidden panel-orcha panel-orcha-gelap">
            <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true"
                loading="lazy" width="512" height="512" class="cap-orca">

            <div class="relative max-w-2xl mb-6 sm:mb-8 reveal">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Kenapa Orcha</p>
                <h2 class="mt-3 judul-panel">Empat hal yang kami pegang teguh.</h2>
            </div>

            <div class="relative grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
                @foreach ($nilaiKami as $urutanNilai => $nilai)
                    {{-- .gelembung-ikon, kotak ikon timbul yang sama dengan
                         kartu hero dan kartu blog di beranda. Sebelumnya kotak
                         bergradasi biru-ke-hijau yang warnanya sudah tidak ada
                         lagi di palet mana pun. --}}
                    {{-- .ubin-orcha tanpa -putih: ubinnya KREM di atas panel
                         gelap, sama seperti ubin langkah di beranda. --}}
                    <div class="relative ubin-orcha reveal">
                        <span class="absolute text-7xl font-black leading-none select-none top-1 right-4 font-heading text-orcha-toska-samar">
                            {{ $urutanNilai + 1 }}
                        </span>
                        @isset($nilai['gambar'])
{{-- Mengikuti ubin "Cara Pesan" di beranda: gambarnya LANGSUNG
                                 di kartu, tanpa kotak, bingkai, atau bayangan —
                                 ukuran, kelas, dan geseran kirinya sama persis.

                                 Latar gelap gambar aslinya dibuang jadi tembus
                                 pandang lewat operasi PENUTUPAN pada topeng bagian
                                 terang: dikembangkan 54px lalu dikikis balik 54px.
                                 Celah gelap DI ANTARA bagian terang — baju di antara
                                 wajah dan peta, kaca di antara badan mobil, tepi biru
                                 papan jalan di antara kertas dan cakram — semuanya
                                 terisi, sementara tepi luarnya kembali ke tempat
                                 semula sehingga latar gelap tidak ikut terbawa.

                                 Pengembangan SAJA (tanpa dikikis balik) sempat
                                 dipakai dan itulah asal pita abu di sekeliling benda:
                                 tepi luarnya terdorong ke luar dan latar gelap
                                 sejauh itu ikut tersalin. --}}
                            <img src="{{ asset('images/nilai/' . $nilai['gambar'] . '.webp') }}"
                                alt="" loading="lazy" decoding="async" width="440" height="440"
                                class="relative object-contain w-20 h-20 mb-2.5 -ml-1.5">
                        @else
                            <span class="relative gelembung-ikon">
                                <x-dynamic-component :component="'heroicon-' . $nilai['icon']" class="w-5 h-5" />
                            </span>
                        @endisset
                        <h3 class="relative text-base font-bold font-heading text-orcha-navy">{{ $nilai['judul'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $nilai['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ==============================================================
             PERJALANAN KAMI
        =============================================================== --}}
        <section class="panel-orcha">
            <div class="max-w-2xl mb-6 sm:mb-8 reveal">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Perjalanan Kami</p>
                <h2 class="mt-3 judul-panel">Dari rombongan kecil ke rombongan satu sekolah.</h2>
            </div>

            {{-- GARIS WAKTU, bukan tiga kartu sejajar.

                 Ketiganya berurutan — awal, tumbuh, kini — dan sebelumnya
                 tidak ada satu pun tanda yang mengatakan itu; ketiganya
                 terbaca seperti tiga hal setara. Sekarang ada rel mendatar
                 dengan titik di atas tiap kartu, dan titik terakhir diberi
                 lingkaran luar supaya "Kini" terbaca sebagai ujung, bukan
                 sekadar kartu ketiga. --}}
            <ol class="relative grid gap-3 mt-10 md:grid-cols-3 sm:gap-4">
                {{-- Rel hanya digambar di layar lebar: di ponsel kartunya
                     bertumpuk ke bawah, dan rel mendatar akan melintang di
                     tempat yang salah. --}}
                <span aria-hidden="true"
                    class="absolute hidden md:block left-6 right-6 -top-6 h-px bg-gradient-to-r from-transparent via-orcha-mist to-transparent"></span>

                @foreach ($perjalanan as $urutan => $tahap)
                    {{-- pb-10: ruang di bawah tulisan untuk angka. Tanpa ruang
                         itu angkanya duduk tepat di atas baris terakhir, dan
                         pada kartu yang tulisannya paling panjang ia menutupi
                         kata — terlihat di kartu "Tumbuh", kata "setiap"
                         tertutup angka 2. --}}
                    <li class="relative ubin-orcha reveal !pb-10">
                        <span aria-hidden="true"
                            class="absolute hidden md:block left-6 -top-[1.84rem] w-3 h-3 rounded-full bg-orcha-toska
                                {{ $loop->last ? 'ring-4 ring-orcha-toska/25' : '' }}"></span>

                        {{-- self-start: tanpa ini lencananya melebar satu kartu
                             penuh. .ubin-orcha itu flex kolom, dan anak flex
                             diregangkan mengikuti lebar induknya — inline-block
                             pada anak flex tidak berpengaruh apa-apa. --}}
                        <span class="relative z-10 self-start inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold tracking-wider uppercase rounded-full bg-orcha-sun/20 text-orcha-amber">
                            {{ $tahap['tahun'] }}
                        </span>

                        <h3 class="relative z-10 mt-3 text-base font-bold font-heading text-orcha-navy">{{ $tahap['judul'] }}</h3>
                        <p class="relative z-10 mt-2 text-sm leading-relaxed text-slate-600">{{ $tahap['isi'] }}</p>

                        {{-- Angka di sudut KANAN-BAWAH dan DI BELAKANG tulisan.

                             Di kanan-atas ia bertabrakan dengan lencana tahap.
                             Lapisnya diatur lewat z-index, bukan urutan markah:
                             angka ini ditulis paling akhir, dan dua unsur
                             berposisi yang sama kuat akan dimenangkan oleh yang
                             belakangan — itu sebabnya ia sempat menimpa kata.
                             z-0 di sini, z-10 pada tulisannya.

                             Tidak dipakai z-index negatif: anak ber-z negatif
                             digambar DI BAWAH latar induknya, jadi angkanya akan
                             hilang sama sekali di balik warna ubin. --}}
                        <span aria-hidden="true"
                            class="absolute z-0 text-5xl font-black leading-none select-none bottom-3 right-4 font-heading text-orcha-toska-samar/70">
                            {{ $urutan + 1 }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- ==============================================================
             MITRA — pita berjalan, sama persis dengan beranda
        =============================================================== --}}
        @if ($partners->isNotEmpty())
            <section class="overflow-hidden panel-orcha !px-0 !py-7">
                <div class="mb-5 text-center">
                    <p class="label-bagian">
                        <span class="w-6 h-px bg-orcha-sun"></span>
                        Mitra perjalanan
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

                <div class="flex overflow-hidden marquee-wrap pita-mitra">
                    <div class="marquee marquee-slow">
                        @foreach ($jalurPartner as $partner)
                            <div class="flex items-center gap-3 px-5 py-3 bg-white border shrink-0 rounded-2xl border-orcha-foam kartu-mitra">
                                {{-- Inisial selalu digambar, foto ditumpangkan di
                                     atasnya; kalau berkas fotonya hilang, onerror
                                     menyingkirkannya dan inisialnya yang terlihat. --}}
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

        {{-- ==============================================================
             AJAKAN PENUTUP — tirai dua lapis seperti beranda
        =============================================================== --}}
        <section class="relative overflow-hidden panel-orcha panel-orcha-gelap !p-0">
            <img src="{{ asset('images/FOOTER/tentang-kami.webp') }}" alt="" loading="lazy"
                class="absolute inset-0 object-cover w-full h-full">
            {{-- Dua lapis, bukan satu kabut rata 25%: lapis mendatar
                 menggelapkan sisi tempat tulisan berada, lapis tegak menahan
                 tepi bawah. Foto aslinya jadi ikut terlihat, bukan abu-abu. --}}
            <div class="absolute inset-0 tirai-ajakan"></div>
            <div class="absolute inset-0 tirai-ajakan-bawah"></div>

            <div class="relative p-7 sm:p-10 lg:p-14">
                <div class="max-w-2xl reveal">
                    <p class="label-bagian !text-orcha-sun">
                        <span class="w-6 h-px bg-orcha-sun"></span> Siap kapan saja
                    </p>
                    <h2 class="mt-3 text-2xl font-extrabold leading-tight text-white font-heading sm:text-3xl lg:text-4xl">
                        Mari jalan bareng Orcha Journey.
                    </h2>
                    <p class="max-w-xl mt-3 text-sm leading-relaxed text-slate-200 sm:text-base">
                        Ceritakan rencana Anda, kami balas dengan itinerary dan rincian biaya di hari yang sama.
                    </p>
                    <div class="flex flex-col gap-3 mt-7 sm:flex-row">
                        <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                            <x-bi-whatsapp class="w-5 h-5" />
                            Chat via WhatsApp
                        </a>
                        <a href="{{ route('kontak') }}" class="btn-orcha btn-orcha-ghost">
                            Lihat Semua Kontak
                            <span class="btn-panah"><x-heroicon-o-arrow-right class="w-4 h-4" /></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
