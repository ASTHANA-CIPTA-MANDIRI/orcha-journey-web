<?php

use Illuminate\Validation\Rule;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use App\Support\NomorTelepon;
use App\Support\SalinanPelanggan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pemesanan Sewa Kendaraan — Orcha Journey')] class extends Component {
    public string $unit = '';

    public string $transmisi = '';

    public string $satuan = 'hari';

    public $durasi = 1;

    public string $tanggalMulai = '';

    public string $jamMulai = '08:00';

    public string $denganSopir = 'ya';

    public string $lokasiAntar = '';

    /**
     * Lokasi pengembalian ditanya terpisah.
     *
     * Penyewa sering mengambil unit di kantor lalu mengembalikannya di bandara,
     * atau sebaliknya. Kalau hanya satu isian, sopir yang menjemput unit
     * berangkat ke alamat yang salah.
     */
    public string $lokasiKembali = '';

    /**
     * Tujuan perjalanan — hanya untuk sewa bersopir.
     *
     * Sewa lepas kunci tidak punya tujuan yang dicatat: penyewa membawa unitnya
     * ke mana pun ia perlu, dan menanyakannya hanya menambah isian tanpa guna.
     */
    public string $tujuan = '';

    /**
     * Perjalanan keluar kota — tarifnya berbeda.
     *
     * Disimpan sebagai pilihan penyewa, BUKAN disimpulkan dari tulisan tujuannya.
     * Menebak "luar kota" dari teks bebas seperti "Borobudur" berarti menagih
     * lebih berdasarkan tebakan.
     */
    public bool $luarKota = false;

    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $catatan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public ?string $kodeTerkirim = null;

    public function mount(): void
    {
        $unit = (string) request()->query('unit', '');

        if ($unit !== '' && $this->armada()->contains('uuid', $unit)) {
            $this->unit = $unit;
            $this->sesuaikanPilihan();
        }
    }

    /**
     * Begitu unit berganti, transmisi dan satuan ikut disesuaikan dengan yang
     * benar-benar tersedia pada unit itu.
     */
    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    public function updatedUnit(): void
    {
        $this->sesuaikanPilihan();
    }

    /**
     * Perjalanan ini memakai sopir kami.
     *
     * Penentunya moda pemesanan, bukan jenis unitnya: mobil yang disewa DENGAN
     * sopir juga tidak diserahkan ke penyewa, jadi yang perlu diketahui tetap
     * titik penjemputan dan tujuannya.
     */
    public function bersopir(): bool
    {
        return $this->denganSopir === 'ya';
    }

    /**
     * Luar kota hanya dijual harian.
     *
     * Perjalanan ke luar kota tidak selesai dalam dua belas jam, jadi satuannya
     * dipaksa ke hari begitu pilihannya dinyalakan — bukan dibiarkan lalu ditolak
     * validasi, karena penyewa tidak bisa menebak aturan yang tidak terlihat.
     */
    public function updatedLuarKota(): void
    {
        if ($this->luarKota) {
            $this->satuan = 'hari';
        }
    }

    private function sesuaikanPilihan(): void
    {
        $mobil = $this->kendaraanTerpilih();

        if (! $mobil) {
            return;
        }

        if (! in_array($this->transmisi, $mobil->transmisi_tersedia_list, true)) {
            $this->transmisi = $mobil->transmisi_tersedia_list[0] ?? '';
        }

        if ($mobil->tarif($this->satuan) === null) {
            $this->satuan = 'hari';
        }

        // Unit yang tidak dilepas tanpa sopir memaksa pilihannya. Tanpa ini,
        // berpindah dari mobil ke HiAce meninggalkan "lepas kunci" terpilih pada
        // unit yang tidak melayaninya — dan perkiraan biayanya ikut salah.
        if (! $mobil->lepas_kunci) {
            $this->denganSopir = 'ya';
        }
    }

    protected function rules(): array
    {
        return [
            'unit' => 'required|exists:cars,uuid',
            'transmisi' => 'required|in:Manual,Matic',
            'luarKota' => 'boolean',
            'satuan' => [
                'required',
                Rule::in($this->luarKota ? ['hari'] : array_keys(config('orcha.satuan_sewa'))),
            ],
            'durasi' => 'required|integer|min:1|max:30',
            'tanggalMulai' => 'required|date|after_or_equal:today',
            'jamMulai' => 'required|date_format:H:i',
            // Menyembunyikan pilihannya di layar tidak cukup: permintaan yang
            // dirakit tangan bisa mengirim "tidak" untuk bus. Aturan ini membaca
            // penanda unitnya, bukan mengulang aturan "hanya mobil" — supaya
            // keputusan admin di halaman armada benar-benar berlaku di sini.
            'denganSopir' => ['required', 'in:ya,tidak', function ($atribut, $nilai, $gagal) {
                $mobil = $this->kendaraanTerpilih();

                if ($nilai === 'tidak' && $mobil && ! $mobil->lepas_kunci) {
                    $gagal($mobil->type_label.' hanya disewakan bersama sopir kami.');
                }
            }],
            // Wajib: tanpa alamat yang jelas, unit tidak bisa diantar maupun
            // dijemput kembali.
            'lokasiAntar' => 'required|string|min:4|max:191',
            // Lokasi pengembalian hanya ditanyakan pada sewa lepas kunci. Pada
            // sewa bersopir unitnya tidak diserahkan ke penyewa, jadi tidak ada
            // yang mengembalikan apa pun — nilainya diisi dari titik penjemputan
            // saat disimpan supaya catatan penyewaannya tetap utuh.
            'lokasiKembali' => [
                Rule::requiredIf(fn () => ! $this->bersopir()),
                'nullable', 'string', 'min:4', 'max:191',
            ],
            'tujuan' => [
                Rule::requiredIf(fn () => $this->bersopir()),
                'nullable', 'string', 'min:3', 'max:191',
            ],
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            // Wajib: kwitansi dan tanda terima unit dikirim ke alamat ini, dan
            // itu berkas yang dibutuhkan penyewa bila terjadi sengketa.
            'email' => 'required|email|max:150',
            'catatan' => 'nullable|string|max:1000',
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'unit' => 'kendaraan',
            'satuan' => 'satuan sewa',
            'tanggalMulai' => 'tanggal mulai',
            'jamMulai' => 'jam mulai',
            'denganSopir' => 'kebutuhan sopir',
            // Sebutan medannya ikut berganti sesuai moda: pesan galat yang
            // menyebut "lokasi pengantaran unit" pada sewa bus membingungkan,
            // karena tidak ada unit yang diantar ke siapa pun.
            'lokasiAntar' => $this->bersopir() ? 'titik penjemputan' : 'lokasi pengantaran unit',
            'lokasiKembali' => 'lokasi pengembalian unit',
            'tujuan' => 'tujuan perjalanan',
            'setuju' => 'persetujuan ketentuan sewa',
        ];
    }

    public function pesan(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->validate();

        $mobil = Car::where('uuid', $this->unit)->firstOrFail();

        // Satuan yang tidak dijual untuk unit ini ditolak di server, bukan
        // sekadar disembunyikan di tampilan.
        if ($mobil->tarif($this->satuan) === null) {
            $this->addError('satuan', 'Unit ini tidak disewakan dengan satuan tersebut.');

            return;
        }

        if (! in_array($this->transmisi, $mobil->transmisi_tersedia_list, true)) {
            $this->addError('transmisi', 'Transmisi itu tidak tersedia untuk unit ini.');

            return;
        }

        $kunci = 'sewa-kendaraan:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('nama', 'Terlalu banyak pemesanan dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        // Tenggat pengembalian dihitung sekali lalu disimpan. Kalau dihitung
        // ulang setiap kali dibaca, mengubah aturan durasi di kemudian hari
        // akan diam-diam menggeser tenggat pesanan yang sudah berjalan — dan
        // denda keterlambatan ikut bergeser bersamanya.
        $selesai = PenyewaanKendaraan::hitungSelesai(
            $this->tanggalMulai, $this->jamMulai, $this->satuan, (int) $this->durasi
        );

        // Unit yang sudah dipesan orang lain di rentang waktu yang sama ditolak
        // di sini, bukan diketahui pagi keberangkatan saat mobilnya sudah
        // dibawa orang pertama.
        $bentrok = PenyewaanKendaraan::bentrok(
            $mobil->id,
            PenyewaanKendaraan::hitungSelesai($this->tanggalMulai, $this->jamMulai, $this->satuan, 0),
            $selesai,
        );

        if ($bentrok->isNotEmpty()) {
            $lain = $bentrok->first();

            $this->addError('tanggalMulai', 'Unit ini sudah dipesan sampai '
                .$lain->jadwal_selesai->translatedFormat('j F Y, H:i')
                .'. Pilih tanggal lain, atau unit lain yang sejenis.');

            return;
        }

        $sewa = PenyewaanKendaraan::create([
            'car_id' => $mobil->id,
            'tanggal_selesai' => $selesai->toDateString(),
            'jam_selesai' => $selesai->format('H:i'),
            // Diisi dari titik penjemputan hanya bila kosong, BUKAN ditimpa.
            // Pada sewa bersopir isiannya memang tidak ditanyakan, jadi biasanya
            // kosong — tetapi kalau ada nilai yang benar-benar diberikan,
            // membuangnya berarti menghilangkan keterangan yang sengaja ditulis.
            'lokasi_kembali' => $this->lokasiKembali ?: $this->lokasiAntar,
            'tujuan' => $this->bersopir() ? $this->tujuan : null,
            'luar_kota' => $this->luarKota,
            'nama_kendaraan' => $mobil->name,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'transmisi' => $this->transmisi,
            'satuan' => $this->satuan,
            'durasi' => $this->durasi,
            'tanggal_mulai' => $this->tanggalMulai,
            'jam_mulai' => $this->jamMulai,
            'dengan_sopir' => $this->denganSopir === 'ya',
            'lokasi_antar' => $this->lokasiAntar ?: null,
            'estimasi_biaya' => $mobil->estimasiBiaya($this->satuan, (int) $this->durasi, $this->denganSopir === 'ya', $this->luarKota),
            'catatan' => $this->catatan ?: null,
        ]);

        // Formulir ini satu-satunya yang tidak pernah mengirim surat, sehingga
        // penyewa tidak memegang bukti apa pun begitu halamannya ditutup —
        // termasuk kode pesanan dan jam pengembaliannya. Padahal justru itu
        // yang dipakai saat menagih denda keterlambatan.
        $rincian = [
            // Sebutan lengkapnya, bukan nama model saja: surat yang hanya menulis
            // "HiAce Commuter" tidak menyebut merek, tipe, tahun, maupun cc —
            // padahal itu yang dipakai penyewa memastikan unit yang datang benar.
            'Kendaraan' => $mobil->sebutan_lengkap.' ('.$sewa->transmisi.')',
            // Jumlah penumpang, bukan jumlah kursi: angka inilah yang dipakai
            // rombongan memastikan semua orang muat.
            'Kapasitas' => $mobil->capacity.' penumpang'
                .($mobil->kursi_total !== $mobil->capacity ? ' ('.$mobil->kursi_total.' kursi)' : ''),
            // Aturan sopir dan pos biaya dibaca menurut WILAYAH pesanannya.
            // Unit yang dalam kota diserahkan apa adanya bisa ditawarkan
            // sepaket bersama sopir dan BBM untuk perjalanan luar kota; surat
            // yang menyalin aturan dalam kota untuk pesanan luar kota
            // menjanjikan hal yang tidak berlaku.
            'Sopir' => $sewa->dengan_sopir
                ? $mobil->sopirLabel($sewa->luar_kota)
                : 'Lepas kunci — penyewa menyetir sendiri',
            // Pos yang ditanggung penyewa disebut terang-terangan. Tanpa ini
            // surat tidak menyebut BBM, tol, dan parkir sama sekali, dan yang
            // tidak tertulis di mana pun paling mudah dipersoalkan saat menagih.
            'BBM, tol, parkir' => $mobil->operasionalLabel($sewa->luar_kota),
            'Mulai' => $sewa->jadwal_mulai
                ? $sewa->jadwal_mulai->translatedFormat('l, j F Y').' pukul '.$sewa->jadwal_mulai->format('H:i')
                : '—',
            'Ditunggu kembali' => $selesai->translatedFormat('l, j F Y').' pukul '.$selesai->format('H:i'),
            'Durasi' => $sewa->durasi_label,
            'Wilayah' => $sewa->luar_kota ? 'Luar kota' : 'Dalam kota',
            // Sebutannya mengikuti moda sewanya. Kwitansi sewa bus yang menulis
            // "Lokasi pengantaran unit" membingungkan penyewa maupun sopir:
            // tidak ada unit yang diserahkan ke siapa pun.
            ...($sewa->dengan_sopir
                ? [
                    'Titik penjemputan' => $sewa->lokasi_antar,
                    'Tujuan' => $sewa->tujuan ?: '—',
                ]
                : [
                    'Lokasi pengantaran' => $sewa->lokasi_antar,
                    'Lokasi pengembalian' => $sewa->lokasi_kembali,
                ]),
            'Penyewa' => $sewa->nama,
            'WhatsApp' => $sewa->whatsapp,
        ];

        $berkas = BerkasKwitansi::buat(
            'Rincian Pemesanan Sewa Kendaraan',
            $sewa->kode,
            $rincian,
            $sewa->catatan,
            $sewa->estimasi_biaya ? 'Rp '.number_format($sewa->estimasi_biaya, 0, ',', '.') : null,
            'Estimasi biaya sewa',
            'Belum Dibayar',
        );

        KirimPemberitahuan::kirim(
            'Pemesanan Sewa Kendaraan Baru',
            $sewa->kode,
            $rincian,
            $sewa->catatan,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('rincian-sewa', $sewa->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $sewa->email,
                judul: 'Pemesanan Sewa Kendaraan Sudah Kami Terima',
                tautan: route('konfirmasi-pembayaran', ['kode' => $sewa->kode]),
                labelTautan: 'Kirim Bukti Transfer',
                langkah: "Simpan kode {$sewa->kode} — dipakai saat mengirim bukti transfer.\n\n"
                    .'Unit ditunggu kembali '.$selesai->translatedFormat('l, j F Y').' pukul '
                    .$selesai->format('H:i').' WIB di '.$sewa->lokasi_kembali.'. Ada tenggang '
                    .config('orcha.denda_sewa.tenggang_menit').' menit; lewat dari itu dikenakan denda '
                    .'keterlambatan '.config('orcha.denda_sewa.persen_tarif_harian_per_jam')
                    .'% tarif harian per jam.'."\n\n"
                    .'Biaya di lampiran masih perkiraan — BBM, tol, dan biaya lokasi dihitung terpisah, '
                    .'dan tim kami mengabari angka pastinya lewat WhatsApp.',
            ),
        );

        $this->kodeTerkirim = $sewa->kode;
        $this->reset(['nama', 'whatsapp', 'email', 'catatan', 'lokasiAntar', 'lokasiKembali', 'tujuan', 'luarKota', 'setuju']);
    }

    public function pesanLagi(): void
    {
        $this->reset(['kodeTerkirim', 'durasi']);
    }

    private function armada()
    {
        return Car::where('is_available', true)
            ->orderByRaw("case type when 'mobil' then 1 when 'hiace' then 2 else 3 end")
            ->orderBy('price_per_day')
            ->get();
    }

    private function kendaraanTerpilih(): ?Car
    {
        return $this->unit ? $this->armada()->firstWhere('uuid', $this->unit) : null;
    }

    public function with(): array
    {
        $mobil = $this->kendaraanTerpilih();

        return [
            'armada' => $this->armada(),
            'mobil' => $mobil,
            // Tenggat pengembalian ditampilkan sebelum dikirim: keterlambatan
            // didenda, jadi penyewa harus tahu jam berapa unit ditunggu kembali
            // sejak sebelum ia menekan Pesan.
            'jadwalSelesai' => $this->tanggalMulai && $this->jamMulai && (int) $this->durasi > 0
                ? PenyewaanKendaraan::hitungSelesai($this->tanggalMulai, $this->jamMulai, $this->satuan, (int) $this->durasi)
                : null,
            'satuanTersedia' => collect(config('orcha.satuan_sewa'))
                // Luar kota hanya harian; pilihan lain tidak ditampilkan sama
                // sekali supaya tidak ada yang dipilih lalu ditolak.
                ->filter(fn ($info, $kunci) => $this->luarKota
                    ? $kunci === 'hari'
                    : ($mobil === null || $mobil->tarif($kunci) !== null)),
            // (int) wajib di sini. Penyewa yang mau mengganti "1" menjadi "2"
            // menghapus dulu isinya, dan pada saat itu durasinya berupa teks
            // kosong — PHP menolak "" untuk parameter int, jadi halamannya galat
            // 500 tepat saat penyewa sedang mengetik. Nol menghasilkan perkiraan
            // kosong, yang memang jawaban yang benar: belum bisa dihitung.
            'estimasi' => $mobil?->estimasiBiaya($this->satuan, (int) $this->durasi, $this->denganSopir === 'ya', $this->luarKota),
            'bersopir' => $this->bersopir(),
        ];
    }
}; ?>

