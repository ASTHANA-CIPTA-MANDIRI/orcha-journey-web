<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationPopuler extends Model
{
    use HasFactory;

    protected $table = 'tbl_destinasi_populer';

    protected $fillable = [
        'destination_name',
        'wilayah',
        'provinsi',
        'deskripsi',
        'total_visitor',
        'main_photo',
        'others_photo',
    ];

    protected $casts = [
        'others_photo' => 'array',
    ];

    /**
     * Label wilayah yang tampil di kartu destinasi & admin.
     */
    public function getWilayahLabelAttribute(): string
    {
        return \App\Models\Etalase\WilayahTambahan::gabungan()[$this->wilayah] ?? 'Indonesia';
    }

    public function scopeDiWilayah($query, ?string $wilayah)
    {
        return $query->when($wilayah, fn ($q) => $q->where('wilayah', $wilayah));
    }
}
