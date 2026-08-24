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

    protected $fillable = ['foto', 'keterangan', 'urutan', 'tampil'];

    protected $casts = [
        'urutan' => 'integer',
        'tampil' => 'boolean',
    ];

    /** Yang dipajang di beranda: yang ditandai tampil, mengikuti urutannya. */
    public function scopeTayang($kueri)
    {
        return $kueri->where('tampil', true)->orderBy('urutan')->orderByDesc('id');
    }
}
