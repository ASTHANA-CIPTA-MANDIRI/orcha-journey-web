<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris biaya dalam RAB, dengan harga yang DIBEKUKAN.
 *
 * master_harga_id hanya rujukan asal-usul — ia dipakai untuk memberi tahu
 * admin bahwa harga master sudah berubah sejak baris ini dibuat, bukan untuk
 * membaca harganya.
 */
class RabBiaya extends Model
{
    protected $table = 'tbl_rab_biaya';

    protected $fillable = [
        'rab_id', 'master_harga_id', 'kategori', 'nama', 'satuan',
        'harga_satuan', 'kapasitas', 'jumlah', 'urutan',
    ];

    protected $casts = [
        'harga_satuan' => 'integer',
        'kapasitas' => 'integer',
        'jumlah' => 'integer',
        'urutan' => 'integer',
    ];

    public function master()
    {
        return $this->belongsTo(MasterHarga::class, 'master_harga_id');
    }
}
