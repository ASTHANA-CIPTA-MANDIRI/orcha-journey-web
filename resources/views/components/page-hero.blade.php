@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'image' => 'images/pantai-senja.webp',
    'posisi' => 'center',
    'gambarPenuh' => null,
    'kelas' => '',
])

{{-- Kepala halaman untuk semua halaman informasi (FAQ, ketentuan, testimoni).

     `posisi` mengatur bagian gambar mana yang tampil setelah dipotong jadi
     bidang lebar — penting untuk foto tegak, yang kalau dibiarkan default
     hanya menampilkan langitnya saja.

     `gambarPenuh` dipakai bila sumbernya sudah berupa URL/berkas siap pakai
     (mis. sampul paket), sehingga tidak perlu lewat asset(). --}}
{{-- `kelas` dipakai halaman yang fotonya harus tampil UTUH: dengan
     memberi seksinya nisbah yang sama dengan fotonya, object-cover tidak
     memotong apa pun. Kelasnya ditulis lengkap di halaman pemanggil, bukan
     dirakit di sini — Tailwind membaca berkas sumber apa adanya, dan kelas
     yang dirangkai dari variabel tidak pernah ikut terbangun. --}}
{{-- BENTUKNYA sama persis dengan hero beranda, memakai kelas yang sama
     (.hero-kabin dan kawan-kawannya) — bukan tiruan yang kebetulan mirip.
     Kalau bentuk itu diubah nanti, beranda dan 18 halaman ini ikut berubah
     bersama.

     Yang berubah dari versi lama: dulu foto selebar layar diredam gelap
     dengan tulisan putih di atasnya; sekarang latarnya krem, fotonya
     menempati separuh kanan dengan tepi kiri dilebur topeng, dan tulisannya
     gelap. --}}
<section class="hero-kabin {{ $kelas }}">
    <div class="hero-kabin-media">
        {{-- JANGAN diberi loading="lazy".

             Foto ini memenuhi layar pertama di setiap halaman yang memakainya,
             jadi ia hampir selalu jadi elemen terbesar yang diukur LCP.
             Menandainya lazy membuat peramban menundanya sampai tata letak
             selesai dihitung — halaman justru terasa LEBIH lambat. --}}
        <img src="{{ $gambarPenuh ?: asset($image) }}" alt=""
            fetchpriority="high" decoding="async"
            style="object-position: {{ $posisi }};">
    </div>

        {{-- Kartu melayang di atas foto, memakai .hero-kabin-kartu — kartu
             yang SAMA dengan hero beranda, lengkap dengan sisi tebal dan
             miringnya. Di beranda isinya ajakan menjelajah; di halaman lain
             isinya jalan pintas bertanya, karena orang yang membuka halaman
             informasi biasanya sedang menimbang dan butuh bertanya.

             Kelas itu sudah menyembunyikan dirinya sendiri di layar sempit —
             di sana fotonya kecil dan kartunya menutupi isinya. --}}
        <a href="https://api.whatsapp.com/send?phone={{ config('orcha.whatsapp') }}&text={{ rawurlencode('Halo Orcha Journey, saya ingin bertanya.') }}"
            target="_blank" rel="noopener noreferrer"
            class="hero-kabin-kartu kartu-tanya">
            <span class="gelembung-ikon">
                <x-bi-whatsapp class="w-5 h-5" />
            </span>
            <span class="block mt-4 text-sm text-[#9a9289]">Masih menimbang?</span>
            <span class="block mt-0.5 text-[1.15rem] font-extrabold leading-[1.15] font-heading text-orcha-navy">
                Tanya Dulu Saja
            </span>
            <span class="flex justify-end mt-3 text-orcha-navy">
                <x-heroicon-o-arrow-right class="w-5 h-5 panah-blog" />
            </span>
        </a>

    <div class="hero-kabin-tirai"></div>

    <div class="relative container-orcha hero-kabin-isi">
        <div class="max-w-xl">
            <nav aria-label="Breadcrumb" class="mb-4 text-xs font-semibold tracking-wide text-slate-500">
                <a href="{{ route('home') }}" class="transition hover:text-orcha-amber">Beranda</a>
                <span class="mx-2 text-slate-400">/</span>
                <span class="text-orcha-amber">{{ $title }}</span>
            </nav>

            @if ($eyebrow)
                <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> {{ $eyebrow }}</p>
            @endif

            {{-- Ukuran, jarak huruf, dan pengaturan sumbu hurufnya disalin dari
                 judul beranda; satu-satunya beda ukurannya sedikit lebih kecil,
                 karena judul halaman ini pendek-pendek sementara judul beranda
                 dua baris penuh. --}}
            {{-- Kata TERAKHIR judul diberi warna emas, meniru "layak dikenang."
                 di beranda. Judul satu warna penuh terbaca rata; satu kata
                 berwarna memberi mata titik berhenti.

                 Dipecah di PHP, bukan ditulis manual di tiap halaman: judulnya
                 datang sebagai satu teks dari 18 pemanggil, dan menyuruh tiap
                 halaman menandai sendiri kata terakhirnya hanya akan membuat
                 sebagian lupa. --}}
            @php
                $kataJudul = preg_split('/\s+/', trim($title));
                $kataAkhir = array_pop($kataJudul);
                $awalJudul = implode(' ', $kataJudul);
            @endphp
            <h1 class="mt-3 font-heading font-extrabold text-orcha-navy leading-[1.04] tracking-[-0.018em]"
                style="font-size: clamp(1.75rem, 4.2vw, 3rem); font-variation-settings: 'SOFT' 40, 'WONK' 1;">
                {{ $awalJudul }}@if ($awalJudul) @endif<span class="sorot-kata">{{ $kataAkhir }}</span>
            </h1>

            @if ($subtitle)
                <p class="max-w-lg mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">{{ $subtitle }}</p>
            @endif
        </div>

        {{-- Barisan jaminan yang SAMA PERSIS dengan hero beranda — isi, ikon,
             dan kelasnya. Tanpa ini kepala halaman cuma berisi judul dan satu
             kalimat, sementara separuh kanannya foto: bidang di bawah tulisan
             menganga dan halaman terbaca kosong.

             Berdiri DI LUAR kolom tulisan supaya ketiganya muat satu baris;
             di dalam kolom selebar 36rem butir ketiga turun ke baris kedua. --}}
        <div class="flex flex-wrap items-center max-w-3xl gap-x-8 gap-y-3 mt-9">
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
