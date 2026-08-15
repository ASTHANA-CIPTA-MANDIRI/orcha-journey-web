<?php

namespace App\Models\Etalase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Partner extends Model
{
    use HasFactory;

    protected $table = 'tbl_partner';

    protected $fillable = [
        'partner_name',
        'foto',
    ];

    public function initials(): string
    {
        return Str::of(trim($this->partner_name))
            ->replaceMatches('/\s+/', ' ')
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }
}
