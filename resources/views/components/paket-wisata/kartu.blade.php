@props([
    'paket',
    // Satu kartu yang sengaja dijadikan tujuan mata. Dipakai beranda, yang
    // hanya menampilkan tiga paket; di halaman daftar lengkap tidak ada yang
    // disorot — kalau setiap layar punya "yang paling", tidak ada yang paling.
    'sorot' => false,
    // Lencana "Terlaris" bawaan yang mengikuti penanda admin. Dimatikan di
    // tempat yang sudah punya sorotan sendiri, supaya lencananya tidak muncul
    // di beberapa kartu sekaligus.
    'lencanaUnggulan' => true,
])

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa =
        'https://api.whatsapp.com/send?phone=' .
        config('orcha.whatsapp') .
        '&text=' .
        rawurlencode("Halo Orcha Journey, saya tertarik paket {$paket->name} ({$paket->category_label}). Boleh minta detailnya?");
    $unggulan = $sorot || ($lencanaUnggulan && (bool) $paket->is_best_choice);
    $destinasi = collect($paket->destination_list ?? [])->filter()->values();
@endphp

@php
    // Yang disorot dibedakan pada EMPAT hal sekaligus, bukan satu: garis tepi
    // tebal, cahaya hangat di bawahnya, terangkat sedikit, dan tidak ikut
    // diredupkan. Satu pembeda saja — garis tepi tipis seperti sebelumnya —
    // hilang begitu kartunya berdampingan dengan dua kartu lain yang sama
    // ramainya.
    // Kelas sorotnya didefinisikan di new-homepage.css, bukan dirangkai dari
    // utilitas ring — ring digambar lewat box-shadow dan berebut properti itu
    // dengan bayangan .card-orcha, sehingga garisnya hilang sama sekali.
    $kelasSorot = $sorot
        ? 'kartu-sorot lg:-translate-y-3 z-10'
        : ($unggulan ? 'ring-2 ring-orcha-sun' : '');

@endphp

