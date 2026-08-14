<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'tanggal_berangkat',
        'titik_jemput',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'jumlah_peserta' => 'integer',
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
                    $kode = 'OT-'.now()->format('dm').'-'.Str::upper(Str::random(4));
                } while (static::where('kode', $kode)->exists());

                $pendaftaran->kode = $kode;
            }
        });
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function riwayatKesehatan(): HasMany
    {
        return $this->hasMany(RiwayatKesehatan::class, 'kode_pendaftaran', 'kode');
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pendaftaran')[$this->status] ?? 'Baru';
    }
}
