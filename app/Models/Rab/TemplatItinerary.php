<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal perjalanan yang disimpan untuk dipakai lagi di RAB berikutnya.
 *
 * Sengaja TERPISAH dari TravelPackage: paket wisata adalah katalog yang
 * dibaca pengunjung situs, sedangkan ini catatan kerja tim. Satu klik di
 * layar RAB tidak boleh mengubah apa yang dilihat publik.
 */
class TemplatItinerary extends Model
{
    protected $table = 'tbl_rab_templat';

    protected $fillable = [
        'nama', 'provinsi', 'jumlah_hari', 'jumlah_kegiatan', 'itinerary', 'oleh',
    ];

    protected $casts = [
        'itinerary' => 'array',
        'jumlah_hari' => 'integer',
        'jumlah_kegiatan' => 'integer',
    ];
}
