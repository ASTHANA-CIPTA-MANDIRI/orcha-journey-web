@props([
    'title',
    'subtitle' => null,
    'eyebrow' => 'Informasi',
    'image' => 'images/pantai-senja.webp',
    'posisi' => 'center',
    'diperbarui' => null,
    'sections' => [],
])

{{-- JANGAN menaruh komentar // atau /* */ di dalam @props([...]) di atas.

     @props menyisipkan teks mentah di antara kurungnya apa adanya ke dalam PHP
     hasil kompilasi, dan pengurai argumen Blade tidak mengerti komentar. Satu
     baris // di sana membuat kompilasi berputar tanpa henti: halamannya tidak
     menjawab sama sekali sampai batas waktu PHP habis, lalu membalas 500 —
     tanpa satu pun pesan yang menyebut komentar sebagai sebabnya.
     Sudah pernah terjadi di berkas ini. Tulis keterangan prop di sini saja.

     `posisi` diteruskan ke <x-page-hero>. Tanpa itu keempat halaman ketentuan
     tidak bisa mengatur bagian foto mana yang tampil setelah dipotong jadi pita
     lebar — dan pita itu memang membuang atas dan bawahnya. --}}

{{-- Kerangka bersama untuk halaman ketentuan/kebijakan: kepala halaman,
     daftar isi yang menempel, lalu isi teks. Isi ditulis sebagai HTML di
     berkas halaman masing-masing. --}}
<div>
    <x-page-hero :title="$title" :subtitle="$subtitle" :eyebrow="$eyebrow" :image="$image" :posisi="$posisi" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">

                <aside class="lg:col-span-3">
                    <nav class="toc-orcha" aria-label="Daftar isi">
                        <p class="px-3 mb-2 text-xs font-bold tracking-widest uppercase text-slate-400">Daftar Isi</p>
                        @foreach ($sections as $bagian)
                            <a href="#{{ $bagian['slug'] }}">{{ $bagian['judul'] }}</a>
                        @endforeach

                        @if ($diperbarui)
                            <p class="px-3 mt-5 text-xs text-slate-400">Terakhir diperbarui: {{ $diperbarui }}</p>
                        @endif
                    </nav>
                </aside>

                <article class="lg:col-span-9 prose-orcha">
                    {{ $slot }}

                    @foreach ($sections as $bagian)
                        <section id="{{ $bagian['slug'] }}">
                            <h2>{{ $bagian['judul'] }}</h2>
                            {!! $bagian['isi'] !!}
                        </section>
                    @endforeach

                    <div class="p-6 mt-12 rounded-3xl bg-orcha-foam/70 sm:p-8">
                        <p class="text-base font-bold font-heading text-orcha-navy">Butuh penjelasan lebih lanjut?</p>
                        <p class="mt-1 text-sm text-slate-600">
                            Hubungi tim Orcha Journey di
                            <a href="https://api.whatsapp.com/send?phone={{ config('orcha.whatsapp') }}"
                                target="_blank" rel="noopener noreferrer">+{{ config('orcha.whatsapp') }}</a>
                            atau <a href="mailto:{{ config('orcha.email') }}">{{ config('orcha.email') }}</a>.
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</div>
