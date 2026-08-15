<?php

namespace App\Models;

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

    public function scopeOfCategory($query, ?string $category)
    {
        return $query->when($category, fn ($q) => $q->where('category', $category));
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranOpenTrip::class, 'travel_package_id');
    }
}
