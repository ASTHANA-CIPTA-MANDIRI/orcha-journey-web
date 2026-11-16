<?php

namespace App\Models\PaketWisata;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu tanggal keberangkatan milik satu paket.
 *
 * Isi paketnya — itinerary, destinasi, fasilitas, foto — dipakai bersama;
 * yang berbeda hanya tanggalnya, dan kadang kuota & harganya.
 *
 * Kuota dan harga yang KOSONG berarti ikut paketnya. Dibaca lewat aksesor
 * di bawah supaya pemanggilnya tidak perlu mengingat aturan itu di dua
 * belas tempat — dan supaya yang lupa tidak diam-diam memakai null sebagai
 * nol.
 */
class Keberangkatan extends Model
{
    protected $table = 'tbl_paket_keberangkatan';

    protected $fillable = [
        'travel_package_id',
        'tanggal_berangkat',
        'tanggal_pulang',
        'kuota',
        'price',
        'original_price',
        'harga_modal',
        'aktif',
        'catatan',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'tanggal_pulang' => 'date',
        'kuota' => 'integer',
        'price' => 'integer',
        'original_price' => 'integer',
        'harga_modal' => 'integer',
        'aktif' => 'boolean',
    ];

    protected $attributes = ['aktif' => true];

    public function paket(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PendaftaranOpenTrip::class, 'keberangkatan_id');
    }

    /** Yang masih dibuka, paling dekat lebih dulu. */
    public function scopeTersedia($kueri)
    {
        return $kueri->where('aktif', true)->orderBy('tanggal_berangkat');
    }

    /** Yang tanggalnya belum lewat. */
    public function scopeBelumLewat($kueri)
    {
        return $kueri->whereDate('tanggal_berangkat', '>=', today());
    }

    /* ------------------------ ikut paket bila kosong ------------------------ */

    public function getKuotaDipakaiAttribute(): ?int
    {
        return $this->kuota ?? $this->paket?->kuota;
    }

    public function getHargaDipakaiAttribute(): ?int
    {
        return $this->price ?? $this->paket?->price;
    }

    public function getHargaCoretDipakaiAttribute(): ?int
    {
        return $this->original_price ?? $this->paket?->original_price;
    }

    public function getModalDipakaiAttribute(): ?int
    {
        return $this->harga_modal ?? $this->paket?->harga_modal;
    }

    /* ------------------------------- kursi -------------------------------- */

    /**
     * Kursi yang sudah terpakai pada tanggal INI saja.
     *
     * Dihitung per keberangkatan, bukan per paket: satu paket dengan tiga
     * tanggal yang kuotanya dihitung bersama akan menolak pendaftar pada
     * tanggal yang masih kosong hanya karena tanggal lain sudah penuh.
     */
    public function getKursiTerpakaiAttribute(): int
    {
        if (array_key_exists('kursi_terpakai_agregat', $this->getAttributes())) {
            return (int) ($this->getAttributes()['kursi_terpakai_agregat'] ?? 0);
        }

        return (int) PendaftaranOpenTrip::query()
            ->where('keberangkatan_id', $this->id)
            ->where('status', '!=', 'batal')
            ->sum('jumlah_peserta');
    }

    /** Sisa kursi; null bila kuotanya memang belum ditetapkan. */
    public function getSisaKursiAttribute(): ?int
    {
        $kuota = $this->kuota_dipakai;

        if (! $kuota) {
            return null;
        }

        return max(0, $kuota - $this->kursi_terpakai);
    }

    /** Tanpa kuota TIDAK pernah penuh — sama seperti perilaku paket. */
    public function getPenuhAttribute(): bool
    {
        return $this->sisa_kursi !== null && $this->sisa_kursi <= 0;
    }

    /**
     * Batas pelunasan untuk TANGGAL INI, bukan untuk paketnya.
     *
     * Sebelumnya hanya paket yang punya batas pelunasan, dihitung dari
     * `tanggal_berangkat` miliknya. Begitu satu paket dijual untuk tiga
     * tanggal, angka itu salah untuk dua di antaranya: pendaftar trip 11–13
     * Desember menerima surat yang menulis "Berangkat: 11 Desember" dan
     * "Batas pelunasan: 22 November" — tanggal yang bisa saja sudah lewat
     * saat suratnya terkirim.
     *
     * Jaraknya tetap satu angka milik perusahaan (orcha.pembayaran), bukan
     * per paket: yang berubah titik hitungnya, bukan kebijakannya.
     */
    public function getBatasPelunasanAttribute(): ?Carbon
    {
        return $this->tanggal_berangkat
            ?->copy()
            ->subDays((int) config('orcha.pembayaran.pelunasan_hari_sebelum'));
    }

    /**
     * Rentang tanggalnya dalam satu kalimat: "11 – 13 Desember 2026".
     *
     * Bulan yang sama tidak disebut dua kali, dan tanggal pulang yang sama
     * dengan berangkat tidak menghasilkan rentang sama sekali.
     */
    public function getRentangTanggalAttribute(): string
    {
        $mulai = $this->tanggal_berangkat;

        if (! $mulai) {
            return '';
        }

        $selesai = $this->tanggal_pulang;

        if (! $selesai || $selesai->isSameDay($mulai)) {
            return $mulai->locale('id')->translatedFormat('j F Y');
        }

        if ($mulai->isSameMonth($selesai) && $mulai->isSameYear($selesai)) {
            return $mulai->locale('id')->translatedFormat('j').' – '
                .$selesai->locale('id')->translatedFormat('j F Y');
        }

        if ($mulai->isSameYear($selesai)) {
            return $mulai->locale('id')->translatedFormat('j F').' – '
                .$selesai->locale('id')->translatedFormat('j F Y');
        }

        return $mulai->locale('id')->translatedFormat('j F Y').' – '
            .$selesai->locale('id')->translatedFormat('j F Y');
    }
}
