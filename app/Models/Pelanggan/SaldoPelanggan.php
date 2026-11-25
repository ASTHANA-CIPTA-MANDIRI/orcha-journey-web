<?php

namespace App\Models\Pelanggan;

use App\Support\NomorTelepon;
use Illuminate\Database\Eloquent\Model;

/**
 * Titipan uang pelanggan yang belum terpakai.
 *
 * Satu baris = satu titipan utuh, dan tidak pernah dipakai separuh. Kalau
 * yang terpakai hanya sebagian, barisnya ditutup lalu sisanya dibuat sebagai
 * baris baru — saldo yang nominalnya diubah-ubah menghapus jejak berapa yang
 * semula dititipkan, dan itu justru angka yang dipersoalkan saat pelanggan
 * menagihnya kembali.
 */
class SaldoPelanggan extends Model
{
    protected $table = 'tbl_saldo_pelanggan';

    protected $fillable = [
        'whatsapp',
        'nama',
        'nominal',
        'asal_kode',
        'alasan',
        'terpakai_pada',
        'terpakai_kode',
        'dicatat_oleh',
    ];

    protected $casts = [
        'nominal' => 'integer',
        'terpakai_pada' => 'datetime',
    ];

    /**
     * Nomor disimpan dalam bentuk yang SUDAH dinormalkan.
     *
     * Orang yang sama menulis nomornya "0812-3456-7890", "081234567890", dan
     * "+6281234567890". Tanpa penyeragaman di satu tempat, saldo yang
     * dititipkan lewat satu ejaan tidak akan pernah ditemukan lewat ejaan
     * yang lain — dan pelanggan yang benar dianggap tidak punya titipan.
     */
    public function setWhatsappAttribute($nilai): void
    {
        $this->attributes['whatsapp'] = NomorTelepon::angka((string) $nilai);
    }

    public function scopeMilik($query, ?string $whatsapp)
    {
        return $query->where('whatsapp', NomorTelepon::angka((string) $whatsapp));
    }

    /** Yang masih bisa dipakai. */
    public function scopeTersedia($query)
    {
        return $query->whereNull('terpakai_pada');
    }

    public function getTerpakaiAttribute(): bool
    {
        return $this->terpakai_pada !== null;
    }
}
