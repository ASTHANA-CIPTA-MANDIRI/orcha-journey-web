<?php

namespace App\Models\SewaKendaraan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu bagian kendaraan yang diperiksa saat serah terima.
 *
 * Dulu dua belas baris di config/orcha.php. Sekarang data, supaya pemilik
 * armada bisa menambah sendiri tanpa menunggu deploy — lihat migrasinya untuk
 * alasan lengkapnya.
 */
class BagianPemeriksaan extends Model
{
    protected $table = 'bagian_pemeriksaan';

    protected $fillable = [
        'kunci', 'label', 'jenis',
        'biaya_lecet', 'biaya_rusak', 'biaya_hilang',
        'urutan', 'aktif',
    ];

    protected $casts = [
        'jenis' => 'array',
        'biaya_lecet' => 'integer',
        'biaya_rusak' => 'integer',
        'biaya_hilang' => 'integer',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        /*
         | Kunci dibuatkan dari labelnya bila admin tidak menuliskannya sendiri.
         | Yang mengisi formulir memikirkan "AC blower atas", bukan
         | "ac_blower_atas" — dan kunci yang diketik tangan cepat sekali
         | berisi spasi atau huruf besar, yang lalu tersimpan apa adanya di
         | ribuan baris kondisi.
         */
        // Pembacaan diingat sepanjang satu permintaan; begitu barisnya berubah
        // ingatan itu dibuang, supaya satu permintaan tidak membaca dua keadaan.
        static::saved(fn () => \App\Support\Pemeriksaan::lupakan());
        static::deleted(fn () => \App\Support\Pemeriksaan::lupakan());

        static::creating(function (self $bagian) {
            if (blank($bagian->kunci)) {
                $bagian->kunci = static::kunciUnik(Str::slug($bagian->label, '_'));
            }

            if ($bagian->urutan === null || $bagian->urutan === 0) {
                $bagian->urutan = (int) static::max('urutan') + 10;
            }
        });
    }

    /** Kunci yang belum terpakai, dengan akhiran angka bila perlu. */
    public static function kunciUnik(string $dasar): string
    {
        $dasar = $dasar !== '' ? $dasar : 'bagian';
        $kunci = $dasar;
        $n = 2;

        while (static::where('kunci', $kunci)->exists()) {
            $kunci = $dasar.'_'.$n++;
        }

        return $kunci;
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('id');
    }

    /**
     * Berlaku untuk jenis kendaraan ini?
     *
     * Disaring di PHP, bukan lewat whereJsonContains: daftarnya belasan baris,
     * dan penyaringan JSON di basis data berbeda perilakunya antara MySQL dan
     * SQLite yang dipakai pengujian. Yang dihemat tidak sebanding dengan satu
     * lagi hal yang harus diingat berbeda di dua tempat.
     */
    public function untukJenis(?string $jenis): bool
    {
        return $jenis === null || in_array($jenis, $this->jenis ?? [], true);
    }

    /** Tarif per tingkat kondisi, bentuknya sama dengan config lama. */
    public function getTarifAttribute(): array
    {
        return [
            'lecet' => $this->biaya_lecet,
            'rusak' => $this->biaya_rusak,
            'hilang' => $this->biaya_hilang,
        ];
    }
}
