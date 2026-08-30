@props(['artikel'])

{{-- Kartu satu artikel. Dipakai daftar /blog dan bagian "Baca juga" di halaman
     detail, jadi bentuknya harus sama di keduanya — pembaca yang mengenali
     kartunya di satu tempat tidak perlu belajar lagi di tempat lain. --}}
<article
    {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden bg-white card-orcha group']) }}>

    <a href="{{ route('blog.detail', $artikel) }}" class="block overflow-hidden aspect-[16/10] bg-orcha-navy">
        <img src="{{ $artikel->sampul_tampil }}" alt="{{ $artikel->judul }}" loading="lazy" decoding="async"
            class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-105">
    </a>

    <div class="flex flex-col flex-1 p-6">
        {{-- Kategori dan tanggal di atas judul: dua hal yang dipakai pembaca
             untuk memutuskan lanjut atau lewat, sebelum ia membaca judulnya. --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            @if ($artikel->kategori_label)
                <a href="{{ route('blog', ['kategori' => $artikel->kategori]) }}"
                    class="font-bold tracking-wide uppercase transition text-orcha-ocean hover:text-orcha-wave">
                    {{ $artikel->kategori_label }}
                </a>
                <span class="text-orcha-mist" aria-hidden="true">•</span>
            @endif

            <time datetime="{{ $artikel->terbit_pada?->toDateString() }}">{{ $artikel->tanggal_terbit }}</time>
            <span class="text-orcha-mist" aria-hidden="true">•</span>
            <span>{{ $artikel->lama_baca }} menit baca</span>
        </div>

        <h3 class="mt-3 text-lg font-bold leading-snug text-orcha-navy">
            <a href="{{ route('blog.detail', $artikel) }}" class="transition hover:text-orcha-ocean">
                {{ $artikel->judul }}
            </a>
        </h3>

        <p class="mt-2 text-sm leading-relaxed text-slate-600 line-clamp-3">
            {{ $artikel->ringkasan_tampil }}
        </p>

        {{-- mt-auto menahan baris ini di dasar kartu, sehingga tombolnya tetap
             sejajar antar kartu meski ringkasannya berbeda panjang. --}}
        <a href="{{ route('blog.detail', $artikel) }}"
            class="inline-flex items-center gap-1.5 mt-auto pt-4 text-sm font-bold text-orcha-ocean transition hover:gap-2.5 hover:text-orcha-wave">
            Baca selengkapnya
            <x-heroicon-o-arrow-right class="w-4 h-4" />
        </a>
    </div>
</article>
