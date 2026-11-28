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

    {{-- rak-panel: panel pertama NAIK MENINDIH kepala halaman, sama seperti
         beranda, blog, FAQ, dan testimoni.

         Satu berkas ini dipakai EMPAT halaman — syarat & ketentuan, ketentuan
         pembayaran, kebijakan pengembalian, dan kebijakan privasi — jadi
         keempatnya ikut berpindah sekaligus. Sebelumnya .section-orcha di atas
         latar putih meninggalkan pita putih kosong setinggi hampir 150px
         antara foto kepala halaman dan isi pertama. --}}
    <div class="container-orcha rak-panel pb-6">
        <div class="grid gap-4 sm:gap-5 lg:grid-cols-12">

            <aside class="lg:col-span-3">
                <nav class="toc-orcha panel-orcha reveal !p-5" aria-label="Daftar isi">
                    <p class="mb-3 label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Daftar Isi</p>
                    @foreach ($sections as $bagian)
                        <a href="#{{ $bagian['slug'] }}">{{ $bagian['judul'] }}</a>
                    @endforeach

                    @if ($diperbarui)
                        <p class="px-3 mt-5 text-xs label-ringkas">Terakhir diperbarui: {{ $diperbarui }}</p>
                    @endif
                </nav>
            </aside>

            <div class="space-y-4 sm:space-y-5 lg:col-span-9">
                <article class="panel-orcha reveal prose-orcha">
                    {{ $slot }}

                    @foreach ($sections as $bagian)
                        <section id="{{ $bagian['slug'] }}" class="scroll-mt-28">
                            <h2>{{ $bagian['judul'] }}</h2>
                            {!! $bagian['isi'] !!}
                        </section>
                    @endforeach
                </article>

                {{-- Kotak bantuan dikeluarkan dari <article>: ia bukan bagian dari
                     ketentuannya, melainkan ajakan sesudahnya — dan di dalam
                     .prose-orcha ia ikut mewarisi jarak serta gaya tulisan isi. --}}
                <div class="panel-orcha panel-orcha-krem reveal">
                    <div class="flex items-start gap-3">
                        <span class="grid w-11 h-11 shrink-0 place-items-center rounded-xl ubin-toska">
                            <x-heroicon-o-chat-bubble-left-right class="w-5 h-5" />
                        </span>

                        <div>
                            <p class="judul-panel-kecil">Butuh penjelasan lebih lanjut?</p>
                            <p class="mt-1.5 text-sm text-slate-600">
                                Hubungi tim Orcha Journey di
                                <a href="https://api.whatsapp.com/send?phone={{ config('orcha.whatsapp') }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="font-semibold text-orcha-toska">+{{ config('orcha.whatsapp') }}</a>
                                atau <a href="mailto:{{ config('orcha.email') }}"
                                    class="font-semibold text-orcha-toska">{{ config('orcha.email') }}</a>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
