@props(['artikel'])

{{-- Kartu satu artikel. Dipakai daftar /blog dan bagian "Baca juga" di halaman
     detail, jadi bentuknya harus sama di keduanya — pembaca yang mengenali
     kartunya di satu tempat tidak perlu belajar lagi di tempat lain.

     Bentuknya ABSTRAK, bukan persegi panjang: sudut daun yang berselang-seling
     antar kartu, kaki foto berupa ombak, dan tombol bundar yang melintasi tepi
     foto. Alasan lengkapnya ada di komentar .kartu-artikel pada
     new-homepage.css — yang di sini cuma markahnya. --}}
<article {{ $attributes->merge(['class' => 'kartu-artikel group']) }}>

    <a href="{{ route('blog.detail', $artikel) }}" wire:navigate class="kartu-artikel-sampul">
        <x-blog.sampul :artikel="$artikel" class="kartu-artikel-gambar aspect-[16/10]" />

        @if ($artikel->kategori_label)
            {{-- Kategori di ATAS foto, bukan di atas judul.

                 Di dalam kartu ia bersaing dengan judul yang jadi isi
                 sesungguhnya; di atas foto ia mengisi bidang yang memang kosong
                 dan langsung terbaca sebagai penanda rubrik — bentuk yang sama
                 dengan lencana wilayah di kartu destinasi. --}}
            <span class="kartu-artikel-label px-3 py-1 text-xs font-bold rounded-full text-orcha-navy bg-orcha-sun shadow-[0_6px_14px_-6px_rgba(40,28,18,.6)]">
                {{ $artikel->kategori_label }}
            </span>
        @endif

        {{-- Ombak: menutup kaki foto dengan warna badan kartu. viewBox
             memakai satuannya sendiri dan preserveAspectRatio="none" membuatnya
             meregang mengikuti lebar kartu berapa pun. aria-hidden: ini
             bentuk, bukan keterangan. --}}
        <svg class="ombak-kartu" viewBox="0 0 400 44" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 44V20c54-17 104 11 168 5s108-29 164-16 50 9 68 4v31z" fill="currentColor" />
        </svg>

        {{-- Lama baca, BUKAN tombol panah. Panah di sini berada di dalam
             tautan foto, jadi tujuannya sama persis dengan "Baca selengkapnya"
             di kaki kartu — pembaca harus berhenti menebak bedanya, dan tidak
             ada bedanya. --}}
        <span class="kartu-artikel-baca">
            <x-heroicon-o-clock class="w-3.5 h-3.5" />
            {{ $artikel->lama_baca }} menit
        </span>
    </a>

    <div class="flex flex-col flex-1 px-5 pt-3 pb-6 sm:px-6">
        {{-- Tanggal saja: lama bacanya sudah berdiri sebagai lencana di tepi
             foto tepat di atas baris ini.

             Tanggalnya berdiri di sisi yang BERLAWANAN dengan lencana: di
             kartu genap lencananya pindah ke kiri, jadi tanggalnya rata kanan.
             Kalau cuma diberi ruang kiri tanpa dirata-kanankan, tanggalnya
             berhenti di tengah-tengah — menggantung jauh dari judul yang
             diterangkannya. --}}
        <p class="text-xs pr-28 group-even:pr-0 group-even:pl-28 group-even:text-right text-slate-500">
            <time datetime="{{ $artikel->terbit_pada?->toDateString() }}">{{ $artikel->tanggal_terbit }}</time>
        </p>

        <h3 class="mt-2 text-lg font-bold leading-snug font-heading text-orcha-navy">
            <a href="{{ route('blog.detail', $artikel) }}" wire:navigate class="transition hover:text-orcha-amber">
                {{ $artikel->judul }}
            </a>
        </h3>

        {{-- Dipotong di tiga baris DAN tingginya dipatok, sama seperti kartu
             destinasi: ringkasan yang panjangnya berbeda-beda menggeser tautan
             di bawahnya ke ketinggian yang berbeda pula, dan sebaris kartu jadi
             terbaca berantakan walaupun tiap kartunya rapi. --}}
        <p class="mt-2 text-sm leading-relaxed line-clamp-3 min-h-[3.9rem] text-slate-600">
            {{ $artikel->ringkasan_tampil }}
        </p>

        {{-- mt-auto menahan baris ini di dasar kartu, sehingga tautannya tetap
             sejajar antar kartu meski ringkasannya berbeda panjang.

             Warnanya EMAS, bukan --color-orcha-ocean. Peubah itu dulu biru laut;
             setelah palet digeser mengikuti logo nilainya jadi #4a5a52, abu
             kehijauan — jadi tautan ini terbaca abu-abu pudar, warna yang tidak
             pernah dipilih siapa pun. --}}
        <a href="{{ route('blog.detail', $artikel) }}" wire:navigate
            class="inline-flex items-center gap-1.5 pt-4 mt-auto text-sm font-bold transition text-orcha-amber hover:gap-2.5 hover:text-orcha-sunset">
            Baca selengkapnya
            <x-heroicon-o-arrow-right class="w-4 h-4" />
        </a>
    </div>
</article>
