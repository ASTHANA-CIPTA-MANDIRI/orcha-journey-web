<?php

namespace App\Models\Rab;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu hal yang bisa dibayar dalam sebuah perjalanan, berikut cara
 * menghitungnya.
 *
 * Dirawat admin. Harganya BUKAN harga yang dipakai penawaran — penawaran
 * membekukan salinannya sendiri di RabBiaya saat baris itu ditambahkan.
 * Mengubah harga di sini hanya memengaruhi RAB yang dibuat sesudahnya.
 */
class MasterHarga extends Model
{
    protected $table = 'tbl_master_harga';

    protected $fillable = [
        'kategori', 'vendor_id', 'nama', 'provinsi', 'daerah', 'destinasi', 'destinasi_lain',
        'satuan', 'harga', 'harga_akhir_pekan', 'kapasitas', 'berlaku_sampai',
        'otomatis', 'aktif', 'catatan',
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'harga' => 'integer',
        'harga_akhir_pekan' => 'integer',
        'kapasitas' => 'integer',
        'berlaku_sampai' => 'date',
        'otomatis' => 'boolean',
        'aktif' => 'boolean',
        'destinasi_lain' => 'array',
    ];

    /**
     * Baris biaya RAB yang berasal dari harga ini.
     *
     * Dipakai menjawab "menaikkan harga ini menyentuh berapa penawaran" —
     * pertanyaan yang selalu ada di kepala sebelum mengubah angka, dan
     * selama ini dijawab dengan menebak.
     */
    public function biaya(): HasMany
    {
        return $this->hasMany(RabBiaya::class, 'master_harga_id');
    }

    /**
     * Tiap kali ANGKANYA berubah, terurut dari yang terbaru.
     *
     * Diurutkan menurut id, bukan created_at: beberapa perubahan dalam satu
     * detik yang sama — misalnya kenaikan massal — punya created_at identik,
     * dan urutannya jadi acak.
     */
    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatHarga::class, 'master_harga_id')->orderByDesc('id');
    }

    /** Dari siapa harga ini dibeli; nomornya dibaca hidup dari sana. */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->nama = trim(preg_replace('/\s+/', ' ', (string) $m->nama));
            $m->provinsi = trim((string) $m->provinsi) ?: null;
            $m->daerah = trim((string) $m->daerah) ?: null;
            $m->destinasi = trim((string) $m->destinasi) ?: null;
        });
    }

    /**
     * Yang berlaku untuk sebuah provinsi: miliknya sendiri DAN yang tanpa
     * provinsi. Asuransi perjalanan tidak berbeda antara Jogja dan Bali, dan
     * memaksa admin mengisinya ulang per provinsi berarti satu harga yang
     * dirawat di sepuluh tempat.
     */
    /**
     * Seluruh destinasi yang tercakup harga ini, sudah dirapikan.
     *
     * Tiket terusan berlaku untuk beberapa tempat sekaligus dan dibayar
     * sekali — Candi Arjuna dan Kawah Sikidang satu tiket. `destinasi` tetap
     * yang utama; sisanya hidup di `destinasi_lain`.
     *
     * SATU-SATUNYA tempat daftar itu dirakit. Penjodohan destinasi tersebar
     * di belasan titik — pencarian tempat, katalog, tarik biaya, pembuang
     * tiket yatim, pil hari — dan masing-masing merakitnya sendiri berarti
     * suatu hari ada satu yang lupa membaca destinasi lainnya, lalu tiket
     * terusan terhapus di sana saja.
     *
     * @return array<int, string>
     */
    public function semuaDestinasi(): array
    {
        return collect([$this->destinasi])
            ->merge($this->destinasi_lain ?? [])
            ->map(fn ($d) => trim((string) $d))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Destinasi ini tercakup harga tersebut?
     *
     * Dicocokkan tanpa memedulikan huruf besar-kecil dan spasi di ujung,
     * dengan alasan yang sama seperti di tarikBiaya(): nama destinasi pernah
     * diketik bebas, jadi "Candi Arjuna " dan "candi arjuna" memang ada di
     * data.
     */
    public function mencakup(?string $destinasi): bool
    {
        $rapi = fn (?string $t) => mb_strtolower(trim((string) $t));
        $cari = $rapi($destinasi);

        if ($cari === '') {
            return false;
        }

        foreach ($this->semuaDestinasi() as $d) {
            if ($rapi($d) === $cari) {
                return true;
            }
        }

        return false;
    }

    public function scopeUntukProvinsi(Builder $q, ?string $provinsi): Builder
    {
        return $q->where('aktif', true)->where(
            fn ($s) => $s->whereNull('provinsi')->orWhere('provinsi', $provinsi)
        );
    }

    /**
     * Harga yang masa berlakunya sudah lewat.
     *
     * Yang tidak diisi tanggalnya TIDAK ikut: kosong berarti "tidak ada
     * batas", bukan "sudah lewat". Memperlakukannya sebagai kedaluwarsa
     * akan menandai hampir seluruh daftar dan membuat tandanya tak berarti.
     */
    public function scopeKedaluwarsa(Builder $q): Builder
    {
        return $q->whereNotNull('berlaku_sampai')->whereDate('berlaku_sampai', '<', now()->toDateString());
    }

    public function getKedaluwarsaAttribute(): bool
    {
        return $this->berlaku_sampai !== null && $this->berlaku_sampai->isBefore(now()->startOfDay());
    }

    public function getBerlakuSampaiTeksAttribute(): ?string
    {
        return $this->berlaku_sampai?->locale('id')->translatedFormat('j F Y');
    }

    /**
     * Harga yang berlaku pada sebuah tanggal, berikut sebabnya.
     *
     * Satu-satunya tempat yang memutuskannya. Pemilihan harga yang tersebar
     * di beberapa tempat akan berbeda tafsir tentang apa itu "akhir pekan",
     * dan selisihnya baru ketahuan saat tagihan vendor datang.
     *
     * Tanggal kosong atau harga akhir pekan yang tidak diisi memulangkan
     * harga biasa tanpa sebab — itu yang membuat seluruh baris master lama
     * berperilaku persis seperti sebelumnya.
     *
     * @param  array<string, string>|null  $libur  peta dari HariLibur::peta()
     * @return array{harga: int, sebab: string|null, akhir_pekan: bool}
     */
    public function hargaUntuk(?Carbon $tanggal, ?array $libur = null): array
    {
        $biasa = ['harga' => (int) $this->harga, 'sebab' => null, 'akhir_pekan' => false];

        if ($this->harga_akhir_pekan === null) {
            return $biasa;
        }

        $hari = HariLibur::periksa($tanggal, $libur);

        if (! $hari['akhir_pekan']) {
            return $biasa;
        }

        return [
            'harga' => (int) $this->harga_akhir_pekan,
            'sebab' => 'harga akhir pekan ('.$hari['sebab'].', '
                .$tanggal->locale('id')->translatedFormat('j M').')',
            'akhir_pekan' => true,
        ];
    }

    /** Apakah harga ini berbeda antara hari kerja dan akhir pekan. */
    public function getDuaHargaAttribute(): bool
    {
        return $this->harga_akhir_pekan !== null
            && (int) $this->harga_akhir_pekan !== (int) $this->harga;
    }

    public function getKategoriLabelAttribute(): string
    {
        return config("orcha.rab.kategori.{$this->kategori}") ?? ucfirst($this->kategori);
    }

    public function getSatuanLabelAttribute(): string
    {
        return config("orcha.rab.satuan.{$this->satuan}.label") ?? $this->satuan;
    }
}
