<?php

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Services\DokuCheckout;
use App\Models\PaketWisata\TravelPackage;
use App\Support\BerkasKwitansi;
use App\Support\KirimPemberitahuan;
use App\Support\NomorTelepon;
use App\Support\SalinanPelanggan;
use App\Support\RincianBiaya;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] #[Title('Pendaftaran Open Trip — Orcha Journey')] class extends Component {
    public string $nama = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $paketId = '';

    /**
     * Tanggal keberangkatan yang dipilih.
     *
     * Satu paket boleh dijual untuk beberapa tanggal dengan isi yang sama.
     * Kosong berarti pengunjung belum memilih — dan bila paketnya memang cuma
     * punya satu, ia diisikan sendiri supaya tidak ada satu pun pertanyaan
     * tambahan untuk pilihan yang tidak ada pilihannya.
     */
    public string $keberangkatanId = '';

    /*
     | SENGAJA tanpa tipe int.
     |
     | Isian angka di peramban mengirim string kosong saat pengguna menghapus
     | angkanya — dan itu wajar: menghapus "21" jadi "2" lalu kosong sebelum
     | mengetik angka baru. Properti bertipe int tidak bisa menerima nilai
     | kosong, dan Livewire menganggapnya properti hilang sehingga halaman
     | berhenti dengan galat. Nilainya dibersihkan saat dipakai dan ditegur
     | oleh aturan validasi.
     */
    public $jumlahPeserta = 1;

    /**
     * Nama tiap peserta. Peserta pertama adalah pemesan itu sendiri, jadi
     * namanya ikut terisi otomatis — pemesan tidak perlu mengetik dua kali.
     */
    public array $peserta = [['nama' => '', 'titik_jemput' => '', 'jenis_kelamin' => '']];

    public string $catatan = '';

    /**
     * Kode rujukan dari alumni yang mengajaknya.
     *
     * Diisi sendiri oleh pendaftar, dan sengaja TIDAK wajib. Sebagian besar
     * orang datang tanpa kode, dan kotak wajib di jalur yang sudah panjang
     * adalah tempat orang berhenti mengisi.
     */
    public string $kodeRujukan = '';

    public bool $setuju = false;

    /** Perangkap bot. */
    public string $situs = '';

    public ?string $kodeTerdaftar = null;

    public function mount(): void
    {
        // Pra-isi paket bila pengunjung datang dari kartu paket, mis. ?paket=<uuid>
        $paket = (string) request()->query('paket', '');

        if ($paket !== '' && $this->paketTersedia()->contains('uuid', $paket)) {
            $this->paketId = $paket;
        }

        $this->keberangkatanId = (string) request()->query('keberangkatan', '');
        $this->samakanKeberangkatan();
    }

    /**
     * Membuang pilihan yang tidak sah, lalu mengisi sendiri bila tunggal.
     *
     * Dipanggil juga saat paketnya berganti. Pilihan yang ditinggalkan dari
     * paket sebelumnya akan menaruh pendaftar di rombongan yang sama sekali
     * lain — dan tidak ada apa pun di layar yang memperlihatkannya.
     */
    private function samakanKeberangkatan(): void
    {
        $pilihan = $this->keberangkatanTersedia();

        if ($this->keberangkatanId !== '' && ! $pilihan->contains('id', (int) $this->keberangkatanId)) {
            $this->keberangkatanId = '';
        }

        if ($this->keberangkatanId === '' && $pilihan->count() === 1) {
            $this->keberangkatanId = (string) $pilihan->first()->id;
        }
    }

    /** Tanggal yang masih dibuka pada paket terpilih. */
    /**
     * Keberangkatan yang SEDANG dipilih di layar — bukan yang terdekat.
     *
     * Dipakai seluruh ringkasan di halaman ini. Tanpa penolong ini tiap
     * tempat membaca $paket->jadwal_label, yang memulangkan rombongan
     * TERDEKAT milik paket: pemesan yang memilih 11–13 Desember membaca
     * kotak ringkasan bertuliskan 4–6 Desember, lalu menekan Daftar dengan
     * yakin ia sudah memesan tanggal yang benar.
     */
    public function keberangkatanTerpilih(): ?\App\Models\PaketWisata\Keberangkatan
    {
        if ($this->keberangkatanId === '') {
            return null;
        }

        return $this->keberangkatanTersedia()->firstWhere('id', (int) $this->keberangkatanId);
    }

    public function keberangkatanTersedia(): \Illuminate\Support\Collection
    {
        $paket = $this->paketId === ''
            ? null
            : TravelPackage::where('uuid', $this->paketId)->first();

        if (! $paket) {
            return collect();
        }

        return $paket->keberangkatanTersedia()->get()
            ->reject(fn ($k) => $k->penuh)
            ->values();
    }

    /**
     * Aturan validasi ini berjalan di server. Atribut `required` di HTML hanya
     * mempercepat umpan balik; data tetap ditolak di sini bila kosong atau
     * tidak sah, termasuk bila pengunjung mengakalinya dari sisi peramban.
     */
    protected function rules(): array
    {
        return [
            'nama' => 'required|string|min:3|max:120',
            'whatsapp' => ['required', 'string', 'max:25', fn ($atribut, $nilai, $gagal) => NomorTelepon::sah($nilai)
                ? null
                : $gagal('Nomor WhatsApp belum benar. Contoh: 0812-3456-7890.')],
            'email' => 'nullable|email|max:150',
            'paketId' => 'required|exists:tbl_travel_package,uuid',

            /*
             | Wajib HANYA bila paketnya memang menawarkan tanggal.
             |
             | Paket lama yang belum punya baris keberangkatan sama sekali
             | tetap bisa didaftari seperti dulu — mewajibkannya di sana
             | berarti menutup pendaftaran untuk paket yang tidak punya
             | pilihan apa pun untuk dipilih.
             */
            'keberangkatanId' => [
                $this->keberangkatanTersedia()->isEmpty() ? 'nullable' : 'required',
                function ($atribut, $nilai, $gagal) {
                    if ($nilai === '') {
                        return;
                    }

                    if (! $this->keberangkatanTersedia()->contains('id', (int) $nilai)) {
                        $gagal('Tanggal keberangkatan itu sudah tidak tersedia. Pilih tanggal lain.');
                    }
                },
            ],

            /*
             | Kursi yang tersisa diperiksa DI SERVER, saat menyimpan.
             |
             | Tulisan "sisa 2 kursi" di layar sudah basi sejak digambar: dua
             | orang bisa membuka halaman yang sama pada menit yang sama, dan
             | keduanya melihat angka yang sama. Yang menentukan bukan yang
             | dilihat, melainkan yang tersisa saat tombolnya ditekan.
             |
             | Paket tanpa kuota tidak pernah penuh — perilakunya persis
             | seperti sebelum kolom kuota ada.
             */
            'jumlahPeserta' => ['required', 'integer', 'min:1', 'max:60',
                function ($atribut, $nilai, $gagal) {
                    $paket = TravelPackage::where('uuid', $this->paketId)->first();

                    if (! $paket) {
                        return;
                    }

                    /*
                     | Sisanya dihitung pada TANGGAL yang dipilih, bukan
                     | sepaket. Kuota yang dihitung bersama akan menolak
                     | pendaftar pada tanggal yang masih kosong hanya karena
                     | tanggal lain sudah penuh.
                     */
                    $kb = $this->keberangkatanId === ''
                        ? null
                        : $this->keberangkatanTersedia()->firstWhere('id', (int) $this->keberangkatanId);

                    $sisa = $kb ? $kb->sisa_kursi : $paket->sisa_kursi;

                    if ($sisa === null) {
                        return;
                    }

                    if ((int) $nilai > $sisa) {
                        /*
                         | Pesannya TIDAK menyebut berapa kursi yang tersisa.
                         |
                         | Sisa kursi bukan angka publik: ketersediaan dibicarakan
                         | lewat WhatsApp, dan angka yang muncul di layar akan
                         | dibandingkan orang dengan yang dikatakan tim di
                         | percakapan. Dua angka yang berbeda melemahkan keduanya.
                         |
                         | Yang tetap dikerjakan pemeriksaan ini: menahan
                         | pendaftaran yang tidak muat. Menerimanya bukan menjaga
                         | peluang — ia menunda kabar buruknya sampai orang itu
                         | sudah mentransfer.
                         */
                        $gagal('Jumlah peserta ini belum bisa kami proses lewat formulir. Hubungi kami lewat WhatsApp — tim kami cek ketersediaannya langsung.');
                    }
                }],
            'peserta' => 'required|array|min:1',
            'peserta.*.nama' => 'required|string|min:3|max:120',
            // Titik jemput hanya ditanya bila paketnya memang menawarkan
            // lebih dari satu; nilainya wajib salah satu dari yang ditawarkan.
            'peserta.*.titik_jemput' => $this->aturanTitikJemput(),
            /*
             | Jenis kelamin WAJIB, dan ditanyakan di sini — bukan nanti.
             |
             | Pembagian kamar seluruhnya bergantung medan ini, dan ia tidak
             | pernah ditebak dari nama: "Sri", "Nur", dan "Adi" dipakai
             | keduanya, dan tebakan yang salah berarti satu orang tidur di
             | kamar yang salah. Sebelum medan ini ada, satu-satunya sumbernya
             | borang riwayat kesehatan — yang diisi belakangan dan tidak
             | selalu diisi semua orang, sehingga admin mengetiknya sendiri
             | satu per satu untuk rombongan enam puluh orang.
             |
             | Ejaannya sama persis dengan borang riwayat kesehatan dan dengan
             | yang diterima API; dua ejaan untuk hal yang sama membuat
             | separuh rombongan terbaca "belum diisi".
             */
            'peserta.*.jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'catatan' => 'nullable|string|max:1000',
            /*
             | Kode yang salah ketik DITEGUR di sini, bukan dibuang diam-diam
             | saat menyimpan.
             |
             | Model memang membuang kode yang tidak sah — itu penjagaan
             | terakhir. Tetapi kalau layarnya ikut diam, orang menekan Daftar,
             | melihat halaman berhasil, dan baru menyadari potongannya hilang
             | saat tagihannya datang. Yang menanggung kekecewaannya orang yang
             | tidak melakukan kesalahan apa pun selain salah satu huruf.
             */
            'kodeRujukan' => ['nullable', 'string', 'max:32',
                function ($atribut, $nilai, $gagal) {
                    $hasil = \App\Support\Rujukan::periksa($nilai, $this->whatsapp);

                    if (! $hasil['sah'] && filled($hasil['sebab'])) {
                        $gagal($hasil['sebab']);
                    }
                }],
            'setuju' => 'accepted',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'paketId' => 'paket open trip',
            'jumlahPeserta' => 'jumlah peserta',
            'kodeRujukan' => 'kode rujukan',
            'peserta.*.nama' => 'nama peserta',
            'peserta.*.titik_jemput' => 'titik jemput peserta',
            'peserta.*.jenis_kelamin' => 'jenis kelamin peserta',
            'setuju' => 'persetujuan syarat & ketentuan',
        ];
    }

    /** Kotak peserta mengikuti jumlahnya, tanpa menghapus yang sudah diisi. */
    /** Nomor dirapikan jadi 0812-3456-7890, apa pun cara pengguna menuliskannya. */
    public function updatedWhatsapp(): void
    {
        $this->whatsapp = NomorTelepon::rapi($this->whatsapp);
    }

    public function updatedJumlahPeserta(): void
    {
        $this->rapikanPeserta();
    }

    public function updatedNama(): void
    {
        // Peserta pertama = pemesan
        $this->peserta[0]['nama'] = $this->nama;
    }

    /** Ganti paket berarti pilihan titik jemputnya berbeda — mulai dari kosong. */
    public function updatedPaketId(): void
    {
        foreach ($this->peserta as $urutan => $satu) {
            $this->peserta[$urutan]['titik_jemput'] = '';
        }

        /*
         | Paket berganti berarti pilihan tanggalnya tidak berlaku lagi.
         |
         | Pilihan yang ditinggalkan dari paket sebelumnya akan menaruh
         | pendaftar di rombongan yang sama sekali lain — dan tidak ada apa
         | pun di layar yang memperlihatkannya.
         */
        $this->keberangkatanId = '';
        $this->samakanKeberangkatan();

        $this->rapikanPeserta();
    }

    private function aturanTitikJemput(): string
    {
        $paket = $this->paketDipilih();

        if (! $paket || ! $paket->punya_pilihan_jemput) {
            return 'nullable|string|max:191';
        }

        return 'required|string|in:'.implode(',', $paket->titik_jemput_list);
    }

    private function paketDipilih(): ?TravelPackage
    {
        return $this->paketId
            ? TravelPackage::where('uuid', $this->paketId)->first()
            : null;
    }

    private function rapikanPeserta(): void
    {
        $jumlah = (int) $this->jumlahPeserta;

        // Isian sedang kosong atau belum masuk akal: biarkan kotak yang sudah
        // terisi apa adanya. Meruntuhkannya jadi satu baris hanya karena
        // pengguna sedang menghapus angka berarti nama yang telanjur diketik
        // ikut hilang. Validasi yang menegur saat dikirim.
        if ($jumlah < 1) {
            return;
        }

        $jumlah = min(60, $jumlah);
        $paket = $this->paketDipilih();

        // Paket dengan satu titik jemput tidak perlu ditanya — langsung diisikan.
        $bawaan = $paket && ! $paket->punya_pilihan_jemput
            ? ($paket->titik_jemput_list[0] ?? '')
            : '';

        $peserta = [];

        foreach (range(0, $jumlah - 1) as $urutan) {
            $lama = $this->peserta[$urutan] ?? [];

            $peserta[] = [
                'nama' => is_array($lama) ? ($lama['nama'] ?? '') : (string) $lama,
                'titik_jemput' => (is_array($lama) ? ($lama['titik_jemput'] ?? '') : '') ?: $bawaan,
                'jenis_kelamin' => is_array($lama) ? ($lama['jenis_kelamin'] ?? '') : '',
            ];
        }

        $peserta[0]['nama'] = $peserta[0]['nama'] ?: $this->nama;

        $this->peserta = $peserta;
    }

    public function daftar(): void
    {
        if (filled($this->situs)) {
            return;
        }

        $this->rapikanPeserta();
        $this->validate();

        $kunci = 'daftar-open-trip:' . request()->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->addError('nama', 'Terlalu banyak pendaftaran dari perangkat ini. Silakan hubungi kami lewat WhatsApp.');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $paket = TravelPackage::where('uuid', $this->paketId)->firstOrFail();

        /*
         | Kuotanya diperiksa ULANG di dalam transaksi, dengan barisnya dikunci.
         |
         | Pemeriksaan di aturan validasi berjalan sebelum ini, dan di antara
         | keduanya ada jeda. Dua pendaftaran yang masuk pada detik yang sama
         | sama-sama membaca "sisa 2", sama-sama lolos, dan keduanya tersimpan —
         | kursinya jadi minus tanpa satu pun galat.
         |
         | Jarang terjadi, tetapi paling mungkin justru pada trip yang sedang
         | diperebutkan: saat kursinya tinggal sedikit dan beberapa orang
         | menekan tombol berbarengan.
         |
         | lockForUpdate menahan baris paketnya sampai transaksi ini selesai,
         | sehingga pendaftaran kedua menunggu lalu membaca angka yang sudah
         | memperhitungkan yang pertama.
         */
        $pendaftaran = DB::transaction(function () use ($paket) {
            $terkunci = TravelPackage::whereKey($paket->id)->lockForUpdate()->first();

            if ($terkunci->kuota !== null && $this->jumlahPeserta > $terkunci->sisa_kursi) {
                // Pesannya sama persis dengan yang di aturan validasi: tidak
                // menyebut angka, dan mengantar ke WhatsApp.
                throw ValidationException::withMessages([
                    'jumlahPeserta' => 'Jumlah peserta ini belum bisa kami proses lewat formulir. '
                        .'Hubungi kami lewat WhatsApp — tim kami cek ketersediaannya langsung.',
                ]);
            }

            // Tanggal dan titik jemput diambil dari paket — bukan dari isian
            // pengunjung — supaya tidak ada pendaftaran dengan jadwal karangan.
            return PendaftaranOpenTrip::create([
                'travel_package_id' => $paket->id,
                'keberangkatan_id' => $this->keberangkatanId ?: null,
                'nama_paket' => $paket->name,
                'nama' => $this->nama,
                'whatsapp' => $this->whatsapp,
                'email' => $this->email ?: null,
                'jumlah_peserta' => $this->jumlahPeserta,
                'kode_rujukan' => $this->kodeRujukan ?: null,
                'daftar_peserta' => collect($this->peserta)->map(fn ($satu) => [
                    'nama' => trim($satu['nama']),
                    'titik_jemput' => trim($satu['titik_jemput'] ?? ''),
                    'jenis_kelamin' => trim($satu['jenis_kelamin'] ?? ''),
                ])->all(),
                // Tanggal yang BENAR-BENAR dipilih, bukan tanggal paket:
                // paket kini boleh punya beberapa, dan yang dibaca surat,
                // kwitansi, dan pengingat adalah yang ini.
                'tanggal_berangkat' => $this->keberangkatanId !== ''
                    ? $this->keberangkatanTersedia()->firstWhere('id', (int) $this->keberangkatanId)?->tanggal_berangkat
                    : $paket->tanggal_berangkat,
                // Yang disimpan titik yang benar-benar dipakai rombongan ini,
                // bukan seluruh titik yang ditawarkan paket.
                'titik_jemput' => collect($this->peserta)->pluck('titik_jemput')
                    ->filter()->unique()->implode(', ') ?: $paket->titik_jemput,
                'catatan' => $this->catatan ?: null,
            ]);
        });

        $rincian = [
            'Paket' => $paket->name,
            // Tanggal yang DIPESAN orang ini. Surat konfirmasi yang menyebut
            // rombongan lain adalah kesalahan yang baru ketahuan di hari
            // keberangkatan, saat orangnya datang pada tanggal yang salah.
            'Keberangkatan' => $pendaftaran->keberangkatan?->rentang_tanggal
                ?: ($pendaftaran->tanggal_berangkat?->translatedFormat('j F Y') ?: ($paket->jadwal_label ?: '—')),
            'Pemesan' => $pendaftaran->nama,
            'WhatsApp' => $pendaftaran->whatsapp,
            'Email' => $pendaftaran->email,
            'Jumlah peserta' => $pendaftaran->jumlah_peserta.' orang',
            'Peserta & titik jemput' => collect($pendaftaran->peserta)
                ->map(fn ($satu) => $satu['nama'].' — '.($satu['titik_jemput'] ?: 'belum dipilih'))
                ->implode("\n"),
        ];

        // Lampirannya bukan kwitansi — belum ada uang yang masuk pada tahap
        // ini. Yang dibutuhkan pelanggan justru tagihannya: berapa DP yang
        // harus ditransfer sekarang, dan berapa sisa pelunasannya.
        $biaya = RincianBiaya::untuk($paket, (int) $pendaftaran->jumlah_peserta);

        $berkas = BerkasKwitansi::buat(
            'Rincian Biaya Pendaftaran',
            $pendaftaran->kode,
            $rincian,
            $pendaftaran->catatan,
            $biaya ? $biaya['dp_teks'] : null,
            $biaya ? 'Dibayar sekarang · DP '.$biaya['dp_persen'].'%' : null,
            'Belum Dibayar',
            biaya: $biaya,
        );

        // Angka biaya ikut ditulis di badan surat supaya terbaca tanpa perlu
        // membuka lampirannya lebih dulu.
        $rincianSurat = $biaya ? array_merge($rincian, [
            'Total biaya' => $biaya['total_teks'].' ('.$biaya['satuan_teks'].' × '.$biaya['orang'].' orang)',
            'DP '.$biaya['dp_persen'].'% — bayar sekarang' => $biaya['dp_teks'],
            'Sisa pelunasan' => $biaya['sisa_teks'].' — paling lambat H-'.$biaya['pelunasan_hari'],
        ]) : $rincian;

        // Dikirim SETELAH tersimpan, dan kegagalannya tidak membatalkan apa pun.
        // Ajakan di suratnya harus cocok dengan yang benar-benar ditemui
        // pelanggan saat tautannya dibuka.
        $gerbang = app(DokuCheckout::class)->aktif();

        KirimPemberitahuan::kirim(
            'Pendaftaran Open Trip Baru',
            $pendaftaran->kode,
            $rincianSurat,
            $pendaftaran->catatan,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('rincian-biaya', $pendaftaran->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $pendaftaran->email,
                judul: 'Pendaftaran Anda Sudah Kami Terima',
                /*
                 | Tombolnya langsung ke langkah berikutnya, bukan ke beranda:
                 | yang ditunggu sekarang pembayarannya.
                 |
                 | Alamatnya sama untuk kedua jalur — halaman itu sendiri yang
                 | memutuskan menampilkan tombol bayar atau formulir bukti. Yang
                 | perlu berbeda cuma ajakannya, karena "Kirim Bukti Transfer"
                 | pada halaman yang menagih pembayaran akan membuat orang
                 | mencari-cari bukti yang belum ada.
                 */
                tautan: route('konfirmasi-pembayaran', ['kode' => $pendaftaran->kode]),
                labelTautan: $gerbang ? 'Bayar Sekarang' : 'Kirim Bukti Transfer',
                langkah: "Simpan kode {$pendaftaran->kode}. Kode inilah yang dipakai untuk membayar, "
                ."mengisi riwayat kesehatan tiap peserta, sampai mengajukan pembatalan.\n\n"
                .($biaya
                    ? 'Berikutnya: bayar DP '.$biaya['dp_persen'].'% sebesar '.$biaya['dp_teks'].' paling lambat '
                        .$biaya['dp_batas_jam'].' jam sejak pendaftaran ini, lewat halaman '
                        .($gerbang ? 'pembayaran kami.' : 'Konfirmasi Pembayaran berikut buktinya.')
                        .' Sisanya '.$biaya['sisa_teks'].' dilunasi paling lambat H-'
                        .$biaya['pelunasan_hari'].' sebelum berangkat. Rinciannya ada di lampiran surat ini.'
                        // Sebagian pelanggan lebih suka sekali bayar dan selesai. Tanpa
                        // disebutkan, mereka mengira DP itu wajib lalu membayar dua
                        // kali untuk sesuatu yang bisa sekali.
                        .' Boleh juga langsung lunas '.$biaya['total_teks'].' sekaligus — '
                        .($gerbang ? 'pilih "Bayar Lunas" di halaman itu.' : 'pilih jenis "Pelunasan" di formulirnya.')
                    : 'Berikutnya: tim kami menghitung biayanya lebih dulu, lalu mengabari Anda lewat WhatsApp.')
                .' Tempat duduk baru terkunci setelah DP masuk.'
                // Rombongan besar butuh pintunya sejak awal: tiap peserta mengisi
                // riwayat kesehatannya sendiri, dan halaman itu menyediakan tautan
                // pribadi per peserta yang tinggal dibagikan lewat WhatsApp.
                .($pendaftaran->jumlah_peserta > 1
                    ? "\n\nRiwayat kesehatan diisi satu formulir untuk tiap peserta karena kondisi tiap orang "
                        .'berbeda. Buka '.route('riwayat-kesehatan', ['kode' => $pendaftaran->kode])
                        .' — di sana terlihat siapa saja yang belum mengisi, lengkap dengan tautan pribadi '
                        .'yang tinggal Anda kirimkan ke masing-masing peserta.'
                    : "\n\nJangan lupa mengisi riwayat kesehatan di "
                        .route('riwayat-kesehatan', ['kode' => $pendaftaran->kode])),
            ),
        );

        $this->kodeTerdaftar = $pendaftaran->kode;
        $this->reset(['nama', 'whatsapp', 'email', 'catatan', 'setuju', 'peserta']);
    }

    public function daftarLagi(): void
    {
        $this->reset(['kodeTerdaftar', 'jumlahPeserta', 'peserta', 'kodeRujukan']);
    }

    /**
     * Hanya paket open trip yang tanggalnya belum lewat.
     */
    private function paketTersedia()
    {
        return TravelPackage::tayang()->where('category', 'open_trip')

            /*
             | "Jangan tawarkan trip yang tanggalnya sudah lewat" — tetapi
             | tanggalnya dibaca dari KEBERANGKATAN bila paket punya.
             |
             | Kolom tanggal_berangkat milik paket adalah peninggalan; paket
             | yang kolomnya tertinggal di masa lalu akan hilang dari kotak
             | pilih ini meski rombongannya masih dibuka, dan calon pendaftar
             | melihatnya sebagai trip yang sudah tidak dijual.
             */
            ->where(fn ($q) => $q
                ->whereHas('keberangkatan', fn ($k) => $k
                    ->where('aktif', true)->whereDate('tanggal_berangkat', '>=', today()))
                ->orWhere(fn ($r) => $r->whereDoesntHave('keberangkatan')
                    ->where(fn ($s) => $s->whereNull('tanggal_berangkat')
                        ->orWhereDate('tanggal_berangkat', '>=', today()))))
            ->orderByRaw('tanggal_berangkat is null')
            ->orderBy('tanggal_berangkat')
            ->get();
    }

    public function with(): array
    {
        $daftarPaket = $this->paketTersedia();

        return [
            'paketOpenTrip' => $daftarPaket,
            'titikJemputPilihan' => $this->paketDipilih()?->titik_jemput_list ?? [],
            'paketTerpilih' => $this->paketId ? $daftarPaket->firstWhere('uuid', $this->paketId) : null,

            /*
             | Biaya dihitung ulang tiap jumlah peserta berubah, supaya promo
             | rombongannya terlihat SAAT orangnya masih bisa menambah teman —
             | bukan setelah formulirnya terkirim.
             |
             | Di situlah letak gunanya: yang sedang mengisi empat peserta
             | perlu tahu bahwa satu orang lagi mengubah harganya.
             */
            'biaya' => RincianBiaya::untuk(
                $this->paketDipilih(),
                (int) $this->jumlahPeserta,
                $this->kodeRujukan,
            ),
        ];
    }
}; ?>

