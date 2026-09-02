<?php

use App\Models\PaketWisata\TravelPackage;
use App\Support\Seo;
use App\Support\NomorTelepon;
use App\Models\PaketWisata\DaftarTunggu;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component
{
    public TravelPackage $paket;

    public function mount(TravelPackage $paket): void
    {
        // Paket draf, terjadwal, atau yang sudah berakhir tidak boleh terbuka
        // meski alamatnya diketahui — halamannya diperlakukan seperti tidak ada.
        abort_unless($paket->sedang_tayang, 404);

        $this->paket = $paket;
    }

    /**
     * Keterangan halaman untuk mesin pencari dan pratinjau tautan.
     *
     * Halaman ini SUDAH didaftarkan di peta situs sejak lama, tetapi tidak
     * pernah mengirim judul maupun keterangannya sendiri. Akibatnya tiap paket
     * muncul di Google dengan judul dan kalimat yang persis sama dengan
     * beranda — "Orcha Journey — Open Trip, Private Trip, Study Tour & Sewa
     * Kendaraan" — sehingga sepuluh hasil pencarian yang berbeda terbaca
     * sebagai sepuluh salinan halaman yang sama, dan tidak satu pun menyebut
     * paket mana yang sedang dilihat.
     *
     * Itu lebih merugikan daripada halaman yang belum ada sama sekali:
     * halamannya sudah diberikan ke mesin pencari, hanya tanpa identitas.
     */
    public function rendering(View $view): void
    {
        $paket = $this->paket;

        // Kategorinya ikut di judul karena itu yang diketik orang: yang
        // mencari "study tour bromo" tidak mengetik nama paketnya.
        $view->title($paket->name.' — '.$paket->category_label.' | Orcha Journey');

        $view->layoutData([
            'seoKeterangan' => Seo::keterangan(khusus: $this->kalimatSeo()),
            'seoGambar' => $paket->sampul,
        ]);
    }

    /**
     * Kalimat cuplikan di hasil pencarian.
     *
     * Disusun dari yang MEMBEDAKAN satu paket dari paket lain — jadwal, lama
     * perjalanan, harga, tempat yang didatangi — bukan kalimat pemasaran umum.
     * Bagian yang datanya belum diisi dilewati begitu saja, sehingga paket yang
     * tanggalnya belum ditetapkan tetap punya kalimat yang utuh, bukan kalimat
     * berlubang.
     */
    private function kalimatSeo(): string
    {
        $paket = $this->paket;

        $bagian = array_filter([
            $paket->category_label.' '.$paket->name,
            $paket->jadwal_label ? 'Berangkat '.$paket->jadwal_label : null,
            filled($paket->duration) ? $paket->duration : null,
            $paket->price > 0 ? 'Rp '.number_format((float) $paket->price, 0, ',', '.').' per orang' : null,
        ]);

        $kalimat = implode('. ', $bagian).'.';

        // Daftar tempatnya ditaruh terakhir: bagian inilah yang paling mungkin
        // terpotong oleh batas panjang, dan kehilangan ekor daftar tempat jauh
        // lebih ringan daripada kehilangan harga atau tanggalnya.
        $tempat = collect($paket->destination_list ?? [])
            ->map(fn ($satu) => is_array($satu) ? ($satu['nama'] ?? $satu['name'] ?? null) : $satu)
            ->filter()
            ->take(4);

        if ($tempat->isNotEmpty()) {
            $kalimat .= ' Mengunjungi '.$tempat->implode(', ').'.';
        }

        return $kalimat;
    }

    /* ------------------------- DAFTAR TUNGGU ------------------------- */

    public string $tungguNama = '';

    public string $tungguWa = '';

    public $tungguJumlah = 1;

    public bool $tungguTerkirim = false;

    /**
     * Menyimpan peminat trip yang kursinya sedang penuh.
     *
     * Sebelumnya halaman ini cuma mengarahkan ke WhatsApp, dan jawabannya tidak
     * tersimpan di mana pun: begitu percakapannya berakhir, peminat itu hilang.
     * Padahal merekalah yang paling mungkin langsung mengambil kursi yang
     * dilepas otomatis tiap jam.
     */
    public function antre(): void
    {
        $this->validate([
            'tungguNama' => 'required|string|min:3|max:120',
            'tungguWa' => ['required', 'string', 'max:25', fn ($a, $n, $gagal) => NomorTelepon::sah($n)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            'tungguJumlah' => 'required|integer|min:1|max:60',
        ], [], [
            'tungguNama' => 'nama',
            'tungguWa' => 'nomor WhatsApp',
            'tungguJumlah' => 'jumlah peserta',
        ]);

        /*
         | updateOrCreate, bukan create.
         |
         | Orang yang bertanya dua kali tidak boleh menempati dua tempat di
         | antrean — dan tanpa ini ia juga menerima dua kabar saat kursinya
         | terbuka. Yang diperbarui jumlah pesertanya, karena rombongan sering
         | bertambah selama menunggu.
         */
        DaftarTunggu::updateOrCreate(
            [
                'travel_package_id' => $this->paket->id,
                'whatsapp' => NomorTelepon::angka($this->tungguWa),
            ],
            [
                'nama' => $this->tungguNama,
                'jumlah_peserta' => (int) $this->tungguJumlah,
                // Dikosongkan lagi: yang memperbarui antreannya berarti masih
                // menunggu, jadi ia berhak dikabari pada kesempatan berikutnya
                // walau pernah dikabari sebelumnya.
                'dikabari_pada' => null,
            ],
        );

        $this->tungguTerkirim = true;
        $this->reset(['tungguNama', 'tungguWa', 'tungguJumlah']);
    }

    public function with(): array
    {
        return [
            'paketLain' => TravelPackage::tayang()->where('category', $this->paket->category)
                ->whereKeyNot($this->paket->id)
                ->orderBy('price')
                ->limit(3)
                ->get(),
        ];
    }
}; ?>

@php
    /*
     | Data terstruktur TouristTrip.
     |
     | Yang membedakannya dari sekadar meta description: harga, mata uang, dan
     | ketersediaan dikirim sebagai DATA, bukan kalimat — itu yang membuat
     | Google boleh menampilkan harga langsung di bawah tautan, bukan menebaknya
     | dari teks halaman.
     |
     | Ditulis di sini, bukan lewat seoSkema di layout, karena hanya bagian
     | tampilan ini yang tahu bentuk akhir datanya. Layout tetap menggambar
     | skema TravelAgency untuk situsnya; keduanya berdampingan dan memang
     | boleh — yang satu menerangkan penjualnya, yang satu barangnya.
     */
    $skemaPaket = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'TouristTrip',
        'name' => $paket->name,
        'description' => $paket->category_label . ' ' . $paket->name
            . ($paket->duration ? ' — ' . $paket->duration : ''),
        'image' => $paket->sampul,
        'url' => route('paket-detail', $paket->uuid),
        'provider' => [
            '@type' => 'TravelAgency',
            'name' => 'Orcha Journey',
            'url' => route('home'),
        ],
        // Tanggal keberangkatan hanya dikirim bila memang sudah ditetapkan.
        // Menyebut tanggal yang belum pasti lebih buruk daripada tidak
        // menyebutnya: yang tertulis di hasil pencarian akan dipegang orang.
        'startDate' => $paket->tanggal_berangkat?->toDateString(),
        'endDate' => $paket->tanggal_pulang?->toDateString(),
        'itinerary' => collect($paket->destination_list ?? [])
            ->map(fn ($satu) => is_array($satu) ? ($satu['nama'] ?? $satu['name'] ?? null) : $satu)
            ->filter()
            ->values()
            ->map(fn ($nama) => ['@type' => 'TouristAttraction', 'name' => $nama])
            ->all() ?: null,
        'offers' => $paket->price > 0 ? array_filter([
            '@type' => 'Offer',
            'price' => (string) (int) $paket->price,
            'priceCurrency' => 'IDR',
            'url' => route('paket-detail', $paket->uuid),
            // sedang_tayang sudah dijamin oleh mount(): halaman ini menjawab
            // 404 untuk paket yang tidak tayang, jadi yang sampai di sini
            // pasti sedang dijual.
            'availability' => 'https://schema.org/InStock',
        ]) : null,
    ], fn ($nilai) => $nilai !== null && $nilai !== [] && $nilai !== '');

    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode("Halo Orcha Journey, saya ingin bertanya soal {$paket->name}.");

    /*
     | Pesan terpisah untuk trip yang kuotanya sudah habis.
     |
     | Isinya menanyakan TANGGAL BERIKUTNYA, bukan sisa tempat di trip ini.
     | Pertanyaan "masih ada tempat?" mengundang jawaban yang melunakkan
     | kabarnya, dan begitu dilunakkan, orang yang terlambat tidak punya alasan
     | bergegas lain kali. Yang ditawarkan keberangkatan berikutnya — di situ
     | ia masih bisa memutuskan cepat.
     */
    $waPenuh = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode("Halo Orcha Journey, saya terlambat mendaftar {$paket->name}. Kapan keberangkatan berikutnya?");
    $dp = config('orcha.pembayaran.dp_persen');
    $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
