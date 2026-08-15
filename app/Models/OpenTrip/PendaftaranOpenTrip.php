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

    /**
     * Daftar peserta dalam bentuk seragam: nama + titik jemputnya.
     *
     * Data lama menyimpan nama saja sebagai deretan teks. Diterjemahkan di
     * sini supaya seluruh aplikasi cukup mengenal satu bentuk, tanpa perlu
     * mengubah baris yang sudah telanjur tersimpan.
     */
    public function getPesertaAttribute(): array
    {
        return collect($this->daftar_peserta ?? [])
            ->map(function ($baris) {
                if (is_array($baris)) {
                    return [
                        'nama' => trim($baris['nama'] ?? ''),
                        'titik_jemput' => trim($baris['titik_jemput'] ?? '') ?: $this->titik_jemput,
                    ];
                }

                return ['nama' => trim((string) $baris), 'titik_jemput' => $this->titik_jemput];
            })
            ->filter(fn ($baris) => $baris['nama'] !== '')
            ->values()
            ->all();
    }

    /**
     * Titik jemput yang benar-benar dipakai rombongan ini, beserta siapa saja
     * yang menunggu di sana — inilah yang dibaca sopir pada hari keberangkatan.
     *
     * @return array<string, array<int, string>>
     */
    public function getJemputPerTitikAttribute(): array
    {
        return collect($this->peserta)
            ->filter(fn ($p) => filled($p['titik_jemput']))
            ->groupBy('titik_jemput')
            ->map(fn ($orang) => $orang->pluck('nama')->all())
            ->all();
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

        return collect($this->peserta)
            ->pluck('nama')
            ->filter(fn ($nama) => ! in_array(mb_strtolower(trim($nama)), $sudah, true))
            ->values()
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_pendaftaran')[$this->status] ?? 'Baru';
    }
}