@php
    $wa = fn (string $pesan) => 'https://api.whatsapp.com/send?phone=' . config('orcha.whatsapp') . '&text=' . rawurlencode($pesan);
    $rupiah = fn ($angka) => 'Rp ' . number_format((float) $angka, 0, ',', '.');
    $dp = config('orcha.pembayaran.dp_persen');
    $pelunasan = config('orcha.pembayaran.pelunasan_hari_sebelum');
    $batasDp = config('orcha.pembayaran.dp_batas_jam');
@endphp

<div>
    <x-page-hero title="Pendaftaran Open Trip" eyebrow="Formulir Pendaftaran"
        subtitle="Pilih trip yang jadwalnya sudah kami tetapkan, isi data Anda, dan kami balas dengan ketersediaan kursi di hari yang sama."
        image="images/HERO/form-pendaftaran-trip.webp" posisi="center top" />

    {{-- rak-panel + panel-orcha, sama seperti beranda dan halaman lain.
         Sebelumnya section-orcha di atas latar putih menyisakan ~130px bidang
         kosong antara kepala halaman dan isian pertama. --}}
    <div class="container-orcha rak-panel pb-6">
        <div class="grid gap-4 sm:gap-5 lg:grid-cols-12">

                {{-- Formulir --}}
                <div class="lg:col-span-8">
                    @if ($kodeTerdaftar)
                        {{-- Kartu "tercatat" memakai bahasa rupa kartu artikel:
                             sudut daun (lebar di kiri-atas dan kanan-bawah, nyaris
                             tajam di dua sudut lain), bayangan padat yang menggeser,
                             dan kaki pita berupa ombak. Bentuknya sengaja TIDAK
                             persegi panjang — ini satu-satunya layar yang muncul
                             sesudah kerja mengisi formulir selesai, dan kotak putih
                             biasa membuatnya terbaca sebagai halaman lain yang
                             kebetulan terbuka, bukan sebagai hasil. --}}
                        <div class="kartu-tercatat">
                            <div class="kepala-tercatat">
                                {{-- Cap air lambang orca, sama seperti panel sorotan
                                     blog dan "Cara Pesan" di beranda. Hiasan: aria-hidden
                                     dan alt kosong. --}}
                                <img src="{{ asset('orcha-logo-only.png') }}" alt="" aria-hidden="true"
                                    loading="lazy" width="512" height="512" class="cap-orca">

                                <div class="relative text-center">
                                    <span class="centang-tercatat">
                                        <x-heroicon-s-check class="w-9 h-9" />
                                    </span>
                                    <h2 class="mt-4 text-2xl font-bold text-white font-heading sm:text-3xl">
                                        Pendaftaran Anda tercatat
                                    </h2>
                                    <p class="max-w-md mx-auto mt-2 text-sm text-white/80">
                                        Simpan kodenya, lalu lanjutkan ke pembayaran uang muka untuk mengunci
                                        kursi Anda.
                                    </p>
                                </div>

                                <svg class="ombak-tercatat" viewBox="0 0 400 44" preserveAspectRatio="none"
                                    aria-hidden="true">
                                    <path d="M0 44V20c54-17 104 11 168 5s108-29 164-16 50 9 68 4v31z"
                                        fill="currentColor" />
                                </svg>
                            </div>

                            <div class="px-6 pt-2 pb-8 text-center sm:px-9 sm:pb-10">
                            {{-- Kode + tombol salin.

                                 Kodenya dipakai di tiga tempat lain — formulir riwayat
                                 kesehatan tiap peserta, halaman bayar, dan lacak pesanan —
                                 jadi yang membacanya hampir pasti perlu memindahkannya.
                                 Menyalin dengan tangan deretan huruf-angka acak adalah
                                 tempat salah ketik paling mudah terjadi, dan salahnya baru
                                 ketahuan saat formulirnya menolak.

                                 navigator.clipboard butuh HTTPS. Di produksi selalu ada,
                                 tetapi di http lokal ia undefined — jadi ada jalan cadangan
                                 lewat textarea + execCommand, dan bila keduanya gagal
                                 tombolnya berkata jujur "gagal menyalin" alih-alih diam
                                 seolah berhasil. --}}
                            <div class="flex flex-wrap items-center justify-center gap-2 mt-5"
                                x-data="{
                                    keadaan: 'diam',
                                    async salin() {
                                        const kode = @js($kodeTerdaftar);
                                        try {
                                            if (navigator.clipboard && window.isSecureContext) {
                                                await navigator.clipboard.writeText(kode);
                                            } else {
                                                const kotak = document.createElement('textarea');
                                                kotak.value = kode;
                                                kotak.setAttribute('readonly', '');
                                                kotak.style.position = 'fixed';
                                                kotak.style.opacity = '0';
                                                document.body.appendChild(kotak);
                                                kotak.select();
                                                const berhasil = document.execCommand('copy');
                                                document.body.removeChild(kotak);
                                                if (! berhasil) throw new Error('execCommand gagal');
                                            }
                                            this.keadaan = 'tersalin';
                                        } catch (e) {
                                            this.keadaan = 'gagal';
                                        }
                                        setTimeout(() => this.keadaan = 'diam', 2500);
                                    }
                                }">
                                <p class="karcis-kode">{{ $kodeTerdaftar }}</p>

                                <button type="button" x-on:click="salin()"
                                    class="inline-flex items-center gap-2 px-4 py-3 text-sm font-bold transition border rounded-2xl border-orcha-mist text-orcha-ocean hover:border-orcha-sky hover:bg-orcha-foam/60"
                                    x-bind:aria-label="keadaan === 'tersalin' ? 'Kode tersalin' : 'Salin kode pesanan'">
                                    <template x-if="keadaan === 'diam'">
                                        <span class="inline-flex items-center gap-2">
                                            <x-heroicon-o-clipboard-document class="w-5 h-5" />
                                            Salin
                                        </span>
                                    </template>
                                    <template x-if="keadaan === 'tersalin'">
                                        <span class="inline-flex items-center gap-2 text-emerald-700">
                                            <x-heroicon-s-check-circle class="w-5 h-5" />
                                            Tersalin
                                        </span>
                                    </template>
                                    <template x-if="keadaan === 'gagal'">
                                        <span class="inline-flex items-center gap-2 text-amber-700">
                                            <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                                            Gagal — salin manual
                                        </span>
                                    </template>
                                </button>
                            </div>

                            {{-- Urutannya: BAYAR dulu, riwayat kesehatan sesudahnya.

                                 Dulu riwayat kesehatan diminta lebih dulu, lalu
                                 pembayaran menyusul setelah tim mengonfirmasi kursi.
                                 Akibatnya yang paling sering terjadi: pendaftar
                                 mengisi riwayat kesehatan untuk seluruh rombongan,
                                 lalu berhenti — kursinya tidak pernah terkunci karena
                                 tidak ada yang menguncinya kecuali uang muka, dan
                                 aturan di halaman ini memang sudah berbunyi "kursi
                                 dikunci setelah DP masuk".

                                 Dengan urutan baru, pekerjaan terberat (mendata tiap
                                 peserta) baru diminta setelah kursinya benar-benar
                                 aman. --}}
                            <div class="p-5 mt-6 text-sm text-left rounded-2xl bg-orcha-foam/60 text-slate-600">
                                <p class="font-bold text-orcha-navy">Langkah berikutnya</p>
                                <ol class="mt-2 space-y-1.5 list-decimal list-inside">
                                    <li>Bayar <strong>uang muka {{ $dp }}%</strong> untuk mengunci kursi Anda.</li>
                                    <li>Setelah pembayaran berhasil, isi
                                        <strong>formulir riwayat kesehatan</strong> tiap peserta.</li>
                                    <li>Tim kami menghubungi Anda lewat WhatsApp. Pelunasan paling lambat
                                        <strong>H-{{ $pelunasan }}</strong> sebelum keberangkatan.</li>
                                </ol>
                            </div>

                            <x-peringatan-pembayaran class="mt-4 text-left" />

                            {{-- SATU tindakan utama, bukan tiga tombol sejajar.

                                 Tiga tombol dengan bobot yang sama memaksa orang yang
                                 baru saja selesai mengisi formulir memilih lagi —
                                 padahal cuma ada satu yang mengunci kursinya. --}}
                            <div class="mt-7">
                                <a href="{{ route('konfirmasi-pembayaran', ['kode' => $kodeTerdaftar]) }}"
                                    class="w-full btn-orcha btn-orcha-sun !py-4 !text-base">
                                    <x-heroicon-o-banknotes class="w-5 h-5" />
                                    Bayar Uang Muka Sekarang
                                    <x-heroicon-o-arrow-right class="w-5 h-5" />
                                </a>

                                <a href="{{ $wa("Halo Orcha Journey, saya sudah mendaftar open trip dengan kode $kodeTerdaftar.") }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="w-full mt-3 btn-orcha btn-orcha-outline">
                                    <x-bi-whatsapp class="w-5 h-5" />
                                    Tanya dulu lewat WhatsApp
                                </a>
                            </div>

                            {{-- Riwayat kesehatan tetap BISA dibuka dari sini, hanya
                                 tidak lagi jadi tombol utama.

                                 Yang membayar lewat transfer manual di luar gerbang
                                 tidak pernah melewati halaman "pembayaran berhasil"
                                 yang menawarkannya, jadi mencabut tautan ini sama saja
                                 menutup satu-satunya jalan mereka ke formulir itu. --}}
                            <p class="mt-5 text-sm text-slate-600">
                                Sudah membayar lewat transfer?
                                <a href="{{ route('riwayat-kesehatan', ['kode' => $kodeTerdaftar]) }}"
                                    class="font-semibold text-orcha-ocean hover:underline">Isi riwayat kesehatan
                                    peserta</a>.
                            </p>

                            {{-- Disebut di sini, saat kodenya masih di layar.

                                 Pertanyaan "pesanan saya bagaimana?" baru muncul
                                 berhari-hari kemudian — dan kalau saat itu ia tidak
                                 tahu halaman ini ada, satu-satunya jalan yang
                                 terpikir adalah bertanya lewat WhatsApp. --}}
                            <p class="mt-5 text-sm text-slate-600">
                                Ingin melihat status, sisa tagihan, dan bukti yang sudah Anda kirim kapan saja?
                                <a href="{{ route('lacak-pesanan', ['kode' => $kodeTerdaftar]) }}"
                                    class="font-semibold text-orcha-ocean hover:underline">Lacak pesanan Anda</a>
                                memakai kode di atas dan 4 digit terakhir nomor WhatsApp Anda.
                            </p>

                            <button type="button" wire:click="daftarLagi"
                                class="mt-5 text-sm font-semibold text-orcha-ocean hover:underline">
                                Daftarkan rombongan lain
                            </button>
                            </div>
                        </div>
                    @elseif ($paketOpenTrip->isEmpty())
                        <div class="p-10 text-center card-orcha">
                            <x-heroicon-o-calendar-days class="w-12 h-12 mx-auto text-orcha-mist" />
                            <p class="mt-3 font-semibold text-orcha-navy">Belum ada jadwal open trip yang dibuka.</p>
                            <p class="mt-1 text-sm text-slate-500">Hubungi kami untuk menanyakan jadwal berikutnya.</p>
                            <a href="{{ $wa('Halo Orcha Journey, kapan jadwal open trip berikutnya dibuka?') }}"
                                target="_blank" rel="noopener noreferrer" class="mt-5 btn-orcha btn-orcha-primary">
                                <x-bi-whatsapp class="w-5 h-5" />
                                Tanya Jadwal
                            </a>
                        </div>
                    @else
                        {{-- Formulirnya dipecah jadi TIGA panel berangka, bukan satu
                             kartu panjang berisi tiga judul kecil.

                             Yang mengisi halaman ini sering orang yang tidak terbiasa
                             dengan formulir panjang — guru pendamping, orang tua murid,
                             ketua rombongan. Satu dinding isian membuat mereka tidak
                             punya cara tahu sudah sampai mana dan masih berapa lagi;
                             yang terbaca cuma "masih panjang". Tiga panel berangka
                             memecahnya jadi tiga pekerjaan pendek yang masing-masing
                             terlihat selesai.

                             Tetap SATU <form> dan satu tombol kirim: memecahnya jadi
                             beberapa langkah yang harus ditekan satu per satu justru
                             menambah pekerjaan, dan membuat isian yang sudah diketik
                             bisa hilang di tengah jalan. --}}
                        <form wire:submit="daftar" class="space-y-4 sm:space-y-5">
                            <div class="hidden" aria-hidden="true">
                                <label for="daftar-situs">Jangan diisi</label>
                                <input id="daftar-situs" type="text" wire:model="situs" tabindex="-1"
                                    autocomplete="off">
                            </div>

                            <div class="panel-orcha reveal space-y-6">
                                <div class="langkah-daftar">
                                    <span class="langkah-nomor">1</span>
                                    <div>
                                        <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Langkah 1
                                            dari 3</p>
                                        <h2 class="mt-2 judul-panel-kecil">Pilih trip.</h2>
                                        <p class="mt-1.5 text-sm text-slate-500">Tanggal keberangkatan dan titik jemput
                                            sudah kami tetapkan untuk tiap trip.</p>
                                    </div>
                                </div>

                            <div>
                                <label for="d-paket" class="label-orcha">Paket open trip <x-wajib /></label>
                                <select id="d-paket" wire:model.live="paketId" required
                                    class="isian-orcha @error('paketId') isian-galat @enderror">
                                    <option value="">— Pilih trip —</option>
                                    @foreach ($paketOpenTrip as $paket)
                                        <option value="{{ $paket->uuid }}">
                                            {{-- Paket dengan beberapa rombongan TIDAK menyebut satu
                                                 tanggal di sini: tanggalnya dipilih di kotak
                                                 berikutnya, dan menyebut salah satunya membuat dua
                                                 isian terbaca saling bertentangan. --}}
                                            @php
                                                $tanggalPaket = 0 < $paket->jumlah_tanggal_lain
                                                    ? ($paket->jumlah_tanggal_lain + 1).' pilihan tanggal'
                                                    : $paket->jadwal_label;
                                            @endphp
                                            {{ $paket->name }}{{ $tanggalPaket ? ' · ' . $tanggalPaket : '' }}
                                            — {{ $rupiah($paket->price) }}/orang
                                        </option>
                                    @endforeach
                                </select>
                                @error('paketId')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tanggal keberangkatan: DIPILIH peserta bila trip yang sama
                                 dijual untuk beberapa tanggal.

                                 Hanya muncul saat memang ada lebih dari satu. Satu pilihan
                                 yang tidak punya pilihan lain adalah pertanyaan yang
                                 jawabannya sudah pasti, dan borang ini sudah panjang. --}}
                            @php $pilihanTanggal = $this->keberangkatanTersedia(); @endphp

                            @if ($pilihanTanggal->count() > 1)
                                <div>
                                    <label for="d-keberangkatan" class="label-orcha">
                                        Tanggal keberangkatan <x-wajib />
                                    </label>
                                    <select id="d-keberangkatan" wire:model.live="keberangkatanId" required
                                        class="isian-orcha @error('keberangkatanId') isian-galat @enderror">
                                        <option value="">— Pilih tanggal —</option>
                                        @foreach ($pilihanTanggal as $kb)
                                            <option value="{{ $kb->id }}" @selected((string) $this->keberangkatanId === (string) $kb->id)>
                                                {{ $kb->rentang_tanggal }}
                                                @if ($kb->harga_dipakai) — {{ $rupiah($kb->harga_dipakai) }}/orang @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('keberangkatanId')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-slate-500">
                                        Isi tripnya sama; yang berbeda hanya tanggalnya.
                                    </p>
                                </div>
                            @endif

                            {{-- Jadwal & titik jemput: ditetapkan, tidak bisa dipilih peserta --}}
                            @if ($paketTerpilih)
                                <div class="kartu-ditetapkan">
                                    <p class="lencana-ditetapkan">
                                        <x-heroicon-s-lock-closed class="w-3.5 h-3.5" />
                                        <span>Sudah ditetapkan oleh Orcha Journey</span>
                                    </p>

                                    {{-- Tiap keterangan diberi ubin ikonnya sendiri.

                                         Empat pasang label-nilai yang seragam terbaca sebagai satu
                                         blok tulisan yang harus dibaca seluruhnya; ikonnya membuat
                                         yang dicari — biasanya tanggal, atau titik jemput —
                                         ketemu tanpa membaca tiga yang lain. --}}
                                    <dl class="grid mt-4 gap-x-4 gap-y-4 sm:grid-cols-2">
                                        <div class="flex gap-3">
                                            <span class="grid w-9 h-9 shrink-0 place-items-center rounded-xl ubin-toska">
                                                <x-heroicon-o-calendar-days class="w-5 h-5" />
                                            </span>
                                            <div class="min-w-0">
                                            <dt class="label-fakta">
                                                Tanggal berangkat</dt>
                                            {{-- Tanggal yang DIPILIH, bukan jadwal_label milik paket.

                                                 jadwal_label memulangkan rombongan terdekat; pada
                                                 paket dengan beberapa tanggal, itu bukan tanggal
                                                 yang sedang dipesan orang ini. --}}
                                            <dd class="nilai-fakta">
                                                {{ $this->keberangkatanTerpilih()?->rentang_tanggal
                                                    ?? ($paketTerpilih->jadwal_label ?? 'Menyusul, akan kami kabari') }}</dd>
                                            </div>
                                        </div>

                                        <div class="flex gap-3">
                                            <span class="grid w-9 h-9 shrink-0 place-items-center rounded-xl ubin-toska">
                                                <x-heroicon-o-map-pin class="w-5 h-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <dt class="label-fakta">Titik jemput</dt>
                                                <dd class="nilai-fakta">
                                                    {{ $paketTerpilih->titik_jemput ?? 'Dikonfirmasi tim kami' }}</dd>
                                            </div>
                                        </div>

                                        <div class="flex gap-3">
                                            <span class="grid w-9 h-9 shrink-0 place-items-center rounded-xl ubin-toska">
                                                <x-heroicon-o-clock class="w-5 h-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <dt class="label-fakta">Durasi</dt>
                                                <dd class="nilai-fakta">{{ $paketTerpilih->duration ?? '—' }}</dd>
                                            </div>
                                        </div>

                                        <div class="flex gap-3">
                                            <span class="grid w-9 h-9 shrink-0 place-items-center rounded-xl ubin-toska">
                                                <x-heroicon-o-user-group class="w-5 h-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <dt class="label-fakta">Minimal berangkat</dt>
                                                <dd class="nilai-fakta">{{ $paketTerpilih->minimal_peserta }} orang</dd>
                                            </div>
                                        </div>
                                    </dl>

                                    {{-- Batas pelunasan mengikuti TANGGAL YANG DIPILIH.

                                         Batas milik paket dihitung dari tanggal berangkat
                                         paket, yang hanya benar untuk rombongan pertama.
                                         Pemesan trip 11-13 Desember yang membaca batas
                                         milik rombongan 27-29 November akan mengira
                                         pelunasannya sudah lewat sebelum ia sempat
                                         membayar. --}}
                                    @php
                                        $kbTerpilih = $this->keberangkatanTersedia()
                                            ->firstWhere('id', (int) $keberangkatanId);
                                        $batasBayar = $kbTerpilih?->batas_pelunasan
                                            ?? ($keberangkatanId === '' ? null : $paketTerpilih->batas_pelunasan);
                                    @endphp

                                    <div class="kaki-ditetapkan">
                                    <p class="catatan-ditetapkan">
                                        Uang muka {{ $dp }}% saat pemesanan, pelunasan paling lambat
                                        @if ($batasBayar)
                                            <strong class="text-orcha-navy">
                                                {{ $batasBayar->translatedFormat('j F Y') }}
                                            </strong> (H-{{ $pelunasan }}).
                                        @else
                                            <strong class="text-orcha-navy">H-{{ $pelunasan }}</strong>
                                            sebelum tanggal keberangkatan yang Anda pilih.
                                        @endif
                                    </p>

                                    {{-- Tautan ini dulu text-orcha-ocean: pada palet hangat itu
                                         #4a5a52, abu kehijauan yang tidak terbaca sebagai tautan
                                         sama sekali. Toska beserta panahnya membuatnya jelas bisa
                                         ditekan tanpa perlu digarisbawahi terus-menerus. --}}
                                    <a href="{{ route('paket-detail', $paketTerpilih->uuid) }}"
                                        class="tautan-itinerary">
                                        <span>Lihat itinerary lengkap</span>
                                        <x-heroicon-s-arrow-right class="w-4 h-4" />
                                    </a>
                                    </div>
                                </div>
                            @endif

                            </div>

                            <div class="panel-orcha reveal space-y-6">
                                <div class="langkah-daftar">
                                    <span class="langkah-nomor">2</span>
                                    <div>
                                        <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Langkah 2
                                            dari 3</p>
                                        <h2 class="mt-2 judul-panel-kecil">Data pemesan.</h2>
                                        <p class="mt-1.5 text-sm text-slate-500">Satu orang sebagai penanggung jawab
                                            rombongan.</p>
                                    </div>
                                </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="d-nama" class="label-orcha">Nama lengkap <x-wajib /></label>
                                    <input id="d-nama" type="text" wire:model="nama" required minlength="3"
                                        maxlength="120" placeholder="Nama pemesan"
                                        class="isian-orcha @error('nama') isian-galat @enderror">
                                    @error('nama')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="d-wa" class="label-orcha">Nomor WhatsApp <x-wajib /></label>
                                    <input id="d-wa" type="tel" inputmode="tel" wire:model.blur="whatsapp" required minlength="8"
                                        maxlength="30" placeholder="0812-3456-7890"
                                        class="isian-orcha orcha-telp @error('whatsapp') isian-galat @enderror">
                                    @error('whatsapp')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="d-email" class="label-orcha">Email <span
                                            class="font-normal text-slate-400">(opsional)</span></label>
                                    <input id="d-email" type="email" wire:model="email" maxlength="150"
                                        placeholder="nama@email.com"
                                        class="isian-orcha @error('email') isian-galat @enderror">
                                    @error('email')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="d-jumlah" class="label-orcha">Jumlah peserta <x-wajib /></label>
                                    <input id="d-jumlah" type="number" min="1" max="60" required
                                        wire:model.live="jumlahPeserta"
                                        class="isian-orcha @error('jumlahPeserta') isian-galat @enderror">
                                    @error('jumlahPeserta')
                                        <p class="galat-orcha">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Kode rujukan.

                                 Ditaruh SESUDAH jumlah peserta dan SEBELUM kotak promo,
                                 supaya keduanya terbaca sebagai satu bagian yang sama:
                                 hal-hal yang membuat harganya turun.

                                 Tidak wajib, dan dikatakan begitu apa adanya. Sebagian
                                 besar orang datang tanpa kode, dan kotak yang terlihat
                                 wajib di jalur yang sudah panjang adalah tempat orang
                                 berhenti mengisi. --}}
                            <div>
                                <label for="d-rujukan" class="label-orcha">
                                    Kode rujukan
                                    <span class="font-normal text-slate-400">(opsional)</span>
                                </label>
                                <input id="d-rujukan" type="text" maxlength="32"
                                    wire:model.live.debounce.500ms="kodeRujukan"
                                    placeholder="Contoh: BUDI-K7QM"
                                    class="isian-orcha uppercase @error('kodeRujukan') isian-galat @enderror">

                                @error('kodeRujukan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @else
                                    @if ($biaya && ($biaya['rujukan_kode'] ?? null))
                                        {{-- Menyebut NAMA pemiliknya, bukan cuma "kode
                                             diterima". Yang mengetik kode temannya ingin
                                             tahu bahwa ia mengetik kode yang benar — dan
                                             nama itulah satu-satunya cara ia tahu. --}}
                                        <p class="flex items-start gap-2 mt-2 text-sm font-semibold text-orcha-ocean">
                                            <x-heroicon-s-check-badge class="w-5 h-5 mt-px shrink-0" />
                                            <span>
                                                Kode dari {{ $biaya['rujukan_nama'] }} dipakai —
                                                potongan {{ $biaya['rujukan_potongan_teks'] }}.
                                            </span>
                                        </p>
                                    @else
                                        <p class="mt-2 text-sm text-slate-500">
                                            Punya kode dari teman yang pernah ikut trip kami? Masukkan di sini.
                                        </p>
                                    @endif
                                @enderror
                            </div>

                            {{-- Promo rombongan, ditampilkan SAAT peserta masih bisa
                                 ditambah.

                                 Dua kalimat yang berbeda maksudnya: yang sudah dapat
                                 diberi tahu hematnya, yang belum diberi tahu berapa orang
                                 lagi yang kurang. Promo yang cuma diumumkan di halaman
                                 paket hanya menguntungkan rombongan yang kebetulan sudah
                                 ramai; yang dituju justru yang masih empat orang. --}}
                            @if ($biaya)
                                {{-- Ringkasan biaya berbentuk kuitansi.

                                     Yang lama satu kalimat abu-abu: "Total Rp 1.350.000 · DP 30%
                                     = Rp 405.000". Angka yang harus ditransfer hari ini berukuran
                                     sama dengan kata sambung di sebelahnya, dan sisa pelunasannya
                                     tidak disebut sama sekali — pengisinya mengurangi sendiri.

                                     Sekarang angka DP berdiri sendiri di ubin gelap (satu-satunya
                                     angka yang perlu ditindaklanjuti hari ini), total dan sisa
                                     turun jadi dua baris di bawah garis sobek. --}}
                                <div class="kartu-biaya">
                                    @if ($biaya['promo_label'] || $biaya['promo_ajakan'])
                                        <div class="space-y-2">
                                            @if ($biaya['promo_label'])
                                                <p class="flex items-start gap-2 text-sm font-bold text-orcha-navy">
                                                    <x-heroicon-s-gift class="w-5 h-5 mt-px text-orcha-toska shrink-0" />
                                                    <span>{{ $biaya['promo_label'] }}</span>
                                                </p>

                                                <p class="text-sm text-slate-600">
                                                    @if ($biaya['promo_gratis_orang'] > 0)
                                                        {{ $biaya['orang'] }} orang, {{ $biaya['promo_gratis_orang'] }} gratis —
                                                        dibayar {{ $biaya['promo_orang_dibayar'] }} orang.
                                                    @endif
                                                    Hemat <strong>{{ $biaya['promo_potongan_teks'] }}</strong> dari
                                                    <span class="line-through">{{ $biaya['total_sebelum_promo_teks'] }}</span>.
                                                </p>
                                            @endif

                                            @if ($biaya['promo_ajakan'])
                                                <p class="flex items-start gap-2 text-sm text-slate-600">
                                                    <x-heroicon-o-user-plus class="w-5 h-5 mt-px text-orcha-sun shrink-0" />
                                                    <span>{{ $biaya['promo_ajakan'] }}</span>
                                                </p>
                                            @endif
                                        </div>

                                        <div class="sobek-biaya" aria-hidden="true">
                                            <span class="takik-biaya takik-biaya-kiri"></span>
                                            <span class="takik-biaya takik-biaya-kanan"></span>
                                        </div>
                                    @endif

                                    {{-- Segel persen duduk SEBARIS DENGAN LABELNYA, bukan di
                                         samping angkanya.

                                         Di samping angka, segel selebar 58px menyisakan 157px
                                         untuk angkanya di layar 390px — sedangkan "Rp 12.750.000"
                                         butuh 205px. Angkanya pecah dua baris ("Rp" sendirian di
                                         atas) pada rombongan yang totalnya besar, yaitu justru
                                         pendaftaran yang paling perlu terbaca benar. --}}
                                    <div class="ubin-dp">
                                        <div class="kepala-dp">
                                            <p class="label-dp">Dibayar sekarang</p>

                                            <span class="segel-dp">
                                                <span class="segel-dp-label">DP</span>
                                                <span class="segel-dp-angka">{{ $biaya['dp_persen'] }}%</span>
                                            </span>
                                        </div>

                                        <p class="angka-dp">{{ $biaya['dp_teks'] }}</p>
                                        <p class="catatan-dp">Paling lambat {{ $biaya['dp_batas_jam'] }} jam setelah
                                            pendaftaran ini.</p>
                                    </div>

                                    <dl class="mt-3">
                                        {{-- Keterangannya menempel pada LABEL, bukan pada angkanya.

                                             Di bawah angka yang rata kanan, keterangan sepanjang ini
                                             pecah dua baris rata kanan juga di layar ponsel, dan
                                             barisnya jadi terbaca seperti tiga angka bertumpuk. --}}
                                        <div class="baris-biaya">
                                            <dt>Total biaya
                                                <span class="ket-biaya">{{ $biaya['satuan_teks'] }} ×
                                                    {{ $biaya['orang'] }} orang</span>
                                            </dt>
                                            <dd>{{ $biaya['total_teks'] }}</dd>
                                        </div>

                                        {{-- Sisa pelunasan DISEBUT, tidak dibiarkan dihitung sendiri.
                                             Ia angka kedua yang paling ditanyakan lewat WhatsApp, dan
                                             satu-satunya yang punya tenggat selain DP. --}}
                                        <div class="baris-biaya">
                                            <dt>Sisa pelunasan
                                                <span class="ket-biaya">paling lambat H-{{ $biaya['pelunasan_hari'] }}
                                                    sebelum berangkat</span>
                                            </dt>
                                            <dd>{{ $biaya['sisa_teks'] }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            {{-- Nama tiap peserta ditulis sejak awal.

                                 Riwayat kesehatan diisi per orang, dan tanpa daftar ini
                                 identitas peserta lain baru diketahui setelah mereka
                                 mengisi formulir itu — terlalu terlambat untuk menyiapkan
                                 kursi, kamar, dan konsumsi. --}}
                            </div>

                            <div class="panel-orcha reveal space-y-6">
                                <div class="langkah-daftar">
                                    <span class="langkah-nomor">3</span>
                                    <div>
                                        <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Langkah 3
                                            dari 3</p>
                                        <h2 class="mt-2 judul-panel-kecil">Nama peserta.</h2>
                                        <p class="mt-1.5 text-sm text-slate-500">Nama tiap orang yang ikut, sesuai
                                            kartu identitas.</p>
                                    </div>
                                </div>

                            <div>
                                {{-- Label lama berbunyi "Peserta & titik jemput" dan berdiri
                                     tepat di bawah judul panel "Nama peserta." — dua baris
                                     yang mengatakan hal yang sama, dan yang pertama menyebut
                                     titik jemput yang belum tentu ada pilihannya di trip ini.
                                     Penanda wajibnya pindah ke kalimat pertama supaya tidak
                                     ikut hilang bersama labelnya.

                                     Keterangannya juga dipecah jadi DUA kalimat pendek, bukan
                                     satu paragraf tiga baris. Yang kedua menjawab "kenapa
                                     harus menyebut laki-laki/perempuan" — pertanyaan yang
                                     pasti muncul, dan kalau tidak dijawab di tempatnya akan
                                     ditanyakan lewat WhatsApp satu per satu. --}}
                                <p class="mb-1 text-sm font-semibold text-orcha-navy">
                                    Peserta pertama adalah Anda sebagai pemesan. <x-wajib />
                                </p>
                                <p class="mb-3 text-sm text-slate-500">
                                    Jenis kelamin dipakai untuk membagi kamar — laki-laki dengan laki-laki,
                                    perempuan dengan perempuan.
                                </p>

                                <div class="space-y-3">
                                    @foreach ($peserta as $urutan => $satu)
                                        <div class="flex items-start gap-3" wire:key="peserta-{{ $urutan }}">
                                            <span
                                                class="flex items-center justify-center w-8 h-8 mt-2 text-sm font-bold rounded-full shrink-0 bg-orcha-foam text-orcha-navy">
                                                {{ $urutan + 1 }}
                                            </span>

                                            <div class="flex-1 grid gap-2 sm:grid-cols-{{ count($titikJemputPilihan) > 1 ? '3' : '2' }}">
                                                <div class="{{ count($titikJemputPilihan) > 1 ? '' : 'sm:col-span-1' }}">
                                                    <input type="text" maxlength="120" required
                                                        wire:model="peserta.{{ $urutan }}.nama"
                                                        placeholder="{{ $urutan === 0 ? 'Nama Anda (pemesan)' : 'Nama peserta ke-' . ($urutan + 1) }}"
                                                        class="isian-orcha @error('peserta.' . $urutan . '.nama') isian-galat @enderror">
                                                    @error('peserta.' . $urutan . '.nama')
                                                        <p class="galat-orcha">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                {{-- Jenis kelamin ditanya DI SINI, bukan di formulir
                                                     kesehatan yang diisi belakangan.

                                                     Kamar hotel dibagi laki-laki dengan laki-laki dan
                                                     perempuan dengan perempuan, dan pembagian itu
                                                     dikerjakan jauh sebelum formulir kesehatan masuk.
                                                     Tanpa medan ini, admin mengetiknya sendiri untuk
                                                     seluruh rombongan — enam puluh kali untuk satu
                                                     study tour. --}}
                                                <div>
                                                    <select required wire:model="peserta.{{ $urutan }}.jenis_kelamin"
                                                        class="isian-orcha @error('peserta.' . $urutan . '.jenis_kelamin') isian-galat @enderror">
                                                        <option value="">— L / P —</option>
                                                        <option value="Laki-laki">Laki-laki</option>
                                                        <option value="Perempuan">Perempuan</option>
                                                    </select>
                                                    @error('peserta.' . $urutan . '.jenis_kelamin')
                                                        <p class="galat-orcha">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                {{-- Titik jemput ditanya PER PESERTA: satu rombongan sering
                                                     berangkat dari kota berbeda, dan sopir perlu tahu siapa
                                                     menunggu di mana. --}}
                                                @if (count($titikJemputPilihan) > 1)
                                                    <div>
                                                        <select required wire:model="peserta.{{ $urutan }}.titik_jemput"
                                                            class="isian-orcha @error('peserta.' . $urutan . '.titik_jemput') isian-galat @enderror">
                                                            <option value="">— Pilih titik jemput —</option>
                                                            @foreach ($titikJemputPilihan as $titik)
                                                                <option value="{{ $titik }}">{{ $titik }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('peserta.' . $urutan . '.titik_jemput')
                                                            <p class="galat-orcha">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if (count($titikJemputPilihan) > 1)
                                    <p class="mt-3 text-sm text-slate-500">
                                        Tiap peserta boleh dijemput di titik yang berbeda. Butuh titik di luar
                                        daftar? Tulis di catatan tambahan — kami kabari apakah bisa dilayani.
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label for="d-catatan" class="label-orcha">Catatan tambahan <span
                                        class="font-normal text-slate-400">(opsional)</span></label>
                                <textarea id="d-catatan" rows="4" wire:model="catatan" maxlength="1000"
                                    placeholder="Misalnya ada peserta anak-anak, lansia, atau permintaan khusus."
                                    class="isian-orcha @error('catatan') isian-galat @enderror"></textarea>
                                @error('catatan')
                                    <p class="galat-orcha">{{ $message }}</p>
                                @enderror
                            </div>

                            </div>

                            {{-- Persetujuan dan tombol kirim berdiri di panelnya SENDIRI.

                                 Sebelumnya keduanya menempel di dasar kartu panjang yang
                                 sama, jadi tidak ada apa pun yang menandai bahwa di sinilah
                                 pengisian berakhir dan keputusan dimulai. Dipisah, panel ini
                                 terbaca sebagai gerbang terakhir — dan centangnya tidak lagi
                                 terlewat karena mengira ia bagian dari isian di atasnya. --}}
                            <div class="panel-orcha reveal space-y-5">
                            <label class="flex items-start gap-3 text-base cursor-pointer text-slate-600">
                                <input type="checkbox" wire:model="setuju" required
                                    class="mt-0.5 w-6 h-6 rounded border-orcha-foam text-orcha-ocean focus:ring-orcha-sky shrink-0">
                                <span>
                                    <x-wajib /> Saya menyetujui
                                    <a href="{{ route('syarat-ketentuan') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">syarat &amp;
                                        ketentuan</a>,
                                    <a href="{{ route('ketentuan-pembayaran') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">ketentuan pembayaran</a>,
                                    dan
                                    <a href="{{ route('kebijakan-pengembalian') }}"
                                        class="font-semibold text-orcha-ocean hover:underline">kebijakan
                                        pengembalian dana</a>.
                                </span>
                            </label>
                            @error('setuju')
                                <p class="galat-orcha">{{ $message }}</p>
                            @enderror

                            <p class="text-sm text-slate-500">
                                Kolom bertanda <x-wajib /> wajib diisi. Yang bertulis
                                <span class="font-semibold">(opsional)</span> boleh dikosongkan.
                            </p>

                            {{-- btn-orcha-sun, tombol ajakan utama yang dipakai beranda —
                                 bukan btn-orcha-primary yang rata. Ini satu-satunya tombol
                                 yang menyelesaikan pekerjaan di halaman ini. --}}
                            <button type="submit" class="w-full btn-orcha btn-orcha-sun !py-4 !text-base"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="daftar">Kirim Pendaftaran</span>
                                <span wire:loading wire:target="daftar">Mengirim…</span>
                            </button>

                            <p class="text-sm text-center text-slate-500">
                                Belum ada pembayaran di langkah ini. Kami balas lewat WhatsApp lebih dulu.
                            </p>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Sisi kanan --}}
                <aside class="lg:col-span-4">
                    {{-- Ikut menggulung bersama halaman, berhenti di atas layar --}}
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <x-peringatan-pembayaran />

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Setelah mendaftar</h2>
                            <ol class="mt-4 space-y-4">
                                @foreach ([['Konfirmasi kursi', 'Kami cek ketersediaan lalu menghubungi Anda lewat WhatsApp.'], ['Riwayat kesehatan', 'Tiap peserta mengisi formulir kesehatan memakai kode pendaftaran.'], ['Uang muka & pelunasan', "Kursi dikunci setelah DP $dp% masuk. Pelunasan paling lambat H-$pelunasan sebelum keberangkatan."], ['Berangkat', 'Jam kumpul di titik jemput dikirim H-1 sebelum keberangkatan.']] as $i => [$judul, $isi])
                                    <li class="flex gap-3">
                                        {{-- .ubin-toska, bukan gradasi sky->ocean.

                                             Dua nama itu peninggalan palet BIRU. Palet
                                             sekarang menimpa keduanya dengan warna hangat —
                                             sky jadi emas pucat, ocean jadi hijau keabuan —
                                             jadi gradasinya membentang dari emas ke abu dan
                                             melewati cokelat kusam di tengah. Kelasnya tetap
                                             ada dan tetap menghasilkan warna, jadi tidak ada
                                             satu pun galat yang menyebutnya. --}}
                                        <span
                                            class="flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full shrink-0 ubin-toska">
                                            {{ $i + 1 }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-bold text-orcha-navy">{{ $judul }}</p>
                                            <p class="mt-0.5 text-sm text-slate-600">{{ $isi }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="p-6 card-orcha sm:p-7">
                            <h2 class="text-lg font-bold font-heading text-orcha-navy">Sudah pernah daftar?</h2>
                            <p class="mt-2 text-sm text-slate-600">Kalau Anda tinggal mengisi data kesehatan peserta,
                                langsung saja ke formulirnya.</p>
                            <a href="{{ route('riwayat-kesehatan') }}"
                                class="w-full mt-4 btn-orcha btn-orcha-outline !py-2.5 !text-sm">
                                <x-heroicon-o-heart class="w-4 h-4" />
                                Formulir Riwayat Kesehatan
                            </a>
                        </div>

                        <div class="p-6 card-orcha sm:p-7 bg-orcha-foam/50">
                            <p class="text-sm text-slate-600">
                                Ingin bertanya dulu sebelum mendaftar?
                            </p>
                            <a href="{{ $wa('Halo Orcha Journey, saya ingin bertanya soal open trip sebelum mendaftar.') }}"
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
    </div>

    <x-skrip-isian />
</div>