@endphp

<div>
    <script type="application/ld+json">{!! json_encode($skemaPaket, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <x-page-hero :title="$paket->name" :eyebrow="$paket->category_label"
        :subtitle="$paket->jadwal_label ? 'Keberangkatan ' . $paket->jadwal_label : $paket->duration"
        image="images/HERO/paket-trip.webp" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="space-y-6 lg:col-span-8">

                    {{-- Ringkasan perjalanan --}}
                    <div class="p-6 card-orcha sm:p-8">
                        <h2 class="text-xl font-bold font-heading text-orcha-navy">Ringkasan Perjalanan</h2>

                        <dl class="grid gap-5 mt-5 sm:grid-cols-2">
                            @php
                                $ringkasan = array_filter([
                                    ['o-calendar-days', 'Keberangkatan', $paket->jadwal_label],
                                    ['o-clock', 'Durasi', $paket->duration],
                                    ['o-map-pin', 'Titik jemput', $paket->titik_jemput],
                                    ['o-user-group', 'Minimal peserta', $paket->minimal_peserta > 1 ? $paket->minimal_peserta . ' orang' : null],
                                ], fn ($baris) => filled($baris[2]));
                            @endphp

                            @foreach ($ringkasan as [$ikon, $label, $nilai])
                                <div class="flex items-start gap-3">
                                    <span
                                        class="flex items-center justify-center w-10 h-10 text-white shrink-0 rounded-xl bg-gradient-to-br from-orcha-sky to-orcha-ocean">
                                        <x-dynamic-component :component="'heroicon-' . $ikon" class="w-5 h-5" />
                                    </span>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wider uppercase text-slate-400">
                                            {{ $label }}</dt>
                                        <dd class="font-bold text-orcha-navy">{{ $nilai }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                        @if ($paket->sudah_lewat)
                            <div class="p-4 mt-6 text-sm border rounded-2xl border-orcha-sun/40 bg-orcha-sun/10 text-orcha-navy">
                                Tanggal keberangkatan paket ini sudah lewat. Hubungi kami untuk menanyakan jadwal
                                berikutnya.
                            </div>
                        @endif
                    </div>

                    {{-- Destinasi --}}
                    @if (!empty($paket->destination_list))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Destinasi yang Dikunjungi</h2>
                            <ul class="grid gap-3 mt-5 sm:grid-cols-2">
                                @foreach ($paket->destination_list as $destinasi)
                                    <li class="flex items-start gap-2 text-sm text-slate-600">
                                        <x-heroicon-s-map-pin class="w-5 h-5 shrink-0 text-orcha-sky" />
                                        <span>{{ $destinasi }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Fasilitas --}}
                    @if (!empty($paket->fasilitas))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Fasilitas Termasuk</h2>
                            <ul class="grid gap-3 mt-5 sm:grid-cols-2">
                                @foreach ($paket->fasilitas as $fasilitas)
                                    <li class="flex items-start gap-2 text-sm text-slate-600">
                                        <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-orcha-sky" />
                                        <span>{{ $fasilitas }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Itinerary --}}
                    @if (!empty($paket->itinerary))
                        <div class="p-6 card-orcha sm:p-8">
                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Itinerary</h2>

                            <div class="mt-6 space-y-6">
                                @foreach ($paket->itinerary as $hari)
                                    @php
                                        $agendaHari = collect($hari['agenda'] ?? [])->filter(fn ($a) => filled($a['kegiatan'] ?? null));
                                        $jamHari = $agendaHari->pluck('jam')->filter()->values();
                                    @endphp

                                    <div>
                                        {{-- Sisi kanan baris hari dulu kosong; diisi ringkasan hari itu
                                             supaya sekilas terlihat padat-tidaknya acara. --}}
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <span
                                                class="inline-block px-4 py-1.5 text-sm font-bold text-white rounded-full bg-gradient-to-r from-orcha-abyss to-orcha-ocean">
                                                {{ $hari['hari'] ?? 'Hari' }}
                                            </span>

                                            @if ($agendaHari->isNotEmpty())
                                                <span class="text-xs font-semibold text-slate-400">
                                                    {{ $agendaHari->count() }} kegiatan
                                                    @if ($jamHari->count() > 1)
                                                        · {{ $jamHari->first() }} – {{ $jamHari->last() }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>

                                        <ul class="relative mt-4 space-y-3 before:absolute before:left-[5px] before:top-2 before:bottom-2 before:w-px before:bg-orcha-foam">
                                            @foreach ($hari['agenda'] ?? [] as $agenda)
                                                {{-- Titik, jam, dan kegiatan sejajar karena ketiganya
                                                     memakai tinggi baris yang sama (leading-6 = 24px);
                                                     titiknya dipusatkan di dalam kotak setinggi itu,
                                                     bukan digeser dengan angka ajaib. --}}
                                                <li class="relative flex items-start gap-3">
                                                    {{-- Cincin putih memutus garis penghubung tepat di
                                                         titiknya, jadi garis tidak tampak menembus titik. --}}
                                                    <span class="relative z-10 flex items-center h-6 shrink-0">
                                                        <span
                                                            class="w-2.5 h-2.5 rounded-full bg-orcha-sky ring-4 ring-white"></span>
                                                    </span>
                                                    <span
                                                        class="font-mono text-sm font-bold leading-6 shrink-0 text-orcha-ocean w-16 tabular-nums">{{ $agenda['jam'] ?? '' }}</span>
                                                    <span
                                                        class="text-sm leading-6 text-slate-600">{{ $agenda['kegiatan'] ?? '' }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-6 text-xs text-slate-500">
                                Urutan kunjungan dapat menyesuaikan kondisi cuaca, lalu lintas, dan keamanan di lokasi.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Sisi kanan: harga & pendaftaran --}}
                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <div class="overflow-hidden card-orcha">
                            @php
                                $adaDiskon = $paket->ada_diskon;
                                $hemat = $paket->hemat_rupiah;
                                $persen = $paket->diskon_tampil;
                            @endphp

                            {{-- Sisi kanan panel harga dulu kosong. Diisi hal yang memang
                                 berguna — besar potongannya — bukan sekadar hiasan. Bila
                                 tidak ada diskon, yang tampil cuma sapuan ombak samar. --}}
                            <div
                                class="relative overflow-hidden p-6 text-white bg-gradient-to-br from-orcha-navy to-orcha-abyss sm:p-7">
                                {{-- Ombak selebar panel, bukan cahaya di satu sudut: sudut
                                     yang menyala membuat sisi kiri terlihat gelap sebelah. --}}
                                <svg class="absolute inset-x-0 bottom-0 w-full h-28 pointer-events-none text-white/[.06]"
                                    viewBox="0 0 400 100" preserveAspectRatio="none" fill="none" aria-hidden="true">
                                    <path d="M0 62c33-20 66-20 100 0s67 20 100 0 67-20 100 0 67 20 100 0" stroke="currentColor"
                                        stroke-width="5" stroke-linecap="round" />
                                    <path d="M0 86c33-20 66-20 100 0s67 20 100 0 67-20 100 0 67 20 100 0" stroke="currentColor"
                                        stroke-width="5" stroke-linecap="round" />
                                </svg>

                                <div class="relative flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        @if ($paket->catatan_promo)
                                            <span
                                                class="inline-block px-3 py-1 mb-3 text-xs font-bold rounded-full bg-orcha-sun text-orcha-navy">
                                                {{ $paket->catatan_promo }}
                                            </span>
                                        @endif

                                        @if ($adaDiskon)
                                            <p class="text-sm line-through text-slate-400">
                                                {{ $rupiah($paket->original_price) }}</p>
                                        @endif

                                        <p class="text-3xl font-black font-heading text-orcha-sun">
                                            {{ $rupiah($paket->price) }}</p>
                                        <p class="text-sm text-slate-300">per orang</p>
                                    </div>

                                    @if ($adaDiskon && $persen > 0)
                                        <div
                                            class="flex flex-col items-center justify-center text-center shrink-0 w-[4.75rem] h-[4.75rem] rounded-full bg-orcha-sun/15 ring-1 ring-orcha-sun/40">
                                            <span
                                                class="text-[.6rem] font-bold tracking-wider uppercase text-orcha-sun/80">Hemat</span>
                                            <span
                                                class="text-lg font-black leading-none font-heading text-orcha-sun">{{ $persen }}%</span>
                                        </div>
                                    @endif
                                </div>

                                @if ($adaDiskon)
                                    <p class="relative mt-3 text-xs text-slate-300">
                                        Lebih hemat <strong class="text-white">{{ $rupiah($hemat) }}</strong> per orang
                                        dari harga normal.
                                    </p>
                                @endif
                            </div>

                            <div class="p-6 space-y-3 sm:p-7">
                                @if ($paket->category === 'open_trip')
                                    @if ($paket->kursi_habis)
                                        {{-- Kuotanya habis dan itu DISEBUT; yang tidak disebut angkanya.

                                             Sisa kursi sengaja tidak diumumkan di halaman
                                             publik: urusan ketersediaan dibicarakan lewat
                                             WhatsApp, dan angka di layar yang berbeda dari
                                             yang dikatakan tim di percakapan justru
                                             melemahkan keduanya.

                                             Yang tetap dilakukan halaman ini: berhenti
                                             menerima pendaftaran ketika armadanya memang
                                             sudah penuh, lalu mengantar orangnya ke tempat
                                             pertanyaannya bisa dijawab. Menerima pendaftaran
                                             yang tidak muat bukan menjaga peluang — ia
                                             menunda kabar buruknya sampai orang itu sudah
                                             mentransfer. --}}
                                        <div class="p-5 text-center rounded-2xl bg-orcha-foam">
                                            {{-- Tanpa ikon api.

                                                 Ikon api berwarna di sebelah kalimat pemasaran
                                                 terbaca sebagai emoji, bukan sebagai bagian
                                                 tampilan — dan itu membuat halamannya terasa
                                                 dirakit cepat. Kalimatnya sendiri sudah cukup
                                                 tegas; yang menguatkannya huruf tebal, bukan
                                                 gambar di sebelahnya. --}}
                                            <p class="text-base font-bold text-center text-orcha-navy">
                                                Kuota untuk trip ini sudah habis
                                            </p>
                                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">
                                                Jangan sampai terlewat lagi — tanggal berikutnya biasanya cepat penuh
                                                juga.
                                            </p>

                                            {{-- Tombolnya di dalam kotak, bukan menyerahkan orang ke
                                                 tombol WhatsApp umum di bawah: yang ini membawa pesan
                                                 pembuka yang sudah menanyakan tanggal berikutnya,
                                                 sehingga percakapannya mulai dari tempat yang masih
                                                 bisa ditutup jadi pendaftaran. --}}
                                            {{-- Daftar tunggu, bukan cuma tautan WhatsApp.

                                                 Tautan mengantar percakapan; yang tersimpan cuma di
                                                 kepala orang yang membalasnya. Kursi di trip ini
                                                 dilepas otomatis tiap jam ketika ada yang tidak
                                                 membayar — dan yang antre di sini adalah orang
                                                 pertama yang bisa dikabari saat itu terjadi. --}}
                                            @if ($tungguTerkirim)
                                                <div class="p-4 mt-4 text-sm text-left rounded-2xl bg-white/70">
                                                    <p class="flex items-center gap-2 font-bold text-orcha-navy">
                                                        <x-heroicon-s-check-circle class="w-5 h-5 text-orcha-ocean" />
                                                        Anda masuk daftar tunggu
                                                    </p>
                                                    <p class="mt-1 text-slate-600">
                                                        Kami kabari lewat WhatsApp begitu ada kursi yang terbuka —
                                                        biasanya karena ada yang batal.
                                                    </p>
                                                </div>
                                            @else
                                                <div class="p-4 mt-4 text-left bg-white/70 rounded-2xl">
                                                    <p class="flex items-center gap-2 text-sm font-bold text-orcha-navy">
                                                        <x-heroicon-s-bell-alert class="w-5 h-5 text-orcha-ocean" />
                                                        Mau dikabari kalau ada kursi terbuka?
                                                    </p>

                                                    {{-- Nomor dan jumlah orang BERDAMPINGAN.

                                                         Ditumpuk ke bawah, kotak ini jadi hampir setinggi kartu
                                                         harganya sendiri — dan tawaran yang panjang di dasar
                                                         halaman lebih sering dilewati daripada diisi. Keduanya
                                                         pendek dan saling berkaitan, jadi memang muat sebaris.

                                                         Yang TIDAK ikut dipadatkan: labelnya. Tanpa label, kotak
                                                         angka di sebelah nomor tidak menyebut apa-apa dan yang
                                                         mengisinya harus menebak. --}}
                                                    <div class="grid gap-3 mt-3">
                                                        <div>
                                                            <label for="tunggu-nama" class="label-orcha">
                                                                Nama <x-wajib />
                                                            </label>
                                                            <input id="tunggu-nama" type="text" wire:model="tungguNama"
                                                                maxlength="120" placeholder="Nama lengkap Anda"
                                                                class="isian-orcha @error('tungguNama') isian-galat @enderror">
                                                            @error('tungguNama')
                                                                <p class="galat-orcha">{{ $message }}</p>
                                                            @enderror
                                                        </div>

                                                        {{-- Label DIPENDEKKAN, dan itu bukan soal selera.

                                                             Kartu ini duduk di kolom samping selebar ~380px.
                                                             "Nomor WhatsApp" pecah jadi dua baris di situ,
                                                             mendorong kotaknya turun sehingga tidak lagi sejajar
                                                             dengan kotak di sebelahnya — dan yang terlihat dua
                                                             isian yang tingginya berbeda tanpa alasan.

                                                             items-end menjaganya tetap rata bawah seandainya
                                                             salah satu label tetap pecah di layar yang lebih
                                                             sempit lagi. --}}
                                                        {{-- FLEX, bukan grid berkolom-span.

                                                             col-span-2 dan col-span-3 TIDAK ADA di CSS terbangun:
                                                             Tailwind hanya membuat kelas yang ditemukannya saat
                                                             build, dan keduanya belum pernah dipakai di proyek
                                                             ini. Akibatnya grid-cols-3 dengan dua isian menyisakan
                                                             satu kolom kosong di kanan — itulah ruang menganga
                                                             yang terlihat sejak awal, bukan salah tata letak.
                                                             Sudah diperiksa langsung di public/build/assets.
                                                             
                                                             flex-1 dan w-24 sudah ada di sana, jadi bentuk ini
                                                             tidak menunggu aset dibangun ulang. --}}
                                                        <div class="flex items-end gap-3">
                                                            <div class="flex-1">
                                                                <label for="tunggu-wa" class="label-orcha">
                                                                    WhatsApp <x-wajib />
                                                                </label>
                                                                <input id="tunggu-wa" type="tel" inputmode="tel"
                                                                    wire:model="tungguWa" maxlength="25"
                                                                    placeholder="0812 3456 7890"
                                                                    class="isian-orcha orcha-telp @error('tungguWa') isian-galat @enderror">
                                                            </div>

                                                            <div class="w-24 shrink-0">
                                                                <label for="tunggu-jumlah" class="label-orcha">
                                                                    Orang <x-wajib />
                                                                </label>
                                                                <input id="tunggu-jumlah" type="number" min="1" max="60"
                                                                    wire:model="tungguJumlah"
                                                                    class="isian-orcha @error('tungguJumlah') isian-galat @enderror">
                                                            </div>
                                                        </div>

                                                        @error('tungguWa')
                                                            <p class="galat-orcha">{{ $message }}</p>
                                                        @enderror
                                                        @error('tungguJumlah')
                                                            <p class="galat-orcha">{{ $message }}</p>
                                                        @enderror

                                                        <button type="button" wire:click="antre"
                                                            wire:loading.attr="disabled" wire:target="antre"
                                                            class="w-full btn-orcha btn-orcha-primary">
                                                            <span wire:loading.remove wire:target="antre">
                                                                <x-heroicon-o-bell-alert class="w-5 h-5" />
                                                                Kabari Saya
                                                            </span>
                                                            <span wire:loading wire:target="antre">Menyimpan…</span>
                                                        </button>

                                                        {{-- Satu baris keterangan untuk keduanya, di BAWAH tombol.

                                                             Di atas tombol ia menyisipkan jeda antara isian
                                                             terakhir dan tindakannya; di bawah, ia jadi penutup
                                                             yang menjawab "lalu apa" tanpa menahan siapa pun. --}}
                                                        <p class="text-xs leading-relaxed text-slate-500">
                                                            Kami kabari lewat WhatsApp begitu ada kursi terbuka —
                                                            yang paling lama menunggu didahulukan, dan jumlahnya
                                                            kami cocokkan dengan kursi yang tersedia.
                                                        </p>
                                                    </div>
                                                </div>
                                            @endif

                                            <a href="{{ $waPenuh }}" target="_blank" rel="noopener noreferrer"
                                                class="w-full mt-3 btn-orcha btn-orcha-outline">
                                                <x-bi-whatsapp class="w-5 h-5" />
                                                Tanya Tanggal Berikutnya
                                            </a>
                                        </div>
                                    @else
                                        <a href="{{ route('pendaftaran-open-trip', ['paket' => $paket->uuid]) }}"
                                            class="w-full btn-orcha btn-orcha-primary">
                                            <x-heroicon-o-pencil-square class="w-5 h-5" />
                                            Daftar Sekarang
                                        </a>
                                    @endif
                                @endif

                                {{-- Tombol WhatsApp umum, TIDAK digambar saat kuotanya habis.

                                     Kotak di atas sudah punya tombol WhatsApp sendiri, dan yang
                                     itu membawa pesan pembuka yang lebih tepat: menanyakan
                                     tanggal berikutnya, bukan bertanya umum soal paket yang
                                     sudah tidak bisa diikuti.

                                     Dua tombol WhatsApp bersusun bukan cuma berulang — yang
                                     kedua justru membatalkan maksud yang pertama, karena ia
                                     mengembalikan percakapan ke "tanya-tanya soal trip ini". --}}
                                @unless ($paket->category === 'open_trip' && $paket->kursi_habis)
                                    <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                                        class="w-full btn-orcha btn-orcha-outline">
                                        <x-bi-whatsapp class="w-5 h-5" />
                                        Tanya via WhatsApp
                                    </a>
                                @endunless

                                <div class="pt-4 mt-4 text-xs border-t border-orcha-foam text-slate-500">
                                    <p>Uang muka <strong class="text-orcha-navy">{{ $dp }}%</strong> saat pemesanan.
                                        Pelunasan paling lambat
                                        <strong class="text-orcha-navy">H-{{ $pelunasan }}</strong>
                                        @if ($paket->batas_pelunasan)
                                            ({{ $paket->batas_pelunasan->translatedFormat('j F Y') }})
                                        @endif
                                        sebelum keberangkatan.
                                    </p>
                                    <a href="{{ route('ketentuan-pembayaran') }}"
                                        class="inline-block mt-2 font-semibold text-orcha-ocean hover:underline">
                                        Lihat ketentuan pembayaran
                                    </a>

                                    <x-peringatan-pembayaran ringkas class="mt-3" />
                                </div>
                            </div>
                        </div>

                        @if ($paketLain->isNotEmpty())
                            <div class="p-6 card-orcha sm:p-7">
                                <h2 class="text-lg font-bold font-heading text-orcha-navy">
                                    {{ $paket->category_label }} lainnya</h2>
                                <div class="mt-4 space-y-3">
                                    @foreach ($paketLain as $lain)
                                        <a href="{{ route('paket-detail', $lain->uuid) }}"
                                            class="block p-4 transition border rounded-2xl border-orcha-foam hover:border-orcha-sky hover:bg-orcha-foam/40">
                                            <p class="text-sm font-bold text-orcha-navy">{{ $lain->name }}</p>
                                            <p class="mt-1 text-sm text-orcha-ocean">{{ $rupiah($lain->price) }}
                                                <span class="text-slate-500">/ orang</span>
                                            </p>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
