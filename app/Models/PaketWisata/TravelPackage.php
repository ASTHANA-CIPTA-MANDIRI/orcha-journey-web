<?php

namespace App\Models\PaketWisata;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TravelPackage extends Model
{
    use HasFactory;

    protected $table = 'tbl_travel_package';

    protected $fillable = [
        'uuid',
        'name',
        'category',
        'status',
        'tayang_mulai',
        'tayang_sampai',
        'berakhir_otomatis',
        'duration',
        'tanggal_berangkat',
        'tanggal_pulang',
        'titik_jemput',
        'minimal_peserta',
        'kuota',
        'price',
        'original_price',
        'harga_modal',
        'discount_percentage',
        'catatan_promo',
        'is_best_choice',
        'destination_list',
        'fasilitas',
        'itinerary',
        'foto',
    ];

    protected $casts = [
        'harga_modal' => 'integer',
        'destination_list' => 'array',
        'fasilitas' => 'array',
        'itinerary' => 'array',
        'is_best_choice' => 'boolean',
        'tanggal_berangkat' => 'date',
        'tanggal_pulang' => 'date',
        'minimal_peserta' => 'integer',
        'kuota' => 'integer',
        'tayang_mulai' => 'datetime',
        'tayang_sampai' => 'datetime',
        'berakhir_otomatis' => 'boolean',
    ];

    /**
     * Modal tidak pernah ikut keluar saat paketnya diubah jadi larik.
     *
     * Halaman publik menyerahkan model paket apa adanya ke Blade, dan sekali
     * ada yang menuliskannya sebagai JSON — komponen Alpine, atribut data,
     * atau tag ld+json — biaya internal Orcha terbaca siapa pun yang membuka
     * kode sumber halaman. API dashboard tetap bisa membacanya karena Resource
     * mengambil kolomnya langsung, bukan lewat toArray().
     *
     * @var list<string>
     */
    protected $hidden = ['harga_modal'];

