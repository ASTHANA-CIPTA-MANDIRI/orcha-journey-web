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
        'harga_luar_kota',
        // Aturan biaya untuk perjalanan luar kota. Kolom tanpa awalan "luar_"
        // di atas berarti dalam kota.
        'luar_termasuk_bbm',
        'luar_biaya_bbm',
        'luar_termasuk_tol',
        'luar_biaya_tol',
        'luar_termasuk_parkir',
        'luar_biaya_parkir',
        'luar_termasuk_sopir',
        'luar_harga_sopir',
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
        'luar_termasuk_bbm' => 'boolean',
        'luar_termasuk_tol' => 'boolean',
        'luar_termasuk_parkir' => 'boolean',
        'luar_termasuk_sopir' => 'boolean',
        'luar_harga_sopir' => 'integer',
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

            $mobil->warisiAturanLuarKota();
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
     *
     * Totalnya dijumlahkan DARI rinciannya, bukan dihitung ulang tersendiri.
     * Dua hitungan sejajar untuk angka yang sama pasti berselisih suatu saat —
     * dan yang dilihat penyewa adalah rinciannya, sementara yang tersimpan
     * totalnya, jadi selisihnya baru ketahuan saat menagih.
     */
    public function estimasiBiaya(string $satuan, int $durasi, bool $denganSopir = false, bool $luarKota = false): ?int
    {
        $rincian = $this->rincianEstimasi($satuan, $durasi, $denganSopir, $luarKota);

        if ($rincian === []) {
            return null;
        }

        return (int) array_sum(array_column($rincian, 'jumlah'));
    }

    /**
     * Perincian perkiraan biaya, baris demi baris.
     *
     * Angka tunggal tanpa perincian membuat penyewa bertanya "kok segitu?" —
     * lalu menanyakannya lewat WhatsApp satu per satu. Tiap baris menyebut
     * pengalinya sendiri, karena sopir dan biaya operasional dihitung HARIAN
     * sementara tarifnya bisa per jam.
     *
     * Kosong berarti tidak bisa diperkirakan: satuan yang tidak dijual unit ini,
     * atau lama sewa yang belum masuk akal.
     *
     * @return array<int, array{label: string, keterangan: string, jumlah: int}>
     */
    public function rincianEstimasi(string $satuan, int $durasi, bool $denganSopir = false, bool $luarKota = false): array
    {
        $tarif = $luarKota ? $this->tarif_luar_kota : $this->tarif($satuan);

        if ($tarif === null || $durasi < 1) {
            return [];
        }

        // Sopir dan biaya operasional sama-sama dihitung harian; sewa per jam
        // tetap terhitung satu hari kerja.
        $hari = match ($satuan) {
            'hari' => $durasi,
            default => 1,
        };

        $satuanKata = $luarKota
            ? 'hari'
            : (config("orcha.satuan_sewa.{$satuan}.satuan") ?? 'hari');

        $rincian = [[
            'label' => $luarKota ? 'Tarif luar kota' : 'Tarif sewa',
            'keterangan' => $this->kali($tarif, $durasi, $satuanKata),
            'jumlah' => (int) ($tarif * $durasi),
        ]];

        // Aturan sopir dan pos biaya dibaca menurut WILAYAHNYA. Unit yang dalam
        // kota diserahkan apa adanya bisa ditawarkan sepaket bersama sopir dan
        // BBM untuk perjalanan luar kota — dan sebaliknya. Membaca aturan dalam
        // kota untuk pesanan luar kota menghasilkan perkiraan yang tidak pernah
        // cocok dengan tagihannya.
        $sopirTermasuk = $this->termasukSopir($luarKota);
        $tarifSopir = $this->hargaSopir($luarKota);

        // Tarif yang sudah termasuk sopir tidak ditambahi lagi. Menambahkannya
        // berarti menagih sopir dua kali untuk unit yang harganya justru sudah
        // dihitung bersama sopirnya.
        if ($denganSopir && ! $sopirTermasuk && $tarifSopir) {
            $rincian[] = [
                'label' => 'Sopir',
                'keterangan' => $this->kali($tarifSopir, $hari, 'hari'),
                'jumlah' => $tarifSopir * $hari,
            ];
        }

        // Biaya pos yang termasuk ikut dihitung karena penyewa memang akan
        // membayarnya. Perkiraan yang melewatkannya menampilkan angka lebih
        // rendah daripada yang ditagihkan — dan selisih itu baru ketahuan saat
        // pembayaran. Disebut nama posnya, bukan "biaya operasional": penyewa
        // berhak tahu yang dibayarnya BBM atau parkir.
        foreach ($this->rincianOperasional($luarKota) as $pos) {
            if (! $pos['termasuk'] || $pos['biaya'] < 1) {
                continue;
            }

            $rincian[] = [
                'label' => $pos['label'],
                'keterangan' => $this->kali((int) $pos['biaya'], $hari, 'hari'),
                'jumlah' => (int) ($pos['biaya'] * $hari),
            ];
        }

        return $rincian;
    }

    private function kali(int $satuanHarga, int $banyak, string $satuanKata): string
    {
        return 'Rp '.number_format($satuanHarga, 0, ',', '.')." × {$banyak} {$satuanKata}";
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
     * Aturan luar kota yang TIDAK disebut mengikuti aturan dalam kota.
     *
     * Kolom luar_* punya nilai bawaan false/null di basis data. Untuk unit yang
     * dibuat tanpa menyebutnya — seeder, tinker, atau pemanggil lama — bawaan
     * itu berarti "di luar kota semuanya ditanggung penyewa", pernyataan yang
     * tidak pernah dimaksudkan siapa pun dan berbeda dari unit yang sama di
     * dalam kota.
     *
     * Aturan yang sama sudah berlaku di controller API dan pada migrasi yang
     * menyalin data lama. Dipasang di sini supaya JALUR MANA PUN ikut terkena,
     * bukan hanya jalur yang kebetulan diingat.
     */
    private function warisiAturanLuarKota(): void
    {
        $tersedia = $this->getAttributes();

        $penanda = ['termasuk_sopir'];
        $nominal = ['harga_sopir'];

        foreach (array_keys((array) config('orcha.pos_operasional', [])) as $pos) {
            $penanda[] = "termasuk_{$pos}";
            $nominal[] = "biaya_{$pos}";
        }

        // Penanda tidak boleh null di basis data, dan nilai dalam kotanya
        // sendiri bisa belum terisi — unit yang dibuat tanpa menyebut satu pun
        // pos biaya. Bawaannya false: tidak termasuk.
        foreach ($penanda as $medan) {
            if (! array_key_exists("luar_{$medan}", $tersedia)) {
                $this->{"luar_{$medan}"} = (bool) ($this->{$medan} ?? false);
            }
        }

        foreach ($nominal as $medan) {
            if (! array_key_exists("luar_{$medan}", $tersedia)) {
                $this->{"luar_{$medan}"} = $this->{$medan} ?? null;
            }
        }
    }

    /**
     * Nama kolom untuk wilayah yang diminta.
     *
     * Kolom tanpa awalan berarti dalam kota; yang berawalan "luar_" untuk
     * perjalanan luar kota. Dipusatkan di sini supaya tidak ada satu pun tempat
     * yang membaca kolom dalam kota padahal pesanannya ke luar kota — kesalahan
     * yang tidak terlihat sampai tagihannya berselisih.
     */
    private function medan(string $nama, bool $luarKota): string
    {
        return $luarKota ? "luar_{$nama}" : $nama;
    }

    /**
     * Keadaan tiap pos biaya, urut sesuai config.
     *
     * @return array<string, array{label: string, termasuk: bool, biaya: int}>
     */
    public function rincianOperasional(bool $luarKota = false): array
    {
        $hasil = [];

        foreach ((array) config('orcha.pos_operasional', []) as $pos => $label) {
            $hasil[$pos] = [
                'label' => $label,
                'termasuk' => (bool) $this->{$this->medan("termasuk_{$pos}", $luarKota)},
                'biaya' => (int) $this->{$this->medan("biaya_{$pos}", $luarKota)},
            ];
        }

        return $hasil;
    }

    /** Aksesor lama tetap berarti DALAM kota. */
    public function getRincianOperasionalAttribute(): array
    {
        return $this->rincianOperasional(false);
    }

    /**
     * Apakah aturan biayanya memang berbeda antara dalam dan luar kota.
     *
     * Dipakai untuk memutuskan perlu-tidaknya menyebut keduanya. Menuliskan dua
     * kalimat yang isinya sama hanya memanjangkan halaman tanpa menambah
     * keterangan.
     */
    public function getBedaAturanLuarKotaAttribute(): bool
    {
        return $this->rincianOperasional(true) !== $this->rincianOperasional(false)
            || $this->termasukSopir(true) !== $this->termasukSopir(false)
            || $this->hargaSopir(true) !== $this->hargaSopir(false);
    }

    /**
     * Jumlah biaya harian dari pos yang termasuk saja.
     *
     * Biaya pada pos yang TIDAK termasuk diabaikan, bukan dijumlahkan. Angka yang
     * tertinggal di sana bukan tagihan — pos itu ditanggung penyewa.
     */
    public function biayaOperasionalTotal(bool $luarKota = false): int
    {
        return collect($this->rincianOperasional($luarKota))
            ->filter(fn ($pos) => $pos['termasuk'])
            ->sum('biaya');
    }

    public function getBiayaOperasionalTotalAttribute(): int
    {
        return $this->biayaOperasionalTotal(false);
    }

    /**
     * Keterangan yang menyebut pos mana termasuk dan mana tidak.
     *
     * Dirakit di satu tempat supaya kartu publik, halaman pemesanan, dan admin
     * menyebutkan hal yang sama.
     */
    /**
     * Apa saja yang sudah tercakup harga sewanya, pos demi pos.
     *
     * Kalimat sepanjang operasionalLabel() menjawab pertanyaan yang sama, tetapi
     * harus dibaca dulu sampai habis. Yang ditanya penyewa di loket — dan admin
     * yang menjawabnya — cuma satu: BBM ditanggung siapa. Bentuk daftar bisa
     * dijawab dengan melirik.
     *
     * Sopir hanya disebut pada sewa bersopir. Pada lepas kunci ia bukan pos yang
     * "tidak termasuk" melainkan pos yang tidak ada: unitnya memang disetir
     * penyewa sendiri, dan menyebutnya hanya menimbulkan pertanyaan baru.
     *
     * @return array<int, array{label: string, termasuk: bool, catatan: string|null}>
     */
    public function apaSajaTermasuk(bool $luarKota = false, bool $denganSopir = false): array
    {
        $daftar = [];

        if ($denganSopir) {
            // Pada sewa bersopir, sopirnya selalu tercakup harga — yang berbeda
            // hanya caranya: sudah menyatu di tarif, atau ditambahkan sebagai
            // baris tersendiri. Keduanya sama-sama dibayar penyewa lewat kami,
            // jadi menandainya "tidak termasuk" justru menyesatkan.
            $daftar[] = [
                'label' => 'Sopir',
                'termasuk' => true,
                'catatan' => $this->termasukSopir($luarKota)
                    ? 'sudah menyatu di tarif'
                    : ($this->hargaSopir($luarKota)
                        ? 'ditagihkan terpisah, '.$this->rupiah($this->hargaSopir($luarKota)).'/hari'
                        : null),
            ];
        }

        foreach ($this->rincianOperasional($luarKota) as $pos) {
            $daftar[] = [
                'label' => $pos['label'],
                'termasuk' => $pos['termasuk'],
                'catatan' => $pos['termasuk'] && $pos['biaya'] > 0
                    ? 'terhitung '.$this->rupiah((int) $pos['biaya']).'/hari'
                    : null,
            ];
        }

        return $daftar;
    }

    public function operasionalLabel(bool $luarKota = false): string
    {
        $rincian = collect($this->rincianOperasional($luarKota));
        $termasuk = $rincian->filter(fn ($pos) => $pos['termasuk'])->pluck('label');
        $ditanggung = $rincian->reject(fn ($pos) => $pos['termasuk'])->pluck('label');

        if ($termasuk->isEmpty()) {
            return self::awalKapital(self::rangkai($ditanggung->all()).' ditanggung penyewa');
        }

        $total = $this->biayaOperasionalTotal($luarKota);
        $kalimat = self::awalKapital(self::rangkai($termasuk->all()).' termasuk'
            .($total > 0 ? ' (+'.$this->rupiah($total).'/hari)' : ''));

        // Pos yang ditanggung penyewa ikut disebut. Menyebut yang termasuk saja
        // membuat sisanya harus disimpulkan sendiri, dan yang disimpulkan tidak
        // bisa dijadikan pegangan saat menagih.
        return $ditanggung->isEmpty()
            ? $kalimat
            : $kalimat.' · '.self::rangkai($ditanggung->all()).' ditanggung penyewa';
    }

    public function getOperasionalLabelAttribute(): string
    {
        return $this->operasionalLabel(false);
    }

    /**
     * Ringkasan sebaris aturan biaya satu wilayah, untuk kartu daftar armada.
     *
     * Kalimat panjang milik operasionalLabel() terlalu berat untuk kartu yang
     * sudah memuat tarif dan spesifikasi. Yang disebut di sini apa yang
     * TERMASUK — itu yang membedakan satu unit dari keadaan biasa. Tarif sopir
     * yang dihitung terpisah tetap disebut nominalnya: itu tambahan yang paling
     * besar, dan menyembunyikannya di balik kata "ditanggung penyewa" membuat
     * penyewa menghitung terlalu murah.
     */
    public function ringkasanWilayah(bool $luarKota = false): string
    {
        $termasuk = collect($this->rincianOperasional($luarKota))
            ->filter(fn ($pos) => $pos['termasuk'])
            ->pluck('label')
            ->all();

        if ($this->termasukSopir($luarKota)) {
            $termasuk[] = 'sopir';
        }

        $kalimat = $termasuk === []
            ? 'semuanya ditanggung penyewa'
            : self::rangkai($termasuk).' termasuk';

        if (! $this->termasukSopir($luarKota) && $this->hargaSopir($luarKota)) {
            $kalimat .= ' · sopir +'.$this->rupiah($this->hargaSopir($luarKota)).'/hari';
        }

        return $kalimat;
    }

    public function getRingkasanDalamKotaAttribute(): string
    {
        return $this->ringkasanWilayah(false);
    }

    public function getRingkasanLuarKotaAttribute(): string
    {
        return $this->ringkasanWilayah(true);
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
    public function termasukSopir(bool $luarKota = false): bool
    {
        return (bool) $this->{$this->medan('termasuk_sopir', $luarKota)};
    }

    public function hargaSopir(bool $luarKota = false): ?int
    {
        $nilai = $this->{$this->medan('harga_sopir', $luarKota)};

        return $nilai === null ? null : (int) $nilai;
    }

    public function sopirLabel(bool $luarKota = false): string
    {
        if ($this->termasukSopir($luarKota)) {
            return 'Harga sudah termasuk sopir';
        }

        if ($this->hargaSopir($luarKota)) {
            return 'Sopir +'.$this->rupiah($this->hargaSopir($luarKota)).'/hari';
        }

        // Unit yang selalu dengan sopir TIDAK boleh sampai di sini: validasinya
        // mewajibkan salah satu dari kedua keadaan di atas. Kalimat ini untuk
        // unit lepas kunci yang memang tidak melayani sewa dengan sopir.
        return 'Tanpa sopir';
    }

    public function getSopirLabelAttribute(): string
    {
        return $this->sopirLabel(false);
    }

    /**
     * Tarif harian untuk perjalanan luar kota.
     *
     * Kosong berarti tidak dibedakan, jadi tarif dalam kotanya yang dipakai —
     * bukan nol, dan bukan menolak pesanan. Sebagian unit memang tidak
     * membedakan keduanya.
     */
    public function getTarifLuarKotaAttribute(): ?int
    {
        return $this->harga_luar_kota ?: $this->price_per_day;
    }

    public function getPunyaTarifLuarKotaAttribute(): bool
    {
        return (bool) $this->harga_luar_kota
            && (int) $this->harga_luar_kota !== (int) $this->price_per_day;
    }

    public function getLuarKotaLabelAttribute(): string
    {
        return $this->punya_tarif_luar_kota
            ? 'Luar kota '.$this->rupiah((int) $this->harga_luar_kota).'/hari'
            : 'Luar kota tarifnya sama';
    }

    private function rupiah(int $angka): string
    {
        return 'Rp '.number_format($angka, 0, ',', '.');
    }
}
