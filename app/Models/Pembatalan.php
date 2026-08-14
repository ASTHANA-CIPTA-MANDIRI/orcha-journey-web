<?php

namespace App\Models;

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

    public function getAlasanLabelAttribute(): string
    {
        return config('orcha.alasan_pembatalan')[$this->alasan] ?? 'Lainnya';
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pembatalan')[$this->status] ?? 'Diajukan';
    }
}