    /**
     * UUID dibuat otomatis; dipakai sebagai kunci alamat halaman publik.
     */
    protected static function booted(): void
    {
        static::creating(function (self $paket) {
            if (blank($paket->uuid)) {
                $paket->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Sampul paket untuk hero halaman detail. Bila admin belum mengunggah
     * foto, dipakai ilustrasi bawaan agar hero tidak sama untuk semua paket.
     */
    public function getSampulAttribute(): string
    {
        if (filled($this->foto)) {
            return $this->foto;
        }

        $ilustrasi = 'images/destinasi/'.Str::slug($this->name).'.svg';

        return file_exists(public_path($ilustrasi))
            ? asset($ilustrasi)
            : asset('images/pantai-wide.webp');
    }

    /**
     * Label kategori yang tampil di landing page & admin (Open Trip/Private Trip/Study Tour).
     */
    public function getCategoryLabelAttribute(): string
    {
        return config('orcha.kategori_paket')[$this->category] ?? 'Open Trip';
    }

    /**
     * "19 – 21 Oktober 2026" bila tanggal pulangnya ada, "19 Oktober 2026"
     * bila hanya sehari, dan null bila tanggalnya belum ditetapkan.
     */
    public function getJadwalLabelAttribute(): ?string
    {
        if (! $this->tanggal_berangkat) {
            return null;
        }

        if (! $this->tanggal_pulang || $this->tanggal_pulang->isSameDay($this->tanggal_berangkat)) {
            return $this->tanggal_berangkat->translatedFormat('j F Y');
        }

        $samaBulan = $this->tanggal_berangkat->isSameMonth($this->tanggal_pulang)
            && $this->tanggal_berangkat->isSameYear($this->tanggal_pulang);

        return $samaBulan
            ? $this->tanggal_berangkat->translatedFormat('j').' – '.$this->tanggal_pulang->translatedFormat('j F Y')
            : $this->tanggal_berangkat->translatedFormat('j M Y').' – '.$this->tanggal_pulang->translatedFormat('j M Y');
    }

    /**
     * Batas pelunasan: paling lambat H-5 sebelum keberangkatan.
     */
    public function getBatasPelunasanAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->tanggal_berangkat?->copy()->subDays(config('orcha.pembayaran.pelunasan_hari_sebelum'));
    }

    public function getSudahLewatAttribute(): bool
    {
        return $this->tanggal_berangkat !== null && $this->tanggal_berangkat->isPast();
    }

    /**
     * Paket yang boleh tampil di website saat ini.
     *
     * Semua syaratnya dihitung dari tanggal, jadi paket terjadwal muncul dan
     * paket lewat menghilang dengan sendirinya — tanpa cron, tanpa ada yang
     * perlu menekan tombol.
     */
    /**
     * Titik jemput yang ditawarkan paket ini, sebagai daftar.
     *
     * Tersimpan sebagai satu baris teks ("Jogja, Klaten, Surakarta") karena
     * itulah yang enak diketik admin. Yang dipakai formulir adalah daftarnya,
     * supaya peserta memilih — bukan mengetik ulang dan berisiko salah tulis.
     */
    public function getTitikJemputListAttribute(): array
    {
        return collect(preg_split('/[,;\n\/]+/', (string) $this->titik_jemput))
            ->map(fn ($titik) => trim($titik))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Benar bila peserta perlu memilih; satu titik saja tidak perlu ditanya. */
    public function getPunyaPilihanJemputAttribute(): bool
    {
        return count($this->titik_jemput_list) > 1;
    }

    /**
     * Kursi yang sudah terpakai.
     *
     * Dihitung dari jumlah peserta pada pendaftaran yang BELUM batal —
     * termasuk yang statusnya masih "baru". Kursi yang sedang ditunggu
     * pembayarannya tetap kursi yang tidak boleh dijual dua kali; menghitung
     * hanya yang sudah lunas berarti menjual kursi yang sama kepada orang
     * kedua selama orang pertama belum sempat mentransfer.
     */
    public function getKursiTerpakaiAttribute(): int
    {
        /*
         | Hasilnya diingat selama objek ini hidup.
         |
         | Aksesor PHP biasa TIDAK disinggahi Eloquent, jadi tiap kali dibaca ia
         | menembak satu query lagi. Daftar paket di panel admin membaca
         | kursi_terpakai dan sisa_kursi untuk tiap baris — dan sisa_kursi
         | membaca kursi_terpakai lagi — sehingga dua belas paket menghasilkan
         | 51 query. Terukur, bukan dikira.
         |
         | Belum terasa pada dua belas paket; pada enam puluh ia jadi ratusan
         | query untuk satu halaman, di server yang sama yang melayani halaman
         | publik.
         */
        if ($this->kursiTerpakaiTersinggahi !== null) {
            return $this->kursiTerpakaiTersinggahi;
        }

        /*
         | Kalau daftarnya sudah menghitungnya sekaligus lewat scope
         | denganKursiTerpakai(), angkanya dipakai apa adanya — tidak ada query
         | tambahan sama sekali.
         |
         | Singgahan per-objek saja tidak cukup: ia menekan dua query per baris
         | jadi satu, tetapi satu-per-baris tetap tumbuh seiring jumlah paket.
         | Yang benar-benar menyelesaikannya menghitung SEMUANYA dalam satu
         | query, dan itu yang dilakukan scope tersebut.
         */
        if (array_key_exists('kursi_terpakai_agregat', $this->getAttributes())) {
            /*
             | Yang diperiksa KEBERADAAN atributnya, bukan nilainya.
             |
             | withSum mengembalikan null — bukan nol — untuk paket yang belum
             | punya satu pun pendaftaran. Memeriksa `!== null` membuat justru
             | paket-paket kosong itu jatuh kembali ke query per baris, dan
             | perbaikannya cuma bekerja separuh. Terukur: masih 4 query
             | tersisa untuk 5 paket sebelum baris ini dibetulkan.
             */
            return $this->kursiTerpakaiTersinggahi = (int) ($this->getAttributes()['kursi_terpakai_agregat'] ?? 0);
        }

        return $this->kursiTerpakaiTersinggahi = (int) \App\Models\OpenTrip\PendaftaranOpenTrip::query()
            ->where('travel_package_id', $this->id)
            ->where('status', '!=', 'batal')
            ->sum('jumlah_peserta');
    }

    /**
     * Singgahan kursi_terpakai, berumur sepanjang objeknya saja.
     *
     * Sengaja tidak lebih lama dari itu: angka ini berubah tiap kali ada yang
     * mendaftar atau batal, dan singgahan yang bertahan antar permintaan akan
     * membuat pemeriksaan kuota memutuskan dengan angka basi — persis pada
     * keadaan yang paling menentukan, saat kursinya tinggal sedikit.
     */
    protected ?int $kursiTerpakaiTersinggahi = null;

    /**
     * Sisa kursi; null bila kuotanya memang belum ditetapkan.
     *
     * DIPAKAI DI SISI DALAM SAJA — pemeriksaan pendaftaran dan layar admin.
     * Angkanya sengaja tidak pernah ditampilkan di halaman publik: ketersediaan
     * dibicarakan lewat WhatsApp, dan angka di layar yang berbeda dari yang
     * dikatakan tim di percakapan melemahkan keduanya. Pernah ada aksesor
     * khusus untuk mengumumkannya; dibuang atas alasan itu.
     */
    public function getSisaKursiAttribute(): ?int
    {
        if (! $this->kuota) {
            return null;
        }

        return max(0, $this->kuota - $this->kursi_terpakai);
    }

    /**
     * Kursinya habis?
     *
     * Paket tanpa kuota TIDAK pernah penuh — perilakunya persis seperti
     * sebelum kolom ini ada. Itu yang membuat migrasinya aman untuk paket lama
     * yang seluruhnya belum punya angka kuota.
     */
    public function getKursiHabisAttribute(): bool
    {
        return $this->sisa_kursi !== null && $this->sisa_kursi <= 0;
    }

    /**
     * Menghitung kursi terpakai untuk SELURUH baris dalam satu query.
     *
     * Dipakai daftar yang menampilkan banyak paket sekaligus — panel admin.
     * Tanpa ini tiap baris menembak query sendiri, dan halaman daftar tumbuh
     * dari puluhan jadi ratusan query seiring bertambahnya paket.
     */
    public function scopeDenganKursiTerpakai($query)
    {
        return $query->withSum(
            ['pendaftaran as kursi_terpakai_agregat' => fn ($q) => $q->where('status', '!=', 'batal')],
            'jumlah_peserta'
        );
    }

    public function scopeTayang($query)
    {
        $sekarang = now();

        return $query->where('status', 'terbit')
            ->where(fn ($q) => $q->whereNull('tayang_mulai')->orWhere('tayang_mulai', '<=', $sekarang))
            ->where(fn ($q) => $q->whereNull('tayang_sampai')->orWhere('tayang_sampai', '>=', $sekarang))
            ->where(fn ($q) => $q->where('berakhir_otomatis', false)
                ->orWhereNull('tanggal_berangkat')
                // Dibandingkan dengan tanggal BESOK, bukan "> hari ini".
                // MySQL menyimpan kolom ini sebagai DATE, sementara SQLite
                // menyimpannya sebagai teks '2026-08-15 00:00:00' — dan teks
                // itu terhitung lebih besar dari '2026-08-15', sehingga paket
                // yang berangkat hari ini sempat lolos di SQLite tapi tidak di
                // MySQL. Memakai '>= besok' hasilnya sama di keduanya.
                ->orWhere('tanggal_berangkat', '>=', $sekarang->copy()->addDay()->toDateString()));
    }

    /** Apakah paket ini sedang tampil di website sekarang. */
    public function getSedangTayangAttribute(): bool
    {
        return static::whereKey($this->getKey())->tayang()->exists();
    }

    /**
     * Keterangan singkat kenapa paket tampil atau tidak — dipakai lencana di
     * dashboard supaya admin tidak perlu menebak.
     */
    public function getStatusTayangAttribute(): string
    {
        if ($this->status === 'draf') {
            return 'draf';
        }

        if ($this->status === 'arsip') {
            return 'arsip';
        }

        if ($this->tayang_mulai && $this->tayang_mulai->isFuture()) {
            return 'terjadwal';
        }

        if ($this->tayang_sampai && $this->tayang_sampai->isPast()) {
            return 'berakhir';
        }

        // Begitu hari keberangkatan tiba, pendaftaran tutup — paket tidak perlu
        // tampil lagi. Karena itu batasnya tanggal berangkat, bukan pulang.
        //
        // `?? true` penting: pada data yang baru dibuat tanpa menyebut kolom
        // ini, nilainya masih null di memori walau di basis data sudah true.
        // Tanpa itu, lencana bisa menulis "Tayang" untuk paket yang justru
        // sudah disaring keluar oleh scopeTayang().
        if (($this->berakhir_otomatis ?? true) && $this->tanggal_berangkat
            && $this->tanggal_berangkat->startOfDay()->lte(now()->startOfDay())) {
            return 'berakhir';
        }

        return 'tayang';
    }

    public function getStatusTayangLabelAttribute(): string
    {
        return config('orcha.status_tayang')[$this->status_tayang] ?? 'Tayang';
    }

    /**
     * Apakah paket ini benar-benar sedang diskon.
     *
     * Angka persen tersimpan tidak dipercaya sendirian: data lama sempat punya
     * 75% padahal harga jualnya tidak lebih murah dari harga asli.
     */
    public function getAdaDiskonAttribute(): bool
    {
        return $this->original_price > 0 && $this->original_price > $this->price;
    }

    /**
     * Persen diskon yang DIPAJANG — satu-satunya sumber untuk kartu maupun
     * halaman detail, supaya keduanya tidak pernah menyebut angka berbeda.
     *
     * Yang dipakai adalah angka simpanan, karena admin boleh membulatkannya
     * untuk keperluan promo. Kalau kosong, baru dihitung dari selisih harga.
     */
    public function getDiskonTampilAttribute(): int
    {
        if (! $this->ada_diskon) {
            return 0;
        }

        $tersimpan = (int) $this->discount_percentage;

        if ($tersimpan > 0) {
            return $tersimpan;
        }

        return (int) floor((($this->original_price - $this->price) / $this->original_price) * 100);
    }

    /** Rupiah yang dihemat pembeli. */
    public function getHematRupiahAttribute(): int
    {
        return $this->ada_diskon ? (int) ($this->original_price - $this->price) : 0;
    }

    /* ------------------------------- KEUNTUNGAN ------------------------------- */

    /**
     * Modal paket sudah diisi admin?
     *
     * Dibedakan dari modal bernilai nol: nol berarti paket ini memang tidak
     * berbiaya, kosong berarti belum pernah dihitung. Laporan keuntungan
     * memakai pembedaan itu untuk memisahkan "untung nol" dari "belum tahu".
     */
    public function getModalTerisiAttribute(): bool
    {
        return $this->harga_modal !== null;
    }

    /**
     * Untung per peserta: harga jual dikurangi modal, keduanya per orang.
     *
     * null bila modalnya belum diisi. Boleh negatif — paket yang dijual di
     * bawah modal memang rugi, dan menyembunyikannya dengan max(0) hanya
     * membuat laporan terlihat sehat padahal tidak.
     */
    public function getMarginPerOrangAttribute(): ?int
    {
        return $this->modal_terisi ? (int) $this->price - (int) $this->harga_modal : null;
    }

    /** Margin sebagai persen harga jual, satu angka di belakang koma. */
    public function getMarginPersenAttribute(): ?float
    {
        if (! $this->modal_terisi || (int) $this->price <= 0) {
            return null;
        }

        return round($this->margin_per_orang / (int) $this->price * 100, 1);
    }

    public function getMarginPerOrangTeksAttribute(): string
    {
        return $this->modal_terisi
            ? \App\Support\RincianBiaya::rupiah($this->margin_per_orang)
            : 'Belum dihitung';
    }

    public function scopeOfCategory($query, ?string $category)
    {
        return $query->when($category, fn ($q) => $q->where('category', $category));
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranOpenTrip::class, 'travel_package_id');
    }
}
