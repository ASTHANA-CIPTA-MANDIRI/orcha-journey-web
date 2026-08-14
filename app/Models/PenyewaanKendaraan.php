<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pemesanan sewa kendaraan yang masuk lewat formulir publik.
 */
class PenyewaanKendaraan extends Model
{
    protected $table = 'tbl_penyewaan_kendaraan';

    protected $fillable = [
        'kode',
        'car_id',
        'nama_kendaraan',
        'nama',
        'whatsapp',
        'email',
        'transmisi',
        'satuan',
        'durasi',
        'tanggal_mulai',
        'jam_mulai',
        'dengan_sopir',
        'lokasi_antar',
        'estimasi_biaya',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'dengan_sopir' => 'boolean',
        'durasi' => 'integer',
        'estimasi_biaya' => 'integer',
    ];

    /**
     * Kode pemesanan, misalnya SK-1408-A7K3.
     */
    protected static function booted(): void
    {
        static::creating(function (self $sewa) {
            if (blank($sewa->kode)) {
                do {
                    $kode = 'SK-'.now()->format('dm').'-'.Str::upper(Str::random(4));
                } while (static::where('kode', $kode)->exists());

                $sewa->kode = $kode;
            }
        });
    }

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function getSatuanLabelAttribute(): string
    {
        return config('orcha.satuan_sewa')[$this->satuan]['label'] ?? 'Hari';
    }

    public function getDurasiLabelAttribute(): string
    {
        return $this->durasi.' '.($this->satuan === 'jam' ? 'jam' : ($this->satuan === '12jam' ? '× 12 jam' : 'hari'));
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_penyewaan')[$this->status] ?? 'Baru';
    }
}
