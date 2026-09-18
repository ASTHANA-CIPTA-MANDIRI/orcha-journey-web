<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;

class RabItinerary extends Model
{
    protected $table = 'tbl_rab_itinerary';

    protected $fillable = ['rab_id', 'hari_ke', 'urutan', 'jam', 'nama', 'keterangan', 'destinasi'];

    protected $casts = ['hari_ke' => 'integer', 'urutan' => 'integer'];
}
