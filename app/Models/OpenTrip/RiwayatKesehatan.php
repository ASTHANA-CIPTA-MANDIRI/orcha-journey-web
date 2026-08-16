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
        return $this->tingkat_perhatian !== 'aman';
    }

    /**
     * Seberapa besar peserta ini perlu diperhatikan: tinggi, sedang, atau aman.
     *
     * Sebelumnya semua isian dianggap sama: satu kalimat "tidak suka pedas" di
     * pantangan makanan membuat peserta ditandai sama merahnya dengan peserta
     * berpenyakit jantung. Pada rombongan dua belas orang hampir semuanya jadi
     * merah, dan penandanya berhenti berarti.
     *
     * Pemisahnya sekarang: apa yang menuntut KESIAPAN sebelum berangkat —
     * obat, penyakit yang bisa kambuh, alergi — berbeda dari apa yang cukup
     * DIINGAT saat di lapangan, seperti pantangan makanan.
     */
    public function getTingkatPerhatianAttribute(): string
    {
        if ($this->alasan_perhatian !== []) {
            return 'tinggi';
        }

        return $this->alasan_catatan === [] ? 'aman' : 'sedang';
    }

    /**
     * Hal yang menuntut kesiapan tim sebelum berangkat.
     *
     * @return array<int, string>
     */
    public function getAlasanPerhatianAttribute(): array
    {
        $berisiko = collect($this->kondisi_khusus ?? [])
            ->intersect(config('orcha.kondisi_berisiko'))
            ->map(fn ($kunci) => config('orcha.kondisi_kesehatan')[$kunci] ?? $kunci)
            ->values()
            ->all();

        return array_values(array_filter([
            ...$berisiko,
            filled($this->alergi) ? 'Alergi: '.$this->alergi : null,
            filled($this->obat_rutin) ? 'Obat rutin: '.$this->obat_rutin : null,
            filled($this->riwayat_penyakit) ? 'Riwayat penyakit: '.$this->riwayat_penyakit : null,
            filled($this->riwayat_operasi) ? 'Riwayat operasi: '.$this->riwayat_operasi : null,
        ]));
    }

    /**
     * Hal yang cukup diingat saat di lapangan.
     *
     * @return array<int, string>
     */
    public function getAlasanCatatanAttribute(): array
    {
        $ringan = collect($this->kondisi_khusus ?? [])
            ->diff(config('orcha.kondisi_berisiko'))
            ->map(fn ($kunci) => config('orcha.kondisi_kesehatan')[$kunci] ?? $kunci)
            ->values()
            ->all();

        return array_values(array_filter([
            ...$ringan,
            filled($this->pantangan_makanan) ? 'Pantangan makanan: '.$this->pantangan_makanan : null,
            filled($this->pantangan_kegiatan) ? 'Pantangan kegiatan: '.$this->pantangan_kegiatan : null,
            // Hanya berarti pada perjalanan yang menyentuh air, tapi tim yang
            // memutuskan itu — jadi tetap disebut, bukan disembunyikan.
            $this->kemampuan_renang === 'tidak_bisa' ? 'Tidak bisa berenang' : null,
        ]));
    }
}
