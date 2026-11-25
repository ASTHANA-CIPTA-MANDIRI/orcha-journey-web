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
                    // 'gambar' mengalahkan 'icon' saat ada — pola yang sama dengan
                    // ubin langkah "Cara Pesan" di beranda. Kanal yang belum punya
                    // gambar tetap memakai ikonnya, jadi menambah gambar berikutnya
                    // cukup menaruh berkas dan satu baris ini.
                    'gambar' => 'whatsapp',
                    'ikon' => 'heroicon-o-chat-bubble-left-right',
                    'judul' => 'WhatsApp',
                    'nilai' => '+' . $wa,
                    'catatan' => 'Paling cepat dibalas, setiap hari 08.00–21.00 WIB',
                    'tautan' => 'https://api.whatsapp.com/send?phone=' . $wa . '&text=' . rawurlencode('Halo Orcha Journey, saya ingin bertanya.'),
                    'aksi' => 'Chat sekarang',
                ],
                [
                    'gambar' => 'email',
                    'ikon' => 'heroicon-o-envelope',
                    'judul' => 'Email',
                    'nilai' => config('orcha.email'),
                    'catatan' => 'Untuk penawaran instansi, sekolah, dan dokumen penagihan',
                    'tautan' => 'mailto:' . config('orcha.email'),
                    'aksi' => 'Kirim email',
                ],
                [
                    'gambar' => 'instagram',
                    'ikon' => 'heroicon-o-camera',
                    'judul' => 'Instagram',
                    'nilai' => '@' . config('orcha.instagram'),
                    'catatan' => 'Dokumentasi perjalanan dan jadwal open trip terbaru',
                    'tautan' => 'https://www.instagram.com/' . config('orcha.instagram') . '/',
                    'aksi' => 'Buka Instagram',
                ],
                [
                    // TikTok dan YouTube tidak ada di Heroicon — logo merek memang
                    // tidak pernah ada di sana. Karena itu kunci 'ikon' menyimpan
                    // NAMA KOMPONEN PENUH, bukan potongan yang dirangkai dengan
                    // awalan 'heroicon-': dengan begitu satu daftar ini bisa
                    // memuat dua set ikon sekaligus tanpa cabang di markahnya.
                    'gambar' => 'tiktok',
                    'ikon' => 'bi-tiktok',
                    'judul' => 'TikTok',
                    'nilai' => '@' . ltrim(config('orcha.tiktok'), '@'),
                    'catatan' => 'Video pendek perjalanan dan suasana di lapangan',
                    'tautan' => 'https://www.tiktok.com/@' . ltrim(config('orcha.tiktok'), '@'),
                    'aksi' => 'Buka TikTok',
                ],
                [
                    'gambar' => 'youtube',
                    'ikon' => 'bi-youtube',
                    'judul' => 'YouTube',
                    'nilai' => '@' . ltrim(config('orcha.youtube'), '@'),
                    'catatan' => 'Rekaman perjalanan utuh dan panduan destinasi',
                    'tautan' => 'https://www.youtube.com/@' . ltrim(config('orcha.youtube'), '@'),
                    'aksi' => 'Buka YouTube',
                ],
                [
                    'gambar' => 'kantor',
                    'ikon' => 'heroicon-o-map-pin',
                    'judul' => 'Kantor',
                    // Bentuk pendek: alamat lengkap jadi empat baris tebal di
                    // ubin sekecil ini dan mengalahkan judul ubinnya sendiri.
                    // Yang lengkap ada di data terstruktur dan di peta.
                    'nilai' => config('orcha.alamat_singkat'),
                    'catatan' => 'Datang langsung? Kabari dulu supaya tim kami siap menerima',
                    // Tautannya memakai TITIK PETA, bukan alamat yang tercetak di
                    // sampingnya. Keduanya kini menunjuk tempat yang sama, tetapi
                    // tetap dipisah: alamat adalah tulisan yang dibaca manusia dan
                    // ikut tercetak di kwitansi, sedangkan yang ini umpan untuk
                    // Google. Merapikan ejaan alamat tidak boleh sampai menggeser
                    // titik petanya; alasannya di config('orcha.peta_kantor').
                    'tautan' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode(config('orcha.peta_kantor.tempat')),
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
    /*
     | Peta dirakit dari config('orcha.peta_kantor'), BUKAN dari config('orcha.alamat').
     |
     | Dulu dari alamat, dan petanya mendarat di kota yang salah karena
     | alamatnya sendiri salah. Alamatnya sudah dikoreksi, tetapi keduanya tetap
     | dipisah; alasannya ada di komentar config-nya.
     |
     | Koordinat (ll) diikutkan supaya titiknya pasti: tanpa itu Google
     | menebak sendiri dari nama yang dicari, dan nama "Orcha Journey" bisa
     | mendarat di tempat lain yang kebetulan mirip.
     */
    $petaTempat = config('orcha.peta_kantor.tempat');
    $petaTitik = config('orcha.peta_kantor.lat') . ',' . config('orcha.peta_kantor.lng');
    $peta = 'https://maps.google.com/maps?q=' . urlencode($petaTempat) . '&ll=' . $petaTitik . '&z=16&output=embed';
    $petaTautan = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($petaTempat);
@endphp

<div>
    <x-page-hero title="Kontak Kami" eyebrow="Hubungi Orcha Journey"
        subtitle="Pilih kanal yang paling nyaman untuk Anda. Pertanyaan lewat WhatsApp biasanya dibalas dalam hitungan menit."
        image="images/HERO/kontak.webp" posisi="center top" />


    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, Tentang Kami, Paket Wisata, Sewa Kendaraan, Destinasi, dan
         Blog. Sebelumnya halaman ini memakai .section-orcha di atas latar
         putih, sehingga ada pita putih kosong antara foto hero dan isinya. --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        {{-- ============ KANAL KONTAK ============
             Panel GELAP, warna logo. Halaman ini tadinya putih dari atas sampai
             bawah — tujuh kartu putih di atas latar putih, tanpa satu pun
             bidang yang membawa warna perusahaan. Yang paling pantas memegang
             bidang itu deretan kanal: ia yang dicari orang begitu halaman
             terbuka. --}}
        <section class="relative overflow-hidden panel-orcha panel-orcha-gelap">
            {{-- Lambang orca sebagai cap air, sama seperti "Cara Pesan" di
                 beranda. aria-hidden + alt kosong: ini hiasan. --}}
            <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true" loading="lazy"
                width="512" height="512" class="cap-orca">

            <div class="relative max-w-2xl mb-6">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Cara Menghubungi</p>
                {{-- Angkanya DIHITUNG dari daftarnya, tidak ditulis lepas.

                     Judul ini sempat berbunyi "Empat jalan" sementara kanalnya
                     sudah enam — kalimat yang membantah isinya sendiri, dan
                     tidak ada satu pun galat yang menandainya. --}}
                @php
                    $angkaKata = [1 => 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan'];
                    $jumlahKanal = $angkaKata[count($kanal)] ?? count($kanal);
                @endphp
                <h2 class="mt-3 judul-panel">{{ $jumlahKanal }} jalan, pilih yang paling nyaman.</h2>
            </div>

            {{-- Tiga kolom, dua baris — bukan empat atau enam sejajar.

                 Dengan enam kanal, empat kolom menyisakan dua ubin menggantung di
                 baris kedua, sedangkan enam kolom memeras tiap ubin jadi 180px dan
                 gambar di dalamnya kembali jadi noda. Tiga kolom membagi enamnya
                 rata dan memberi tiap ubin ruang yang cukup untuk gambarnya. --}}
            {{-- DUA kolom, bukan tiga, dan ubinnya MENDATAR.

                 Enam ubin menumpuk ke bawah memakan lebih dari 600px hanya untuk
                 enam baris kontak. Dengan gambar di samping tulisannya, tiap ubin
                 tinggal sekitar 150px — dan gambarnya justru lebih lebar daripada
                 saat ia bertengger di atas ubin selebar sepertiga panel.

                 Bentuk ubinnya ada di .ubin-kanal pada new-homepage.css: sudut
                 daun berselang-seling, bidang gambar bergumpal, dan gambarnya
                 berpindah sisi di ubin genap. --}}
            {{-- Dua kolom baru mulai di lg (1024px), bukan sm (640px).

                 Terukur pada lebar 820px: dua kolom menyisakan lajur tulisan
                 selebar 190px di samping gambar, dan "halo@orchajourney.com"
                 patah jadi dua baris. Di bawah 1024px satu kolom justru lebih
                 lega — ubinnya tetap mendatar, cuma selebar panel. --}}
            <div class="relative grid gap-3 lg:grid-cols-2 sm:gap-4">
                @foreach ($kanal as $item)
                    <div class="ubin-kanal">
                        @isset($item['gambar'])
                            {{-- Bidang bergumpal sebagai LATAR, gambarnya utuh di
                                 dalamnya (object-contain) — tidak ada yang
                                 terpotong oleh bentuk itu.

                                 Semua gambar kanal dipasang di kanvas 660x440 yang
                                 sama, jadi satu pasang width/height benar untuk
                                 semuanya dan ruangnya dipesan tepat sebelum
                                 gambarnya terunduh.

                                 alt KOSONG — judul kanalnya ada tepat di sebelah,
                                 dan isi gambarnya sudah tercetak sebagai tulisan
                                 sungguhan di ubin ini juga. --}}
                            <span class="kanal-bidang">
                                <img src="{{ asset('images/kanal/' . $item['gambar'] . '.webp') }}" alt=""
                                    loading="lazy" decoding="async" width="660" height="440">
                            </span>
                        @else
                            {{-- Kanal tanpa gambar: ubin emas berisi ikonnya,
                                 dipasang di bidang bergumpal yang sama supaya
                                 ukurannya tetap sejajar dengan yang bergambar. --}}
                            <span class="kanal-bidang">
                                <span class="flex items-center justify-center w-14 h-14 rounded-2xl bg-orcha-sun text-orcha-navy">
                                    <x-dynamic-component :component="$item['ikon']" class="w-7 h-7" />
                                </span>
                            </span>
                        @endisset

                        {{-- flex-1: blok ini harus MENGISI sisa lebar ubin.

                             Tanpa itu lebarnya mengikuti isinya sendiri, dan di
                             ubin genap yang arahnya row-reverse isinya dipadatkan
                             dari KANAN — jadi kanal yang keterangannya lebih
                             pendek mulai lebih menjorok ke kanan daripada
                             tetangganya. Terukur: tulisan TikTok 333px sedangkan
                             Email dan Kantor 392px, dan tepi kirinya meleset 60px,
                             persis selisih itu.

                             Di kolom kiri cacatnya tidak terlihat karena arahnya
                             row: tepi yang bergerigi jatuh di sisi kanan, di dalam
                             jarak tepi ubin.

                             min-w-0: anak flex tidak boleh menyusut di bawah lebar
                             isinya kecuali disuruh, dan alamat kantor yang panjang
                             akan mendorong bidang gambar keluar ubin. --}}
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold font-heading text-orcha-navy">{{ $item['judul'] }}</h3>

                            {{-- Nilainya emas tulisan (4,6:1 di atas krem), bukan
                                 --color-orcha-ocean yang kini abu kehijauan dan
                                 nyaris sewarna keterangan di bawahnya. --}}
                            <p class="mt-0.5 text-sm font-semibold break-words text-orcha-amber">{{ $item['nilai'] }}</p>
                            <p class="mt-1 text-sm leading-snug text-slate-600">{{ $item['catatan'] }}</p>

                            <a href="{{ $item['tautan'] }}" target="_blank" rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 mt-2 text-sm font-bold transition text-orcha-amber hover:gap-2.5 hover:text-orcha-sunset">
                                {{ $item['aksi'] }}
                                <x-heroicon-o-arrow-right class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============ FORMULIR + PESAN SIAP KIRIM ============ --}}
        <section class="panel-orcha">
            <div class="max-w-2xl mb-7">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Kirim Pesan</p>
                <h2 class="mt-3 judul-panel !text-[clamp(1.35rem,2.2vw,1.9rem)]">Tulis panjang pun tidak masalah.</h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    {{-- Tidak menyebut "di sebelah kanan": di layar ponsel kolom itu
                         justru berada di BAWAH formulir, dan petunjuk arah yang
                         salah lebih membingungkan daripada tidak ada petunjuk. --}}
                    Pesan masuk ke panel kami dan dibalas lewat WhatsApp atau email yang Anda cantumkan. Kalau sedang
                    buru-buru, pakai pesan siap kirim yang sudah kami siapkan.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">

                {{-- Formulir kontak --}}
                <div class="lg:col-span-7">
                    @if ($terkirim)
                        <div class="p-6 text-center border rounded-3xl border-orcha-foam bg-orcha-foam/70 sm:p-8">
                            {{-- Toska, bukan --color-orcha-sky. Peubah itu kini emas
                                 pucat, dan tanda centang emas di atas krem nyaris
                                 tidak terlihat — kabar baik yang tidak sampai. --}}
                            <x-heroicon-s-check-circle class="w-12 h-12 mx-auto text-orcha-toska" />
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
                        <form wire:submit="kirim" class="space-y-5">
                            {{-- Perangkap bot, disembunyikan dari pengguna --}}
                            <div class="hidden" aria-hidden="true">
                                <label for="situs">Jangan diisi</label>
                                <input id="situs" type="text" wire:model="situs" tabindex="-1" autocomplete="off">
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
                                        class="font-semibold transition text-orcha-amber hover:text-orcha-sunset hover:underline">kebijakan privasi</a>.
                                </p>

                                {{-- shrink-0: tombol ini anak flex, dan tanpa itu ia
                                     ikut menyusut mengalah pada kalimat panjang di
                                     sebelah kirinya — "Kirim Pesan" patah dua baris
                                     dan tombolnya berubah jadi gumpalan persegi. --}}
                                <button type="submit" class="btn-orcha btn-orcha-primary shrink-0" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="kirim">Kirim Pesan</span>
                                    <span wire:loading wire:target="kirim">Mengirim…</span>
                                    <x-heroicon-o-paper-airplane class="w-5 h-5" wire:loading.remove
                                        wire:target="kirim" />
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Pesan cepat + jam operasional --}}
                <div class="space-y-4 lg:col-span-5">
                    <div class="p-5 border rounded-3xl border-orcha-foam bg-orcha-foam/40 sm:p-6">
                        <h3 class="text-base font-bold font-heading text-orcha-navy">Atau pakai pesan siap kirim</h3>
                        <p class="mt-1.5 text-sm text-slate-600">Pilih salah satu, WhatsApp terbuka dengan pesan yang
                            sudah terisi.</p>

                        <div class="mt-4 space-y-2.5">
                            @foreach ($pesanCepat as $pesanSiap)
                                <a href="{{ $waLink($pesanSiap) }}" target="_blank" rel="noopener noreferrer"
                                    class="flex items-start gap-3 p-3.5 text-sm transition bg-white border rounded-2xl border-orcha-foam text-slate-600 hover:border-orcha-sun hover:shadow-[0_12px_24px_-18px_rgba(40,28,18,.6)]">
                                    <x-bi-whatsapp class="w-5 h-5 mt-0.5 shrink-0 text-green-600" />
                                    <span>{{ $pesanSiap }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-5 border rounded-3xl border-orcha-foam sm:p-6">
                        <h3 class="flex items-center gap-2 text-base font-bold font-heading text-orcha-navy">
                            <x-heroicon-o-clock class="w-5 h-5 text-orcha-toska" />
                            Jam Operasional
                        </h3>
                        <dl class="mt-3 divide-y divide-orcha-foam">
                            @foreach ($jamOperasional as $baris)
                                <div class="flex items-center justify-between gap-4 py-2.5 text-sm">
                                    <dt class="text-slate-600">{{ $baris['hari'] }}</dt>
                                    <dd class="font-semibold text-right text-orcha-navy">{{ $baris['jam'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ PETA ============ --}}
        <section class="overflow-hidden panel-orcha !p-0">
            <div class="flex flex-col gap-3 px-[clamp(1.35rem,3.4vw,3rem)] py-5 sm:flex-row sm:items-center sm:justify-between">
                {{-- Alamatnya TIDAK diulang di sini: ia sudah tercetak lengkap di
                     ubin "Kantor" pada panel paling atas, dan peta di bawah ini
                     menunjukkan tempatnya sendiri. --}}
                <div>
                    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Kantor Kami</p>
                    <p class="mt-2 text-sm text-slate-600">Mampir boleh — kabari dulu supaya tim kami siap menerima.</p>
                </div>
                <a href="{{ $petaTautan }}"
                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-outline !py-2.5 !text-sm shrink-0">
                    <x-heroicon-o-map-pin class="w-4 h-4" />
                    Buka di Google Maps
                </a>
            </div>

            {{-- loading="lazy" dipertahankan: peta Google berat dan letaknya jauh
                 di bawah layar pertama. Bingkai ini TIDAK ikut tergambar pada
                 tangkapan layar penuh otomatis — itu sifat `lazy`, bukan peta
                 yang rusak; sudah dibuktikan dengan menggulirkannya ke layar. --}}
            <iframe src="{{ $peta }}" title="Peta lokasi kantor Orcha Journey" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                class="block w-full border-0 h-80 sm:h-[26rem]"></iframe>
        </section>

        {{-- ============ TAUTAN BANTUAN ============ --}}
        <section class="panel-orcha panel-orcha-krem">
            <div class="max-w-2xl mb-6">
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Sebelum Bertanya</p>
                <h2 class="mt-3 judul-panel !text-[clamp(1.35rem,2.2vw,1.9rem)]">Tiga hal yang paling sering ditanyakan.</h2>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
                @foreach ([['FAQ', 'Jawaban cepat soal pemesanan dan pembayaran', route('faq')], ['Ketentuan Pembayaran & DP', 'Besaran uang muka dan tenggat pelunasan', route('ketentuan-pembayaran')], ['Kebijakan Pengembalian', 'Aturan pembatalan dan refund', route('kebijakan-pengembalian')]] as [$judul, $isi, $tautan])
                    <a href="{{ $tautan }}" wire:navigate class="ubin-orcha ubin-orcha-putih group">
                        <h3 class="flex items-center justify-between gap-2 font-bold transition font-heading text-orcha-navy group-hover:text-orcha-amber">
                            {{ $judul }}
                            <x-heroicon-o-arrow-right
                                class="w-4 h-4 transition-transform shrink-0 text-orcha-sun group-hover:translate-x-1" />
                        </h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $isi }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <x-skrip-isian />
</div>
