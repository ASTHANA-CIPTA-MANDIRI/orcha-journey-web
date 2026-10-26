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

    protected $fillable = ['nama', 'kontak', 'jenis', 'catatan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $v) {
            $v->nama = trim(preg_replace('/\s+/', ' ', (string) $v->nama));
            $v->kontak = trim((string) $v->kontak) ?: null;
            $v->jenis = trim((string) $v->jenis) ?: null;
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
}
