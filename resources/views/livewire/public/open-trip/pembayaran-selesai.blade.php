<?php

use App\Models\OpenTrip\PembayaranDoku;
use App\Services\DokuCheckout;
use App\Support\TerimaNotifikasiDoku;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Status Pembayaran — Orcha Journey')] class extends Component {
    public string $invoice = '';

    /** Pelanggan menutup halaman DOKU tanpa membayar. */
    public bool $batal = false;

    /**
     * Nomor tagihannya boleh datang sebagai parameter, bukan hanya dari query.
     *
     * Rutenya sendiri tidak punya parameter, jadi di produksi yang terpakai
     * selalu query string — DOKU-lah yang menyusun alamat kembalinya. Yang
     * dibuka di sini kemampuan memanggil halaman ini secara langsung, tanpa
     * harus memalsukan permintaan HTTP lebih dulu.
     */
    public function mount(?string $invoice = null): void
    {
        $this->invoice = strtoupper(trim($invoice ?: (string) request()->query('invoice', '')));
        $this->batal = (bool) request()->query('batal');
    }

    /**
     * Halaman ini TIDAK menentukan apa pun; ia hanya melaporkan.
     *
     * Yang mencatat pembayaran adalah notifikasi bertanda tangan dari DOKU
     * (lihat DokuNotifikasiController), dan itu terjadi di jalur yang sama
     * sekali terpisah dari peramban pelanggan. Alamat ini bisa dibuka siapa
     * pun tanpa pernah membayar sepeser pun, jadi apa pun yang diputuskan di
     * sini akan berarti melunasi tagihan dengan cara mengetik alamat.
     *
     * Sebaliknya juga benar dan lebih sering terjadi: orang membayar lalu
     * menutup tabnya sebelum sempat kembali. Pembayarannya tetap masuk.
     */
    private function pembayaran(): ?PembayaranDoku
    {
        if (blank($this->invoice)) {
            return null;
        }

        return PembayaranDoku::where('invoice', $this->invoice)->first();
    }

    /**
     * Bertanya langsung ke DOKU, bukan menunggu dikabari.
     *
     * Notifikasi dikirim SEKALI ke alamat yang kita daftarkan. Kalau saat itu
     * server sedang di-deploy, jaringannya putus, atau alamatnya sudah basi,
     * kabarnya hilang — dan yang terlihat kemudian adalah pelanggan yang sudah
     * membayar sementara layar kami tetap menyatakan menunggu, tanpa satu pun
     * cara memperbaikinya selain mengetik langsung ke basis data.
     *
     * Dipanggil dari wire:poll, jadi berjalan sendiri selama halaman terbuka.
     * Halaman ini tetap TIDAK memutuskan apa pun: jawaban DOKU-lah yang
     * memutuskan, dan ia diproses lewat jalur yang sama persis dengan
     * notifikasi — supaya pembayaran yang masuk lewat cadangan ini
     * menghasilkan akibat yang identik dengan yang masuk lewat jalur utama.
     */
    public function periksa(): void
    {
        $bayar = $this->pembayaran();

        /*
         | Yang sudah selesai tidak perlu ditanyakan lagi.
         |
         | Tetapi yang baru lewat batas waktu MASIH ditanyakan, selama tenggang
         | di config. Jam kedaluwarsa itu milik kami, bukan milik DOKU: yang
         | membayar pada menit terakhir menyelesaikan transaksinya beberapa
         | detik sesudah jam kami menyatakan mati, dan berhenti bertanya tepat
         | pada detik itu berarti uangnya sudah berpindah sementara layar kami
         | menyatakan batas waktu habis.
         */
        if (! $bayar || ! $bayar->masih_pantas_diperiksa) {
            return;
        }

        $jawaban = app(DokuCheckout::class)->cekStatus($bayar->invoice);

        if ($jawaban === null) {
            return;
        }

        TerimaNotifikasiDoku::proses($jawaban);
    }

    public function with(): array
    {
        $bayar = $this->pembayaran();

        return [
            'bayar' => $bayar,

            /*
             | Selama masih "menunggu", halaman menyegarkan dirinya sendiri.
             |
             | Notifikasi DOKU biasanya tiba beberapa detik sesudah pembayaran,
             | tetapi pelanggan sering kembali lebih dulu — terutama pada QRIS,
             | yang selesai dalam sekejap. Tanpa penyegaran, yang ia lihat
             | adalah "belum tercatat" untuk pembayaran yang sebenarnya sedang
             | dalam perjalanan, lalu ia menghubungi WhatsApp untuk sesuatu
             | yang beres sendiri dua detik kemudian.
             */
            'menunggu' => $bayar?->status === 'menunggu' && ! $bayar->sudah_kedaluwarsa,

            /*
             | DUA HAL YANG BERBEDA, dan sempat saya satukan keliru.
             |
             | 'menunggu' menentukan APA YANG TERTULIS di layar: begitu jamnya
             | lewat, pelanggan harus dibilangi batas waktunya habis, bukan
             | disuruh menunggu tagihan yang tidak lagi menerima apa-apa.
             |
             | 'masihDiperiksa' menentukan APAKAH KAMI MASIH BERTANYA ke DOKU.
             | Itu berlanjut beberapa menit sesudahnya, karena yang membayar di
             | menit terakhir menyelesaikan transaksinya beberapa detik sesudah
             | jam kami menyatakan mati. Layarnya boleh menyerah; jalur
             | pemeriksaannya belum.
             |
             | Akibatnya layar kedaluwarsa pun tetap berdenyut diam-diam, dan
             | berubah sendiri jadi "Pembayaran berhasil" bila uangnya ternyata
             | masuk.
             */
            'masihDiperiksa' => (bool) $bayar?->masih_pantas_diperiksa,

            /*
             | Kedaluwarsa dihitung dari JAM, bukan dari status.
             |
             | Statusnya baru berubah kalau DOKU mengirimkan notifikasi
             | kedaluwarsa, dan notifikasi itu punya sakelarnya sendiri di
             | dashboard yang bisa mati tanpa ada yang menyadarinya. Tanpa
             | hitungan ini, halaman akan menyatakan "menunggu pembayaran Anda"
             | untuk tagihan yang sudah mati — berikut tombol menuju halaman
             | DOKU yang tidak lagi menerima apa-apa.
             */
            'kedaluwarsa' => $bayar?->status === 'kedaluwarsa' || (bool) $bayar?->sudah_kedaluwarsa,
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp');
@endphp

<div>
    <section class="bg-white section-orcha">
        <div class="max-w-2xl px-4 mx-auto">

            {{-- wire:poll hanya dipasang saat memang ada yang ditunggu. Halaman
                 yang terus menyegarkan diri sesudah pembayarannya pasti cuma
                 membebani server dan menghabiskan kuota data pelanggan. --}}
            {{-- 10 detik, bukan 5.

                 Tiap denyut kini bukan sekadar menggambar ulang halaman: ia
                 bertanya ke DOKU. DOKU sendiri menyarankan menunggu sekitar
                 semenit sesudah pembayaran sebelum status akhirnya bisa
                 dipercaya, jadi bertanya dua kali per sepuluh detik sudah
                 lebih rapat daripada yang berguna. --}}
            <div class="p-8 text-center card-orcha sm:p-10" @if ($masihDiperiksa) wire:poll.10s="periksa" @endif>

                @if (! $bayar)
                    <x-heroicon-s-question-mark-circle class="w-16 h-16 mx-auto text-slate-400" />
                    <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                        Pembayaran tidak ditemukan
                    </h1>
                    <p class="max-w-md mx-auto mt-2 text-slate-600">
                        Kami tidak mengenali nomor tagihan pada alamat ini. Bila Anda baru saja
                        membayar, hubungi kami lewat WhatsApp dengan menyebutkan kode pesanan Anda —
                        uang yang sudah berpindah tidak hilang.
                    </p>

                @elseif ($bayar->status === 'berhasil')
                    {{-- Centang yang menggambar dirinya sendiri.

                         Gambar diam menyampaikan hasilnya; gerakan menyampaikan
                         bahwa hasil itu BARUSAN terjadi. Di halaman yang dibuka
                         orang beberapa detik sesudah uangnya berpindah, itu
                         persis perbedaan yang ingin ia rasakan. --}}
                    <x-centang-sukses class="mx-auto" />
                    <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                        Pembayaran berhasil
                    </h1>
                    <p class="max-w-md mx-auto mt-2 text-slate-600">
                        {{ $bayar->nominal_formatted }} untuk pesanan
                        <strong class="text-orcha-navy">{{ $bayar->kode }}</strong> sudah kami terima.
                        Tanda terimanya dikirim ke email Anda.
                    </p>

                    @unless (str_starts_with($bayar->kode, 'SK-'))
                        <p class="max-w-md mx-auto mt-4 text-sm text-slate-600">
                            Satu langkah lagi: isi riwayat kesehatan peserta supaya tim kami siap di
                            lapangan.
                        </p>
                        <a href="{{ route('riwayat-kesehatan', ['kode' => $bayar->kode]) }}"
                            class="inline-flex mt-4 btn-orcha btn-orcha-primary">
                            Isi Riwayat Kesehatan
                        </a>
                    @endunless

                @elseif ($menunggu)
                    {{-- Dua kalimat berbeda untuk satu status yang sama.

                         Yang menutup halaman DOKU tanpa membayar dan yang baru saja
                         membayar sama-sama mendarat di sini berstatus "menunggu",
                         tetapi keduanya butuh diberi tahu hal yang berlawanan. --}}
                    @if ($batal)
                        <x-heroicon-s-x-circle class="w-16 h-16 mx-auto text-slate-400" />
                        <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                            Pembayaran belum selesai
                        </h1>
                        <p class="max-w-md mx-auto mt-2 text-slate-600">
                            Anda menutup halaman pembayaran sebelum selesai. Tidak ada uang yang
                            terpotong, dan pesanan <strong>{{ $bayar->kode }}</strong> masih tersimpan.
                        </p>
                        <a href="{{ route('konfirmasi-pembayaran', ['kode' => $bayar->kode]) }}"
                            class="inline-flex mt-5 btn-orcha btn-orcha-primary">
                            Coba Bayar Lagi
                        </a>
                    @else
                        {{-- DUA FASE, dan pemisahnya bukan gaya melainkan uang.

                             Pelanggan sampai di halaman ini karena DOKU yang
                             mengembalikannya — artinya ia baru saja menyelesaikan
                             sesuatu di sana. Notifikasi yang mencatat pembayarannya
                             berjalan di jalur terpisah dan biasanya tiba beberapa
                             detik kemudian, tetapi pelanggan hampir selalu lebih
                             cepat.

                             Yang dulu ia lihat pada detik-detik itu: "belum tercatat
                             lunas", berikut tombol Lanjutkan Pembayaran. Itu bukan
                             sekadar membingungkan — itu mengundang orang yang uangnya
                             sudah berpindah untuk membayar untuk kedua kalinya, dan
                             uang kedua itu nyata serta harus dikembalikan manual.

                             Jadi selama jendela pemastian, tombol bayar TIDAK ADA.
                             Sembilan puluh detik: DOKU sendiri menyarankan menunggu
                             sekitar semenit sebelum statusnya bisa dipercaya, dan
                             denyut sepuluh detik memberi sembilan kali pemeriksaan di
                             dalamnya. --}}
                        <div x-data="{
                                total: 90,
                                sisa: 90,
                                mulai: null,
                                init() {
                                    /*
                                     | Waktu mulainya dititipkan ke sessionStorage,
                                     | bukan disimpan di sini. Tiap denyut wire:poll
                                     | menggambar ulang halaman, dan hitungan yang
                                     | hidup di dalam komponen akan kembali ke 90
                                     | setiap sepuluh detik — hitung mundur yang tidak
                                     | pernah sampai nol.
                                     */
                                    const kunci = 'orcha-tunggu-{{ $bayar->invoice }}';
                                    try {
                                        this.mulai = Number(sessionStorage.getItem(kunci)) || null;
                                        if (! this.mulai) {
                                            this.mulai = Date.now();
                                            sessionStorage.setItem(kunci, this.mulai);
                                        }
                                    } catch (e) {
                                        // Peramban yang menolak menyimpan tetap boleh
                                        // memakai halaman ini; hitungannya saja yang
                                        // mulai ulang bila digambar ulang.
                                        this.mulai = this.mulai || Date.now();
                                    }

                                    const hitung = () => {
                                        const lewat = Math.floor((Date.now() - this.mulai) / 1000);
                                        this.sisa = Math.max(0, this.total - lewat);
                                    };

                                    hitung();
                                    setInterval(hitung, 1000);
                                }
                             }">

                            {{-- ======================= FASE 1: MEMASTIKAN ======================= --}}
                            <div x-show="sisa > 0">
                                {{-- Cincin yang menyusut, bukan jam diam. Yang diminta
                                     dari pelanggan di sini adalah MENUNGGU, dan menunggu
                                     tanpa tahu sampai kapan adalah alasan orang menutup
                                     tab. --}}
                                <div class="relative w-20 h-20 mx-auto">
                                    <svg class="w-20 h-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                        <circle cx="18" cy="18" r="16" fill="none"
                                            stroke="currentColor" stroke-width="3" class="text-slate-200" />
                                        <circle cx="18" cy="18" r="16" fill="none"
                                            stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                            class="text-orcha-ocean"
                                            stroke-dasharray="100.53"
                                            :stroke-dashoffset="100.53 * (1 - sisa / total)"
                                            style="transition: stroke-dashoffset .95s linear" />
                                    </svg>
                                    <span class="absolute inset-0 flex items-center justify-center text-lg font-bold font-heading text-orcha-navy"
                                        x-text="sisa"></span>
                                </div>

                                <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                                    Sedang memastikan pembayaran Anda
                                </h1>

                                <p class="max-w-md mx-auto mt-2 text-slate-600">
                                    Pembayaran <strong class="text-orcha-navy">{{ $bayar->nominal_formatted }}</strong>
                                    untuk pesanan <strong>{{ $bayar->kode }}</strong> sedang kami cocokkan
                                    dengan penyedia pembayaran. Biasanya selesai dalam hitungan detik.
                                </p>

                                {{-- Kalimat terpenting di seluruh halaman, dan diberi
                                     kotaknya sendiri supaya tidak terlewat. --}}
                                <p class="max-w-md px-4 py-3 mx-auto mt-4 text-sm font-semibold border border-amber-200 bg-amber-50 text-amber-900 rounded-xl">
                                    Jangan tutup halaman ini, dan <strong>jangan membayar lagi</strong>.
                                    Bila uang Anda sudah berpindah, pembayarannya pasti tercatat.
                                </p>

                                <div class="mt-4">
                                    <button type="button" wire:click="periksa"
                                        class="text-sm font-semibold text-orcha-ocean hover:underline"
                                        wire:loading.attr="disabled" wire:target="periksa">
                                        <span wire:loading.remove wire:target="periksa">Periksa sekarang</span>
                                        <span wire:loading wire:target="periksa">Memeriksa…</span>
                                    </button>
                                </div>
                            </div>

                            {{-- ================= FASE 2: SUDAH LEWAT JENDELANYA ================= --}}
                            {{-- Disembunyikan lewat gaya sebaris, bukan x-cloak.

                                 x-cloak hanya bekerja bila ada aturan CSS yang
                                 menyembunyikannya, dan aturan itu tidak pernah
                                 ada di proyek ini. Tanpa penjagaan yang benar,
                                 kotak "belum tercatat" berkedip lebih dulu di
                                 layar orang yang baru saja membayar — persis
                                 kalimat yang sedang kita hindari. Alpine akan
                                 menimpa display ini saat kondisinya benar. --}}
                            <div x-show="sisa === 0" style="display: none">
                                <x-heroicon-s-clock class="w-16 h-16 mx-auto text-orcha-sky" />

                                <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                                    Pembayaran belum tercatat
                                </h1>

                                <p class="max-w-md mx-auto mt-2 text-slate-600">
                                    Tagihan <strong class="text-orcha-navy">{{ $bayar->nominal_formatted }}</strong>
                                    untuk pesanan <strong>{{ $bayar->kode }}</strong> belum masuk ke sistem kami.
                                </p>

                                {{-- Yang uangnya sudah terpotong didahulukan, dan
                                     jalannya BUKAN tombol bayar. Ia sudah membayar;
                                     yang ia butuhkan orang, bukan halaman pembayaran
                                     kedua. --}}
                                <p class="max-w-md px-4 py-3 mx-auto mt-4 text-sm border border-amber-200 bg-amber-50 text-amber-900 rounded-xl">
                                    <strong>Uang Anda sudah terpotong?</strong> Jangan membayar lagi.
                                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="font-semibold underline">
                                        Hubungi kami lewat WhatsApp
                                    </a>
                                    dengan menyebut kode <strong>{{ $bayar->kode }}</strong> — uang yang sudah
                                    berpindah tidak hilang, dan kami yang mencocokkannya.
                                </p>

                                <div class="mt-4">
                                    <button type="button" wire:click="periksa"
                                        class="text-sm font-semibold text-orcha-ocean hover:underline"
                                        wire:loading.attr="disabled" wire:target="periksa">
                                        <span wire:loading.remove wire:target="periksa">Periksa sekali lagi</span>
                                        <span wire:loading wire:target="periksa">Memeriksa…</span>
                                    </button>
                                </div>

                                {{-- Baru di sini, dan sengaja paling bawah serta paling
                                     tenang: yang benar-benar belum sempat membayar tetap
                                     butuh jalannya, tetapi ia tidak boleh jadi hal
                                     pertama yang dilihat orang yang sudah membayar. --}}
                                @if ($bayar->url)
                                    <p class="max-w-md mx-auto mt-6 text-sm text-slate-500">
                                        Belum sempat membayar sama sekali?
                                        <a href="{{ $bayar->url }}"
                                            class="font-semibold text-orcha-ocean hover:underline">
                                            Buka lagi halaman pembayarannya</a>.
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif

                @else
                    {{-- BUKAN segitiga peringatan merah.

                         Tidak ada yang rusak: tautannya memang dirancang berumur
                         pendek, kursinya masih milik pelanggan, dan tagihannya utuh.
                         Ikon bahaya membuat orang mengira pesanannya hangus — lalu
                         ia menghubungi WhatsApp untuk sesuatu yang bisa ia
                         selesaikan sendiri dengan satu tombol. --}}
                    @if ($kedaluwarsa)
                        <div class="relative w-20 h-20 mx-auto">
                            <div class="absolute inset-0 rounded-full bg-amber-100"></div>
                            <x-heroicon-s-clock class="absolute inset-0 w-10 h-10 m-auto text-amber-600" />
                        </div>
                    @else
                        <x-heroicon-s-exclamation-triangle class="w-16 h-16 mx-auto text-orcha-sun" />
                    @endif

                    <h1 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                        {{ $kedaluwarsa ? 'Tautan pembayaran sudah kedaluwarsa' : 'Pembayaran tidak berhasil' }}
                    </h1>

                    {{-- Ketenangan lebih dulu, keterangan belakangan.

                         Yang membaca judul di atas sedang menduga pesanannya ikut
                         hangus. Kalimat pertama harus membantah dugaan itu sebelum
                         ia sempat mengendap. --}}
                    <p class="max-w-md mx-auto mt-2 text-slate-600">
                        @if ($kedaluwarsa)
                            Kursi dan tagihan Anda <strong class="text-orcha-navy">tidak berubah</strong> —
                            tinggal buat tautan baru, lalu bayar seperti biasa.
                        @else
                            Tidak ada uang yang terpotong, dan pesanan
                            <strong>{{ $bayar->kode }}</strong> masih tersimpan.
                        @endif
                    </p>

                    {{-- Apa yang ikut berpindah ke tagihan baru, disebut sebagai
                         angka — bukan dijanjikan sebagai kalimat. Orang yang baru
                         saja kehilangan satu tautan lebih percaya pada daftar yang
                         bisa ia cocokkan sendiri. --}}
                    @if ($kedaluwarsa)
                        <dl class="max-w-xs mx-auto mt-5 overflow-hidden text-sm border divide-y divide-slate-100 rounded-2xl border-orcha-mist">
                            <div class="flex items-center justify-between px-4 py-2.5">
                                <dt class="text-slate-500">Kode pesanan</dt>
                                <dd class="font-bold text-orcha-navy">{{ $bayar->kode }}</dd>
                            </div>
                            <div class="flex items-center justify-between px-4 py-2.5">
                                <dt class="text-slate-500">Tagihan</dt>
                                <dd class="font-bold text-orcha-navy">
                                    Rp {{ number_format((int) $bayar->nominal_pokok, 0, ',', '.') }}
                                </dd>
                            </div>
                        </dl>

                        {{-- Peringatan pembayaran ganda tetap MENDAHULUI tombolnya.

                             Tombol di bawah membuat tagihan baru, dan bagi orang yang
                             uangnya sudah terpotong beberapa detik sebelum tautannya
                             mati, menekannya berarti membayar dua kali. Diringkas jadi
                             satu baris supaya tidak mendominasi layar mayoritas yang
                             memang belum membayar — tetapi tetap terbaca lebih dulu. --}}
                        {{-- Rata KIRI, bukan rata tengah.

                             Kalimat dua baris yang dirata-tengah memutus dirinya di
                             tempat yang tidak dipilih siapa pun, dan tautan di
                             tengahnya jadi sulit dikenali sebagai tautan. Judul boleh
                             rata tengah; kalimat yang harus dibaca tidak. --}}
                        <div class="flex max-w-md gap-2.5 px-4 py-3 mx-auto mt-4 text-sm text-left border border-amber-200 bg-amber-50 text-amber-900 rounded-xl">
                            <x-heroicon-s-exclamation-triangle class="w-5 h-5 mt-px shrink-0 text-amber-600" />
                            <p>
                                Sudah terlanjur membayar? <strong>Jangan buat tautan baru.</strong>
                                <a href="{{ $wa }}" target="_blank" rel="noopener" class="font-semibold underline">
                                    Hubungi kami</a> — pembayaran menit terakhir tetap kami cocokkan.
                            </p>
                        </div>
                    @endif

                    <a href="{{ route('konfirmasi-pembayaran', ['kode' => $bayar->kode]) }}"
                        class="inline-flex mt-5 btn-orcha btn-orcha-primary">
                        {{ $kedaluwarsa ? 'Buat Tautan Baru' : 'Buat Tagihan Baru' }}
                    </a>

                    @if ($kedaluwarsa)
                        {{-- Nomor tagihan lamanya ditaruh PALING BAWAH dan dikecilkan.
                             Ia cuma berguna saat pelanggan menyebutkannya ke kami; di
                             baris pembuka ia hanya deretan huruf yang tidak
                             menjelaskan apa pun. --}}
                        <p class="mt-4 text-xs text-slate-400">
                            Tautan lama: {{ $bayar->invoice }}
                        </p>
                    @endif
                @endif

                <p class="mt-6 text-sm text-slate-500">
                    Ada yang perlu ditanyakan?
                    <a href="{{ $wa }}" target="_blank" rel="noopener"
                        class="font-semibold text-orcha-ocean hover:underline">Hubungi kami lewat WhatsApp</a>.
                </p>
            </div>
        </div>
    </section>
</div>
