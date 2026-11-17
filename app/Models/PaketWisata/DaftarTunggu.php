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
        'travel_package_id', 'nama', 'whatsapp', 'email', 'jumlah_peserta',
        'dikabari_pada', 'dihubungi_pada', 'dihubungi_oleh',
        'keberangkatan_id', 'ditawarkan_pada', 'ditahan_sampai',
    ];

    protected $casts = [
        'jumlah_peserta' => 'integer',
        'dikabari_pada' => 'datetime',
        'dihubungi_pada' => 'datetime',
        'ditawarkan_pada' => 'datetime',
        'ditahan_sampai' => 'datetime',
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
    /**
     * Kursi yang sedang DITAHAN untuk orang ini, dan belum lewat waktunya.
     *
     * Dibaca sebagai tiga keadaan, bukan dua: belum pernah ditawari, sedang
     * ditahan, dan tahanannya sudah lewat. Yang ketiga itu yang paling penting
     * — kursinya boleh ditawarkan ke orang berikutnya, dan tanpa keadaan itu
     * ia menggantung selamanya atas nama orang yang tidak membalas.
     */
    public function getKeadaanTawaranAttribute(): string
    {
        if ($this->ditawarkan_pada === null) {
            return 'belum';
        }

        if ($this->ditahan_sampai === null || $this->ditahan_sampai->isFuture()) {
            return 'ditahan';
        }

        return 'lewat';
    }

    public function keberangkatan(): BelongsTo
    {
        return $this->belongsTo(Keberangkatan::class, 'keberangkatan_id');
    }

    public function scopeBelumDikabari($query)
    {
        return $query->whereNull('dikabari_pada')->orderBy('created_at');
    }

    /**
     * Yang menuntut seseorang mengangkat telepon.
     *
     * Tiga syarat sekaligus, dan ketiganya perlu:
     *
     *   kursinya sudah terbuka   — sebelum itu tidak ada yang bisa dikabarkan
     *   tidak punya surel        — sistem tidak bisa menjangkaunya sama sekali
     *   belum dihubungi siapa pun
     *
     * Yang tanpa surel TETAPI kursinya belum terbuka tidak termasuk: ia cuma
     * menunggu, dan menghitungnya membuat penandanya menyala terus tanpa
     * pernah bisa dinolkan.
     */
    public function scopePerluDihubungi($query)
    {
        return $query
            ->whereNotNull('dikabari_pada')
            ->whereNull('dihubungi_pada')
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''));
    }
}
