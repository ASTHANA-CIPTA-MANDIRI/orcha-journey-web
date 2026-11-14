<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu foto momen perjalanan pelanggan.
 *
 * Terpisah dari foto destinasi meski keduanya gambar: destinasi menjual
 * TEMPAT, galeri menunjukkan ORANG yang sudah berangkat. Menumpangkannya pada
 * destinasi memaksa admin mengarang destinasi baru hanya untuk memajang foto
 * rombongan.
 */
class Galeri extends Model
{
    protected $table = 'tbl_galeri';

    protected $fillable = ['foto', 'keterangan', 'fokus_x', 'fokus_y', 'urutan', 'tampil'];

    protected $casts = [
        'fokus_x' => 'integer',
        'fokus_y' => 'integer',
        'urutan' => 'integer',
        'tampil' => 'boolean',
    ];

    /**
     * Titik fokus siap pakai untuk object-position CSS.
     *
     * Dirakit di satu tempat supaya beranda dan panel admin tidak pernah
     * menyusunnya sendiri-sendiri lalu berbeda format.
     */
    public function getFokusCssAttribute(): string
    {
        return ($this->fokus_x ?? 50).'% '.($this->fokus_y ?? 50).'%';
    }

    /** Yang dipajang di beranda: yang ditandai tampil, mengikuti urutannya. */
    public function scopeTayang($kueri)
    {
        return $kueri->where('tampil', true)->orderBy('urutan')->orderByDesc('id');
    }
}
