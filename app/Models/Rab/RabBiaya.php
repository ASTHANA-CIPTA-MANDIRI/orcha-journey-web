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
        'harga_satuan', 'harga_sebab', 'pakai_akhir_pekan', 'tingkat_harga', 'musim_id',
        'kapasitas', 'unit_manual', 'jumlah', 'urutan', 'catatan',
    ];

    protected $casts = [
        'harga_satuan' => 'integer',
        'kapasitas' => 'integer',
        'unit_manual' => 'integer',
        'jumlah' => 'integer',
        'urutan' => 'integer',
        'pakai_akhir_pekan' => 'boolean',
        'musim_id' => 'integer',
    ];

    /**
     | TINGKAT harga yang dibekukan baris ini, dan satu-satunya daftarnya.
     |
     | Dibaca kode, bukan orang — yang dibaca orang adalah harga_sebab.
     | Ditulis sebagai tetapan supaya "musim" yang salah ketik berbunyi di
     | tempat yang menulisnya, bukan diam-diam membuat baris itu diperlakukan
     | sebagai hari kerja pada "Pakai harga master" berikutnya.
     */
    public const TINGKAT = ['hari_kerja', 'akhir_pekan', 'musim'];

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

    /**
     * Musim yang menentukan harga beku baris ini, bila ada.
     *
     * Boleh kosong walau tingkat_harga-nya 'musim': musim yang dihapus
     * meninggalkan barisnya utuh dengan musim_id jadi null. Harganya tidak
     * berubah — penawaran lama tidak boleh bergeser — yang hilang hanya
     * kemampuan menyebut nama musimnya, dan harga_sebab masih menyimpan
     * kalimatnya.
     */
    public function musim()
    {
        return $this->belongsTo(MusimHarga::class, 'musim_id');
    }

    /**
     * Sisi master yang harus disentuh "Pakai harga master" & "Simpan ke master".
     *
     * Dibaca dari tingkat_harga, dengan pakai_akhir_pekan sebagai jaring
     * untuk baris yang dibuat sebelum kolomnya ada. Keduanya seharusnya
     * selalu sepakat — migrasi menerjemahkan yang lama — tetapi menebak dari
     * yang mana saja yang kebetulan terisi lebih baik daripada memulangkan
     * 'hari_kerja' untuk baris akhir pekan, yang akan menurunkan harganya.
     */
    /**
     * Harga master SEKARANG pada sisi yang dipakai baris ini.
     *
     * Di model, bukan di pemanggilnya, karena DUA pihak membutuhkannya:
     * HitungRab saat memberi tahu "harga master sudah berubah", dan
     * RabController saat tombol "Pakai harga master" ditekan. Dua salinan
     * aturan ini akan berselisih begitu salah satunya diubah — dan yang
     * kalah adalah yang menulis harga, bukan yang cuma menampilkannya.
     *
     * Baris berharga akhir pekan yang dibandingkan dengan harga hari kerja
     * akan selamanya berteriak "lebih mahal Rp 10.000", dan admin akan
     * menurutinya — lalu penawarannya kekurangan modal justru di tanggal
     * paling mahal. Dengan tarif musim, selisihnya jauh lebih besar.
     *
     * Memulangkan null bila sisinya sudah tidak ada lagi di master — tarif
     * musim yang dihapus. Bukan harga terdekat: angka yang sekadar mirip
     * akan ditawarkan layar sebagai "harga master terbaru", lalu dipakai.
     */
    public function hargaMasterKini(): ?int
    {
        if (! $this->master) {
            return null;
        }

        if ($this->tingkat === 'musim') {
            $musim = ($this->master->relationLoaded('musim')
                ? $this->master->musim
                : $this->master->musim()->get())
                ->firstWhere('musim_id', (int) $this->musim_id);

            return $musim ? (int) $musim->harga : null;
        }

        if ($this->tingkat === 'akhir_pekan' && $this->master->harga_akhir_pekan !== null) {
            return (int) $this->master->harga_akhir_pekan;
        }

        return (int) $this->master->harga;
    }

    public function getTingkatAttribute(): string
    {
        if (in_array($this->tingkat_harga, self::TINGKAT, true)) {
            return $this->tingkat_harga;
        }

        return $this->pakai_akhir_pekan ? 'akhir_pekan' : 'hari_kerja';
    }
}
