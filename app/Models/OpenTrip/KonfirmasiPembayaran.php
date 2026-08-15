<?php

namespace App\Models\OpenTrip;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Database\Eloquent\Model;

/**
 * Bukti pembayaran yang dikirim pelanggan lewat formulir.
 *
 * Sengaja tidak terikat relasi keras ke pendaftaran atau penyewaan: yang
 * dipegang cuma kodenya. Pelanggan bisa saja salah ketik kode, dan bila itu
 * terjadi buktinya tetap masuk untuk diperiksa admin — lebih baik daripada
 * ditolak mentah lalu uangnya terlanjur berpindah tanpa catatan.
 */
class KonfirmasiPembayaran extends Model
{
    protected $table = 'tbl_konfirmasi_pembayaran';

    protected $fillable = [
        'kode',
        'jenis',
        'nominal',
        'tanggal_transfer',
        'bank_pengirim',
        'atas_nama_pengirim',
        'bukti',
        'catatan',
        'status',
        'catatan_admin',
    ];

    protected $casts = [
        'tanggal_transfer' => 'date',
        'nominal' => 'integer',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranOpenTrip::class, 'kode', 'kode');
    }

    public function penyewaan()
    {
        return $this->belongsTo(PenyewaanKendaraan::class, 'kode', 'kode');
    }

    /** Pesanan yang dimaksud kode ini, apa pun jenisnya. */
    public function pesanan()
    {
        return str_starts_with($this->kode, 'SK-')
            ? $this->penyewaan
            : $this->pendaftaran;
    }

    public function getJenisLabelAttribute(): string
    {
        return config('orcha.jenis_pembayaran')[$this->jenis] ?? 'Lainnya';
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pembayaran')[$this->status] ?? 'Menunggu Dicek';
    }

    public function getNominalFormattedAttribute(): string
    {
        return 'Rp '.number_format($this->nominal, 0, ',', '.');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status', 'menunggu');
    }
}
