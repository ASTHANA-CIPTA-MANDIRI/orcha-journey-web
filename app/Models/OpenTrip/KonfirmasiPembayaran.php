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
        // transfer = bukti unggahan pelanggan atau catatan manual admin.
        // doku = masuk sendiri lewat notifikasi gerbang pembayaran.
        'kanal',
        'nominal',
        'tanggal_transfer',
        'bank_pengirim',
        'atas_nama_pengirim',
        'bukti',
        'bukti_riwayat',
        'catatan',
        'status',
        'catatan_admin',
    ];

    protected $casts = [
        'tanggal_transfer' => 'date',
        'nominal' => 'integer',
        // Arsip bukti yang pernah dipakai: [{jalur, diganti_pada, oleh}].
        'bukti_riwayat' => 'array',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranOpenTrip::class, 'kode', 'kode');
    }

    public function penyewaan()
    {
        return $this->belongsTo(PenyewaanKendaraan::class, 'kode', 'kode');
    }

    /**
     * Percobaan bayar DOKU yang melahirkan catatan ini.
     *
     * Hanya ada untuk baris berkanal 'doku'. Dari sinilah rincian angkanya
     * datang — pokok, kode unik, nomor tagihan — yang tidak disimpan di tabel
     * ini karena bukan sifat catatan pembayarannya, melainkan sifat percobaan
     * yang menghasilkannya.
     */
    public function percobaanDoku()
    {
        return $this->hasOne(PembayaranDoku::class, 'konfirmasi_id');
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

    /**
     * Pecahan nominal untuk pembayaran yang masuk lewat gerbang.
     *
     * Ditaruh di model, bukan di resource. Dua tempat menyusun daftar
     * pembayaran — PembayaranResource untuk layar bukti, dan endpoint detail
     * pendaftaran untuk kartu di halaman pesanan — dan keduanya sempat
     * menyusunnya sendiri-sendiri. Akibatnya kanal tidak ikut terkirim di
     * salah satunya, dan layar yang seharusnya menyembunyikan kotak bukti
     * tetap memajangnya. Cacat seperti itu tidak menghasilkan galat apa pun;
     * ia hanya menampilkan hal yang salah, diam-diam.
     *
     * @return array{pokok: int, kode_unik: int, total: int, invoice: string, channel: string|null, dibayar_pada: string|null}|null
     */
    public function getRincianGerbangAttribute(): ?array
    {
        $percobaan = $this->percobaanDoku;

        if (! $percobaan) {
            return null;
        }

        return [
            'pokok' => (int) $percobaan->nominal_pokok,
            'kode_unik' => (int) $percobaan->kode_unik,
            'total' => (int) $percobaan->nominal,
            'invoice' => (string) $percobaan->invoice,
            'channel' => $percobaan->channel,
            'dibayar_pada' => $percobaan->dibayar_pada?->toIso8601String(),
        ];
    }

    /** Dibayar lewat gerbang, bukan lewat bukti yang diperiksa manusia. */
    public function getLewatGerbangAttribute(): bool
    {
        return $this->kanal === 'doku';
    }
}
