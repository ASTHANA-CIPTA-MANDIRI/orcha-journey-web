<?php

namespace App\Models\Rab;

use App\Support\Rab\PaketDariRab;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris biaya dalam RAB, dengan harga yang DIBEKUKAN.
 *
 * master_harga_id hanya rujukan asal-usul — ia dipakai untuk memberi tahu
 * admin bahwa harga master sudah berubah sejak baris ini dibuat, bukan untuk
 * membaca harganya.
 */
class RabBiaya extends Model
{
    protected $table = 'tbl_rab_biaya';

    protected $fillable = [
        'rab_id', 'master_harga_id', 'vendor_id', 'kategori', 'nama', 'satuan',
        'harga_satuan', 'harga_sebab', 'pakai_akhir_pekan', 'kapasitas',
        'jumlah', 'urutan', 'catatan',
    ];

    protected $casts = [
        'harga_satuan' => 'integer',
        'kapasitas' => 'integer',
        'jumlah' => 'integer',
        'urutan' => 'integer',
        'pakai_akhir_pekan' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $b) => self::selaraskanPaket($b->rab));
        static::deleted(fn (self $b) => self::selaraskanPaket($b->rab));
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

    public function master()
    {
        return $this->belongsTo(MasterHarga::class, 'master_harga_id');
    }

    /**
     * Vendornya SENGAJA tidak ikut dibekukan.
     *
     * Harga dibekukan karena penawaran yang angkanya berubah sendiri adalah
     * sengketa yang menunggu terjadi. Nomor telepon justru sebaliknya: yang
     * dibutuhkan selalu nomor yang berlaku hari ini, bukan nomor yang
     * berlaku saat penawaran dibuat.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
