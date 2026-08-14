<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimoni extends Model
{
    use HasFactory;

    protected $table = 'tbl_testimoni';

    protected $fillable = [
        'customer_name',
        'rating',
        'testimonial',
        'avatar',
    ];
}
