<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationPopuler extends Model
{
    use HasFactory;

    protected $table = 'tbl_destinasi_populer';

    protected $fillable = [
        'destination_name',
        'total_visitor',
        'main_photo',
        'others_photo',
    ];

    protected $casts = [
        'others_photo' => 'array',
    ];
}
