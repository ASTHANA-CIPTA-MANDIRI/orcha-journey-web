<?php

use App\Models\Etalase\DestinationPopuler;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.guest')] #[Title('Destinasi Populer — Orcha Journey')] class extends Component {
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'wilayah', except: '')]
    public string $wilayah = '';

    /**
     * Destinasi yang sedang dibuka detailnya.
     *
     * Ikut di alamat, bukan hanya keadaan di layar: pengunjung yang menemukan
     * satu destinasi menarik biasanya mengirimkannya ke teman seperjalanan, dan
     * tautan yang selalu mendarat di daftar memaksa penerimanya mencari sendiri.
     * Tombol kembali peramban pun jadi menutup detail, bukan meninggalkan
     * halaman.
     */
    #[Url(as: 'lihat', except: null)]
    public ?int $lihat = null;

    public function buka(int $id): void
    {
        $this->lihat = $id;
    }

    public function tutupDetail(): void
    {
        $this->lihat = null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWilayah(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'destinations' => DestinationPopuler::query()
                // Dikelompokkan supaya filter wilayah tidak ikut ter-OR
                ->when($this->search, fn ($q) => $q->where(fn ($sub) => $sub->where('destination_name', 'like', "%{$this->search}%")
                    ->orWhere('provinsi', 'like', "%{$this->search}%")))
                ->diWilayah($this->wilayah ?: null)
                // Yang baru ditambahkan di atas — halaman ini ikut jadi kabar
                // "ada apa yang baru", bukan daftar yang sama setiap kali
                // dibuka. Jumlah pengunjungnya tetap tampil di tiap kartu,
                // jadi yang hilang urutannya saja, bukan keterangannya.
                ->latest()
                ->latest('id')
                ->paginate(9),
            // Dicari terpisah dari daftar: destinasi yang dibuka lewat tautan
            // belum tentu ada di halaman yang sedang tampil, apalagi saat
            // penyaringnya sedang menyala.
            'detail' => $this->lihat ? DestinationPopuler::find($this->lihat) : null,
            'total' => DestinationPopuler::count(),
            'totalPengunjung' => (int) DestinationPopuler::sum('total_visitor'),
            'totalProvinsi' => DestinationPopuler::distinct()->count('provinsi'),
            'daftarWilayah' => collect(\App\Models\Etalase\WilayahTambahan::gabungan())
                ->map(fn ($label, $kunci) => [
                    'kunci' => $kunci,
                    'label' => $label,
                    'jumlah' => DestinationPopuler::where('wilayah', $kunci)->count(),
                ])
                ->filter(fn ($baris) => $baris['jumlah'] > 0)
                ->values(),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Destinasi Populer" eyebrow="Seluruh Indonesia"
        subtitle="Dari Pulau Weh sampai Raja Ampat. Tempat-tempat yang paling sering diminta pelanggan kami, lengkap dengan jumlah pengunjung yang sudah kami antar."
        image="images/HERO/destinasi.webp" />

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, Tentang Kami, Paket Wisata, dan Sewa Kendaraan. --}}
    <div class="container-orcha rak-panel pb-6 space-y-4 sm:space-y-5">

        <section class="panel-orcha">
            {{-- Ringkasan + pencarian --}}
            <div class="flex flex-col gap-5 mb-8 lg:flex-row lg:items-center lg:justify-between">
                {{-- Angka dulu ANGKA DI BAWAH LABEL dengan huruf label sebesar
                     11px; yang pertama terbaca justru labelnya, padahal angkanya
                     yang jadi isi. Sekarang angkanya di atas dan berwarna emas,
                     labelnya di bawah — urutan baca mengikuti urutan penting. --}}
                <div class="flex flex-wrap gap-3">
                    @foreach ([[$total, 'Destinasi'], [$totalProvinsi, 'Provinsi'], [shortNumber($totalPengunjung), 'Pengunjung diantar']] as [$angka, $labelAngka])
                        <div class="px-5 py-3 border rounded-2xl border-orcha-foam bg-orcha-foam/60">
                            <p class="text-2xl font-black leading-none font-heading text-orcha-amber">{{ $angka }}</p>
                            <p class="mt-1.5 text-xs font-semibold tracking-wider uppercase text-slate-500">{{ $labelAngka }}</p>
                        </div>
                    @endforeach
                </div>

                <label class="relative w-full lg:max-w-sm">
                    <span class="sr-only">Cari destinasi</span>
                    <x-heroicon-o-magnifying-glass
                        class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.400ms="search"
                        placeholder="Cari nama destinasi atau provinsi..."
                        class="w-full py-3 pr-4 text-sm bg-white border pl-11 rounded-2xl border-orcha-foam focus:border-orcha-sun focus:outline-none focus:ring-2 focus:ring-orcha-sun/30">
                </label>
            </div>

            {{-- Filter wilayah --}}
            {{-- Dipusatkan di layar lebar. Baris di atasnya sudah terbagi kiri-kanan
                 (ringkasan dan pencarian), jadi deretan tab yang ikut rata kiri
                 menggantung tanpa penyeimbang di kanannya. Di layar sempit tetap
                 rata kiri: deretnya digulung mendatar, dan yang digulung harus
                 mulai dari tepi. --}}
            <div class="mb-10 tab-scroller lg:justify-center">
                <button type="button" wire:click="$set('wilayah', '')"
                    class="tab-orcha {{ $wilayah === '' ? 'tab-orcha-active' : '' }}">
                    Semua Wilayah <span class="opacity-60">({{ $total }})</span>
                </button>
                @foreach ($daftarWilayah as $baris)
                    <button type="button" wire:click="$set('wilayah', '{{ $baris['kunci'] }}')"
                        class="tab-orcha {{ $wilayah === $baris['kunci'] ? 'tab-orcha-active' : '' }}">
                        {{ $baris['label'] }} <span class="opacity-60">({{ $baris['jumlah'] }})</span>
                    </button>
                @endforeach
            </div>

            @if ($destinations->isEmpty())
                <div class="p-12 text-center card-orcha">
                    <x-heroicon-o-map-pin class="w-12 h-12 mx-auto text-orcha-mist" />
                    <p class="mt-3 font-semibold text-orcha-navy">Destinasi tidak ditemukan.</p>
                    <p class="mt-1 text-sm text-slate-500">Coba kata kunci lain, atau tanyakan destinasi impian Anda ke
                        kami.</p>
                </div>
            @else
                {{-- ==========================================================
                     KARTU DESTINASI BERIRAMA

                     Sebelumnya sembilan kartu di halaman ini berbentuk sama
                     persis: satu kisi 3x3 berisi persegi yang hanya berbeda
                     isinya. Mata membaca kartu pertama lalu menganggap delapan
                     sisanya sudah diketahui — daftarnya terasa datar walaupun
                     tiap destinasinya menarik.

                     Iramanya dibuat dari TIGA hal sekaligus, bukan satu:

                     1. UKURAN — kartu pertama tiap halaman mengambil dua lajur
                        (dan dua baris di layar lebar), jadi halaman ini punya
                        satu titik masuk yang jelas, bukan sembilan yang setara.
                     2. BENTUK — batas foto dan isi bukan garis lurus melainkan
                        lengkung, dan lengkungnya bergilir tiga rupa.
                     3. WARNA — aksen bergilir dua warna merek (toska dan emas),
                        dipakai pada goresan di lengkung, semburat di sudut isi,
                        dan bingkai kartu saat disentuh kursor.

                     Tiga dan dua berganti bersama, jadi polanya baru berulang
                     tiap ENAM kartu — lebih panjang daripada satu baris kisi,
                     sehingga tidak ada baris yang terbaca sebagai pengulangan.
                     Sembilan kartu per halaman juga mengisi kedua tata letak
                     dengan pas: 1 besar + 8 kecil memenuhi kisi dua lajur
                     maupun tiga lajur tanpa menyisakan petak kosong.
                --}}
                @php
                    /*
                     | Lengkung pemisah foto dan isi.
                     |
                     | viewBox dipatok 400x48 dengan preserveAspectRatio="none",
                     | jadi SATU jalur yang sama meregang mengikuti lebar kartu:
                     | kartu sorot yang dua kali lebih lebar tidak perlu jalur
                     | sendiri, dan lengkungnya tetap setinggi yang sama.
                     |
                     | 'isi' diberi warna kartu, jadi isinya seolah naik
                     | menerobos foto. 'garis' hanya tepi atasnya, dipakai
                     | sebagai goresan tipis berwarna aksen.
                     |
                     | Dasar 'isi' sengaja ditarik sampai y=64, JAUH melewati
                     | dasar viewBox yang 48. Kalau ia berhenti pas di 48,
                     | tepi bawah jalurnya berimpit dengan tepi elemen — dan
                     | karena tinggi elemennya pecahan (clamp), Safari
                     | membulatkan isian itu ke DALAM dan menyisakan satu
                     | baris piksel foto yang bocor melintang penuh selebar
                     | kartu. Terukur di potret pengguna 27 Nov 2026: satu
                     | baris warna foto di antara dua baris putih bersih.
                     | Chrome tidak pernah memperlihatkannya, jadi ini TIDAK
                     | bisa diuji dengan potret Chrome. Kelebihannya dipotong
                     | <svg> sendiri (overflow:hidden bawaan).
                     | Keduanya jalur terpisah karena goresannya digambar
                     | SESUDAH isinya — kalau satu jalur, warna isi akan menutupi
                     | separuh goresannya sendiri.
                     */
                    $lengkungDest = [
                        [
                            'isi' => 'M0,64 L0,26 C76,2 158,4 228,18 C296,31 350,40 400,33 L400,64 Z',
                            'garis' => 'M0,26 C76,2 158,4 228,18 C296,31 350,40 400,33',
                        ],
                        [
                            'isi' => 'M0,64 L0,33 C62,47 112,9 182,13 C252,17 304,43 400,19 L400,64 Z',
                            'garis' => 'M0,33 C62,47 112,9 182,13 C252,17 304,43 400,19',
                        ],
                        [
                            'isi' => 'M0,64 L0,41 C124,41 252,8 400,5 L400,64 Z',
                            'garis' => 'M0,41 C124,41 252,8 400,5',
                        ],
                    ];

                    // Dua warna saja, bukan satu untuk tiap wilayah: warna di
                    // sini HIASAN, dan warna hiasan yang berbeda-beda terbaca
                    // sebagai penggolongan yang ternyata tidak berarti apa-apa.
                    $aksenDest = ['var(--color-orcha-toska)', 'var(--color-orcha-sun)'];
                @endphp

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 sm:gap-6">
                    @foreach ($destinations as $dest)
                        <article
                            class="kartu-dest kartu-dest-v{{ $loop->index % 3 + 1 }} flex flex-col overflow-hidden card-orcha group {{ $loop->first ? 'sm:col-span-2 xl:row-span-2' : '' }}"
                            style="--aksen: {{ $aksenDest[$loop->index % 2] }}">

                            <div
                                class="relative overflow-hidden {{ $loop->first ? 'h-56 sm:h-72 xl:h-auto xl:flex-1 xl:min-h-[24rem]' : 'h-52 sm:h-56' }}">
                                {{-- onerror: BERKAS fotonya bisa hilang walau kolomnya
                                     terisi — dan yang muncul ikon rusak bawaan
                                     peramban di tengah kartu. `?:` di atas hanya
                                     menangkap kolom yang KOSONG, bukan berkas yang
                                     sudah tidak ada. Di sini fotonya diganti foto
                                     cadangan, dan penangannya dilepas supaya tidak
                                     berputar kalau cadangannya pun gagal. --}}
                                <img src="{{ $dest->main_photo ?: asset('images/pantai-senja.webp') }}"
                                    alt="{{ $dest->destination_name }}" loading="lazy"
                                    onerror="this.onerror=null; this.src='{{ asset('images/pantai-senja.webp') }}';"
                                    {{-- absolute + inset-0 + w-full + h-full, bukan sekadar
                                         h-full: di kartu sorot wadahnya tidak punya tinggi
                                         sendiri (xl:flex-1), jadi `height:100%` luruh jadi
                                         auto dan gambarnya memakai TINGGI ASLINYA — kartunya
                                         memanjang sampai 800px tanpa satu pun galat. Dengan
                                         absolute, gambar tidak ikut menghitung tinggi wadah
                                         sama sekali; tingginya murni dari petak kisi. --}}
                                    class="absolute inset-0 object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">

                                {{-- Selubungnya dibuat kelas, bukan utilitas bertingkat:
                                     judulnya kini duduk LEBIH TINGGI supaya tidak
                                     tertutup lengkung, jadi bagian gelapnya harus naik
                                     ikut judulnya — bukan hanya menebal di dasar yang
                                     justru tertutup lengkung. --}}
                                <div class="absolute inset-0 selubung-dest"></div>

                                {{-- Nol pengunjung bukan bukti apa-apa; ia justru membaca
                                     terbalik dari maksud lencananya. Destinasi yang baru
                                     dicatat memang belum pernah kami antar siapa pun —
                                     yang perlu dikatakan "baru", bukan "0". --}}
                                @if ($dest->total_visitor > 0)
                                    <span
                                        class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full top-3 left-3 text-orcha-navy bg-orcha-sun">
                                        <x-heroicon-s-user-group class="w-4 h-4" />
                                        {{ shortNumber($dest->total_visitor) }} pengunjung
                                    </span>
                                @elseif ($dest->created_at?->gt(now()->subDays(30)))
                                    <span
                                        class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-white rounded-full top-3 left-3 bg-orcha-sky">
                                        {{-- Jam, bukan kilau: yang ditandai lencana ini "baru
                                             dicatat", dan kilau terbaca sebagai hiasan emoji
                                             alih-alih keterangan. Lencana pengunjung di
                                             sebelahnya juga memakai ikon yang menerangkan
                                             isinya, bukan yang menghiasinya. --}}
                                        <x-heroicon-s-clock class="w-4 h-4" />
                                        Baru
                                    </span>
                                @endif

                                <span
                                    class="absolute px-3 py-1 text-xs font-bold rounded-full top-3 right-3 text-white bg-white/20 backdrop-blur">
                                    {{ $dest->wilayah_label }}
                                </span>

                                {{-- bottom-14: lengkung di bawah setinggi paling banyak
                                     3rem, jadi judul yang duduk di bawah angka itu akan
                                     tertelan separuh — dan karena lengkungnya berwarna
                                     putih seperti kartunya, potongannya tidak terbaca
                                     sebagai cacat, hanya sebagai huruf yang hilang. --}}
                                <div class="absolute bottom-14 left-4 right-4">
                                    {{-- Namanya tautan sungguhan ke halaman destinasinya.

                                         Tombol "Lihat Detail" di bawah membuka panel di halaman
                                         ini — itu tetap, alasannya ada di panelnya. Tetapi
                                         tombol bukan tautan: mesin pencari tidak menekannya,
                                         sehingga tanpa <a> di sini tidak ada satu pun jalan
                                         menuju halaman destinasi selain peta situs. --}}
                                    <h2
                                        class="font-bold leading-tight text-white font-heading {{ $loop->first ? 'text-2xl sm:text-3xl xl:text-4xl' : 'text-xl sm:text-2xl' }}">
                                        <a href="{{ route('destinasi.detail', $dest) }}" wire:navigate
                                            class="transition hover:text-orcha-sun">
                                            {{ $dest->destination_name }}
                                        </a>
                                    </h2>
                                    @if ($dest->alamat_singkat)
                                        {{-- Daerahnya disebut lebih dulu: itu yang dicari dan
                                             ditanyakan penyewa — berangkat dari mana, menginap
                                             di mana. "Jawa Timur" saja membentang 47 ribu km
                                             persegi. --}}
                                        <p class="flex items-center gap-1 mt-1 text-sm text-slate-200">
                                            <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                            {{ $dest->alamat_singkat }}
                                        </p>
                                    @endif
                                </div>

                                {{-- vector-effect="non-scaling-stroke" wajib: viewBox-nya
                                     diregangkan tidak seimbang (preserveAspectRatio="none"),
                                     dan tanpa itu goresannya ikut melar — tebal di kartu
                                     sorot, tipis di kartu biasa. --}}
                                <svg class="absolute left-0 w-full lengkung-dest" viewBox="0 0 400 48"
                                    preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                    <path d="{{ $lengkungDest[$loop->index % 3]['isi'] }}" fill="#fff" />
                                    <path d="{{ $lengkungDest[$loop->index % 3]['garis'] }}" fill="none"
                                        stroke="var(--aksen)" stroke-width="2.5" stroke-linecap="round"
                                        vector-effect="non-scaling-stroke" />
                                </svg>
                            </div>

                            <div
                                class="relative flex flex-col p-5 sm:p-6 {{ $loop->first ? 'xl:flex-none' : 'flex-1' }}">
                                {{-- Semburat warna di sudut: bidang putih sebesar ini di
                                     bawah foto yang ramai akan terbaca kosong. Letaknya
                                     bergilir mengikuti rupa lengkungnya supaya kartu
                                     bersebelahan tidak punya titik berat yang sama. --}}
                                <span class="semburat-dest" aria-hidden="true"></span>

                                {{-- Dipotong di tiga baris dan tingginya dipatok. Keterangan
                                     yang panjangnya berbeda-beda menggeser galeri kecil di
                                     bawahnya ke ketinggian yang berbeda pula, dan sebaris
                                     kartu jadi terbaca berantakan walaupun tiap kartunya
                                     rapi. --}}
                                <p
                                    class="relative min-h-[4.1rem] mb-4 text-sm leading-relaxed line-clamp-3 text-slate-600 {{ $loop->first ? 'sm:max-w-2xl' : '' }}">
                                    {{ $dest->deskripsi }}
                                </p>

                                {{-- Galeri kecil dan tombolnya satu kelompok yang menempel ke
                                     dasar kartu. Dipisah, galerinya mengikuti panjang
                                     keterangan sementara tombolnya menempel ke dasar — dua
                                     ketinggian berbeda dalam satu baris kartu. --}}
                                {{-- Lebar tindakan kartu sorot DIPATOK. Tanpa itu tombol
                                     "Lihat Detail" meregang sampai 640px — tombol selebar
                                     itu tidak terbaca sebagai tombol lagi, melainkan sebagai
                                     pita; dan jarak antara tulisannya dan ikon WhatsApp di
                                     ujung kanan jadi sejauh mata harus melompat. --}}
                                <div class="relative mt-auto {{ $loop->first ? 'sm:max-w-sm' : '' }}">
                                    @if (!empty($dest->others_photo))
                                        <div class="flex items-center justify-between gap-2 mb-4">
                                            <div class="flex gap-2">
                                                @foreach (array_slice($dest->others_photo, 0, 4) as $thumb)
                                                    <img src="{{ $thumb }}" alt="" loading="lazy"
                                                        {{-- Ubin foto yang berkasnya hilang MENYINGKIR,
                                                         bukan diganti cadangan: deretan ini
                                                         pratinjau galeri, dan menampilkan foto
                                                         senja yang sama berkali-kali justru
                                                         membohongi isinya. --}}
                                                        onerror="this.remove();"
                                                        class="object-cover w-12 h-12 rounded-xl ring-1 ring-orcha-foam">
                                                @endforeach
                                            </div>

                                            {{-- Rata kanan, seimbang dengan gambarnya di kiri:
                                             yang kiri contoh, yang kanan berapa banyak. --}}
                                            <span class="text-xs font-semibold whitespace-nowrap text-slate-400">
                                                {{ count($dest->others_photo) + 1 }} foto
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Dua tindakan berdampingan: melihat dulu, atau langsung
                                     bertanya. Sebelumnya hanya ada tombol WhatsApp, sehingga
                                     satu-satunya cara mengetahui lebih banyak adalah menanyakannya
                                     — pertanyaan yang jawabannya sudah ada di sini. --}}
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="buka({{ $dest->id }})"
                                            class="flex-1 btn-orcha btn-orcha-sun !py-2.5 !text-sm">
                                            <x-heroicon-o-photo class="w-4 h-4" />
                                            Lihat Detail
                                        </button>

                                        <a href="{{ $wa("Halo Orcha Journey, saya ingin ke {$dest->destination_name}. Ada paket atau armadanya?") }}"
                                            target="_blank" rel="noopener noreferrer"
                                            class="btn-orcha btn-orcha-outline !py-2.5 !text-sm !px-4"
                                            aria-label="Tanya paket ke {{ $dest->destination_name }} lewat WhatsApp">
                                            <x-bi-whatsapp class="w-4 h-4" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $destinations->links('partials.paginasi-orcha') }}
                </div>
            @endif

        </section>

        <section class="panel-orcha panel-orcha-krem">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left sm:justify-between">
                <div>
                    <p class="text-lg font-bold font-heading text-orcha-navy">Punya destinasi impian sendiri?</p>
                    <p class="mt-1 text-sm text-slate-600">Sebutkan tempatnya — kami susun rute, armada, dan biayanya
                        untuk Anda.</p>
                </div>
                <a href="{{ $wa('Halo Orcha Journey, saya ingin membuat perjalanan ke destinasi pilihan saya.') }}"
                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-sun">
                    <x-bi-whatsapp class="w-5 h-5" />
                    Rencanakan Sekarang
                </a>
            </div>
        </section>
    </div>

    {{-- Jendela detail destinasi.

         Di halaman yang sama, bukan halaman tersendiri: pengunjung sedang
         menyusuri daftar dengan penyaring wilayah yang sudah ia atur, dan
         memindahkannya ke halaman lain berarti ia harus menyusunnya lagi saat
         kembali. Keadaan terbukanya tetap ikut di alamat, jadi tautannya bisa
         dibagikan dan tombol kembali peramban menutupnya. --}}
    @if ($detail)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
            role="dialog" aria-modal="true" aria-labelledby="judul-destinasi" wire:key="detail-{{ $detail->id }}">
            {{-- Latar gelap ikut menutup: itu yang pertama dicoba orang saat
                 ingin keluar, sebelum mencari tombol silang. --}}
            <button type="button" wire:click="tutupDetail" aria-label="Tutup detail"
                class="absolute inset-0 bg-orcha-navy/70 backdrop-blur-sm"></button>

            @php
                $galeri = array_values(array_filter(array_merge(
                    [$detail->main_photo],
                    $detail->others_photo ?? [],
                )));
            @endphp

            <div x-data="{ aktif: 0 }" @keydown.escape.window="$wire.tutupDetail()"
                class="relative w-full max-w-3xl overflow-hidden bg-white shadow-2xl rounded-t-3xl sm:rounded-3xl max-h-[92vh] overflow-y-auto">

                <div class="relative bg-orcha-foam aspect-[16/10]">
                    @forelse ($galeri as $urutan => $foto)
                        <img src="{{ $foto }}" alt="{{ $detail->destination_name }}"
                            x-show="aktif === {{ $urutan }}" x-cloak loading="lazy" decoding="async"
                            class="absolute inset-0 object-cover w-full h-full">
                    @empty
                        <div class="flex items-center justify-center w-full h-full text-slate-400">
                            <x-heroicon-o-photo class="w-12 h-12" />
                        </div>
                    @endforelse

                    <div class="absolute inset-x-0 bottom-0 h-32 pointer-events-none bg-gradient-to-t from-orcha-navy/90 to-transparent">
                    </div>

                    <button type="button" wire:click="tutupDetail" aria-label="Tutup detail"
                        class="absolute flex items-center justify-center w-9 h-9 rounded-full top-4 right-4 bg-white/90 text-orcha-navy backdrop-blur hover:bg-white">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>

                    <span class="absolute inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full top-4 left-4 text-orcha-navy bg-orcha-sun">
                        <x-heroicon-s-user-group class="w-4 h-4" />
                        {{ shortNumber($detail->total_visitor) }} pengunjung diantar
                    </span>

                    <div class="absolute inset-x-0 bottom-0 p-5 sm:p-7">
                        <h2 id="judul-destinasi" class="text-2xl font-bold text-white font-heading sm:text-3xl">
                            {{ $detail->destination_name }}
                        </h2>
                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-sm text-slate-200">
                            @if ($detail->alamat_singkat)
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-s-map-pin class="w-4 h-4 text-orcha-sun" />
                                    {{ $detail->alamat_singkat }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-globe-asia-australia class="w-4 h-4 text-orcha-sun" />
                                {{ $detail->wilayah_label }}
                            </span>
                        </p>
                    </div>
                </div>

                {{-- Foto lain dipilih di sisi peramban: mengganti gambar tidak
                     mengubah apa pun di server, dan perjalanan bolak-balik ke
                     server hanya membuat galerinya terasa berat. --}}
                @if (count($galeri) > 1)
                    <div class="flex gap-2 px-5 pt-4 overflow-x-auto sm:px-7">
                        @foreach ($galeri as $urutan => $foto)
                            <button type="button" @click="aktif = {{ $urutan }}"
                                :class="aktif === {{ $urutan }} ? 'ring-2 ring-orcha-ocean' : 'ring-1 ring-orcha-foam opacity-70'"
                                class="overflow-hidden rounded-xl shrink-0"
                                aria-label="Lihat foto {{ $urutan + 1 }}">
                                <img src="{{ $foto }}" alt="" loading="lazy" decoding="async"
                                    class="object-cover w-16 h-16">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="p-5 sm:p-7">
                    @if ($detail->deskripsi)
                        <p class="text-sm leading-relaxed text-slate-600">{{ $detail->deskripsi }}</p>
                    @else
                        <p class="text-sm text-slate-400">Keterangan destinasi ini belum ditulis.</p>
                    @endif

                    {{-- Dua jalan lanjut yang berbeda: rombongan yang butuh paket
                         lengkap, dan yang hanya perlu kendaraannya. Sebelumnya
                         hanya WhatsApp, sehingga yang sudah tahu ingin menyewa
                         unit tetap harus bertanya dulu. --}}
                    <div class="grid gap-2 mt-6 sm:grid-cols-2">
                        <a href="{{ $wa("Halo Orcha Journey, saya ingin ke {$detail->destination_name}. Ada paket atau armadanya?") }}"
                            target="_blank" rel="noopener noreferrer"
                            class="btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                            <x-bi-whatsapp class="w-4 h-4" />
                            Tanya Paket ke Sini
                        </a>
                        <a href="{{ route('sewa-kendaraan') }}" wire:navigate
                            class="btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                            <x-heroicon-o-truck class="w-4 h-4" />
                            Lihat Armada
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
