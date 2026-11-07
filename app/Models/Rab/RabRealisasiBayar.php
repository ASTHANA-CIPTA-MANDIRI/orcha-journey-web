<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali uang keluar (atau kembali) untuk sebuah biaya.
 *
 * Nominalnya SELALU positif; arahnya ditentukan `jenis`. Refund yang ditulis
 * sebagai angka negatif akan memaksa setiap penjumlahan di seluruh laporan
 * memeriksa tandanya, dan satu tempat yang lupa membuat totalnya salah tanpa
 * satu pun galat.
 */
class RabRealisasiBayar extends Model
{
    protected $table = 'tbl_rab_realisasi_bayar';

    protected $fillable = [
        'realisasi_id', 'jenis', 'nominal', 'tanggal', 'bukti', 'bukti_riwayat', 'oleh', 'catatan',
    ];

    protected $casts = [
        'realisasi_id' => 'integer',
        'nominal' => 'integer',
        'tanggal' => 'date',
        'bukti_riwayat' => 'array',
    ];

    public function realisasi(): BelongsTo
    {
        return $this->belongsTo(RabRealisasi::class, 'realisasi_id');
    }

    public function getJenisLabelAttribute(): string
    {
        return config("orcha.rab.realisasi_jenis.{$this->jenis}") ?? ucfirst($this->jenis);
    }

    /** Uang KELUAR (+) atau MASUK (−), dari jenisnya. */
    public function getArahAttribute(): int
    {
        return $this->jenis === 'refund' ? -1 : 1;
    }

    public function getTanggalTeksAttribute(): ?string
    {
        return $this->tanggal?->locale('id')->translatedFormat('j M Y');
    }

    /**
     * Menyimpan bukti baru tanpa membuang yang lama.
     *
     * Yang lama diarsipkan ke bukti_riwayat, berkasnya TIDAK dihapus dari
     * cakram — dan aman di sana karena orcha:berkas-yatim sengaja tidak
     * pernah menyentuh folder bukti-bayar. Polanya sama persis dengan
     * konfirmasi pembayaran peserta.
     */
    public function gantiBukti(string $jalurBaru, ?string $oleh = null): void
    {
        $riwayat = $this->bukti_riwayat ?? [];

        if (filled($this->bukti)) {
            $riwayat[] = [
                'jalur' => $this->bukti,
                'diganti_pada' => now()->toIso8601String(),
                'oleh' => $oleh,
            ];
        }

        $this->update(['bukti' => $jalurBaru, 'bukti_riwayat' => $riwayat ?: null]);
    }
}
