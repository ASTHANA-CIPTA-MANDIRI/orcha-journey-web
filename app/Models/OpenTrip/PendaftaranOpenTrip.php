<?php

namespace App\Models\OpenTrip;

use App\Models\PaketWisata\TravelPackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PendaftaranOpenTrip extends Model
{
    protected $table = 'tbl_pendaftaran_open_trip';

    protected $fillable = [
        'kode',
        'travel_package_id',
        'nama_paket',
        'nama',
        'whatsapp',
        'email',
        'jumlah_peserta',
        'daftar_peserta',
        'tanggal_berangkat',
        'titik_jemput',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'jumlah_peserta' => 'integer',
        'daftar_peserta' => 'array',
    ];

    /**
     * Kode pendaftaran dibuat otomatis, misalnya OT-2608-A7K3.
     * Kode inilah yang dipakai peserta untuk mengisi form riwayat kesehatan.
     */
    protected static function booted(): void
    {
        static::creating(function (self $pendaftaran) {
            if (blank($pendaftaran->kode)) {
                do {
                    $kode = 'OT-'.now()->format('dm').'-'.Str::upper(Str::random(4));
                } while (static::where('kode', $kode)->exists());

                $pendaftaran->kode = $kode;
            }
        });
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function riwayatKesehatan(): HasMany
    {
        return $this->hasMany(RiwayatKesehatan::class, 'kode_pendaftaran', 'kode');
    }

    public function konfirmasiPembayaran()
    {
        return $this->hasMany(KonfirmasiPembayaran::class, 'kode', 'kode');
    }

    /**
     * Berapa peserta yang riwayat kesehatannya sudah masuk.
     *
     * Dipakai penanda kelengkapan: kesehatan diisi per orang, dan yang belum
     * mengisi harus terlihat jauh sebelum hari keberangkatan — bukan diketahui
     * saat rombongan sudah berkumpul.
     */
    public function getKesehatanTerisiAttribute(): int
    {
        return $this->riwayatKesehatan()->count();
    }

    public function getKesehatanLengkapAttribute(): bool
    {
        return $this->kesehatan_terisi >= $this->jumlah_peserta;
    }

    /** Nama peserta yang belum mengisi riwayat kesehatan. */
    public function getPesertaBelumIsiAttribute(): array
    {
        $sudah = $this->riwayatKesehatan()->pluck('nama_peserta')
            ->map(fn ($nama) => mb_strtolower(trim($nama)))
            ->all();

        return collect($this->daftar_peserta ?? [])
            ->filter(fn ($nama) => ! in_array(mb_strtolower(trim($nama)), $sudah, true))
            ->values()
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pendaftaran')[$this->status] ?? 'Baru';
    }
}