@php
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
@endphp

<div>
    <x-page-hero title="Pemesanan Sewa Kendaraan" eyebrow="Formulir Sewa"
        subtitle="Pilih unit, lama sewa, dan kebutuhan sopir. Perkiraan biayanya langsung terhitung sebelum Anda mengirim."
        image="images/kendaraan.jpg" />

    <section class="bg-white section-orcha">
        <div class="container-orcha">
            <div class="grid gap-6 lg:grid-cols-12">

                <div class="lg:col-span-8">
                    @if ($kodeTerkirim)
                        <div class="p-8 text-center card-orcha sm:p-10">
                            <x-heroicon-s-check-circle class="w-16 h-16 mx-auto text-orcha-sky" />
                            <h2 class="mt-4 text-2xl font-bold font-heading text-orcha-navy">Pemesanan tercatat</h2>
                            <p class="mt-2 text-sm text-slate-600">Simpan kode ini untuk memudahkan komunikasi dengan tim
                                kami.</p>

                            <p
                                class="inline-block px-6 py-3 mt-5 text-2xl font-black tracking-widest rounded-2xl font-heading bg-orcha-foam text-orcha-navy">
                                {{ $kodeTerkirim }}
                            </p>

                            <p class="mt-4 text-sm text-slate-600">
                                Tim kami mengecek ketersediaan unit lalu mengirim rincian biaya final lewat WhatsApp.
                            </p>

                            <x-peringatan-pembayaran ringkas class="mt-3" />

                            <div class="flex flex-col justify-center gap-3 mt-6 sm:flex-row">
                                <a href="{{ $wa("Halo Orcha Journey, saya baru memesan sewa kendaraan dengan kode $kodeTerkirim.") }}"
                                    target="_blank" rel="noopener noreferrer" class="btn-orcha btn-orcha-primary">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Konfirmasi via WhatsApp
                                </a>

                                <a href="{{ route('konfirmasi-pembayaran', ['kode' => $kodeTerkirim]) }}"
                                    class="btn-orcha btn-orcha-outline">
                                    <x-heroicon-o-banknotes class="w-5 h-5" />
                                    Konfirmasi Pembayaran
                                </a>
                                <button type="button" wire:click="pesanLagi" class="btn-orcha btn-orcha-outline">
                                    Pesan Unit Lain
                                </button>
                            </div>
                        </div>
                    @else
                        <form wire:submit="pesan" class="p-6 space-y-6 card-orcha sm:p-8">
                            <div class="hidden" aria-hidden="true">
                                <label for="sk-situs">Jangan diisi</label>
                                <input id="sk-situs" type="text" wire:model="situs" tabindex="-1" autocomplete="off">
                            </div>

                            {{-- Bagian formulir dinomori. Tiga judul tanpa nomor terbaca
                                 sebagai tiga kotak yang berdiri sendiri, sehingga penyewa
                                 tidak tahu ia sedang di bagian ke berapa dan berapa lagi
                                 yang tersisa — pertanyaan pertama yang membuat orang
                                 menutup formulir panjang. --}}
                            <div class="flex items-start gap-3">
                                <span class="langkah-orcha">1</span>
                                <div>
                                    <h2 class="text-xl font-bold font-heading text-orcha-navy">Pilih Kendaraan</h2>
                                    <p class="mt-1 text-sm text-slate-500">Tarif per jam, per 12 jam, dan per hari
                                        berbeda — pilih yang paling sesuai kebutuhan.</p>
                                </div>
                            </div>

                            <div>
                                <label for="sk-unit" class="label-orcha">Kendaraan <x-wajib /></label>
                                <select id="sk-unit" wire:model.live="unit" required
                                    class="isian-orcha @error('unit') isian-galat @enderror">
                                    <option value="">— Pilih unit —</option>
                                    @foreach ($armada->groupBy('type') as $jenis => $daftar)
                                        <optgroup label="{{ config('orcha.jenis_kendaraan')[$jenis] ?? $jenis }}">
                                            @foreach ($daftar as $item)
                                                <option wire:key="unit-{{ $item->uuid }}" value="{{ $item->uuid }}"
                                                    @selected($unit === $item->uuid)>
                                                    {{ $item->name }} · {{ $item->transmisi_label }} ·
                                                    {{ $rupiah($item->price_per_day) }}/hari
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('unit')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($mobil)
                                {{-- Daftar tarif, bukan tiga kolom sejajar.

                                     Sebagai tiga kolom, satuan yang TIDAK dijual unit ini
                                     ("Tidak tersedia") ditulis setebal harga yang sungguh
                                     ada, dan tidak ada yang menunjukkan tarif mana yang
                                     sedang dipakai untuk pesanan ini — penyewa melihat tiga
                                     angka lalu menebak sendiri. --}}
                                <div class="p-5 rounded-2xl bg-orcha-foam/70">
                                    <p class="text-xs font-bold tracking-wider uppercase text-orcha-ocean">Tarif unit
                                        ini</p>

                                    @php
                                        $barisTarif = [
                                            ['jam', 'Per jam', $mobil->harga_per_jam],
                                            ['12jam', 'Paket 12 jam', $mobil->harga_12_jam],
                                            ['hari', 'Per hari', $mobil->price_per_day],
                                        ];

                                        if ($mobil->punya_tarif_luar_kota) {
                                            $barisTarif[] = ['luar', 'Luar kota per hari', $mobil->harga_luar_kota];
                                        }

                                        // Tarif yang dipakai mengikuti pilihan wilayah lebih
                                        // dulu: unit yang disewa ke luar kota selalu dihitung
                                        // harian, satuan apa pun yang dipilih sebelumnya.
                                        $tarifDipakai = $luarKota && $mobil->punya_tarif_luar_kota ? 'luar' : $satuan;
                                    @endphp

                                    <dl class="mt-3 space-y-1.5">
                                        @foreach ($barisTarif as [$kunci, $label, $harga])
                                            <div
                                                class="flex items-baseline justify-between gap-3 text-sm {{ $kunci === $tarifDipakai ? 'font-semibold text-orcha-navy' : '' }}">
                                                <dt class="flex items-center gap-2 {{ $kunci === $tarifDipakai ? '' : 'text-slate-500' }}">
                                                    {{ $label }}
                                                    @if ($kunci === $tarifDipakai)
                                                        <span
                                                            class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase rounded text-white bg-orcha-ocean">
                                                            Dipakai
                                                        </span>
                                                    @endif
                                                </dt>
                                                <dd class="tabular {{ $harga ? ($kunci === $tarifDipakai ? 'font-black text-orcha-navy' : 'font-bold text-orcha-ocean') : 'text-slate-400' }}">
                                                    {{ $harga ? $rupiah($harga) : 'Tidak dijual' }}
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>

                                    {{-- Keterangan sopir dan BBM jadi daftar berikon, bukan dua
                                         kalimat biru bertumpuk yang terbaca seperti tautan. --}}
                                    <ul class="pt-3 mt-3 space-y-1.5 text-xs border-t text-slate-500 border-white">
                                        {{-- Keterangan mengikuti wilayah yang sedang dipilih.
                                             Menampilkan aturan dalam kota pada pesanan luar kota
                                             berarti menjanjikan hal yang tidak berlaku — dan itu
                                             baru ketahuan saat menagih. --}}
                                        @foreach ([[collect($mobil->rincianOperasional((bool) $luarKota))->contains('termasuk', true), $mobil->operasionalLabel((bool) $luarKota)], [$mobil->termasukSopir((bool) $luarKota), $mobil->sopirLabel((bool) $luarKota)]] as [$termasuk, $kalimat])
                                            <li class="flex items-start gap-1.5">
                                                @if ($termasuk)
                                                    <x-heroicon-s-check-circle
                                                        class="w-4 h-4 mt-px shrink-0 text-orcha-ocean" />
                                                @else
                                                    <x-heroicon-o-information-circle
                                                        class="w-4 h-4 mt-px shrink-0 text-slate-300" />
                                                @endif
                                                <span class="{{ $termasuk ? 'font-semibold text-slate-600' : '' }}">
                                                    {{ $kalimat }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Transmisi: hanya yang tersedia pada unit terpilih --}}
                            <fieldset>
                                <legend class="label-orcha">Transmisi <x-wajib /></legend>
                                @if ($mobil)
                                    @if ($mobil->punya_dua_transmisi)
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($mobil->transmisi_tersedia_list as $pilihan)
                                                <label class="pilihan-centang">
                                                    <input type="radio" class="sr-only" value="{{ $pilihan }}"
                                                        wire:model="transmisi" @checked($transmisi === $pilihan)>
                                                    <span class="kotak" aria-hidden="true">
                                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                            stroke-width="3.2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M4 10.5 8 14.5 16 5.5" />
                                                        </svg>
                                                    </span>
                                                    <span>{{ $pilihan }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="px-4 py-3 text-sm font-semibold border rounded-2xl border-orcha-foam text-orcha-navy bg-orcha-foam/40">
                                            {{ $mobil->transmisi_label }}
                                            <span class="font-normal text-slate-500">— unit ini hanya tersedia dalam
                                                transmisi tersebut</span>
                                        </p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-500">Pilih kendaraan dulu untuk melihat transmisi yang
                                        tersedia.</p>
                                @endif
                                @error('transmisi')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <hr class="border-orcha-foam">

                            <div class="flex items-start gap-3">
                                <span class="langkah-orcha">2</span>
                                <div>
                                    <h2 class="text-xl font-bold font-heading text-orcha-navy">Waktu Sewa</h2>
                                    <p class="mt-1 text-sm text-slate-500">Tanggal, lama pakai, wilayah perjalanan, dan
                                        kebutuhan sopir.</p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-satuan" class="label-orcha">Satuan sewa <x-wajib /></label>
                                    {{-- wire:key dan selected, keduanya perlu.

                                         Daftar ini MENYUSUT jadi satu pilihan saat wilayahnya
                                         luar kota, lalu kembali jadi tiga. Tanpa kunci, Livewire
                                         mencocokkan <option> menurut urutan: simpul "Per hari"
                                         dipakai ulang menjadi "Per jam". Tanpa atribut selected,
                                         peramban lalu menampilkan pilihan pertama — sehingga
                                         satuannya terbaca "Per jam" padahal harganya tetap
                                         dihitung harian, dan penyewa melihat dua hal yang saling
                                         bertentangan pada layar yang sama. --}}
                                    <select id="sk-satuan" wire:model.live="satuan" required
                                        class="isian-orcha @error('satuan') isian-galat @enderror">
                                        @foreach ($satuanTersedia as $kunci => $info)
                                            <option wire:key="satuan-{{ $kunci }}" value="{{ $kunci }}"
                                                @selected($satuan === $kunci)>
                                                {{ $info['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('satuan')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-durasi" class="label-orcha">Lama sewa <x-wajib /></label>

                                    {{-- Satuannya ditulis DI DALAM isian, bukan sebagai
                                         keterangan di bawahnya.

                                         Angka tanpa satuan mudah salah baca: "2" pada paket 12
                                         jam berarti 24 jam, bukan dua hari. Menaruhnya menempel
                                         pada angkanya membuat keduanya terbaca sekaligus, dan
                                         keterangan di bawah tidak perlu lagi mengulanginya.

                                         Tombol naik-turun bawaan disembunyikan supaya tidak
                                         bertumpuk dengan tulisan satuannya. --}}
                                    @php
                                        $satuanTeks = config('orcha.satuan_sewa')[$satuan]['satuan'] ?? 'hari';
                                    @endphp

                                    <div class="relative">
                                        <input id="sk-durasi" type="number" min="1" max="30" required
                                            wire:model.live="durasi"
                                            class="isian-orcha [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none {{ $satuan === '12jam' ? 'isian-satuan-lebar' : 'isian-satuan' }} @error('durasi') isian-galat @enderror">
                                        <span
                                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-sm font-semibold pointer-events-none text-slate-500">
                                            {{ $satuanTeks }}
                                        </span>
                                    </div>

                                    @error('durasi')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-tanggal" class="label-orcha">Tanggal mulai <x-wajib /></label>
                                    <input id="sk-tanggal" type="date" required min="{{ now()->toDateString() }}"
                                        wire:model.live="tanggalMulai"
                                        class="isian-orcha @error('tanggalMulai') isian-galat @enderror">
                                    @error('tanggalMulai')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-jam" class="label-orcha">Jam mulai <x-wajib /></label>
                                    <input id="sk-jam" type="time" required wire:model.live="jamMulai"
                                        class="isian-orcha @error('jamMulai') isian-galat @enderror">
                                    @error('jamMulai')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Wilayah perjalanan menentukan tarifnya.

                                 Dipilih penyewa, BUKAN disimpulkan dari tulisan tujuannya:
                                 menebak "luar kota" dari teks bebas seperti "Borobudur" berarti
                                 menagih lebih berdasarkan tebakan, dan tebakan yang salah soal
                                 harga paling cepat merusak kepercayaan. --}}
                            <fieldset>
                                <legend class="label-orcha">Wilayah perjalanan <x-wajib /></legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ([[false, 'Dalam kota'], [true, 'Luar kota']] as [$nilai, $label])
                                        <label class="pilihan-centang">
                                            <input type="radio" class="sr-only" value="{{ $nilai ? 1 : 0 }}"
                                                wire:model.live="luarKota" @checked((bool) $luarKota === $nilai)>
                                            <span class="kotak" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                    stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M4 10.5 8 14.5 16 5.5" />
                                                </svg>
                                            </span>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                {{-- Batasannya WAJIB tertulis di sini.

                                     Penyewa yang memilih wilayahnya sendiri tanpa tahu batasnya
                                     hanya menebak, dan selisih tarifnya baru dipersoalkan saat
                                     menagih. Kalimatnya diambil dari config supaya bisa diubah
                                     tanpa menyentuh berkas ini. --}}
                                {{-- Tiga keterangan disatukan ke dalam satu kotak. Sebagai tiga
                                     baris lepas berwarna beda-beda, ketiganya terbaca seperti
                                     tiga peringatan berturut-turut di bawah pilihan yang baru
                                     saja dibuat — padahal isinya satu hal: apa batasnya, berapa
                                     tarifnya, dan apa yang terjadi kalau salah pilih. --}}
                                <div class="p-4 mt-3 space-y-2 text-xs rounded-2xl bg-orcha-foam/60 text-slate-500">
                                    <p class="flex items-start gap-2">
                                        <x-heroicon-o-map class="w-4 h-4 mt-px shrink-0 text-orcha-ocean" />
                                        <span>{{ config('orcha.wilayah_sewa.dalam_kota') }}</span>
                                    </p>

                                    @if ($mobil)
                                        <p class="flex items-start gap-2 {{ $mobil->punya_tarif_luar_kota ? 'font-semibold text-slate-600' : '' }}">
                                            <x-heroicon-o-banknotes class="w-4 h-4 mt-px shrink-0 text-orcha-ocean" />
                                            <span>
                                                {{ $mobil->luar_kota_label }}
                                                @if ($luarKota)
                                                    · dihitung harian
                                                @endif
                                            </span>
                                        </p>
                                    @endif

                                    {{-- Pilihan penyewa bukan keputusan akhir: pesanan ini masih
                                         permintaan yang dikonfirmasi admin, jadi salah pilih tidak
                                         berakhir sebagai tagihan yang mengejutkan. --}}
                                    <p class="flex items-start gap-2">
                                        <x-heroicon-o-chat-bubble-left-right
                                            class="w-4 h-4 mt-px shrink-0 text-slate-400" />
                                        <span>{{ config('orcha.wilayah_sewa.catatan') }}</span>
                                    </p>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend class="label-orcha">Kebutuhan sopir <x-wajib /></legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ([['ya', 'Dengan sopir'], ['tidak', 'Lepas kunci']] as [$nilai, $label])
                                        @if ($nilai === 'tidak' && $mobil && ! $mobil->lepas_kunci)
                                            <span class="pilihan-centang opacity-50 cursor-not-allowed">
                                                <span class="kotak" aria-hidden="true"></span>
                                                <span>{{ $label }}</span>
                                            </span>
                                        @else
                                            <label class="pilihan-centang">
                                                <input type="radio" class="sr-only" value="{{ $nilai }}"
                                                    wire:model.live="denganSopir" @checked($denganSopir === $nilai)>
                                                <span class="kotak" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                                        stroke-width="3.2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M4 10.5 8 14.5 16 5.5" />
                                                    </svg>
                                                </span>
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                @if ($mobil && ! $mobil->lepas_kunci)
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ $mobil->name }} hanya disewakan bersama sopir kami —
                                        {{ $mobil->capacity }} penumpang dari {{ $mobil->kursi_total }} kursi.
                                    </p>
                                @endif
                                @error('denganSopir')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            {{-- Dua isian yang berganti sesuai moda sewanya.

                                 LEPAS KUNCI: unitnya diserahkan lalu diambil kembali, jadi
                                 yang ditanyakan alamat antar dan alamat ambil. Dua alamat dan
                                 bukan satu, karena penyewa sering mengambil unit di kantor lalu
                                 mengembalikannya di bandara.

                                 BERSOPIR: unitnya TIDAK diserahkan ke penyewa. Sopir kami yang
                                 menjemput lalu mengantar, jadi yang perlu diketahui titik
                                 penjemputan dan tujuannya. Menanyakan alamat serah unit pada
                                 penyewa bus menghasilkan jawaban yang tidak dipakai siapa pun,
                                 sementara tujuannya — yang menentukan lama jalan, BBM, dan
                                 kesiapan sopir — tidak pernah tercatat sama sekali. --}}
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-lokasi" class="label-orcha">
                                        {{ $bersopir ? 'Titik penjemputan' : 'Lokasi pengantaran unit' }}
                                        <x-wajib />
                                    </label>
                                    <input id="sk-lokasi" type="text" wire:model="lokasiAntar" required minlength="4" maxlength="191"
                                        placeholder="{{ $bersopir
                                            ? 'Contoh: Hotel Malioboro, atau alamat lengkap'
                                            : 'Contoh: Bandara YIA, atau alamat lengkap' }}"
                                        class="isian-orcha @error('lokasiAntar') isian-galat @enderror">
                                    @error('lokasiAntar')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if ($bersopir)
                                    <div>
                                        <label for="sk-tujuan" class="label-orcha">Tujuan perjalanan <x-wajib /></label>
                                        <input id="sk-tujuan" type="text" wire:model="tujuan" required minlength="3" maxlength="191"
                                            placeholder="Contoh: Borobudur — Dieng, atau Bromo"
                                            class="isian-orcha @error('tujuan') isian-galat @enderror">
                                        @error('tujuan')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-slate-500">
                                            Sebutkan kota atau lokasi yang dituju. Rute lengkapnya
                                            bisa ditulis di catatan.
                                        </p>
                                    </div>
                                @else
                                    <div>
                                        <label for="sk-lokasi-kembali" class="label-orcha">Lokasi pengembalian unit <x-wajib /></label>
                                        <input id="sk-lokasi-kembali" type="text" wire:model="lokasiKembali" required minlength="4" maxlength="191"
                                            placeholder="Boleh sama dengan lokasi pengantaran"
                                            class="isian-orcha @error('lokasiKembali') isian-galat @enderror">
                                        @error('lokasiKembali')
                                            <p class="galat-orcha">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            </div>

                            {{-- ============ TENGGAT PENGEMBALIAN ============
                                 Ditampilkan sebelum dikirim, bukan setelahnya: keterlambatan
                                 didenda, jadi penyewa harus tahu jam berapa unit ditunggu
                                 kembali sejak sebelum ia menekan Pesan. --}}
                            @if ($jadwalSelesai)
                                <div class="flex items-start gap-3 p-4 rounded-2xl bg-orcha-foam/70">
                                    <x-heroicon-s-clock class="w-5 h-5 mt-0.5 shrink-0 text-orcha-ocean" />
                                    <div>
                                        <p class="text-sm font-bold text-orcha-navy">
                                            Unit ditunggu kembali
                                            {{ $jadwalSelesai->translatedFormat('l, d F Y') }} pukul
                                            {{ $jadwalSelesai->format('H:i') }} WIB
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Ada tenggang {{ config('orcha.denda_sewa.tenggang_menit') }} menit.
                                            Lewat dari itu dikenakan denda keterlambatan
                                            {{ config('orcha.denda_sewa.persen_tarif_harian_per_jam') }}% tarif harian
                                            per jam.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <hr class="border-orcha-foam">

                            <div class="flex items-start gap-3">
                                <span class="langkah-orcha">3</span>
                                <div>
                                    <h2 class="text-xl font-bold font-heading text-orcha-navy">Data Penyewa</h2>
                                    <p class="mt-1 text-sm text-slate-500">Nomor WhatsApp dipakai untuk mengirim
                                        rincian biaya final.</p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="sk-nama" class="label-orcha">Nama lengkap <x-wajib /></label>
                                    <input id="sk-nama" type="text" wire:model="nama" required minlength="3"
                                        maxlength="120" placeholder="Nama penyewa"
                                        class="isian-orcha @error('nama') isian-galat @enderror">
                                    @error('nama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="sk-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                    <input id="sk-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="0812-3456-7890"
                                        class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
                                    @error('whatsapp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="sk-email" class="label-orcha">Email <x-wajib /></label>
                                <input id="sk-email" type="email" wire:model="email" required maxlength="150"
                                    placeholder="nama@email.com"
                                    class="isian-orcha @error('email') isian-galat @enderror">
                                @error('email')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sk-catatan" class="label-orcha">Catatan tambahan <span
                                        class="font-normal text-slate-400">(opsional)</span></label>
                                <textarea id="sk-catatan" rows="3" wire:model="catatan" maxlength="1000"
                                    placeholder="Rencana rute, jumlah penumpang, atau permintaan khusus."
                                    class="isian-orcha @error('catatan') isian-galat @enderror"></textarea>
                                @error('catatan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            <label class="flex items-start gap-3 text-sm cursor-pointer text-slate-600">
                                <input type="checkbox" wire:model="setuju" required
                                    class="mt-0.5 w-5 h-5 rounded border-orcha-foam text-orcha-ocean focus:ring-orcha-sky">
                                <span>
                                    <x-wajib /> Saya menyetujui
                                    <a href="{{ route('syarat-ketentuan') }}#sewa-kendaraan"
                                        class="font-semibold text-orcha-ocean hover:underline">ketentuan sewa
                                        kendaraan</a>
                                    dan memahami bahwa BBM, tol, parkir, serta tiket masuk lokasi dihitung terpisah.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">Kolom bertanda <x-wajib /> wajib diisi.</p>

                            <button type="submit" class="w-full btn-orcha btn-orcha-primary"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="pesan">Kirim Pemesanan</span>
                                <span wire:loading wire:target="pesan">Mengirim…</span>
                            </button>
                        </form>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-24">
                        {{-- Perkiraan biaya ikut berubah saat pilihan diubah --}}
                        <div class="overflow-hidden card-orcha">
                            <div class="p-6 text-white bg-gradient-to-br from-orcha-navy to-orcha-abyss sm:p-7">
                                <p class="text-xs font-bold tracking-wider uppercase text-orcha-sun">Perkiraan biaya</p>
                                <p class="mt-2 text-3xl font-black font-heading tabular">
                                    {{ $estimasi ? $rupiah($estimasi) : '—' }}
                                </p>
                                @if ($mobil)
                                    <p class="mt-1 text-sm text-slate-300">
                                        {{ $mobil->name }} · {{ $durasi }}
                                        {{ config('orcha.satuan_sewa')[$satuan]['satuan'] ?? 'hari' }}
                                        · {{ $denganSopir === 'ya' ? 'dengan sopir' : 'lepas kunci' }}
                                    </p>
                                @endif
                            </div>

                            {{-- Perincian angkanya, bukan angka tunggal.

                                 "Rp 1.800.000" tanpa penjelasan memancing pertanyaan yang
                                 sama berulang kali lewat WhatsApp — kenapa segitu, sopirnya
                                 sudah termasuk belum, BBM-nya dihitung tidak. Rinciannya
                                 dijumlahkan di model dan totalnya diambil DARI jumlah itu,
                                 jadi yang dibaca penyewa tidak mungkin berbeda dari yang
                                 tersimpan. --}}
                            @php
                                $rincianEstimasi = $mobil
                                    ? $mobil->rincianEstimasi($satuan, (int) $durasi, $denganSopir === 'ya', (bool) $luarKota)
                                    : [];
                            @endphp

                            @if ($rincianEstimasi)
                                <div class="px-6 pt-5 sm:px-7">
                                    <dl class="space-y-2 text-sm">
                                        @foreach ($rincianEstimasi as $pos)
                                            <div class="flex items-baseline justify-between gap-3">
                                                <dt class="text-slate-600">
                                                    {{ $pos['label'] }}
                                                    <span
                                                        class="block text-xs text-slate-400 tabular">{{ $pos['keterangan'] }}</span>
                                                </dt>
                                                <dd class="font-semibold text-orcha-navy tabular">
                                                    {{ $rupiah($pos['jumlah']) }}
                                                </dd>
                                            </div>
                                        @endforeach

                                        <div
                                            class="flex items-baseline justify-between gap-3 pt-2 border-t border-orcha-foam">
                                            <dt class="font-bold text-orcha-navy">Perkiraan total</dt>
                                            <dd class="font-black text-orcha-navy tabular">{{ $rupiah($estimasi) }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            <div class="p-6 text-xs sm:p-7 text-slate-500">
                                <p>Angka di atas baru perkiraan. Biaya final dikonfirmasi tim kami setelah ketersediaan
                                    unit dicek, dan belum termasuk BBM, tol, parkir, serta tiket masuk lokasi.</p>
                            </div>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <x-peringatan-pembayaran class="mb-5" />

                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Cara kerjanya</h2>
                            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                                @foreach (['Anda kirim pemesanan lewat formulir ini.', 'Kami cek ketersediaan unit pada tanggal tersebut.', 'Rincian biaya final dikirim lewat WhatsApp.', 'Unit dikunci setelah uang muka masuk.'] as $i => $langkah)
                                    <li class="flex gap-3">
                                        <span
                                            class="flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full shrink-0 bg-orcha-ocean">{{ $i + 1 }}</span>
                                        <span>{{ $langkah }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">Butuh beberapa unit sekaligus atau rute khusus?</p>
                            <a href="{{ $wa('Halo Orcha Journey, saya ingin menyewa beberapa unit kendaraan sekaligus.') }}"
                                target="_blank" rel="noopener noreferrer"
                                class="w-full mt-3 btn-orcha btn-orcha-primary !py-2.5 !text-sm">
                                <x-bi-whatsapp class="w-4 h-4" />
                                Tanya via WhatsApp
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <x-skrip-isian />
</div>
