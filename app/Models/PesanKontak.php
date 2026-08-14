<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesanKontak extends Model
{
    protected $table = 'tbl_pesan_kontak';

    protected $fillable = [
        'nama',
        'whatsapp',
        'email',
        'keperluan',
        'pesan',
        'dibaca_pada',
        'ip',
    ];

    protected $casts = [
        'dibaca_pada' => 'datetime',
    ];

    public function getKeperluanLabelAttribute(): string
    {
        return config('orcha.keperluan_kontak')[$this->keperluan] ?? 'Lainnya';
    }

    public function getSudahDibacaAttribute(): bool
    {
        return $this->dibaca_pada !== null;
    }

    public function scopeBelumDibaca($query)
    {
        return $query->whereNull('dibaca_pada');
    }
}
