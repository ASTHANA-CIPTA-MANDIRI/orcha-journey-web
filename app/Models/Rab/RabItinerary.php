<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;

class RabItinerary extends Model
{
    protected $table = 'tbl_rab_itinerary';

    protected $fillable = ['rab_id', 'hari_ke', 'urutan', 'jam', 'jam_selesai', 'nama', 'keterangan', 'destinasi'];

    /**
     * Lamanya kegiatan, dihitung — bukan disimpan.
     *
     * Angka yang bisa dihitung dan juga disimpan akan berselisih suatu hari:
     * jam selesainya diubah, durasinya tidak, dan yang membaca tidak punya
     * cara tahu mana dari dua angka itu yang benar.
     *
     * Melewati tengah malam dihitung sebagai hari berikutnya — perjalanan
     * malam 18.00–05.00 memang sebelas jam, bukan minus tiga belas.
     */
    public function getDurasiMenitAttribute(): ?int
    {
        $mulai = self::keMenit($this->jam);
        $selesai = self::keMenit($this->jam_selesai);

        if ($mulai === null || $selesai === null) {
            return null;
        }

        return $selesai >= $mulai ? $selesai - $mulai : $selesai + 24 * 60 - $mulai;
    }

    /** "11 jam", "1 jam 30 menit", "30 menit". */
    public function getDurasiTeksAttribute(): ?string
    {
        $menit = $this->durasi_menit;

        if ($menit === null || $menit === 0) {
            return null;
        }

        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        return trim(($jam ? $jam.' jam ' : '').($sisa ? $sisa.' menit' : ''));
    }

    private static function keMenit(?string $jam): ?int
    {
        if (! is_string($jam) || preg_match('/^(\d{1,2}):(\d{2})$/', $jam, $c) !== 1) {
            return null;
        }

        return (int) $c[1] * 60 + (int) $c[2];
    }

    protected $casts = ['hari_ke' => 'integer', 'urutan' => 'integer'];
}
