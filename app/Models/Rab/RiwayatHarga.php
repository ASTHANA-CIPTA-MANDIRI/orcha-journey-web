<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali harga berubah.
 *
 * Hanya dicatat saat ANGKANYA yang berubah. Mengganti nama, vendor, atau
 * catatan tidak meninggalkan baris di sini — riwayat yang penuh dengan
 * perubahan yang tidak mengubah uang tidak bisa dibaca.
 */
class RiwayatHarga extends Model
{
    protected $table = 'tbl_master_harga_riwayat';

    protected $fillable = ['master_harga_id', 'harga_lama', 'harga_baru', 'oleh', 'catatan'];

    protected $casts = [
        'master_harga_id' => 'integer',
        'harga_lama' => 'integer',
        'harga_baru' => 'integer',
    ];

    public function harga(): BelongsTo
    {
        return $this->belongsTo(MasterHarga::class, 'master_harga_id');
    }

    /** Selisihnya, bertanda: positif berarti naik. */
    public function getSelisihAttribute(): int
    {
        return $this->harga_baru - $this->harga_lama;
    }

    /**
     * Berapa persen berubah, dibulatkan satu angka di belakang koma.
     *
     * Harga lama nol tidak dibagi — persennya tidak punya arti, dan
     * pembagiannya akan memulangkan galat alih-alih angka.
     */
    public function getPersenAttribute(): ?float
    {
        if ($this->harga_lama <= 0) {
            return null;
        }

        return round($this->selisih / $this->harga_lama * 100, 1);
    }
}
