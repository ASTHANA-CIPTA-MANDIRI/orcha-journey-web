<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu perintah massal yang pernah dijalankan, berikut nilai lama tiap
 * baris yang disentuhnya — supaya bisa dikembalikan utuh.
 */
class BatchMassal extends Model
{
    protected $table = 'tbl_master_harga_batch';

    protected $fillable = ['aksi', 'jumlah', 'sebelum', 'oleh', 'dibatalkan_pada'];

    protected $casts = [
        'jumlah' => 'integer',
        'sebelum' => 'array',
        'dibatalkan_pada' => 'datetime',
    ];

    /**
     * Perintah yang menghapus tidak bisa dikembalikan — barisnya sudah
     * tidak ada, dan membuatnya ulang akan memberi id baru yang tidak
     * dikenali RAB mana pun.
     */
    public function bisaDibatalkan(): bool
    {
        return $this->aksi !== 'hapus' && $this->dibatalkan_pada === null;
    }
}
