<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kali penawaran dikirim ke pelanggan, berikut angkanya saat itu. */
class VersiPenawaran extends Model
{
    protected $table = 'tbl_rab_versi';

    protected $fillable = [
        'rab_id', 'urutan', 'harga_total', 'harga_per_orang', 'untung',
        'persen_untung', 'jumlah_peserta', 'jumlah_baris', 'oleh', 'rincian',
    ];

    protected $casts = [
        'rab_id' => 'integer',
        'urutan' => 'integer',
        'harga_total' => 'integer',
        'harga_per_orang' => 'integer',
        'untung' => 'integer',
        'persen_untung' => 'float',
        'jumlah_peserta' => 'integer',
        'jumlah_baris' => 'integer',
        /*
         | Potret utuh penawaran saat ia dikirim: baris biayanya, margin,
         | pembulatan, dan kesepakatan harga. Angka ringkas di kolom-kolom
         | sebelah cukup untuk MENJAWAB "dulu berapa?", tetapi tidak cukup
         | untuk MENGEMBALIKANNYA.
         |
         | null berarti versi lama — dicatat sebelum kolom ini ada, jadi
         | rinciannya memang tidak pernah tersimpan dan tidak bisa dipulihkan.
         */
        'rincian' => 'array',
    ];

    /** Versi ini menyimpan rinciannya, jadi bisa dipasang kembali. */
    public function bisaDipulihkan(): bool
    {
        return is_array($this->rincian) && ($this->rincian['baris'] ?? []) !== [];
    }

    public function rab(): BelongsTo
    {
        return $this->belongsTo(Rab::class, 'rab_id');
    }
}
