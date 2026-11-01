<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali harga berubah.
 *
 * Hanya dicatat saat ANGKANYA yang berubah. Mengganti nama, vendor, atau
 * catatan tidak meninggalkan baris di sini — riwayat yang penuh dengan
 * perubahan yang tidak mengubah uang tidak bisa dibaca.
 */
class RiwayatHarga extends Model
{
    protected $table = 'tbl_master_harga_riwayat';

    protected $fillable = [
        'master_harga_id', 'batch_id', 'harga_lama', 'harga_baru',
        'akhir_pekan_lama', 'akhir_pekan_baru', 'oleh', 'catatan',
    ];

    protected $casts = [
        'master_harga_id' => 'integer',
        'batch_id' => 'integer',
        'harga_lama' => 'integer',
        'harga_baru' => 'integer',
        'akhir_pekan_lama' => 'integer',
        'akhir_pekan_baru' => 'integer',
    ];

    public function harga(): BelongsTo
    {
        return $this->belongsTo(MasterHarga::class, 'master_harga_id');
    }

    /**
     * Satu-satunya tempat riwayat harga ditulis.
     *
     * Dua jalur menaikkan harga master — layar Master Harga dan temuan tim
     * yang dibawa pulang dari sebuah RAB. Dua tempat yang menulis riwayat
     * hal yang sama akan berselisih begitu salah satunya diubah, dan
     * riwayat yang berselisih lebih buruk daripada tidak ada riwayat.
     *
     * Tidak mencatat apa pun bila angkanya tidak berubah.
     */
    public static function rekam(
        MasterHarga $master,
        int $hargaLama,
        ?string $oleh = null,
        ?string $catatan = null,
        ?int $batchId = null,
        ?int $akhirPekanLama = null,
    ): void {
        $akhirPekanBaru = $master->harga_akhir_pekan === null ? null : (int) $master->harga_akhir_pekan;

        /*
         | Salah satu dari dua angka cukup. Harga akhir pekan yang naik
         | sendirian — hari kerjanya tetap — tetap sebuah kenaikan harga,
         | dan tanpa baris ini ia tidak terbaca di mana pun.
         */
        if ((int) $master->harga === $hargaLama && $akhirPekanBaru === $akhirPekanLama) {
            return;
        }

        self::create([
            'master_harga_id' => $master->id,
            'batch_id' => $batchId,
            'harga_lama' => $hargaLama,
            'harga_baru' => (int) $master->harga,
            'akhir_pekan_lama' => $akhirPekanLama,
            'akhir_pekan_baru' => $akhirPekanBaru,
            'oleh' => $oleh,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Apakah baris ini mencatat perubahan harga akhir pekan.
     *
     * Dibedakan dari "tidak diketahui": baris riwayat yang dibuat sebelum
     * kolomnya ada memulangkan kedua nilainya null, dan itu bukan berarti
     * harga akhir pekannya tidak berubah.
     */
    public function getAkhirPekanBerubahAttribute(): bool
    {
        return $this->akhir_pekan_lama !== $this->akhir_pekan_baru;
    }

    /** Selisihnya, bertanda: positif berarti naik. */
    public function getSelisihAttribute(): int
    {
        return $this->harga_baru - $this->harga_lama;
    }

    /**
     * Berapa persen berubah, dibulatkan satu angka di belakang koma.
     *
     * Harga lama nol tidak dibagi — persennya tidak punya arti, dan
     * pembagiannya akan memulangkan galat alih-alih angka.
     */
    public function getPersenAttribute(): ?float
    {
        if ($this->harga_lama <= 0) {
            return null;
        }

        return round($this->selisih / $this->harga_lama * 100, 1);
    }
}
