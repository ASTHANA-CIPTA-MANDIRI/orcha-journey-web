<?php

namespace App\Models\Rab;

use App\Support\Rab\PaketDariRab;
use Illuminate\Database\Eloquent\Model;

class RabItinerary extends Model
{
    protected $table = 'tbl_rab_itinerary';

    protected $fillable = ['rab_id', 'hari_ke', 'urutan', 'jam', 'jam_selesai', 'nama', 'keterangan', 'destinasi'];

    protected static function booted(): void
    {
        static::saved(fn (self $k) => self::selaraskanPaket($k->rab));
        static::deleted(fn (self $k) => self::selaraskanPaket($k->rab));
    }

    /**
     * Paket wisata yang dirakit dari RAB ini ikut diperbarui.
     *
     * Penyelarasan dipasang di MODEL, bukan di tiap titik API, karena RAB
     * berubah dari belasan tempat — tambah biaya, ubah harga, hapus baris,
     * susun ulang, tarik biaya, simpan jadwal, pulihkan versi. Memanggilnya
     * satu per satu di sana berarti yang lupa dipasang akan diam-diam
     * membiarkan paket publik memuat harga yang sudah tidak berlaku, dan
     * tidak ada galat yang memberi tahu.
     *
     * Pendaftar yang sudah ada tidak terpengaruh: PendaftaranOpenTrip
     * membekukan harga_jual dan harga_modal miliknya sendiri saat dibuat.
     */
    protected static function selaraskanPaket(?Rab $rab): void
    {
        if ($rab && $rab->travel_package_id) {
            PaketDariRab::selaraskan($rab);
        }
    }

    public function rab()
    {
        return $this->belongsTo(Rab::class, 'rab_id');
    }

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
