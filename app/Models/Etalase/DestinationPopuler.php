<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DestinationPopuler extends Model
{
    use HasFactory;

    protected $table = 'tbl_destinasi_populer';

    protected $fillable = [
        'destination_name',
        'slug',
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

    protected static function booted(): void
    {
        static::saving(function (self $destinasi) {
            if (blank($destinasi->slug)) {
                $destinasi->slug = static::slugUnik($destinasi->destination_name, $destinasi->id);
            }
        });
    }

    /**
     * Slug dibuat SEKALI, saat barisnya belum punya.
     *
     * Sengaja tidak ikut berubah ketika namanya disunting. Alamat destinasi
     * sudah beredar — dibagikan di WhatsApp, dicatat mesin pencari — dan
     * memperbaiki ejaan nama tidak sepadan dengan mematikan semua tautan yang
     * sudah tersebar. Pola yang sama dipakai slug artikel.
     */
    public static function slugUnik(string $nama, ?int $kecuali = null): string
    {
        $dasar = Str::slug($nama) ?: 'destinasi';
        $slug = $dasar;
        $n = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))
            ->exists()) {
            $slug = "$dasar-$n";
            $n++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
