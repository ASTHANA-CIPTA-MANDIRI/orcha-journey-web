<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Services\DokuCheckout;
use App\Models\OpenTrip\Angsuran;
use App\Support\MulaiPembayaranDoku;
use App\Support\RencanaAngsuran;
use App\Support\PemilikPesanan;
use App\Support\TagihanPesanan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * Halaman pembayaran pelanggan.
 *
 * Dulu halaman ini sebuah formulir: pelanggan mentransfer sendiri ke rekening
 * yang dikirim admin lewat WhatsApp, lalu mengunggah tangkapan layarnya untuk
 * dicek manusia. Seluruh jalur itu SUDAH DICABUT dari sisi publik.
 *
 * Yang menggantikannya bukan hanya kenyamanan. Bukti unggahan cuma klaim —
 * ia harus diperiksa satu per satu terhadap mutasi rekening, dan selama
 * pemeriksaan itu kursi pelanggan menggantung. Pembayaran lewat gerbang
 * memastikan uangnya sebelum halaman ini selesai memuat.
 *
 * Unggah bukti transfer tetap ada, tetapi HANYA DI SISI ADMIN (lemon):
 * pelanggan yang kesulitan membayar daring ditolong lewat percakapan, dan
 * admin yang mencatatkan pembayarannya sesudah mencocokkan mutasi sendiri.
 * Itu sengaja bukan sesuatu yang bisa dikerjakan orang tanpa admin — bedanya
 * persis di situ.
 */
