<?php

namespace App\Models\OpenTrip;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data kesehatan peserta open trip.
 *
 * Isinya termasuk data sensitif, jadi hanya ditampilkan di panel admin dan
 * tidak pernah dikirim lewat WhatsApp maupun ditayangkan di halaman publik.
 */
class RiwayatKesehatan extends Model
{
    protected $table = 'tbl_riwayat_kesehatan';

    protected $fillable = [
        'kode_pendaftaran',
        'nama_peserta',
        'usia',
        'jenis_kelamin',
        'tinggi_badan',
        'berat_badan',
        'golongan_darah',
        'riwayat_penyakit',
        'kondisi_khusus',
        'riwayat_operasi',
        'alergi',
        'pantangan_makanan',
        'obat_rutin',
        'pantangan_kegiatan',
        'kemampuan_renang',
        'asuransi',
        'catatan_tambahan',
        'kontak_darurat_nama',
        'kontak_darurat_hp',
        'kontak_darurat_hubungan',
        'setuju_data_kesehatan',
    ];

    protected $casts = [
        'usia' => 'integer',
        'tinggi_badan' => 'integer',
        'berat_badan' => 'integer',
        'kondisi_khusus' => 'array',
        'setuju_data_kesehatan' => 'boolean',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PendaftaranOpenTrip::class, 'kode_pendaftaran', 'kode');
    }

    public function getAdaCatatanKhususAttribute(): bool
    {
        return filled($this->riwayat_penyakit)
            || filled($this->alergi)
            || filled($this->obat_rutin)
            || filled($this->pantangan_kegiatan)
            || filled($this->pantangan_makanan)
            || filled($this->riwayat_operasi)
            || ! empty($this->kondisi_khusus);
    }
}
