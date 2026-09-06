<?php

namespace App\Models\OpenTrip;

use App\Models\PaketWisata\TravelPackage;
use App\Support\KodePesanan;
use App\Support\RincianBiaya;
use App\Support\Rujukan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PendaftaranOpenTrip extends Model
{
    protected $table = 'tbl_pendaftaran_open_trip';

    protected $fillable = [
        'kode',
        'travel_package_id',
        'nama_paket',
        'nama',
        'whatsapp',
        'email',
        'jumlah_peserta',
        'pendamping_gratis',
        'biaya_tetap',
        'harga_jual',
        'harga_modal',
        'potongan_promo',
        'daftar_peserta',
        'riwayat_penggantian',
        'surat_penggantian',
        'surat_penggantian_pada',
        'tanggal_berangkat',
        'titik_jemput',
        'catatan',
        'status',
        'pengingat_pelunasan_pada',
        'briefing_pada',
        'kode_rujukan',
        'potongan_rujukan',
        'imbalan_rujukan',
        'imbalan_dibayar_pada',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'jumlah_peserta' => 'integer',
        'pendamping_gratis' => 'integer',
        'biaya_tetap' => 'integer',
        'harga_jual' => 'integer',
        'potongan_promo' => 'integer',
        'harga_modal' => 'integer',
        'daftar_peserta' => 'array',
        'riwayat_penggantian' => 'array',
        'surat_penggantian_pada' => 'datetime',
        'pengingat_pelunasan_pada' => 'datetime',
        'briefing_pada' => 'datetime',
        'potongan_rujukan' => 'integer',
        'imbalan_rujukan' => 'integer',
        'imbalan_dibayar_pada' => 'datetime',
    ];

    /**
     * Biaya tetap dimulai dari nol, bukan null — bahkan sebelum barisnya
     * tersimpan.
     *
     * Basis data sudah memberi nilai bawaan, tetapi model yang baru dibuat
     * belum membacanya kembali. Tanpa baris ini, kode yang memeriksa
     * keuntungan sebelum refresh melihat null dan memperlakukan biayanya
     * sebagai "belum diketahui" — padahal rombongan tanpa carter memang tidak
     * punya biaya tetap, dan itu keadaan yang diketahui.
     */
    protected $attributes = [
        'biaya_tetap' => 0,
    ];

    /**
     * Kode pendaftaran dibuat otomatis, misalnya OT-2608-A7K3.
     * Kode inilah yang dipakai peserta untuk mengisi form riwayat kesehatan.
     */
    protected static function booted(): void
    {
        static::creating(function (self $pendaftaran) {
            if (blank($pendaftaran->kode)) {
                do {
                    $kode = KodePesanan::untuk('OT');
                } while (static::where('kode', $kode)->exists());

                $pendaftaran->kode = $kode;
            }

            // Harga jual dan modal dibekukan di sini, bukan dibaca ulang dari
            // paket saat laporan dibuka. Modal paket berubah sepanjang tahun
            // mengikuti harga hotel dan sewa bus; tanpa jejak ini, keuntungan
            // bulan lalu ikut berubah tiap kali admin merevisi modal hari ini.
            $paket = $pendaftaran->paket()->first();

            if ($paket) {
                $pendaftaran->harga_jual ??= (int) $paket->price;
                $pendaftaran->harga_modal ??= $paket->harga_modal;

                /*
                 | Potongan promonya ikut dibekukan, dengan alasan yang sama.
                 |
                 | Tingkat promo berubah sepanjang tahun; tanpa dibekukan,
                 | laporan bulan lalu ikut berubah tiap admin menyunting angka
                 | promo hari ini — dan yang membacanya tidak punya cara tahu
                 | kenapa angkanya bergeser.
                 */
                $pendaftaran->potongan_promo ??= (int) (
                    RincianBiaya::untuk($paket, (int) $pendaftaran->jumlah_peserta)['promo_potongan'] ?? 0
                );
            }

            /*
             | Kode rujukan diperiksa DI SINI, sekali, saat pendaftarannya
             | disimpan.
             |
             | Inilah satu-satunya tempat nomor WhatsApp pendaftarnya
             | diketahui, jadi inilah satu-satunya tempat penjagaan "kode milik
             | sendiri" bisa benar-benar berlaku. Halaman yang menampilkan
             | angkanya boleh memeriksa tanpa nomor — yang ditampilkan sekadar
             | perkiraan; yang mengikat angkanya baris ini.
             |
             | Kode yang ditolak DIBUANG, bukan disimpan dengan potongan nol.
             | Menyimpannya berarti daftar rujukan memuat kode-kode yang tidak
             | pernah sah, dan yang membaca laporan komisi nanti menghitungnya.
             */
            $periksa = Rujukan::periksa($pendaftaran->kode_rujukan, $pendaftaran->whatsapp);

            if (! $periksa['sah']) {
                $pendaftaran->kode_rujukan = null;
                $pendaftaran->potongan_rujukan = 0;
                $pendaftaran->imbalan_rujukan = 0;

                return;
            }

            // Bentuk kodenya diseragamkan ke yang tersimpan — pendaftar
            // mengetik "budi-k7qm" dan laporan komisi mengelompokkannya
            // terpisah dari "BUDI-K7QM" kalau tidak diseragamkan.
            $pendaftaran->kode_rujukan = $periksa['kode']->kode;

            /*
             | Keduanya dibekukan, alasannya sama dengan harga dan promo.
             |
             | Imbalan rujukan berubah sepanjang tahun. Tanpa dibekukan, komisi
             | yang BELUM DIBAYARKAN ikut berubah setiap kali angkanya
             | disunting hari ini — dan yang menagih nanti orang yang mengingat
             | angka lain daripada yang tertulis di layar kita.
             */
            $pendaftaran->potongan_rujukan ??= Rujukan::potongan();
            $pendaftaran->imbalan_rujukan ??= Rujukan::imbalan();
        });
    }

    /**
     * Pendaftaran yang imbalan rujukannya BENAR-BENAR jadi hak pemilik kode.
     *
     * Syaratnya lunas, bukan sekadar terdaftar.
     *
     * Sebelum ini imbalannya terhitung terutang sejak orangnya mengisi
     * formulir. Akibatnya laporan komisi memuat uang yang belum pernah masuk:
     * orang yang mendaftar lalu tidak pernah membayar, dan orang yang
     * membatalkan, keduanya tetap menambah tagihan komisi. Yang menagihnya
     * kemudian pemilik kode — dengan angka yang kita sendiri yang menampilkan.
     *
     * Uang muka pun belum cukup. DP bisa hangus, pesanannya bisa batal, dan
     * kursinya bisa dilepas karena pelunasannya tidak pernah datang — dan
     * komisi yang sudah terlanjur dibayarkan tidak bisa ditarik kembali.
     */
    public function scopeImbalanBerhak($query)
    {
        return $query->whereNotNull('kode_rujukan')->where('status', 'lunas');
    }

    /** Sudah berhak, tetapi uangnya belum dikirimkan ke pemilik kode. */
    public function scopeImbalanBelumDibayar($query)
    {
        return $query->imbalanBerhak()->whereNull('imbalan_dibayar_pada');
    }

    /**
     * Sudah memakai kode, tetapi belum lunas — jadi belum jadi hak siapa pun.
     *
     * Ditampilkan terpisah, bukan disembunyikan: pemilik kode yang bertanya
     * "kenapa komisi saya belum muncul" perlu dijawab dengan angka, bukan
     * dengan keterangan bahwa datanya tidak ada.
     */
    public function scopeImbalanMenunggu($query)
    {
        return $query->whereNotNull('kode_rujukan')
            ->whereNotIn('status', ['lunas', 'batal']);
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    /**
     * Pendaftaran yang uangnya belum lengkap sementara tanggalnya sudah dekat.
     *
     * Inilah daftar yang perlu dikejar orang. Pengingat otomatis mengurus yang
     * normal — yang membaca suratnya lalu mentransfer; yang tersisa di sini
     * justru yang TIDAK bergerak setelah dikirimi, dan itu hanya bisa
     * diselesaikan lewat telepon.
     *
     * Tanpa saringan ini admin membuka pendaftaran satu per satu dan
     * menghitung tanggalnya di kepala — pekerjaan yang cukup melelahkan
     * sehingga tidak pernah benar-benar dikerjakan, dan uangnya menguap tanpa
     * ada yang menyadarinya.
     *
     * Yang belum membayar sepeser pun TIDAK termasuk: kursinya sudah diurus
     * LepaskanKursiTertahan, dan mencampurnya di sini membuat daftar tagihan
     * bercampur dengan daftar pemesanan yang sudah setengah mati.
     */
    public function scopePerluDitagih($query, ?int $dalamHari = null)
    {
        $dalamHari ??= (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5)
            + (int) config('orcha.pengingat.pelunasan_hari_sebelum_batas', 3);

        return $query
            ->where('status', 'dp_masuk')
            ->whereNotNull('tanggal_berangkat')
            // Yang sudah berangkat tidak lagi bisa ditagih lewat jalur ini —
            // itu urusan penagihan biasa, bukan penyelamatan kursi.
            ->whereDate('tanggal_berangkat', '>=', now()->toDateString())
            ->whereDate('tanggal_berangkat', '<=', now()->addDays($dalamHari)->toDateString());
    }

    /**
     * Berapa hari lagi berangkat; negatif berarti sudah lewat.
     *
     * Dihitung dari awal hari, bukan dari jam sekarang. "Berangkat besok" pada
     * pukul sebelas malam tetap satu hari, bukan nol koma sekian yang
     * dibulatkan jadi nol.
     */
    public function getHariKeBerangkatAttribute(): ?int
    {
        return $this->tanggal_berangkat
            ? now()->startOfDay()->diffInDays($this->tanggal_berangkat->startOfDay(), false)
            : null;
    }

    public function riwayatKesehatan(): HasMany
    {
        return $this->hasMany(RiwayatKesehatan::class, 'kode_pendaftaran', 'kode');
    }

    /**
     * Daftar peserta dalam bentuk seragam: nama + titik jemputnya.
     *
     * Data lama menyimpan nama saja sebagai deretan teks. Diterjemahkan di
     * sini supaya seluruh aplikasi cukup mengenal satu bentuk, tanpa perlu
     * mengubah baris yang sudah telanjur tersimpan.
     */
    public function getPesertaAttribute(): array
    {
        return collect($this->daftar_peserta ?? [])
            ->map(function ($baris) {
                if (is_array($baris)) {
                    return [
                        'nama' => trim($baris['nama'] ?? ''),
                        'titik_jemput' => trim($baris['titik_jemput'] ?? '') ?: $this->titik_jemput,
                        /*
                         | Bus dan kamar boleh kosong, dan kosongnya berarti
                         | "belum dibagi" — bukan "tidak ikut".
                         |
                         | Dibedakan karena pembagian bus baru dikerjakan
                         | beberapa hari sebelum berangkat, jauh setelah
                         | namanya masuk. Menganggap yang kosong sebagai
                         | kesalahan membuat layar penuh peringatan merah
                         | selama berminggu-minggu, dan peringatan yang selalu
                         | ada akhirnya tidak dibaca.
                         */
                        'bus' => trim($baris['bus'] ?? '') ?: null,
                        'kamar' => trim($baris['kamar'] ?? '') ?: null,
                    ];
                }

                return [
                    'nama' => trim((string) $baris),
                    'titik_jemput' => $this->titik_jemput,
                    'bus' => null,
                    'kamar' => null,
                ];
            })
            ->filter(fn ($baris) => $baris['nama'] !== '')
            ->values()
            ->all();
    }

    /**
     * Titik jemput yang benar-benar dipakai rombongan ini, beserta siapa saja
     * yang menunggu di sana — inilah yang dibaca sopir pada hari keberangkatan.
     *
     * @return array<string, array<int, string>>
     */
    public function getJemputPerTitikAttribute(): array
    {
        return collect($this->peserta)
            ->filter(fn ($p) => filled($p['titik_jemput']))
            ->groupBy('titik_jemput')
            ->map(fn ($orang) => $orang->pluck('nama')->all())
            ->all();
    }

    /**
     * Peserta dikelompokkan menurut bus, untuk dibacakan saat berangkat.
     *
     * Yang membacanya di lapangan perlu satu daftar per bus, bukan daftar
     * seluruh rombongan yang harus disaring sendiri sambil berdiri di
     * parkiran.
     *
     * Yang belum dibagi dikumpulkan dalam kelompok bernama kosong, bukan
     * dibuang — rombongan yang setengah dibagi adalah keadaan yang paling
     * sering terjadi, dan menyembunyikan sisanya membuat orang mengira
     * pembagiannya sudah selesai.
     *
     * @return array<int, array{kelompok: string, anggota: array<int, string>}>
     */
    public function getBusPerKelompokAttribute(): array
    {
        return self::kelompokkan($this->peserta, 'bus');
    }

    /**
     * Peserta dikelompokkan menurut kamar, untuk diserahkan ke hotel.
     *
     * @return array<int, array{kelompok: string, anggota: array<int, string>}>
     */
    public function getKamarPerKelompokAttribute(): array
    {
        return self::kelompokkan($this->peserta, 'kamar');
    }

    /**
     * BERUPA DAFTAR PASANGAN, bukan larik berkunci — dan itu bukan selera.
     *
     * Nomor kamar selalu angka: "201", "202". PHP memaksa kunci larik yang
     * berupa angka jadi integer, dan larik ber-kunci-integer yang dikirim
     * lewat JSON pulang sebagai daftar biasa — kunci "201" berubah jadi
     * indeks 0. Daftar kamar yang diserahkan ke hotel lalu tertulis "Kamar 0",
     * dan tidak ada satu pun galat yang menjelaskannya.
     *
     * Ditemukan lewat uji, bukan lewat keluhan resepsionis hotel.
     *
     * @param  array<int, array<string, mixed>>  $peserta
     * @return array<int, array{kelompok: string, anggota: array<int, string>}>
     */
    private static function kelompokkan(array $peserta, string $kunci): array
    {
        return collect($peserta)
            ->groupBy(fn (array $satu) => (string) ($satu[$kunci] ?? ''))
            ->map(fn ($orang, $nama) => [
                'kelompok' => (string) $nama,
                'anggota' => $orang->pluck('nama')->all(),
            ])
            ->values()
            ->all();
    }

    public function konfirmasiPembayaran()
    {
        return $this->hasMany(KonfirmasiPembayaran::class, 'kode', 'kode');
    }

    /**
     * Rencana angsuran yang sedang berlaku, bila ada.
     *
     * Ditautkan lewat kode, sama seperti konfirmasi pembayaran — rencananya
     * milik PESANAN, dan pesanan di sini bisa berupa pendaftaran maupun
     * penyewaan kendaraan. Kodenya berbeda awalan, jadi tidak ada yang saling
     * tertukar.
     *
     * Yang dibatalkan tidak ikut. Ia disimpan untuk menjawab "dulu dijanjikan
     * apa", bukan untuk digambar sebagai jadwal yang masih berjalan.
     */
    public function angsuranAktif(): HasOne
    {
        return $this->hasOne(Angsuran::class, 'kode', 'kode')
            ->whereNull('dibatalkan_pada')
            ->latestOfMany();
    }

    /**
     * Berapa peserta yang riwayat kesehatannya sudah masuk.
     *
     * Dipakai penanda kelengkapan: kesehatan diisi per orang, dan yang belum
     * mengisi harus terlihat jauh sebelum hari keberangkatan — bukan diketahui
     * saat rombongan sudah berkumpul.
     */
    public function getKesehatanTerisiAttribute(): int
    {
        return $this->riwayatKesehatan()->count();
    }

    public function getKesehatanLengkapAttribute(): bool
    {
        return $this->kesehatan_terisi >= $this->jumlah_peserta;
    }

    /** Nama peserta yang belum mengisi riwayat kesehatan. */
    public function getPesertaBelumIsiAttribute(): array
    {
        $sudah = $this->riwayatKesehatan()->pluck('nama_peserta')
            ->map(fn ($nama) => mb_strtolower(trim($nama)))
            ->all();

        return collect($this->peserta)
            ->pluck('nama')
            ->filter(fn ($nama) => ! in_array(mb_strtolower(trim($nama)), $sudah, true))
            ->values()
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pendaftaran')[$this->status] ?? 'Baru';
    }

    /* ------------------------------- KEUNTUNGAN ------------------------------- */

    /**
     * Harga jual per orang yang berlaku untuk pendaftaran ini.
     *
     * Yang dipakai jejak beku miliknya sendiri. Pendaftaran yang masuk sebelum
     * pembekuan ini ada tidak punya jejak, jadi meminjam angka paketnya — itu
     * keterangan terbaik yang tersedia, dan lebih berguna daripada laporan
     * yang kosong untuk seluruh riwayat sebelumnya.
     */
    public function getJualSatuanAttribute(): ?int
    {
        return $this->harga_jual ?? ($this->paket?->price !== null ? (int) $this->paket->price : null);
    }

    /** Modal per orang; null berarti belum pernah dihitung, bukan nol. */
    public function getModalSatuanAttribute(): ?int
    {
        return $this->harga_modal ?? $this->paket?->harga_modal;
    }

    public function getMarginSatuanAttribute(): ?int
    {
        return $this->modal_satuan === null || $this->jual_satuan === null
            ? null
            : $this->jual_satuan - $this->modal_satuan;
    }

    /**
     * Uang masuk dari pendaftaran ini bila seluruhnya dibayar.
     *
     * Potongan promonya DIKURANGKAN. Tanpa itu laporan menghitung harga penuh
     * sementara pelanggan ditagih setelah potongan — dan keuntungan yang
     * dipakai mengambil keputusan jadi lebih besar daripada uang yang
     * benar-benar masuk.
     */
    /**
     * Berapa orang yang BENAR-BENAR DITAGIH.
     *
     * Berbeda dari jumlah_peserta, dan bedanya nyata: guru pendamping study
     * tour ikut berangkat, menempati kursi bus, masuk manifes, dan mengisi
     * riwayat kesehatan — tetapi tidak dibayar sekolahnya.
     *
     * Sebelum kolom ini ada, satu-satunya cara menyatakannya adalah menurunkan
     * jumlah peserta, dan gurunya lalu hilang dari keempat hal di atas.
     * Padahal justru dialah yang paling perlu punya kontak darurat tercatat.
     *
     * Tidak pernah nol: rombongan yang seluruhnya gratis tetap satu baris
     * tagihan, dan pembagian apa pun terhadap nol akan meledak di tempat lain.
     */
    public function getPesertaDibayarAttribute(): int
    {
        return max(1, (int) $this->jumlah_peserta - (int) ($this->pendamping_gratis ?? 0));
    }

    public function getOmzetAttribute(): int
    {
        $penuh = (int) ($this->jual_satuan ?? 0) * $this->peserta_dibayar;

        /*
         | Potongan rujukan ikut dikurangkan, sama seperti potongan promo.
         |
         | Uang yang tidak pernah masuk tidak boleh tercatat sebagai omzet.
         | Kesalahan yang sama pada promo rombongan dulu melaporkan keuntungan
         | lima puluh persen lebih besar daripada kenyataannya — dan angka itu
         | dipakai memutuskan paket mana yang layak dijalankan lagi.
         |
         | Imbalan untuk pemilik kode TIDAK dikurangkan di sini: ia biaya, dan
         | biaya masuk di sisi lain laporan. Menguranginya dari omzet membuat
         | omzet tidak lagi sama dengan yang ditagihkan ke pelanggan.
         */
        return max(0, $penuh
            - (int) ($this->potongan_promo ?? 0)
            - (int) ($this->potongan_rujukan ?? 0));
    }

    /**
     * Seluruh biaya pendaftaran ini: yang mengikuti kepala, plus yang tidak.
     *
     * Dikalikan jumlah_peserta, BUKAN peserta_dibayar — dan itu bukan
     * kelalaian. Guru pendamping study tour tidak membayar, tetapi ia tetap
     * menempati kursi bus, makan siang, dan kamar hotel. Biayanya nyata; yang
     * tidak ada cuma pendapatannya.
     *
     * Biaya tetap ditambahkan UTUH, tanpa dikalikan apa pun. Itulah gunanya:
     * carter bus tidak jadi lebih mahal karena penumpangnya bertambah satu.
     */
    public function getModalTotalAttribute(): ?int
    {
        if ($this->modal_satuan === null) {
            return null;
        }

        return $this->modal_satuan * max(1, (int) $this->jumlah_peserta)
            + (int) ($this->biaya_tetap ?? 0);
    }

    /**
     * Modal sesungguhnya per kepala, biaya tetap sudah dibagi rata.
     *
     * Berbeda dari modal_satuan, dan bedanya justru yang perlu dibaca admin:
     * modal_satuan adalah angka yang ia ketik, sedangkan ini angka yang
     * menentukan apakah harganya masuk akal. Rombongan bertiga dengan carter
     * Rp 3.000.000 menanggung Rp 1.000.000 per kepala di luar biaya per
     * orangnya — dan itu tidak terlihat di mana pun sampai dihitung di sini.
     */
    public function getModalPerKepalaAttribute(): ?int
    {
        return $this->modal_total === null
            ? null
            : (int) round($this->modal_total / max(1, (int) $this->jumlah_peserta));
    }

    /**
     * Keuntungan pendaftaran ini: uang masuk dikurangi modalnya.
     *
     * Dihitung dari OMZET, bukan dari margin per orang dikalikan jumlah
     * peserta. Keduanya sama selama tidak ada promo — tetapi begitu ada,
     * margin per orang tidak tahu apa-apa soal potongan yang diberikan, dan
     * keuntungan yang dilaporkan jadi lebih besar daripada uang yang
     * benar-benar masuk. Terukur pada rombongan sepuluh orang bertingkat
     * "gratis 1": Rp 4.300.000 dilaporkan, Rp 2.870.000 kenyataannya.
     */
    public function getKeuntunganAttribute(): ?int
    {
        return $this->modal_total === null
            ? null
            : $this->omzet - $this->modal_total;
    }

    /**
     * Pendaftaran yang keuntungannya sudah benar-benar didapat.
     *
     * Hanya yang lunas. DP masuk berarti uangnya baru sebagian dan pesanannya
     * masih bisa batal — kalau ikut dihitung, laporan keuntungan menggelembung
     * oleh pesanan yang belum tentu jadi, dan itulah angka yang paling
     * berbahaya untuk dijadikan dasar keputusan.
     */
    public function scopeSudahUntung($query)
    {
        return $query->where('status', 'lunas');
    }

    /** Pesanan yang uangnya belum utuh tapi masih hidup — bukan keuntungan. */
    public function scopeMasihPotensi($query)
    {
        return $query->whereNotIn('status', ['lunas', 'batal']);
    }
}
