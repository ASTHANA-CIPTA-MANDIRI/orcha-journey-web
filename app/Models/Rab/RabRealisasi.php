<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekap pengeluaran satu baris biaya RAB, sesudah trip jalan.
 *
 * Menyimpan apa yang BENAR-BENAR ditagih vendor. Pembayarannya sendiri ada
 * di baris anak — DP, pelunasan, cicilan, refund.
 */
class RabRealisasi extends Model
{
    protected $table = 'tbl_rab_realisasi';

    protected $fillable = ['rab_biaya_id', 'tagihan', 'pic', 'catatan', 'oleh'];

    protected $casts = [
        'rab_biaya_id' => 'integer',
        'tagihan' => 'integer',
    ];

    public function biaya(): BelongsTo
    {
        return $this->belongsTo(RabBiaya::class, 'rab_biaya_id');
    }

    public function bayar(): HasMany
    {
        return $this->hasMany(RabRealisasiBayar::class, 'realisasi_id')->orderBy('tanggal')->orderBy('id');
    }

    /**
     * Uang yang BENAR-BENAR keluar: pembayaran dikurangi yang kembali.
     *
     * Refund disimpan sebagai jenis tersendiri dengan nominal positif, bukan
     * sebagai angka negatif — jadi pengurangannya dikerjakan di SATU tempat
     * ini. Dibiarkan negatif di kolomnya, setiap penjumlahan di seluruh
     * laporan harus ingat memeriksa tandanya, dan yang lupa salah tanpa
     * berbunyi.
     */
    public function getDibayarAttribute(): int
    {
        $bayar = $this->relationLoaded('bayar') ? $this->bayar : $this->bayar()->get();

        return (int) $bayar->reduce(
            fn (int $t, RabRealisasiBayar $b) => $b->jenis === 'refund'
                ? $t - (int) $b->nominal
                : $t + (int) $b->nominal,
            0,
        );
    }

    /** Sudah diisi tagihannya? Nol adalah tagihan yang sah, kosong bukan. */
    public function getSudahDirekapAttribute(): bool
    {
        return $this->tagihan !== null;
    }

    /**
     * Masih kurang dibayar ke vendor. Negatif berarti KELEBIHAN bayar.
     *
     * Dipisah dari "hemat" dengan sengaja — lihat RekapRab::baris().
     */
    public function getKurangBayarAttribute(): ?int
    {
        return $this->tagihan === null ? null : (int) $this->tagihan - $this->dibayar;
    }

    /** Pembayaran yang belum punya bukti sama sekali. */
    public function getTanpaBuktiAttribute(): int
    {
        $bayar = $this->relationLoaded('bayar') ? $this->bayar : $this->bayar()->get();

        return $bayar->filter(fn (RabRealisasiBayar $b) => blank($b->bukti))->count();
    }
}
