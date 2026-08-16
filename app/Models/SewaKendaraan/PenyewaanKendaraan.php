<?php

namespace App\Models\SewaKendaraan;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pemesanan sewa kendaraan yang masuk lewat formulir publik.
 */
class PenyewaanKendaraan extends Model
{
    protected $table = 'tbl_penyewaan_kendaraan';

    protected $fillable = [
        'kode',
        'car_id',
        'nama_kendaraan',
        'nama',
        'whatsapp',
        'email',
        'transmisi',
        'satuan',
        'durasi',
        'tanggal_mulai',
        'jam_mulai',
        'dengan_sopir',
        'lokasi_antar',
        'lokasi_kembali',
        'tanggal_selesai',
        'jam_selesai',
        'diserahkan_pada',
        'dikembalikan_pada',
        'kilometer_awal',
        'kilometer_akhir',
        'bahan_bakar_awal',
        'bahan_bakar_akhir',
        'kondisi_awal',
        'kondisi_akhir',
        'jaminan',
        'berkas_jaminan',
        'denda_keterlambatan',
        'denda_kerusakan',
        'denda_lain',
        'catatan_denda',
        'estimasi_biaya',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'dengan_sopir' => 'boolean',
        'durasi' => 'integer',
        'estimasi_biaya' => 'integer',
        'tanggal_selesai' => 'date',
        'diserahkan_pada' => 'datetime',
        'dikembalikan_pada' => 'datetime',
        'kondisi_awal' => 'array',
        'kondisi_akhir' => 'array',
        'denda_keterlambatan' => 'integer',
        'denda_kerusakan' => 'integer',
        'denda_lain' => 'integer',
    ];

    /**
     * Kode pemesanan, misalnya SK-1408-A7K3.
     */
    protected static function booted(): void
    {
        static::creating(function (self $sewa) {
            if (blank($sewa->kode)) {
                do {
                    $kode = 'SK-'.now()->format('dm').'-'.Str::upper(Str::random(4));
                } while (static::where('kode', $kode)->exists());

                $sewa->kode = $kode;
            }
        });
    }

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function getSatuanLabelAttribute(): string
    {
        return config('orcha.satuan_sewa')[$this->satuan]['label'] ?? 'Hari';
    }

    public function getDurasiLabelAttribute(): string
    {
        return $this->durasi.' '.($this->satuan === 'jam' ? 'jam' : ($this->satuan === '12jam' ? '× 12 jam' : 'hari'));
    }

    public function getStatusLabelAttribute(): string
    {
        return config('orcha.status_penyewaan')[$this->status] ?? 'Baru';
    }

    /**
     * Kapan unit ini seharusnya kembali.
     *
     * Dihitung dari jam mulai ditambah durasinya, lalu DISIMPAN saat pemesanan
     * dibuat. Kalau dihitung ulang setiap kali dibaca, mengubah aturan durasi
     * di kemudian hari akan diam-diam menggeser tenggat pesanan yang sudah
     * berjalan — dan denda ikut bergeser bersamanya.
     */
    public static function hitungSelesai(string $tanggalMulai, string $jamMulai, string $satuan, int $durasi): Carbon
    {
        $mulai = Carbon::parse($tanggalMulai.' '.$jamMulai);

        return match ($satuan) {
            'jam' => $mulai->copy()->addHours($durasi),
            '12jam' => $mulai->copy()->addHours(12 * $durasi),
            default => $mulai->copy()->addDays($durasi),
        };
    }

    public function getJadwalMulaiAttribute(): ?Carbon
    {
        return $this->tanggal_mulai
            ? Carbon::parse($this->tanggal_mulai->toDateString().' '.($this->jam_mulai ?: '00:00'))
            : null;
    }

    public function getJadwalSelesaiAttribute(): ?Carbon
    {
        if ($this->tanggal_selesai) {
            return Carbon::parse($this->tanggal_selesai->toDateString().' '.($this->jam_selesai ?: '00:00'));
        }

        // Baris lama yang tersimpan sebelum kolomnya ada tetap bisa dibaca.
        return $this->tanggal_mulai
            ? self::hitungSelesai($this->tanggal_mulai->toDateString(), $this->jam_mulai ?: '00:00', $this->satuan, (int) $this->durasi)
            : null;
    }

    /**
     * Terlambat berapa menit — dihitung dari waktu pengembalian sebenarnya,
     * atau dari sekarang bila unit belum kembali.
     */
    public function getTerlambatMenitAttribute(): int
    {
        $tenggat = $this->jadwal_selesai;

        if (! $tenggat || in_array($this->status, ['selesai', 'batal'], true) && ! $this->dikembalikan_pada) {
            return 0;
        }

        $pembanding = $this->dikembalikan_pada ?: now();

        return max(0, (int) $tenggat->diffInMinutes($pembanding, false));
    }

    public function getTerlambatAttribute(): bool
    {
        return $this->terlambat_menit > config('orcha.denda_sewa.tenggang_menit');
    }