new #[Layout('components.layouts.guest')] #[Title('Bayar Pesanan — Orcha Journey')] class extends Component {
    public string $kode = '';

    /**
     * Empat digit terakhir nomor WhatsApp pemesan — kunci kedua.
     *
     * Lihat App\Support\PemilikPesanan untuk alasannya.
     */
    public string $empatDigit = '';

    /** dp | pelunasan */
    public string $jenis = 'dp';

    /** Begitu pelanggan memilih sendiri, sistem berhenti menebak. */
    public bool $jenisDipilihSendiri = false;

    /**
     * Kegagalan saat membuka halaman DOKU, untuk ditampilkan apa adanya.
     *
     * Sengaja tidak lewat addError(): ini bukan kesalahan pengisian yang
     * menempel pada satu kolom, melainkan kabar bahwa gerbangnya tidak bisa
     * dihubungi. Menggantungkannya di bawah salah satu isian membuat orang
     * mengira ada yang salah dengan ketikannya.
     */
    public string $galatBayar = '';

    /**
     * Tagihan yang sudah dibuka di DOKU dan menunggu dilanjutkan pelanggan.
     *
     * Diisi bayar(), lalu digambar sebagai panel konfirmasi. Halaman TIDAK
     * langsung melompat ke DOKU, dan itu disengaja: kode uniknya baru
     * ditempelkan pada saat ini, dan pelanggan berhak melihat angka akhirnya
     * berikut asal-usulnya selagi masih di situs kami — bukan mendadak
     * menemukan nominal yang berbeda dari yang ia tekan, di halaman milik
     * pihak lain.
     *
     * @var array{invoice: string, label: string, pokok: int, kode_unik: int, nominal: int, url: string}|null
     */
    public ?array $siapBayar = null;

    public function mount(): void
    {
        $this->kode = strtoupper(trim((string) request()->query('kode', '')));

        // Tautan dari surat pendaftaran sudah membawa kodenya, jadi pilihan
        // yang pantas sudah bisa ditentukan sebelum halaman tampil.
        $this->selaraskanJenis();
    }

    /** Digitnya diketik belakangan, jadi pemeriksaannya harus ikut berjalan. */
    public function updatedEmpatDigit(): void
    {
        $this->updatedKode();
    }

    public function updatedKode(): void
    {
        $this->kode = strtoupper(trim($this->kode));
        $this->selaraskanJenis();
    }

    public function updatedJenis(): void
    {
        $this->jenisDipilihSendiri = true;
    }

    /**
     * Membuka halaman pembayaran DOKU, lalu mengantar pelanggan ke sana.
     *
     * Perhatikan yang TIDAK dikirim ke sini: nominalnya. Angka yang ditagihkan
     * dihitung ulang di server dari tagihan yang tersimpan (lihat
     * MulaiPembayaranDoku). Dulu nominal memang datang dari peramban, dan itu
     * tidak apa-apa karena ia cuma laporan yang nanti dicocokkan admin. Di
     * sini angkanya adalah yang benar-benar akan ditagihkan, jadi menerimanya
     * dari peramban sama saja membiarkan orang menentukan harganya sendiri.
     */
    public function bayar(): void
    {
        $this->galatBayar = '';

        $pesanan = $this->pesanan();

        if (! $pesanan) {
            $this->addError('kode', 'Pesanan tidak ditemukan. Periksa lagi kode pesanan dan 4 digit terakhir nomor WhatsApp Anda.');

            return;
        }

        // Jenis yang tidak sedang ditawarkan ditolak, bukan diam-diam
        // diperbaiki: pelanggan yang mengirimkannya tidak sedang menekan
        // tombol di halaman ini, dan menuruti kiriman semacam itu berarti
        // membiarkan orang memilih sendiri berapa yang ia bayar.
        if (! array_key_exists($this->jenis, $this->pilihanBayar())) {
            $this->galatBayar = 'Pilihan pembayaran itu tidak tersedia untuk pesanan ini.';

            return;
        }

        /*
         | Dibatasi, meski tiap percobaan cuma membuat baris "menunggu" yang
         | tidak mempengaruhi tagihan.
         |
         | Yang dijaga bukan basis data kita, melainkan kuota panggilan ke
         | DOKU: satu skrip yang menekan tombol ini berulang kali menghabiskan
         | jatah permintaan yang dibutuhkan pelanggan yang sungguhan sedang
         | membayar. Batasnya longgar — dua belas per jam cukup untuk orang
         | yang berganti pikiran soal channel beberapa kali.
         */
        $kunci = 'bayar-doku:'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, 12)) {
            $this->galatBayar = 'Terlalu banyak percobaan pembayaran dari perangkat ini. '
                .'Coba lagi nanti, atau hubungi kami lewat WhatsApp.';

            return;
        }
        RateLimiter::hit($kunci, 3600);

        try {
            $bayar = MulaiPembayaranDoku::untuk($pesanan, $this->jenis);
        } catch (\RuntimeException $e) {
            $this->galatBayar = $e->getMessage();

            return;
        }

        $this->siapBayar = [
            'invoice' => $bayar->invoice,
            'label' => $this->pilihanBayar()[$this->jenis]['label'] ?? 'Pembayaran',
            'pokok' => $bayar->nominal_pokok,
            'kode_unik' => $bayar->kode_unik,
            'nominal' => $bayar->nominal,
            'url' => (string) $bayar->url,
        ];
    }

    /** Kembali memilih; tagihan yang telanjur dibuka dibiarkan kedaluwarsa sendiri. */
    public function ulangi(): void
    {
        $this->siapBayar = null;
        $this->galatBayar = '';
    }

    /**
     * Pesanan yang kodenya cocok DAN nomornya cocok.
     *
     * Kode saja tidak cukup. Halaman ini menampilkan posisi tagihan begitu
     * kodenya dikenali, jadi kode yang ditebak dengan beruntung ikut memberi
     * tahu siapa orangnya, ikut trip apa, dan berapa sisa utangnya — dan
     * kodenya memang bisa ditebak (lihat App\Support\PemilikPesanan).
     */
    private function pesanan(): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        return PemilikPesanan::cariTerbatas($this->kode, $this->empatDigit, request()->ip());
    }

    /**
     * Menyetel jenis pembayaran ke yang paling masuk akal untuk pesanan ini.
     *
     * Berhenti bekerja begitu pelanggan memilih sendiri. Pilihan yang berubah
     * sendiri setelah ditekan adalah cara tercepat membuat orang berhenti
     * mempercayai angka di layar — dan yang sedang dibaca di sini adalah
     * angka yang akan keluar dari rekeningnya.
     */
    private function selaraskanJenis(): void
    {
        if ($this->jenisDipilihSendiri) {
            return;
        }

        $pilihan = $this->pilihanBayar();

        if ($pilihan === [] || array_key_exists($this->jenis, $pilihan)) {
            return;
        }

        $this->jenis = (string) array_key_first($pilihan);
    }

    /**
     * Pilihan pembayaran yang pantas ditawarkan, berikut angkanya.
     *
     * Dihitung di satu tempat lalu dipakai tiga kali — untuk menggambar
     * tombolnya, untuk menentukan pilihan bawaannya, dan untuk memeriksa
     * pilihan yang dikirim balik. Dua hitungan terpisah untuk pertanyaan yang
     * sama adalah asal celah tempat tombol menawarkan satu angka sementara
     * server menagihkan angka lain.
     *
     * Angkanya HARGA APA ADANYA, tanpa kode unik.
     *
     * Kode unik ditempelkan belakangan, saat percobaan bayarnya dibuat.
     * Menampilkannya sudah tertempel di sini membuat pelanggan membandingkan
     * dua angka ganjil yang tidak ia mengerti asalnya, tepat pada langkah
     * ketika yang ia butuhkan cuma satu perbandingan: uang muka atau lunas.
     *
     * @return array<string, array{label: string, keterangan: string, pokok: int}>
     */
    private function pilihanBayar(): array
    {
        $pesanan = $this->pesanan();
        $tagihan = TagihanPesanan::untuk($pesanan);

        if (! $pesanan || $tagihan === [] || $tagihan['lunas']) {
            return [];
        }

        $susun = function (string $jenis, string $label, string $keterangan) use ($tagihan): ?array {
            $pokok = TagihanPesanan::nominalUntukJenis($tagihan, $jenis);

            if (! $pokok || $pokok < 1000) {
                return null;
            }

            return [
                'label' => $label,
                'keterangan' => $keterangan,
                'pokok' => $pokok,
            ];
        };

        /*
         | Pesanan berencana angsuran hanya menawarkan SATU pilihan: termin
         | yang jatuh tempo berikutnya.
         |
         | Menyodorkan "bayar lunas" di sebelahnya membatalkan gunanya
         | keringanan — yang meminta angsuran justru orang yang tidak sanggup
         | membayar sekaligus, dan tombol itu cuma mengingatkannya pada hal
         | yang sedang ia hindari. Yang ingin melunasi lebih awal tetap bisa,
         | lewat admin.
         */
        $rencana = Angsuran::aktifUntuk($pesanan->kode);
        $termin = RencanaAngsuran::terminBerikutnya($rencana);

        if ($termin) {
            return ['angsuran' => [
                'label' => $termin['urutan'] === 1 ? 'Uang Muka' : 'Angsuran ke-'.($termin['urutan'] - 1),
                'keterangan' => 'Jatuh tempo '.$termin['jatuh_tempo']->translatedFormat('j F Y').'.',
                'pokok' => $termin['kurang'],
            ]];
        }

        // Uang muka hanya ditawarkan selama belum ada yang masuk. Sesudah itu
        // yang tersisa memang cuma pelunasannya, dan menawarkan "DP" untuk
        // kedua kalinya cuma membingungkan.
        $pilihan = $tagihan['sudah'] > 0
            ? ['pelunasan' => $susun('pelunasan', 'Lunasi Sisa', 'Sisa tagihan Anda.')]
            : [
                'dp' => $susun('dp', 'Uang Muka '.$tagihan['dp_persen'].'%', 'Kursi ditahan setelah uang muka masuk.'),
                'pelunasan' => $susun('pelunasan', 'Bayar Lunas', 'Sekali bayar, selesai.'),
            ];

        return array_filter($pilihan);
    }

    public function with(): array
    {
        $pesanan = $this->pesanan();

        return [
            'pesanan' => $pesanan,
            'tagihan' => TagihanPesanan::untuk($pesanan),

            /*
             | Gerbang mati BUKAN berarti kembali ke formulir unggahan.
             |
             | Formulir itu sudah dicabut dari sisi publik, dan menghidupkannya
             | kembali diam-diam saat gerbangnya bermasalah justru memulihkan
             | persis hal yang hendak dihilangkan: pembayaran yang dinyatakan
             | lewat gambar, dicek manusia, sementara kursinya menggantung.
             |
             | Yang ditampilkan sebagai gantinya adalah kabar jujur berikut
             | jalan keluarnya — hubungi admin, dan admin yang mencatatkan
             | pembayarannya dari sisi dalam.
             */
            'gerbangAktif' => app(DokuCheckout::class)->aktif(),
            'pilihanBayar' => $this->pilihanBayar(),

            // Jadwal ditampilkan seluruhnya, bukan hanya termin berikutnya:
            // yang sedang kesulitan keuangan perlu melihat seluruh
            // kewajibannya untuk merencanakan, bukan disodori satu per satu.
            'jadwalAngsuran' => RencanaAngsuran::posisi(
                $pesanan ? Angsuran::aktifUntuk($pesanan->kode) : null
            ),
        ];
    }
}; ?>

