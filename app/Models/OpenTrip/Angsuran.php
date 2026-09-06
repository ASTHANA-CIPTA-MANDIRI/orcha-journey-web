<?php

namespace App\Models\OpenTrip;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Support\PemilikPesanan;
use Illuminate\Database\Eloquent\Model;

/**
 * Rencana angsuran yang diberikan admin untuk satu pesanan.
 *
 * Satu pesanan hanya boleh punya satu rencana yang aktif. Yang lama tidak
 * dihapus saat diganti — ia ditandai dibatalkan, karena yang perlu dijawab
 * saat ada sengketa adalah "dulu dijanjikan apa", dan baris yang hilang tidak
 * menjawab apa pun.
 */
class Angsuran extends Model
{
    protected $table = 'tbl_angsuran';

    protected $fillable = [
        'kode',
        'jumlah_termin',
        'total',
        'dibuat_oleh',
        'catatan',
        'dibatalkan_pada',
    ];

    protected $casts = [
        'jumlah_termin' => 'integer',
        'total' => 'integer',
        'dibatalkan_pada' => 'datetime',
    ];

    public function termin()
    {
        return $this->hasMany(AngsuranTermin::class)->orderBy('urutan');
    }

    /** Pesanan yang dijadwalkan, apa pun jenisnya. */
    public function pesanan(): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        return PemilikPesanan::tanpaPeriksa($this->kode);
    }

    public function scopeAktif($query)
    {
        return $query->whereNull('dibatalkan_pada');
    }

    /** Rencana yang sedang berlaku untuk sebuah pesanan, bila ada. */
    public static function aktifUntuk(?string $kode): ?self
    {
        if (blank($kode)) {
            return null;
        }

        return static::with('termin')->aktif()->where('kode', $kode)->latest('id')->first();
    }
}