<article
    {{ $attributes->merge(['class' => 'relative flex flex-col overflow-hidden bg-white card-orcha group transition-transform ' . $kelasSorot]) }}>

    {{-- ============ SAMPUL ============
         Kartu ini dulu tanpa gambar sama sekali — semuanya teks rata kiri yang
         menumpuk. Sampul memberi jangkar visual sebelum satu kata pun dibaca.

         Memakai foto unggahan admin; bila paket belum punya foto, otomatis
         jatuh ke ilustrasi bawaan sehingga kartunya tidak pernah kosong. --}}
    {{-- aspect-[16/10], TANPA batas tinggi.

         Dulu tingginya dipatok h-44/sm:h-52 untuk menghindari kartu yang
         membengkak: terukur, kartu 591px lebar dengan rasio 16/10 punya sampul
         368px dan kartunya jadi 789px. Itu memang jadi lebih tinggi, dan itu
         yang dipilih — karena memotong posternya lebih mahal daripada kartu
         yang memanjang.

         Batas max-h-64 sempat dipasang lalu DILEPAS: pada kartu selebar 620px
         batas 256px membuat rasio panelnya jadi 2,4 sementara posternya 1,60,
         sehingga tersisa pita buram ~105px di kiri dan kanan. Panel
         berkotak-hitam begitu terbaca seperti video yang salah ukuran. Dengan
         rasio dibiarkan utuh, poster 1,60 mengisi panelnya nyaris pas: yang
         tersisa cuma ~16px di tiap sisi, akibat pb-5 di bawah.

         Rasio 16/10 dipilih karena hampir persis rasio poster yang dipakai
         (1585x992 = 1,60), jadi di lebar kartu mana pun pita kosongnya tipis.

         Sampul TANPA tulisan apa pun di atasnya.

         Judul paket dulu ditaruh menumpang foto di atas selubung gelap, dan itu
         dua kerugian sekaligus: selubungnya menutupi sepertiga foto, dan judul
         yang panjang — "Open Trip Pantai Gunungkidul" sudah dua baris — menutup
         makin banyak. Judulnya sekarang turun ke badan kartu, jadi fotonya utuh
         dan judulnya tidak pernah menimpa apa pun. --}}
    <div class="relative aspect-[16/10] overflow-hidden bg-orcha-navy">
        {{-- LATAR: salinan sampul yang diburamkan, mengisi pita kosong.

             Dialah yang membesar saat disentuh tetikus, bukan posternya:
             memperbesar gambar ber-object-contain membuatnya melewati tepi
             dan terpotong lagi — justru yang mau dihindari di sini. --}}
        <img src="{{ $paket->sampul }}" alt="" aria-hidden="true" loading="lazy" decoding="async"
            class="absolute inset-0 object-cover w-full h-full scale-110 blur-xl opacity-70
                   transition-transform duration-700 group-hover:scale-125">

        {{-- object-CONTAIN, dan kotaknya berhenti 20px di atas dasar.

             Sampul paket sering berupa POSTER dengan tulisannya menyatu di
             gambar: judul, harga, titik jemput. Dua hal memotongnya sebelum
             ini — object-cover membuang sisi kiri-kanan, dan panel badan
             kartu yang menindih 20px menutupi baris paling bawah posternya.
             Terukur pada poster yang tayang: 1585x992 (rasio 1,60).

             bottom-5 menjauhkan poster dari panel itu; latar buram di
             belakangnya tetap penuh, jadi yang tertindih warna, bukan huruf.

             aspect-[16/10] menggantikan tinggi tetap h-44/sm:h-52: rasio itu
             hampir persis rasio posternya, sehingga pita kosongnya nyaris
             tidak ada sekaligus tidak ada yang terpotong. --}}
        <img src="{{ $paket->sampul }}" alt="{{ $paket->name }}" loading="lazy"
            class="absolute inset-0 object-contain w-full h-full pb-5">

        <span
            class="absolute top-4 left-4 px-3 py-1 text-[0.7rem] font-bold tracking-wider uppercase rounded-full bg-white/90 text-orcha-amber backdrop-blur">
            {{ $paket->category_label }}
        </span>

        @if ($unggulan)
            {{-- Bunyinya tetap "Terlaris", tidak dinaikkan jadi "paling banyak
                 dipesan".

                 Sempat saya tulis begitu supaya klaimnya terdengar berdasar —
                 dan justru itu kesalahannya: kalimat yang menyebut angka bisa
                 dibantah angka. Penanda ini dipasang admin di halaman
                 pengelolaan, tidak dihitung dari jumlah pendaftaran, dan saat
                 diperiksa paket yang tertandai memang ada yang pendaftarannya
                 nol. Yang disorot dibedakan ukurannya, bukan ditambahi klaim
                 yang tidak dijamin datanya. --}}
            <span
                class="absolute top-4 right-4 inline-flex items-center gap-1.5 rounded-full bg-orcha-sun text-orcha-navy font-black uppercase tracking-wider
                    {{ $sorot ? 'px-4 py-1.5 text-xs shadow-lg shadow-orcha-sun/40' : 'px-3 py-1 text-[0.7rem]' }}">
                @if ($sorot)
                    <x-heroicon-s-fire class="w-4 h-4" />
                @endif
                Terlaris
            </span>
        @endif
    </div>

    {{-- Badan kartu = PANEL BERBAHU yang menumpang tepi bawah foto, bentuk yang
         sama dengan panel pada kartu destinasi di beranda. Itu yang membuat kedua
         kartu terbaca sekeluarga, dan yang membuat kartu paket tidak lagi berupa
         dua kotak bertumpuk dengan garis lurus di antaranya.

         -mt-5 + z-10: panelnya naik 20px menindih foto. Tanpa z-10 ia tetap
         digambar di bawah foto karena foto datang belakangan dalam urutan DOM.

         pb-0: pita krem di kaki kartu membawa padding bawahnya sendiri. Tanpa
         ini, padding badan kartu menyisakan pita putih tipis DI BAWAH pita krem,
         dan kakinya terbaca menggantung — bukan menutup kartu. --}}
    <div class="relative z-10 flex flex-col flex-1 p-4 pb-0 -mt-5 sm:p-5 sm:pb-0 kartu-paket-isi">
        {{-- Pembungkus ber-pb-4: memberi jarak tetap antara isi terakhir (cip
             destinasi atau cip promo) dan GARIS pita kaki. Tanpa ini cipnya
             menempel ke garis, dan jaraknya ikut berubah-ubah mengikuti isi mana
             yang kebetulan paling bawah. --}}
        {{-- Rata kiri, seperti panel kartu destinasi. Bentuk panelnya yang
             membedakan kartu ini dari kartu lain di halaman — bukan perataan
             tulisannya. Rata tengah sempat dicoba, dan di dalam panel berbahu ia
             bertabrakan: bahunya di kiri, tulisannya di tengah, jadi tidak ada
             satu pun garis tegak yang dipakai bersama. --}}
        <div class="pb-4">
            <h3 class="text-lg font-bold leading-snug sm:text-xl font-heading text-orcha-navy">
                {{ $paket->name }}
            </h3>

            @if ($paket->jadwal_label)
                <p class="flex flex-wrap items-center gap-1.5 mt-1 text-xs font-semibold text-orcha-amber">
                    <x-heroicon-s-calendar-days class="w-4 h-4 shrink-0" />
                    {{ $paket->jadwal_label }}

                    {{-- Tanggal di atas cuma yang TERDEKAT. Satu paket kerap dijual
                         untuk beberapa rombongan, dan kartu yang menyebut satu
                         tanggal saja membuat pengunjung yang tidak bisa di tanggal
                         itu menutup halamannya — padahal ada tanggal lain yang
                         masih terbuka. --}}
                    @if (0 < $paket->jumlah_tanggal_lain)
                        <span class="px-2 py-0.5 text-[0.7rem] font-bold rounded-full bg-orcha-sun/15 text-orcha-amber">
                            +{{ $paket->jumlah_tanggal_lain }} tanggal lain
                        </span>
                    @endif
                </p>
            @endif


        {{-- ============ KETERANGAN SINGKAT ============
             SATU baris berisi tiga keterangan, bukan kotak dua sel bersekat yang
             masing-masing menumpuk ikon + nilai + label.

             Terukur, bentuk lama memakan 108px (kotak 88 + baris jemput 20) untuk
             tiga potong keterangan yang semuanya pendek — dan label "DURASI" /
             "PESERTA" di bawah nilainya tidak menambah apa pun: "1 hari" dan
             "Min. 6 orang" sudah menyebut dirinya sendiri. --}}
        <div class="flex flex-wrap items-center mt-3 text-[0.8rem] gap-x-3 gap-y-1.5 text-slate-600">
            <span class="inline-flex items-center gap-1.5">
                <x-heroicon-o-clock class="w-4 h-4 shrink-0 text-orcha-sky" />
                <span class="font-semibold text-orcha-navy">{{ $paket->duration ?: '—' }}</span>
            </span>

            <span class="w-1 h-1 rounded-full bg-orcha-mist"></span>

            <span class="inline-flex items-center gap-1.5">
                <x-heroicon-o-user-group class="w-4 h-4 shrink-0 text-orcha-sky" />
                <span class="font-semibold text-orcha-navy">{{ $paket->minimal_peserta > 1 ? 'Min. ' . $paket->minimal_peserta . ' orang' : 'Bebas' }}</span>
            </span>

            @if ($paket->titik_jemput)
                <span class="w-1 h-1 rounded-full bg-orcha-mist"></span>
                <span class="inline-flex items-center gap-1.5">
                    <x-heroicon-s-map-pin class="w-4 h-4 shrink-0 text-orcha-sun" />
                    <span class="font-semibold text-orcha-navy">{{ $paket->titik_jemput }}</span>
                </span>
            @endif
        </div>

        {{-- ============ DESTINASI ============
             Berupa cip yang mengalir mengikuti lebar kartu, bukan daftar
             menurun — mengisi ruang kosong di kanan dan lebih cepat dipindai. --}}
        @if ($destinasi->isNotEmpty())
            {{-- Label "DESTINASI TERMASUK" dibuang: nama tempat di dalam cip sudah
                 terbaca sebagai destinasi, dan satu baris label memakan tinggi yang
                 sama dengan satu baris cip. Tiga cip, bukan empat — cip keempat
                 hampir selalu membuat barisnya pecah dua di kisi tiga lajur. --}}
            <div class="flex flex-wrap gap-1.5 mt-3">
                @foreach ($destinasi->take(3) as $satu)
                    <span
                        class="px-2.5 py-1 text-xs font-medium rounded-full bg-orcha-foam border border-orcha-mist/60 text-orcha-navy">{{ $satu }}</span>
                @endforeach

                @if ($destinasi->count() > 3)
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-orcha-sky/15 text-orcha-ocean">
                        +{{ $destinasi->count() - 3 }} lagi
                    </span>
                @endif
            </div>
        @endif

        @if ($paket->catatan_promo)
            <p class="inline-flex gap-1.5 items-center px-3 py-1 mt-3 text-xs font-bold rounded-full bg-orcha-sun/20 text-orcha-amber">
                <x-heroicon-s-sparkles class="w-3.5 h-3.5" />
                {{ $paket->catatan_promo }}
            </p>
        @endif

        </div>

        {{-- ============ KAKI: HARGA + TINDAKAN ============
             Harga dan tombol duduk di atas PITA KREM yang melebar sampai tepi
             kartu, bukan di atas putih yang sama dengan bagian di atasnya.

             Sebelumnya seluruh badan kartu putih polos dari atas sampai bawah,
             dan keterangan, cip, harga, serta tombol semuanya rata kiri dengan
             warna yang mirip — itu yang membuatnya terbaca monoton. Dengan pita,
             kartunya punya dua lapis yang jelas: "apa isinya" di atas, "berapa
             dan apa yang bisa saya lakukan" di bawah.

             -mx menembus padding badan kartu; sudut bawahnya ikut membulat
             sendiri karena article-nya ber-overflow-hidden. --}}
        <div class="-mx-4 sm:-mx-5 mt-auto px-4 sm:px-5 pt-3 pb-4 sm:pb-5 bg-orcha-foam/55 border-t border-orcha-mist/50">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] tracking-wide uppercase text-slate-400">Mulai dari</p>
                    {{-- Angkanya NAVY, bukan jingga: tombol di bawahnya sudah jingga
                         pekat, dan dua jingga besar bersebelahan saling berebut. --}}
                    <p class="text-xl font-black leading-tight font-heading text-orcha-navy">
                        {{ $rupiah($paket->price) }}
                        <span class="text-xs font-medium text-slate-500">/ orang</span>
                    </p>
                </div>

                @if ($paket->diskon_tampil > 0)
                    {{-- Jingga, bukan merah: merah di tengah palet krem-jingga ini
                         terbaca sebagai peringatan, padahal isinya kabar baik. --}}
                    <div class="text-right shrink-0">
                        <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full text-orcha-amber bg-orcha-sun/20">{{ 'Hemat ' . $paket->diskon_tampil . '%' }}</span>
                        <p class="mt-1 text-xs line-through text-slate-400">{{ $rupiah($paket->original_price) }}</p>
                    </div>
                @endif
            </div>

        {{-- Dua tombol BERDAMPINGAN, bukan bertumpuk. Bertumpuk keduanya memakan
             104px — seperlima tinggi kartu untuk dua tindakan yang sama-sama
             pendek. Tulisannya ikut dipendekkan supaya muat separuh lebar:
             "Lihat Detail & Itinerary" -> "Lihat Detail". --}}
            <div class="grid grid-cols-2 gap-2 mt-3">
            <a href="{{ route('paket-detail', $paket->uuid) }}"
                class="btn-orcha !py-2.5 !text-sm {{ $unggulan ? 'btn-orcha-sun' : 'btn-orcha-primary' }}">
                Lihat Detail
                <x-heroicon-o-arrow-right class="w-4 h-4" />
            </a>

            @if ($paket->category === 'open_trip')
                <a href="{{ route('pendaftaran-open-trip', ['paket' => $paket->uuid]) }}"
                    class="btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                    Daftar
                </a>
            @else
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                    class="btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                    <x-bi-whatsapp class="w-4 h-4" />
                    WhatsApp
                </a>
            @endif
            </div>
        </div>
    </div>
</article>
