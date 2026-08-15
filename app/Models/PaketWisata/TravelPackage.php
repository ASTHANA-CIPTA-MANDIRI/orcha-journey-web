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
        'price',
        'original_price',
        'discount_percentage',
        'catatan_promo',
        'is_best_choice',
        'destination_list',
        'fasilitas',
        'itinerary',
        'foto',
    ];

    protected $casts = [
        'destination_list' => 'array',
        'fasilitas' => 'array',
        'itinerary' => 'array',
        'is_best_choice' => 'boolean',
        'tanggal_berangkat' => 'date',
        'tanggal_pulang' => 'date',
        'minimal_peserta' => 'integer',
        'tayang_mulai' => 'datetime',
        'tayang_sampai' => 'datetime',
        'berakhir_otomatis' => 'boolean',
    ];

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
            : asset('images/pantai-wide.jpg');
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

    public function scopeOfCategory($query, ?string $category)
    {
        return $query->when($category, fn ($q) => $q->where('category', $category));
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranOpenTrip::class, 'travel_package_id');
    }
}
