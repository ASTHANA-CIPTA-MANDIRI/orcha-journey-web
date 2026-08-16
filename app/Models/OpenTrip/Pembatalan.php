<?php

namespace App\Models\OpenTrip;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan pembatalan perjalanan yang dikirim lewat formulir publik.
 * Besaran pengembalian dana tetap dihitung manual oleh tim sesuai
 * Kebijakan Pembatalan & Pengembalian Dana.
 */
class Pembatalan extends Model
{
    protected $table = 'tbl_pembatalan';

    protected $fillable = [
        'kode_pendaftaran',
        'nama_pemohon',
        'whatsapp',
        'email',
        'alasan',
        'penjelasan',
        'jumlah_dibatalkan',
        'bank',
        'nomor_rekening',
        'atas_nama_rekening',
        'status',
        'catatan_admin',
    ];

    protected $casts = [
        'jumlah_dibatalkan' => 'integer',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranOpenTrip::class, 'kode_pendaftaran', 'kode');
    }

    public function penyewaan(): BelongsTo
    {
        return $this->belongsTo(PenyewaanKendaraan::class, 'kode_pendaftaran', 'kode');
    }

    /**
     * Pesanan yang dibatalkan — open trip atau sewa kendaraan.
     *
     * Kodenya sendiri yang menentukan: SK- untuk sewa kendaraan, sisanya open
     * trip. Cara yang sama dipakai KonfirmasiPembayaran, karena keduanya
     * memang menunjuk pesanan lewat kode yang diketik pelanggan.
     */
    public function pesanan(): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        return self::milik($this->kode_pendaftaran);
    }

    /** Mencari pesanan dari kodenya saja, tanpa perlu ada baris pembatalan. */
    public static function milik(?string $kode): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        $kode = strtoupper(trim((string) $kode));

        if (blank($kode)) {
            return null;
        }

        return str_starts_with($kode, 'SK-')
            ? PenyewaanKendaraan::with('kendaraan')->where('kode', $kode)->first()
            : PendaftaranOpenTrip::with('paket')->where('kode', $kode)->first();
    }

    public function getAlasanLabelAttribute(): string
    {
        return config('orcha.alasan_pembatalan')[$this->alasan] ?? 'Lainnya';
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pembatalan')[$this->status] ?? 'Diajukan';
    }
}