@php
    $wa = 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp');
@endphp

<div>
    <x-page-hero title="Bayar Pesanan" eyebrow="Pembayaran Online"
        subtitle="Bayar uang muka atau pelunasan langsung di sini — lewat transfer bank, QRIS, atau dompet digital."
        image="images/HERO/form-konfirmasi-pembayaran.webp" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($gerbangAktif)
                        <div class="p-6 card-orcha sm:p-8 space-y-7">
                            @include('livewire.public.open-trip.partials.pencari-pesanan')

                            @if ($galatBayar)
                                <div
                                    class="p-4 text-sm border rounded-2xl border-red-200 bg-red-50 text-red-800">
                                    {{ $galatBayar }}
                                    <a href="{{ $wa }}" target="_blank" rel="noopener"
                                        class="font-semibold underline">Hubungi kami lewat WhatsApp</a>
                                    bila berulang.
                                </div>
                            @endif

                            @if ($siapBayar)
                                {{-- Langkah terakhir sebelum meninggalkan situs.

                                     Di sinilah kode uniknya muncul — sesudah dipilih, bukan
                                     sebelumnya. Angka ganjil di ujung nominal akan disangka
                                     salah hitung kalau tidak diterangkan, dan orang yang
                                     mengira begitu berhenti untuk bertanya, tepat pada
                                     langkah yang paling tidak boleh terputus. --}}
                                <div class="p-5 border-2 rounded-2xl border-orcha-ocean bg-orcha-foam/50 sm:p-6">
                                    <p class="text-sm font-bold text-orcha-ocean">{{ $siapBayar['label'] }}</p>

                                    <p class="mt-1 text-xs font-semibold tracking-wide uppercase text-slate-500">
                                        Total yang harus dibayar
                                    </p>
                                    <p class="text-3xl font-bold font-heading text-orcha-navy">
                                        Rp {{ number_format($siapBayar['nominal'], 0, ',', '.') }}
                                    </p>

                                    <dl class="pt-4 mt-4 space-y-1 text-sm border-t border-white/70">
                                        <div class="flex justify-between">
                                            <dt class="text-slate-600">Tagihan</dt>
                                            <dd class="font-semibold text-orcha-navy">
                                                Rp {{ number_format($siapBayar['pokok'], 0, ',', '.') }}
                                            </dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-slate-600">Kode unik</dt>
                                            <dd class="font-semibold text-orcha-ocean">
                                                + {{ number_format($siapBayar['kode_unik'], 0, ',', '.') }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <p class="mt-3 text-sm text-slate-600">
                                        Kode unik <strong>{{ $siapBayar['kode_unik'] }}</strong> adalah penanda
                                        pembayaran ini supaya langsung kami kenali. Harga pesanan Anda tidak naik.
                                    </p>

                                    <p class="mt-2 text-xs text-slate-500">
                                        Nomor tagihan: <span class="font-mono">{{ $siapBayar['invoice'] }}</span>
                                    </p>

                                    {{-- Tautan biasa, bukan tombol Livewire.

                                         Alamat DOKU-nya sudah ada di tangan; melompatinya lewat
                                         satu perjalanan bolak-balik ke server hanya menambah satu
                                         titik yang bisa gagal, tepat sebelum uang berpindah. --}}
                                    <a href="{{ $siapBayar['url'] }}" class="w-full mt-5 btn-orcha btn-orcha-primary">
                                        Lanjutkan ke Pembayaran
                                    </a>

                                    <button type="button" wire:click="ulangi"
                                        class="w-full mt-2 text-sm font-semibold text-slate-500 hover:text-orcha-ocean">
                                        Ganti pilihan
                                    </button>
                                </div>
                            @elseif ($pilihanBayar)
                                {{-- Jadwal angsuran, bila pesanan ini mendapatkannya.

                                     Ditampilkan SELURUHNYA, bukan hanya termin
                                     berikutnya. Yang sedang kesulitan keuangan perlu
                                     melihat seluruh kewajibannya untuk merencanakan;
                                     disodori satu per satu ia tidak pernah tahu kapan
                                     ini berakhir. --}}
                                @if ($jadwalAngsuran)
                                    @php
                                        $lunasTermin = collect($jadwalAngsuran)->where('status', 'lunas')->count();
                                        $totalTermin = count($jadwalAngsuran);
                                        // 'kurang' sudah KUMULATIF — termin ke-3 memuat
                                        // kekurangan termin ke-2 di dalamnya. Menjumlahkannya
                                        // menghitung uang yang sama dua kali, dan angka sisa yang
                                        // lebih besar daripada tagihannya adalah jenis salah yang
                                        // membuat orang berhenti percaya seluruh halaman.
                                        $sisaTermin = (int) collect($jadwalAngsuran)->max('kurang');
                                        // Termin terdekat yang belum tertutup — satu-satunya baris
                                        // yang menuntut tindakan, jadi satu-satunya yang ditonjolkan.
                                        $terminBerikutnya = collect($jadwalAngsuran)->firstWhere('status', '!=', 'lunas')['urutan'] ?? null;
                                    @endphp

                                    <div class="mb-7">
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <h2 class="text-xl font-bold font-heading text-orcha-navy">Jadwal Angsuran Anda</h2>

                                            {{-- Ringkasan sebelum daftarnya.

                                                 Yang membuka halaman ini sedang menjawab satu
                                                 pertanyaan: saya masih harus bayar berapa. Menyuruhnya
                                                 menjumlahkan sendiri tiga baris di bawah adalah
                                                 pekerjaan yang bisa kita kerjakan untuknya, dan
                                                 hasilnya tidak akan salah hitung. --}}
                                            <p class="text-sm font-semibold text-slate-500">
                                                <span class="text-emerald-700">{{ $lunasTermin }} dari {{ $totalTermin }}</span> termin lunas
                                                @if ($sisaTermin > 0)
                                                    · sisa <span class="text-orcha-navy">Rp {{ number_format($sisaTermin, 0, ',', '.') }}</span>
                                                @endif
                                            </p>
                                        </div>

                                        {{-- Keadaan tiap termin ditandai TIGA kali: garis warna di
                                             tepi kiri, ikon, dan pilnya. Berlebihan dengan sengaja —
                                             yang membaca halaman ini sedang cemas soal uang, dan
                                             satu pil kecil di pojok kanan terlewat persis saat ia
                                             paling perlu terbaca. Garis tepinya juga yang membuat
                                             seluruh jadwal terbaca dalam sekali sapu, tanpa
                                             membaca satu kata pun. --}}
                                        <ul class="mt-4 overflow-hidden border divide-y divide-slate-100 rounded-2xl border-orcha-mist">
                                            @foreach ($jadwalAngsuran as $baris)
                                                <li @class([
                                                    'flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-l-4',
                                                    'border-l-emerald-500 bg-emerald-50/60' => $baris['status'] === 'lunas',
                                                    'border-l-red-500 bg-red-50/60' => $baris['status'] === 'telat',
                                                    // Kedua cabang ini SALING MENIADAKAN dengan
                                                    // sengaja. Menempelkan dua kelas border-l pada
                                                    // elemen yang sama membuat warnanya ditentukan
                                                    // urutan di berkas CSS terbangun, bukan oleh
                                                    // niat yang tertulis di sini — dan urutan itu
                                                    // bisa berubah tanpa satu pun baris blade
                                                    // disentuh.
                                                    'border-l-slate-200' =>
                                                        $baris['status'] === 'menunggu' && $baris['urutan'] !== $terminBerikutnya,
                                                    // Termin yang sedang ditagih diberi biru yang
                                                    // sama dengan kartu di bawahnya — keduanya
                                                    // bicara tentang uang yang sama.
                                                    'border-l-orcha-ocean bg-orcha-foam/50' =>
                                                        $baris['status'] === 'menunggu' && $baris['urutan'] === $terminBerikutnya,
                                                ])>
                                                    <div class="flex items-start gap-2.5">
                                                        @if ($baris['status'] === 'lunas')
                                                            <x-heroicon-s-check-circle class="w-5 h-5 mt-0.5 shrink-0 text-emerald-600" />
                                                        @elseif ($baris['status'] === 'telat')
                                                            <x-heroicon-s-exclamation-triangle class="w-5 h-5 mt-0.5 shrink-0 text-red-600" />
                                                        @else
                                                            <x-heroicon-o-clock class="w-5 h-5 mt-0.5 shrink-0 text-slate-400" />
                                                        @endif

                                                        <div>
                                                            <p class="font-bold text-orcha-navy">
                                                                {{-- Nama terminnya dari satu tempat, bukan dirakit
                                                                     di sini. Surat tanda terima menyebut termin yang
                                                                     sama, dan penamaan yang dirakit dua kali akan
                                                                     berbeda suatu saat — pelanggan lalu membaca
                                                                     "Angsuran ke-1" di email untuk baris yang di
                                                                     layar ini bernama "Uang muka". --}}
                                                                {{ \App\Support\RencanaAngsuran::labelTermin($baris['urutan']) }}

                                                                @if ($baris['status'] !== 'lunas' && $baris['urutan'] === $terminBerikutnya)
                                                                    <span class="ml-1 text-xs font-bold align-middle text-orcha-ocean">← giliran ini</span>
                                                                @endif
                                                            </p>
                                                            <p class="text-sm text-slate-500">
                                                                Jatuh tempo {{ $baris['jatuh_tempo']->translatedFormat('j F Y') }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="text-right">
                                                        <p @class([
                                                            'font-bold',
                                                            // Yang sudah lunas tidak perlu menuntut
                                                            // perhatian lagi; angkanya dipudarkan
                                                            // supaya yang belum dibayar menonjol
                                                            // tanpa harus dibuat lebih besar.
                                                            'text-slate-400 line-through' => $baris['status'] === 'lunas',
                                                            'text-orcha-navy' => $baris['status'] !== 'lunas',
                                                        ])>
                                                            Rp {{ number_format($baris['nominal'], 0, ',', '.') }}
                                                        </p>
                                                        <span @class([
                                                            'inline-flex items-center gap-1 mt-0.5 text-xs font-bold px-2 py-0.5 rounded-full',
                                                            'bg-emerald-100 text-emerald-800' => $baris['status'] === 'lunas',
                                                            'bg-red-100 text-red-800' => $baris['status'] === 'telat',
                                                            'bg-slate-100 text-slate-600' => $baris['status'] === 'menunggu',
                                                        ])>
                                                            {{ ['lunas' => 'Sudah dibayar', 'telat' => 'Lewat jatuh tempo', 'menunggu' => 'Belum dibayar'][$baris['status']] }}
                                                        </span>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if (collect($jadwalAngsuran)->where('status', 'telat')->isNotEmpty())
                                            {{-- Yang terlewat disebutkan sekali lagi di luar daftar.
                                                 Nadanya sengaja tenang: yang menunggak tahu ia
                                                 menunggak, dan kalimat yang menghakimi membuatnya
                                                 menghindari kami — persis kebalikan dari yang kita
                                                 butuhkan. --}}
                                            <p class="px-4 py-3 mt-3 text-sm font-semibold text-red-800 border border-red-200 bg-red-50 rounded-xl">
                                                Ada termin yang sudah lewat jatuh tempo. Silakan
                                                selesaikan lewat tombol di bawah, atau hubungi kami
                                                bila perlu penyesuaian jadwal.
                                            </p>
                                        @endif
                                    </div>
                                @endif

                                <div>
                                    <h2 class="text-xl font-bold font-heading text-orcha-navy">
                                        {{ $jadwalAngsuran ? 'Bayar Termin Berikutnya' : 'Pilih Pembayaran' }}
                                    </h2>

                                    {{-- Kartu, bukan dropdown.

                                         Yang dipilih di sini menentukan berapa uang yang keluar dari
                                         rekening orang, dan angkanya harus terbaca SEBELUM memilih —
                                         bukan sesudah, setelah isian lain ikut berubah. Dropdown
                                         menyembunyikan tepat bagian yang paling perlu dibandingkan. --}}
                                    <div class="grid gap-3 mt-4 sm:grid-cols-2">
                                        @foreach ($pilihanBayar as $kunci => $opsi)
                                            <label
                                                class="relative flex flex-col p-4 transition border-2 cursor-pointer rounded-2xl
                                                    {{ $jenis === $kunci ? 'border-orcha-ocean bg-orcha-foam/60' : 'border-orcha-mist hover:border-orcha-sky' }}">
                                                <input type="radio" wire:model.live="jenis" value="{{ $kunci }}"
                                                    class="sr-only">
                                                <span class="text-sm font-bold text-orcha-ocean">{{ $opsi['label'] }}</span>
                                                <span class="mt-1 text-2xl font-bold font-heading text-orcha-navy">
                                                    Rp {{ number_format($opsi['pokok'], 0, ',', '.') }}
                                                </span>
                                                <span class="mt-1 text-sm text-slate-500">{{ $opsi['keterangan'] }}</span>

                                                @if ($jenis === $kunci)
                                                    <x-heroicon-s-check-circle
                                                        class="absolute w-6 h-6 top-3 right-3 text-orcha-ocean" />
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>

                                    <button type="button" wire:click="bayar" class="w-full mt-5 btn-orcha btn-orcha-primary"
                                        wire:loading.attr="disabled" wire:target="bayar">
                                        <span wire:loading.remove wire:target="bayar">Bayar Sekarang</span>
                                        <span wire:loading wire:target="bayar">Menyiapkan pembayaran…</span>
                                    </button>

                                    <p class="mt-3 text-sm text-center text-slate-500">
                                        Harga di atas belum termasuk kode unik — penandanya ditambahkan pada
                                        langkah berikutnya, dan angkanya kami tampilkan sebelum Anda membayar.
                                    </p>
                                </div>
                            @elseif ($pesanan && $tagihan && $tagihan['lunas'])
                                <div
                                    class="p-5 text-center border rounded-2xl border-emerald-200 bg-emerald-50">
                                    <x-heroicon-s-check-circle class="w-12 h-12 mx-auto text-emerald-600" />
                                    <p class="mt-2 font-bold text-emerald-800">Pesanan ini sudah lunas</p>
                                    <p class="mt-1 text-sm text-emerald-700">
                                        Tidak ada yang perlu dibayar lagi. Sampai jumpa di perjalanan.
                                    </p>
                                </div>

                            @elseif ($pesanan)
                                {{-- Pesanan ketemu, belum lunas, tetapi tidak ada satu pun
                                     pilihan bayar yang bisa disusun.

                                     Sebelum cabang ini ada, rantainya berakhir di @endif dan
                                     halaman berhenti begitu saja: pelanggan membaca "Pilih di
                                     bawah", lalu tidak ada apa-apa di bawah. Buntu tanpa
                                     sepatah kata, dan tidak ada cara menebak apa yang salah.

                                     Penyebabnya penjaga nominal minimum di pilihanBayar():
                                     tagihan di bawah Rp 1.000 tidak bisa dikirim ke gerbang
                                     mana pun. Nyata pada pesanan uji bertotal Rp 3, dan bisa
                                     terjadi pada pesanan sungguhan yang menyisakan recehan
                                     setelah promo atau pembayaran sebagian. --}}
                                <div class="p-5 border rounded-2xl border-amber-200 bg-amber-50">
                                    <div class="flex gap-3">
                                        <x-heroicon-s-exclamation-triangle
                                            class="w-6 h-6 shrink-0 text-amber-600" />
                                        <div>
                                            <p class="font-bold text-amber-900">
                                                Sisa tagihannya terlalu kecil untuk dibayar online
                                            </p>
                                            <p class="mt-1 text-sm text-amber-900">
                                                Sisa <strong>{{ $tagihan['sisa_teks'] ?? '-' }}</strong> berada di
                                                bawah batas terendah yang bisa diproses gerbang pembayaran.
                                                Hubungi kami lewat
                                                <a href="{{ $wa }}" target="_blank" rel="noopener"
                                                    class="font-semibold underline">WhatsApp</a>
                                                dengan menyebut kode <strong>{{ $pesanan->kode }}</strong> —
                                                tim kami yang menyelesaikannya dari sisi dalam.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Gerbang tidak bisa dihubungi.

                             Yang TIDAK dilakukan di sini: menghidupkan kembali formulir
                             unggah bukti. Formulir itu sudah dicabut dari sisi publik, dan
                             memunculkannya diam-diam saat gerbang bermasalah justru
                             memulihkan persis hal yang hendak dihilangkan — pembayaran yang
                             dinyatakan lewat gambar, dicek manusia, sementara kursinya
                             menggantung.

                             Yang ditampilkan: kabar jujur berikut jalan keluarnya. Admin
                             tetap bisa mencatatkan pembayaran dari sisi dalam, dan jalur
                             itu memang sengaja tidak bisa ditempuh tanpa admin. --}}
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-wrench-screwdriver class="w-16 h-16 mx-auto text-orcha-sun" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">
                                Pembayaran online sedang tidak tersedia
                            </h2>
                            <p class="max-w-lg mx-auto mt-2 text-slate-600">
                                Kami sedang tidak bisa memproses pembayaran daring untuk sementara.
                                Pesanan Anda tetap tersimpan dan kursinya tidak hilang.
                            </p>
                            <p class="max-w-lg mx-auto mt-2 text-slate-600">
                                Hubungi kami lewat WhatsApp dengan menyebutkan kode pesanan Anda —
                                tim kami akan menuntun pembayarannya dan mencatatkannya langsung.
                            </p>

                            <a href="{{ $wa }}" target="_blank" rel="noopener"
                                class="inline-flex mt-6 btn-orcha btn-orcha-primary">
                                <x-bi-whatsapp class="w-5 h-5" />
                                Hubungi Kami
                            </a>
                        </div>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <x-peringatan-pembayaran />

                        {{-- Langkah-langkahnya hanya ditampilkan saat memang bisa
                             ditempuh. Daftar "pilih uang muka → diarahkan ke DOKU"
                             di sebelah kartu yang menyatakan pembayaran online sedang
                             mati adalah dua kalimat yang saling membantah di layar
                             yang sama. --}}
                        @if ($gerbangAktif)
                            <div class="p-6 card-orcha sm:p-7">
                                <h2 class="text-lg font-bold font-heading text-orcha-navy">Bagaimana prosesnya</h2>
                                <ol class="mt-4 space-y-3 text-sm text-slate-600">
                                    @foreach (['Masukkan kode pesanan dan 4 digit terakhir WhatsApp Anda.', 'Pilih uang muka atau bayar lunas.', 'Bayar lewat bank, QRIS, atau dompet digital.', 'Pembayaran tercatat sendiri dalam hitungan detik — tanpa menunggu dicek.'] as $i => $langkah)
                                        <li class="flex gap-3">
                                            <span
                                                class="flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full shrink-0 bg-orcha-ocean">{{ $i + 1 }}</span>
                                            <span>{{ $langkah }}</span>
                                        </li>
                                    @endforeach
                                </ol>

                                <p class="mt-5 text-sm text-slate-500">
                                    Pembayaran diproses DOKU, penyedia jasa pembayaran berizin Bank
                                    Indonesia. Kami tidak pernah meminta PIN, OTP, atau nomor kartu Anda
                                    lewat pesan apa pun.
                                </p>

                                <a href="{{ route('ketentuan-pembayaran') }}"
                                    class="inline-block mt-3 text-sm font-semibold text-orcha-ocean hover:underline">
                                    Lihat ketentuan pembayaran
                                </a>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>

</div>
