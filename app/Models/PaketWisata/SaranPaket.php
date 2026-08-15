<?php

namespace App\Models\PaketWisata;

use Illuminate\Database\Eloquent\Model;

/**
 * Saran isian paket — destinasi yang dikunjungi dan fasilitas.
 *
 * Dipakai formulir paket di dashboard supaya admin mengklik, bukan mengetik
 * ulang. Daftarnya tumbuh sendiri setiap ada isian baru, dan boleh dirapikan
 * dengan menghapus yang sudah tidak dipakai.
 *
 * Menghapus saran TIDAK mengubah paket yang sudah tersimpan — yang hilang
 * hanya pilihan cepatnya.
 */
class SaranPaket extends Model
{
    protected $table = 'tbl_saran_paket';

    protected $fillable = ['jenis', 'nama'];

    public const JENIS = ['destinasi', 'fasilitas'];

    /**
     * Catat nama-nama baru, abaikan yang sudah ada.
     *
     * @param  array<int, string>  $daftar
     */
    public static function catat(string $jenis, array $daftar): void
    {
        if (! in_array($jenis, self::JENIS, true)) {
            return;
        }

        foreach ($daftar as $nama) {
            $nama = trim((string) $nama);

            if ($nama === '') {
                continue;
            }

            static::firstOrCreate(['jenis' => $jenis, 'nama' => $nama]);
        }
    }

    public function scopeJenis($query, string $jenis)
    {
        return $query->where('jenis', $jenis);
    }
}
