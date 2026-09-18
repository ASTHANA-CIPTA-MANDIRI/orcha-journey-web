<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu hal yang bisa dibayar dalam sebuah perjalanan, berikut cara
 * menghitungnya.
 *
 * Dirawat admin. Harganya BUKAN harga yang dipakai penawaran — penawaran
 * membekukan salinannya sendiri di RabBiaya saat baris itu ditambahkan.
 * Mengubah harga di sini hanya memengaruhi RAB yang dibuat sesudahnya.
 */
class MasterHarga extends Model
{
    protected $table = 'tbl_master_harga';

    protected $fillable = [
        'kategori', 'nama', 'provinsi', 'daerah', 'destinasi',
        'satuan', 'harga', 'kapasitas', 'otomatis', 'aktif', 'catatan',
    ];

    protected $casts = [
        'harga' => 'integer',
        'kapasitas' => 'integer',
        'otomatis' => 'boolean',
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->nama = trim(preg_replace('/\s+/', ' ', (string) $m->nama));
            $m->provinsi = trim((string) $m->provinsi) ?: null;
            $m->daerah = trim((string) $m->daerah) ?: null;
            $m->destinasi = trim((string) $m->destinasi) ?: null;
        });
    }

    /**
     * Yang berlaku untuk sebuah provinsi: miliknya sendiri DAN yang tanpa
     * provinsi. Asuransi perjalanan tidak berbeda antara Jogja dan Bali, dan
     * memaksa admin mengisinya ulang per provinsi berarti satu harga yang
     * dirawat di sepuluh tempat.
     */
    public function scopeUntukProvinsi(Builder $q, ?string $provinsi): Builder
    {
        return $q->where('aktif', true)->where(
            fn ($s) => $s->whereNull('provinsi')->orWhere('provinsi', $provinsi)
        );
    }

    public function getKategoriLabelAttribute(): string
    {
        return config("orcha.rab.kategori.{$this->kategori}") ?? ucfirst($this->kategori);
    }

    public function getSatuanLabelAttribute(): string
    {
        return config("orcha.rab.satuan.{$this->satuan}.label") ?? $this->satuan;
    }
}
