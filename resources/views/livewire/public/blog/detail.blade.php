<?php

use App\Models\Blog\Artikel;
use App\Support\Blog\PenyaringHtml;
use App\Support\Seo;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component
{
    public Artikel $artikel;

    /**
     * Draf tidak bisa dibuka lewat tautan langsung.
     *
     * Route-model binding mencocokkan slug saja, jadi tanpa pemeriksaan ini
     * tulisan yang belum matang — atau yang dijadwalkan terbit minggu depan —
     * sudah bisa dibaca siapa pun yang menebak alamatnya. Memakai aksesor yang
     * sama dengan scopeTayang() supaya "tayang" hanya punya satu arti.
     */
    public function mount(Artikel $artikel): void
    {
        abort_unless($artikel->sedang_tayang, 404);

        $this->artikel = $artikel;

        $artikel->tambahDilihat();
    }

    /**
     * Judul tab dan keterangan mesin pencari diambil dari artikelnya sendiri.
     *
     * App\Support\Seo menyimpan keterangan per NAMA RUTE, dan itu tepat untuk
     * halaman yang isinya tetap. Artikel tidak begitu — satu rute melayani
     * ratusan tulisan berbeda, jadi keterangannya harus datang dari barisnya.
     */
    public function rendering(View $view): void
    {
        $a = $this->artikel;

        // meta_title dipakai apa adanya bila diisi: batas panjang judul di
        // hasil pencarian sudah diperhitungkan admin saat menulisnya.
        $view->title(filled($a->meta_title) ? $a->meta_title : $a->judul.' — Blog Orcha Journey');

        $view->layoutData([
            'seoKeterangan' => Seo::keterangan(khusus: $a->meta_description_tampil),
            'seoGambar' => $a->sampul_tampil,
        ]);
    }

    public function with(): array
    {
        return ['lainnya' => $this->artikel->lainnya(3)];
    }
}; ?>

