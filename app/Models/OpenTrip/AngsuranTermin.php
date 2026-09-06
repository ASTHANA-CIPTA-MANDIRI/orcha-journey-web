<?php

namespace App\Models\OpenTrip;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu termin dalam rencana angsuran.
 *
 * Sengaja TIDAK punya kolom status maupun "sudah dibayar". Status termin
 * diturunkan dari jumlah kumulatif pembayaran yang sudah masuk — lihat
 * App\Support\RencanaAngsuran::posisi(). Menyimpannya berarti mengalokasikan
 * tiap pembayaran ke termin tertentu, dan alokasi itu punya kasus khusus yang
 * tidak habis-habis: bayar lebih, bayar kurang, bayar dua termin sekaligus.
 * Tiap kasus adalah satu tempat baru untuk desinkron dengan tagihan.
 */
class AngsuranTermin extends Model
{
    protected $table = 'tbl_angsuran_termin';

    protected $fillable = [
        'angsuran_id',
        'urutan',
        'nominal',
        'jatuh_tempo',
        'diingatkan_pada',
        'dilaporkan_telat_pada',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'nominal' => 'integer',
        'jatuh_tempo' => 'date',
        'diingatkan_pada' => 'datetime',
        'dilaporkan_telat_pada' => 'datetime',
    ];

    public function angsuran()
    {
        return $this->belongsTo(Angsuran::class);
    }

    public function getNominalFormattedAttribute(): string
    {
        return 'Rp '.number_format($this->nominal, 0, ',', '.');
    }
}
