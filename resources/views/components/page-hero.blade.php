@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'image' => 'images/pantai-senja.jpg',
    'posisi' => 'center',
    'gambarPenuh' => null,
])

{{-- Kepala halaman untuk semua halaman informasi (FAQ, ketentuan, testimoni).

     `posisi` mengatur bagian gambar mana yang tampil setelah dipotong jadi
     bidang lebar — penting untuk foto tegak, yang kalau dibiarkan default
     hanya menampilkan langitnya saja.

     `gambarPenuh` dipakai bila sumbernya sudah berupa URL/berkas siap pakai
     (mis. sampul paket), sehingga tidak perlu lewat asset(). --}}
<section class="relative overflow-hidden bg-orcha-navy">
    <img src="{{ $gambarPenuh ?: asset($image) }}" alt=""
        class="absolute inset-0 object-cover w-full h-full opacity-60"
        style="object-position: {{ $posisi }};">
    <div class="absolute inset-0 bg-gradient-to-br from-orcha-navy/85 via-orcha-navy/55 to-orcha-abyss/70"></div>

    <div class="relative container-orcha pt-32 pb-20 sm:pt-40 sm:pb-28">
        <nav aria-label="Breadcrumb" class="mb-5 text-xs font-semibold tracking-wide text-slate-300">
            <a href="{{ route('home') }}" class="transition hover:text-orcha-sky">Beranda</a>
            <span class="mx-2 text-slate-500">/</span>
            <span class="text-orcha-sun">{{ $title }}</span>
        </nav>

        @if ($eyebrow)
            <p class="aksen-orcha">{{ $eyebrow }}</p>
        @endif

        <h1 class="title-orcha title-orcha-light {{ $eyebrow ? '-mt-1' : '' }}">{{ $title }}</h1>

        @if ($subtitle)
            <p class="max-w-2xl mt-4 text-slate-300 pengantar-orcha">{{ $subtitle }}</p>
        @endif
    </div>

    <svg class="wave-divider" viewBox="0 0 1440 120" preserveAspectRatio="none" fill="currentColor" aria-hidden="true">
        <path d="M0,64 C180,110 360,20 540,36 C720,52 900,116 1080,104 C1200,96 1320,72 1440,44 L1440,120 L0,120 Z" />
    </svg>
</section>
