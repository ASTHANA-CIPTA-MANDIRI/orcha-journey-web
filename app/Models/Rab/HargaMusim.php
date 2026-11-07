<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga satu baris master pada satu musim.
 *
 * Yang TIDAK punya baris di sini berarti "pakai aturan biasa" — hari kerja
 * atau akhir pekan seperti sebelumnya. Tiket Candi Arjuna tetap Rp 10.000
 * pada 25 Desember, jadi ia memang tidak punya baris, dan itu bukan data
 * yang kurang.
 *
 * Bedanya dengan "belum diisi" tidak bisa dibaca dari tabel ini sendiri —
 * karena itu HargaHari::belumPunyaHargaMusim() menyebut baris yang jatuh di
 * dalam musim tanpa punya harganya, dan layar menampilkannya. Jatuh balik ke
 * tarif hari kerja tanpa bilang apa-apa adalah selisih yang baru terbaca di
 * kasir hotel.
 */
class HargaMusim extends Model
{
    protected $table = 'tbl_master_harga_musim';

    protected $fillable = ['master_harga_id', 'musim_id', 'harga'];

    protected $casts = [
        'master_harga_id' => 'integer',
        'musim_id' => 'integer',
        'harga' => 'integer',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(MasterHarga::class, 'master_harga_id');
    }

    public function musim(): BelongsTo
    {
        return $this->belongsTo(MusimHarga::class, 'musim_id');
    }
}
