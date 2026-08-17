<?php

namespace App\Models\SewaKendaraan;

use App\Support\SewaKendaraan\NomorPolisi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'brand',
        'type',
        'nopol',
        'price_per_day',
        'harga_per_jam',
        'harga_12_jam',
        'harga_sopir',
        'transmission',
        'transmisi_tersedia',
        'capacity',
        'image',
        'is_available',
        'kondisi_terkini',
        'kondisi_diperiksa_pada',
        'kondisi_catatan',
        'varian',
        'tahun',
        'cc',
        'lepas_kunci',
        'termasuk_bbm',
        'biaya_bbm',
        'termasuk_tol',
        'biaya_tol',
        'termasuk_parkir',
        'biaya_parkir',
        'termasuk_sopir',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'transmisi_tersedia' => 'array',
        'price_per_day' => 'integer',
        'harga_per_jam' => 'integer',
        'harga_12_jam' => 'integer',
        'harga_sopir' => 'integer',
        'kondisi_terkini' => 'array',
        'kondisi_diperiksa_pada' => 'datetime',
        'lepas_kunci' => 'boolean',
        'termasuk_bbm' => 'boolean',
        'termasuk_tol' => 'boolean',
        'termasuk_parkir' => 'boolean',
        'termasuk_sopir' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $mobil) {
            if (blank($mobil->uuid)) {
                $mobil->uuid = (string) Str::uuid();
            }

            if (blank($mobil->transmisi_tersedia)) {
                $mobil->transmisi_tersedia = [$mobil->transmission];
            }
        });
    }

    /**
     * Label jenis kendaraan yang tampil di landing page & admin (Mobil/HiAce/Bus).
     */
    public function getTypeLabelAttribute(): string
    {
        return config('orcha.jenis_kendaraan')[$this->type] ?? 'Mobil';
    }

    /**
     * Transmisi yang benar-benar tersedia untuk unit ini. Dipakai kartu daftar
     * dan formulir pemesanan supaya pelanggan tidak salah menduga.
     */
    public function getTransmisiTersediaListAttribute(): array
    {
        $daftar = $this->transmisi_tersedia;

        return ! empty($daftar) ? array_values($daftar) : array_filter([$this->transmission]);
    }

    public function getTransmisiLabelAttribute(): string
    {
        return implode(' & ', $this->transmisi_tersedia_list) ?: '—';
    }

    public function getPunyaDuaTransmisiAttribute(): bool
    {
        return count($this->transmisi_tersedia_list) > 1;
    }

    /**
     * Tarif per satuan waktu. Nilai null berarti satuan itu tidak dijual.
     */
    public function tarif(string $satuan): ?int
    {
        return match ($satuan) {
            'jam' => $this->harga_per_jam,
            '12jam' => $this->harga_12_jam,
            default => $this->price_per_day,
        };
    }

    /**
     * Perkiraan biaya sewa. Angka final tetap dikonfirmasi tim, karena BBM,
     * tol, dan biaya lokasi dihitung terpisah.
     */
    public function estimasiBiaya(string $satuan, int $durasi, bool $denganSopir = false): ?int
    {
        $tarif = $this->tarif($satuan);

        if ($tarif === null || $durasi < 1) {
            return null;
        }

        $total = $tarif * $durasi;

        // Sopir dan biaya operasional sama-sama dihitung harian; sewa per jam
        // tetap terhitung satu hari kerja.
        $hari = match ($satuan) {
            'hari' => $durasi,
            default => 1,
        };

        // Tarif yang sudah termasuk sopir tidak ditambahi lagi. Menambahkannya
        // berarti menagih sopir dua kali untuk unit yang harganya justru sudah
        // dihitung bersama sopirnya.
        if ($denganSopir && ! $this->termasuk_sopir && $this->harga_sopir) {
            $total += $this->harga_sopir * $hari;
        }

        // Biaya pos yang termasuk ikut dihitung karena penyewa memang akan
        // membayarnya. Perkiraan yang melewatkannya menampilkan angka lebih
        // rendah daripada yang ditagihkan — dan selisih itu baru ketahuan saat
        // pembayaran.
        $total += $this->biaya_operasional_total * $hari;

        return (int) $total;
    }

    public function scopeOfType($query, ?string $type)
    {
        return $query->when($type, fn ($q) => $q->where('type', $type));
    }

    public function penyewaan(): HasMany
    {
        return $this->hasMany(PenyewaanKendaraan::class);
    }

    /**
     * Sebutan lengkap unit: "Toyota Agya G 2025 · 1.200 cc".
     *
     * Dirakit dari kolom terpisah, bukan dibaca dari satu kolom nama yang sudah
     * dijejali semuanya. Bagian yang belum diketahui dilewati, jadi unit lama
     * yang tahun dan cc-nya kosong tetap terbaca wajar sebagai "Toyota Avanza".
     */
    public function getSebutanLengkapAttribute(): string
    {
        $bagian = array_filter([
            trim((string) $this->brand),
            trim((string) $this->name),
            trim((string) $this->varian),
            $this->tahun ? (string) $this->tahun : null,
        ]);

        $sebutan = implode(' ', $bagian);

        return $this->cc
            ? $sebutan.' · '.number_format($this->cc, 0, ',', '.').' cc'
            : $sebutan;
    }

    /**
     * Kursi total unit, termasuk kursi sopir.
     *
     * capacity menyimpan kursi PENUMPANG — angka yang dipakai menjawab "muat
     * berapa orang?" dan yang tertulis di penawaran. Untuk unit yang selalu
     * dengan sopir, kursi sopirnya ditambahkan kembali di sini supaya spesifikasi
     * pabriknya tetap bisa disebut: "14 penumpang dari 15 kursi".
     *
     * Sebelumnya kebalikannya — capacity menyimpan kursi total dan penumpang
     * dihitung belakangan. Itu keliru arah: yang paling sering dibaca dan
     * dijanjikan adalah jumlah penumpang, jadi itulah yang seharusnya tersimpan
     * apa adanya, bukan yang harus dihitung ulang tiap kali dipakai.
     */
    public function getKursiTotalAttribute(): int
    {
        $kursi = (int) $this->capacity;

        return $this->lepas_kunci ? $kursi : $kursi + 1;
    }

    public function getLepasKunciLabelAttribute(): string
    {
        return $this->lepas_kunci ? 'Boleh lepas kunci' : 'Selalu dengan sopir';
    }

    /**
     * Nomor polisi selalu tersimpan kapital dan berspasi baku.
     *
     * Dipasang sebagai mutator, bukan dibersihkan di controller, supaya jalur
     * mana pun ikut terkena — API, admin, seeder, maupun perbaikan lewat tinker.
     * Membersihkan di satu controller saja berarti jalur lain tetap bisa
     * memasukkan "ab-4169-te" dan pencarian nopol kembali tidak dapat diandalkan.
     */
    public function setNopolAttribute($nilai): void
    {
        $this->attributes['nopol'] = NomorPolisi::rapikan($nilai);
    }

    /**
     * Keadaan tiap pos biaya, urut sesuai config.
     *
     * @return array<string, array{label: string, termasuk: bool, biaya: int}>
     */
    public function getRincianOperasionalAttribute(): array
    {
        $hasil = [];

        foreach ((array) config('orcha.pos_operasional', []) as $pos => $label) {
            $hasil[$pos] = [
                'label' => $label,
                'termasuk' => (bool) $this->{"termasuk_{$pos}"},
                'biaya' => (int) $this->{"biaya_{$pos}"},
            ];
        }

        return $hasil;
    }

    /**
     * Jumlah biaya harian dari pos yang termasuk saja.
     *
     * Biaya pada pos yang TIDAK termasuk diabaikan, bukan dijumlahkan. Angka yang
     * tertinggal di sana bukan tagihan — pos itu ditanggung penyewa.
     */
    public function getBiayaOperasionalTotalAttribute(): int
    {
        return collect($this->rincian_operasional)
            ->filter(fn ($pos) => $pos['termasuk'])
            ->sum('biaya');
    }

    /**
     * Keterangan yang menyebut pos mana termasuk dan mana tidak.
     *
     * Dirakit di satu tempat supaya kartu publik, halaman pemesanan, dan admin
     * menyebutkan hal yang sama.
     */
    public function getOperasionalLabelAttribute(): string
    {
        $rincian = collect($this->rincian_operasional);
        $termasuk = $rincian->filter(fn ($pos) => $pos['termasuk'])->pluck('label');
        $ditanggung = $rincian->reject(fn ($pos) => $pos['termasuk'])->pluck('label');

        if ($termasuk->isEmpty()) {
            return self::awalKapital(self::rangkai($ditanggung->all()).' ditanggung penyewa');
        }

        $total = $this->biaya_operasional_total;
        $kalimat = self::awalKapital(self::rangkai($termasuk->all()).' termasuk'
            .($total > 0 ? ' (+'.$this->rupiah($total).'/hari)' : ''));

        // Pos yang ditanggung penyewa ikut disebut. Menyebut yang termasuk saja
        // membuat sisanya harus disimpulkan sendiri, dan yang disimpulkan tidak
        // bisa dijadikan pegangan saat menagih.
        return $ditanggung->isEmpty()
            ? $kalimat
            : $kalimat.' · '.self::rangkai($ditanggung->all()).' ditanggung penyewa';
    }

    private static function awalKapital(string $kalimat): string
    {
        return mb_strtoupper(mb_substr($kalimat, 0, 1)).mb_substr($kalimat, 1);
    }

    /**
     * Merangkai daftar dengan "dan" di penghubung terakhir: "BBM, tol, dan parkir".
     *
     * @param  list<string>  $bagian
     */
    private static function rangkai(array $bagian): string
    {
        // Label di config berhuruf besar karena dipakai sebagai label isian
        // ("Tol", "Parkir"). Di tengah kalimat huruf besar itu salah, jadi
        // dikecilkan — kecuali akronim seperti BBM, yang justru salah bila
        // dikecilkan.
        $bagian = array_map(
            fn (string $kata) => $kata === mb_strtoupper($kata) ? $kata : mb_strtolower($kata),
            $bagian,
        );

        if (count($bagian) <= 1) {
            return $bagian[0] ?? '';
        }

        $akhir = array_pop($bagian);

        return count($bagian) === 1
            ? $bagian[0].' dan '.$akhir
            : implode(', ', $bagian).', dan '.$akhir;
    }

    /**
     * Keterangan soal sopir: sudah termasuk, tambahan, atau tidak tersedia.
     *
     * Tiga keadaan yang berbeda maknanya, dan sebelumnya dua di antaranya
     * dinyatakan dengan cara yang sama — harga_sopir kosong.
     */
    public function getSopirLabelAttribute(): string
    {
        if ($this->termasuk_sopir) {
            return 'Harga sudah termasuk sopir';
        }

        if ($this->harga_sopir) {
            return 'Sopir +'.$this->rupiah($this->harga_sopir).'/hari';
        }

        // Unit yang selalu dengan sopir TIDAK boleh sampai di sini: validasinya
        // mewajibkan salah satu dari kedua keadaan di atas. Kalimat ini untuk
        // unit lepas kunci yang memang tidak melayani sewa dengan sopir.
        return 'Tanpa sopir';
    }

    private function rupiah(int $angka): string
    {
        return 'Rp '.number_format($angka, 0, ',', '.');
    }
}
