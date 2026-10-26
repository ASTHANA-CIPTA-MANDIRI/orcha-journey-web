<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dari siapa sebuah harga dibeli.
 *
 * Satu vendor memasok banyak harga — Hiace, Elf, dan bus besar dari pemilik
 * armada yang sama — jadi nomornya hidup di satu tempat. Nomor yang berganti
 * cukup diubah sekali dan seluruh harga yang memakainya ikut benar; kalau
 * disalin ke tiap baris, yang terlewat satu pun membuat nomor lama bertahan
 * tanpa ada yang tahu mana yang masih berlaku.
 */
class Vendor extends Model
{
    protected $table = 'tbl_vendor';

    protected $fillable = ['nama', 'kontak', 'jenis', 'provinsi', 'catatan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            $v->nama = trim(preg_replace('/\s+/', ' ', (string) $v->nama));
            $v->kontak = trim((string) $v->kontak) ?: null;
            $v->jenis = trim((string) $v->jenis) ?: null;
            $v->provinsi = trim((string) $v->provinsi) ?: null;
            $v->catatan = trim((string) $v->catatan) ?: null;
        });
    }

    public function harga(): HasMany
    {
        return $this->hasMany(MasterHarga::class, 'vendor_id');
    }

    public function scopeAktif($q)
    {
        return $q->where('aktif', true);
    }

    /**
     * Vendor yang masuk akal untuk provinsi ini.
     *
     * Yang provinsinya kosong ikut terbawa — kosong berarti berlaku di mana
     * saja, bukan belum diisi.
     */
    public function scopeUntukProvinsi($q, ?string $provinsi)
    {
        return $q->when($provinsi, fn ($s) => $s->where(
            fn ($w) => $w->whereNull('provinsi')->orWhere('provinsi', $provinsi)
        ));
    }

    /** Baris biaya RAB yang menunjuk vendor ini. */
    public function biaya(): HasMany
    {
        return $this->hasMany(RabBiaya::class, 'vendor_id');
    }
}
