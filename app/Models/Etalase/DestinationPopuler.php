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
        'daerah',
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

    /**
     * Alamat singkat destinasi: "Banyuwangi, Jawa Timur".
     *
     * Dirakit di satu tempat supaya kartu, jendela detail, dan surat menyebut
     * hal yang sama. Bagian yang belum diketahui dilewati — destinasi lama
     * belum punya daerah, dan menuliskan koma menggantung membuatnya tampak
     * seperti data yang rusak.
     */
    public function getAlamatSingkatAttribute(): string
    {
        return collect([$this->daerah, $this->provinsi])
            ->filter(fn ($bagian) => trim((string) $bagian) !== '')
            ->implode(', ');
    }

    public function scopeDiWilayah($query, ?string $wilayah)
    {
        return $query->when($wilayah, fn ($q) => $q->where('wilayah', $wilayah));
    }
}
