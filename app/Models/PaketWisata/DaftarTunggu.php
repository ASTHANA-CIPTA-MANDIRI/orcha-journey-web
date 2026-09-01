<?php

namespace App\Models\PaketWisata;

use App\Support\NomorTelepon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu peminat yang menunggu kursi terbuka.
 *
 * Halaman paket yang penuh mengarahkan orang ke WhatsApp, dan jawabannya tidak
 * tersimpan di mana pun: begitu percakapannya berakhir, peminat itu hilang.
 * Padahal merekalah yang paling mungkin langsung mengambil kursi yang dilepas
 * otomatis.
 */
class DaftarTunggu extends Model
{
    protected $table = 'tbl_daftar_tunggu';

    protected $fillable = [
        'travel_package_id', 'nama', 'whatsapp', 'email', 'jumlah_peserta', 'dikabari_pada',
    ];

    protected $casts = [
        'jumlah_peserta' => 'integer',
        'dikabari_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        /*
         | Nomornya diseragamkan sebelum disimpan.
         |
         | Kunci uniknya membandingkan teks apa adanya, sehingga "0812…",
         | "+62812…", dan "0812-3456-7890" akan terbaca sebagai tiga orang
         | berbeda — dan ketiganya menempati antrean yang sama sekaligus
         | menerima tiga kabar saat kursinya terbuka.
         */
        static::saving(fn (self $antre) => $antre->whatsapp = NomorTelepon::angka($antre->whatsapp));
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    /** Yang belum pernah dikabari, urut dari yang paling lama menunggu. */
    public function scopeBelumDikabari($query)
    {
        return $query->whereNull('dikabari_pada')->orderBy('created_at');
    }
}