@php
    $a = $artikel;
    $url = route('blog.detail', $a);

    /*
     | Data terstruktur BlogPosting — dibaca Google untuk menampilkan tanggal
     | terbit dan penerbit di hasil pencarian.
     |
     | Penulisnya ORGANISASI, bukan kolom penulis. Nama orang yang menulis
     | artikel tidak perlu ikut terbit ke hasil pencarian, dan Google membaca
     | tipe Organization sebagai nama penerbit.
     */
    $dataTerstruktur = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $a->judul,
        'description' => $a->meta_description_tampil,
        'image' => $a->sampul_tampil,
        'datePublished' => optional($a->terbit_pada ?? $a->created_at)->toIso8601String(),
        'dateModified' => optional($a->updated_at)->toIso8601String(),
        'author' => ['@type' => 'Organization', 'name' => 'Orcha Journey'],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Orcha Journey',
            'logo' => ['@type' => 'ImageObject', 'url' => asset('orcha-logo-only.png')],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<div class="orc-artikel">
    <script type="application/ld+json">{!! $dataTerstruktur !!}</script>

    {{-- Hero yang SAMA dengan halaman /blog: gambar dan krop yang sama persis.

         Yang berganti hanya tulisannya — judul artikel, bukan "Blog Orcha
         Journey". Halaman artikel hanya boleh punya satu <h1>, dan yang paling
         pantas memegangnya adalah judul tulisannya sendiri; itu pula yang
         dibaca mesin pencari sebagai judul halaman.

         Sampul artikel TIDAK dipakai sebagai latar hero. Foto yang jadi latar
         harus diredupkan supaya judul di atasnya terbaca, sedangkan sampul
         artikel justru dipilih untuk dilihat — tempatnya di dalam artikel. --}}
    <x-page-hero :title="$a->judul" eyebrow="{{ $a->kategori_label ?? 'Blog' }}"
        image="images/HERO/blog.webp" posisi="center 88%" />

    <section class="pt-12 pb-16 bg-white sm:pt-16 sm:pb-20">
        <div class="container-orcha orc-art-lebar">

            {{-- Remah roti TIDAK diulang di sini: <x-page-hero> sudah membawa
                 miliknya sendiri, sama seperti seluruh halaman Orcha lain.
                 Jalan kembali ke daftar blog tetap ada di dua tempat — menu
                 atas dan tombol "Kembali ke Blog" di kaki artikel. --}}

            <div class="orc-art-kisi">

                {{-- ============ ARTIKEL ============ --}}
                <article>
                    {{-- Judul dan kategori TIDAK diulang di sini — keduanya sudah
                         dipegang hero di atas. Mengulangnya berarti dua <h1> di
                         satu halaman, dan mesin pencari harus menebak sendiri
                         mana judul yang sebenarnya. Yang tersisa keterangannya
                         saja, dan kategorinya tetap bisa diklik dari sini. --}}
                    <div class="orc-art-meta">
                        {{-- Penulis ditulis sebagai TIM, bukan diambil dari kolom
                             penulis, supaya nama orang tidak ikut terbit ke halaman
                             publik dan hasil pencarian. Kolomnya tetap ada untuk
                             keperluan admin sendiri. --}}
                        @if ($a->kategori_label)
                            <a href="{{ route('blog', ['kategori' => $a->kategori]) }}" class="orc-art-kat-tautan">
                                <x-bi-tag-fill class="w-3.5 h-3.5" /> {{ $a->kategori_label }}
                            </a>
                        @endif
                        <span><x-bi-person-circle class="w-4 h-4" /> Tim Orcha Journey</span>
                        <span><x-bi-calendar3 class="w-4 h-4" />
                            <time datetime="{{ $a->terbit_pada?->toDateString() }}">{{ $a->tanggal_terbit }}</time>
                        </span>
                        <span><x-bi-clock class="w-4 h-4" /> {{ $a->lama_baca }} menit baca</span>
                        <span><x-bi-eye class="w-4 h-4" /> {{ number_format($a->dilihat, 0, ',', '.') }}x dilihat</span>
                    </div>

                    {{-- Sampul di DALAM artikel, bukan sebagai latar hero.

                         Foto yang jadi latar harus diredupkan supaya judul di
                         atasnya terbaca — sedangkan sampul artikel justru dipilih
                         untuk dilihat, bukan untuk digelapkan. --}}
                    <div class="orc-art-sampul">
                        <img src="{{ $a->sampul_tampil }}" alt="{{ $a->judul }}" loading="lazy" decoding="async">
                    </div>

                    {{-- Ringkasan TIDAK ditampilkan di sini.

                         Isinya diambil dari kalimat yang memang sudah ada di
                         dalam artikel, jadi menampilkannya sebagai pembuka
                         membuat pembaca membaca kalimat yang sama dua kali —
                         sekali di atas, sekali lagi beberapa paragraf kemudian.

                         Ringkasannya tetap dipakai di tempat yang pembacanya
                         BELUM membuka artikel: kartu di daftar blog, keterangan
                         di hasil pencarian, dan pratinjau tautan WhatsApp. --}}

                    <div class="isi-artikel">
                        {{-- DISARING saat tampil, bukan dicetak mentah.

                             Isi artikel datang dari lemon lewat API — aplikasi lain
                             di server lain. Tanpa penyaring, siapa pun yang bisa
                             menulis artikel dapat menyisipkan skrip yang berjalan
                             di peramban setiap pengunjung orchajourney.com. --}}
                        {!! PenyaringHtml::bersihkan($a->isi) !!}
                    </div>

                    <div class="orc-art-kaki">
                        <div class="orc-bagi">
                            <span class="label">Bagikan:</span>
                            <a class="wa" target="_blank" rel="noopener" title="WhatsApp"
                                href="https://wa.me/?text={{ urlencode($a->judul . ' — ' . $url) }}">
                                <x-bi-whatsapp class="w-5 h-5" />
                            </a>
                            <a class="fb" target="_blank" rel="noopener" title="Facebook"
                                href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($url) }}">
                                <x-bi-facebook class="w-5 h-5" />
                            </a>
                            <a class="tw" target="_blank" rel="noopener" title="X"
                                href="https://twitter.com/intent/tweet?text={{ urlencode($a->judul) }}&url={{ urlencode($url) }}">
                                <x-bi-twitter-x class="w-5 h-5" />
                            </a>
                            {{-- Alpine, bukan skrip terpisah: layout guest tidak
                                 punya tumpukan skrip, dan Alpine sudah dimuat
                                 halaman ini untuk menu dan tab. --}}
                            <button type="button" class="salin" x-data="{ tersalin: false }"
                                :class="tersalin && 'tersalin'"
                                @click="navigator.clipboard.writeText('{{ $url }}').then(() => {
                                    tersalin = true;
                                    setTimeout(() => tersalin = false, 1500);
                                })"
                                :title="tersalin ? 'Tautan tersalin' : 'Salin tautan'">
                                <template x-if="!tersalin"><span><x-bi-link-45deg class="w-5 h-5" /></span></template>
                                <template x-if="tersalin"><span><x-bi-check-lg class="w-5 h-5" /></span></template>
                            </button>
                        </div>

                        <a href="{{ route('blog') }}" class="orc-kembali">
                            <x-bi-arrow-left class="w-4 h-4" />
                            Kembali ke Blog
                        </a>
                    </div>
                </article>

                {{-- ============ KOLOM SAMPING ============ --}}
                <aside class="orc-art-samping">
                    @if ($lainnya->isNotEmpty())
                        <div class="orc-kartu-samping">
                            <div class="judul">
                                <x-bi-collection class="w-5 h-5" />
                                Bacaan Lainnya
                            </div>

                            @foreach ($lainnya as $lain)
                                <a href="{{ route('blog.detail', $lain) }}" class="orc-samping-pos">
                                    <span class="gambar">
                                        <img src="{{ $lain->sampul_tampil }}" alt="{{ $lain->judul }}" loading="lazy"
                                            decoding="async">
                                    </span>
                                    <span class="teks">
                                        <span class="tanggal">{{ $lain->terbit_pada?->locale('id')->translatedFormat('j M Y') }}</span>
                                        <span class="nama">{{ Str::limit($lain->judul, 58) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    {{-- Ajakan yang nyambung dengan isi blog: yang membaca panduan
                         perjalanan sedang menimbang mau berangkat ke mana. --}}
                    <div class="orc-kartu-ajakan">
                        <h4>Siap berangkat?</h4>
                        <p>Lihat paket open trip, private trip, dan study tour Orcha Journey — harganya sudah termasuk
                            transportasi, pemandu, dan tiket masuk.</p>
                        <a href="{{ route('paket-wisata') }}" class="tombol">
                            <x-bi-map class="w-4 h-4" />
                            Lihat Paket Wisata
                        </a>
                        <a href="https://api.whatsapp.com/send?phone={{ config('orcha.whatsapp') }}&text={{ rawurlencode('Halo Orcha Journey, saya baru membaca artikel "' . $a->judul . '" dan ingin bertanya.') }}"
                            target="_blank" rel="noopener" class="wa">
                            <x-bi-whatsapp class="w-4 h-4" />
                            atau tanya lewat WhatsApp
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