    /**
     * Denda keterlambatan yang DIUSULKAN sistem.
     *
     * Angka ini usulan, bukan keputusan: admin yang menetapkan berapa yang
     * benar-benar ditagih, karena alasan telat kadang memang di luar kuasa
     * penyewa. Yang penting angkanya bisa dijelaskan asal-usulnya.
     */
    public function getDendaKeterlambatanUsulanAttribute(): int
    {
        $aturan = config('orcha.denda_sewa');
        $lewat = $this->terlambat_menit - $aturan['tenggang_menit'];

        if ($lewat <= 0) {
            return 0;
        }

        $tarifHarian = $this->kendaraan?->price_per_day ?? 0;

        if ($tarifHarian <= 0) {
            return 0;
        }

        $jam = (int) ceil($lewat / 60);
        $perJam = $tarifHarian * $aturan['persen_tarif_harian_per_jam'] / 100;
        $maksimalPerHari = $tarifHarian * $aturan['maksimal_persen_per_hari'] / 100;

        // Batas atas dihitung per hari keterlambatan, bukan sekali untuk
        // seluruhnya: telat tiga hari memang tiga kali lipat.
        $hari = (int) ceil($jam / 24);

        return (int) round(min($jam * $perJam, $hari * $maksimalPerHari));
    }

    /**
     * Denda kerusakan yang DIUSULKAN dari hasil pemeriksaan.
     *
     * Admin tidak perlu menaksir dari nol setiap kali: ceklis fisik yang sudah
     * diisi langsung menjadi satu angka, dan tiap barisnya bisa ditunjukkan ke
     * penyewa. Hanya bagian yang memburuk selama masa sewa yang dihitung —
     * lecet lama tidak pernah ikut.
     *
     * Angka ini usulan, bukan tagihan: nota bengkel yang sebenarnya selalu
     * menang, dan admin bebas mengubahnya.
     */
    public function getDendaKerusakanUsulanAttribute(): int
    {
        $tarif = config('orcha.biaya_kerusakan');
        $urutan = array_keys(config('orcha.kondisi_pemeriksaan'));
        $awal = $this->kondisi_awal ?? [];
        $jumlah = 0;

        foreach ($this->kondisi_akhir ?? [] as $bagian => $sesudah) {
            $sebelum = $awal[$bagian] ?? 'baik';

            if (array_search($sesudah, $urutan, true) <= array_search($sebelum, $urutan, true)) {
                continue;
            }

            // Yang ditagih selisihnya, bukan biaya penuh: unit yang diserahkan
            // sudah lecet lalu kembali rusak tidak pantas ditagih seolah
            // sebelumnya mulus.
            $jumlah += max(0, ($tarif[$bagian][$sesudah] ?? 0) - ($tarif[$bagian][$sebelum] ?? 0));
        }

        return (int) $jumlah;
    }

    /**
     * Rincian usulan denda kerusakan, satu baris per bagian.
     *
     * @return array<int, array{bagian: string, dari: string, jadi: string, biaya: int}>
     */
    public function getRincianDendaKerusakanAttribute(): array
    {
        $tarif = config('orcha.biaya_kerusakan');
        $urutan = array_keys(config('orcha.kondisi_pemeriksaan'));
        $awal = $this->kondisi_awal ?? [];
        $baris = [];

        foreach ($this->kondisi_akhir ?? [] as $bagian => $sesudah) {
            $sebelum = $awal[$bagian] ?? 'baik';

            if (array_search($sesudah, $urutan, true) <= array_search($sebelum, $urutan, true)) {
                continue;
            }

            $baris[] = [
                'bagian' => config('orcha.pemeriksaan_kendaraan')[$bagian] ?? $bagian,
                'dari' => config('orcha.kondisi_pemeriksaan')[$sebelum] ?? $sebelum,
                'jadi' => config('orcha.kondisi_pemeriksaan')[$sesudah] ?? $sesudah,
                'biaya' => max(0, ($tarif[$bagian][$sesudah] ?? 0) - ($tarif[$bagian][$sebelum] ?? 0)),
            ];
        }

        return $baris;
    }

    public function getTotalDendaAttribute(): int
    {
        return (int) ($this->denda_keterlambatan + $this->denda_kerusakan + $this->denda_lain);
    }

    public function getTotalTagihanAttribute(): int
    {
        return (int) $this->estimasi_biaya + $this->total_denda;
    }

    /**
     * Kerusakan yang BARU muncul selama masa sewa.
     *
     * Inilah alasan kondisi disimpan per bagian: yang ditagihkan ke penyewa
     * hanya bagian yang kondisinya memburuk dibanding saat unit diserahkan.
     * Lecet yang sudah ada sejak awal tidak ikut terhitung.
     *
     * @return array<int, array{bagian: string, dari: string, jadi: string}>
     */
    public function getKerusakanBaruAttribute(): array
    {
        $awal = $this->kondisi_awal ?? [];
        $akhir = $this->kondisi_akhir ?? [];

        if ($akhir === []) {
            return [];
        }

        $urutan = array_keys(config('orcha.kondisi_pemeriksaan'));
        $nilai = fn ($kondisi) => array_search($kondisi, $urutan, true);

        $baru = [];

        foreach ($akhir as $bagian => $sesudah) {
            $sebelum = $awal[$bagian] ?? 'baik';

            if ($nilai($sesudah) > $nilai($sebelum)) {
                $baru[] = [
                    'bagian' => config('orcha.pemeriksaan_kendaraan')[$bagian] ?? $bagian,
                    'dari' => config('orcha.kondisi_pemeriksaan')[$sebelum] ?? $sebelum,
                    'jadi' => config('orcha.kondisi_pemeriksaan')[$sesudah] ?? $sesudah,
                ];
            }
        }

        return $baru;
    }
}
