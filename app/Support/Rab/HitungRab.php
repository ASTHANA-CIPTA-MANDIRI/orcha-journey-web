<?php

namespace App\Support\Rab;

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\RincianBiaya;

/**
 * Seluruh hitungan RAB, di satu tempat.
 *
 * Layar lemon, PDF penawaran, PDF internal, dan "Jadikan Pendaftaran" semuanya
 * membaca dari sini. Hitungan yang diulang di dua tempat akan berbeda suatu
 * hari — dan pada RAB, "berbeda" berarti pelanggan menerima penawaran dengan
 * angka yang tidak sama dengan yang dicatat kantor.
 */
class HitungRab
{
    /**
     * Pengali sebuah satuan untuk RAB ini.
     *
     * unit_hari dan kamar_malam menghitung JUMLAH UNIT dari kapasitasnya:
     * 17 peserta di Hiace berkapasitas 14 butuh dua unit, bukan 1,2. Membulatkan
     * ke bawah berarti tiga orang tidak punya kursi; membulatkan ke atas memang
     * kenyataannya — unit kedua disewa utuh walau setengah kosong.
     */
    public static function faktor(string $satuan, ?int $kapasitas, Rab $rab): int
    {
        $peserta = max(1, (int) $rab->jumlah_peserta);
        $hari = max(1, (int) $rab->jumlah_hari);
        $malam = max(0, (int) $rab->jumlah_malam);
        $unit = $kapasitas && $kapasitas > 0 ? (int) ceil($peserta / $kapasitas) : 1;

        return match ($satuan) {
            'orang' => $peserta,
            'orang_hari' => $peserta * $hari,
            'rombongan' => 1,
            'hari' => $hari,
            'unit_hari' => $unit * $hari,
            'kamar_malam' => $unit * $malam,
            default => 0,
        };
    }

    /**
     * Keterangan pengali dalam kalimat: "Rp 850.000 × 2 unit × 3 hari".
     *
     * Admin yang membaca subtotal Rp 5.100.000 untuk satu baris sewa bus perlu
     * tahu dari mana angka itu datang tanpa membuka kalkulator. Angka tanpa
     * asal-usul adalah angka yang tidak dipercaya — lalu dihitung ulang di
     * kertas, dan kertasnya yang dipakai.
     */
    public static function penjelasan(RabBiaya $b, Rab $rab): string
    {
        $peserta = max(1, (int) $rab->jumlah_peserta);
        $hari = max(1, (int) $rab->jumlah_hari);
        $malam = max(0, (int) $rab->jumlah_malam);
        $unit = $b->kapasitas && $b->kapasitas > 0 ? (int) ceil($peserta / $b->kapasitas) : 1;

        $bagian = [RincianBiaya::rupiah($b->harga_satuan)];

        if ($b->jumlah > 1) {
            $bagian[] = $b->jumlah.'×';
        }

        $bagian = array_merge($bagian, match ($b->satuan) {
            'orang' => [$peserta.' orang'],
            'orang_hari' => [$peserta.' orang', $hari.' hari'],
            'rombongan' => [],
            'hari' => [$hari.' hari'],
            'unit_hari' => [$unit.' unit', $hari.' hari'],
            'kamar_malam' => [$unit.' kamar', $malam.' malam'],
            default => [],
        });

        return implode(' × ', $bagian);
    }

    public static function subtotal(RabBiaya $b, Rab $rab): int
    {
        return (int) $b->harga_satuan * max(1, (int) $b->jumlah)
            * self::faktor($b->satuan, $b->kapasitas, $rab);
    }

    public static function variabel(string $satuan): bool
    {
        return (bool) config("orcha.rab.satuan.{$satuan}.variabel", false);
    }

    /**
     * Margin yang DIMINTA admin, sebelum pembulatan harga.
     *
     * Tiga cara, karena tiga cara itu memang dipakai orang menawar:
     *   persen    — "untung 20% dari modal"
     *   per_orang — "tambah Rp 150.000 per kepala"
     *   total     — "saya mau bersih Rp 3 juta dari rombongan ini"
     */
    public static function margin(Rab $rab, int $modal): int
    {
        $nilai = max(0, (int) $rab->margin_nilai);

        return match ($rab->margin_jenis) {
            'persen' => (int) round($modal * $nilai / 100),
            'per_orang' => $nilai * max(1, (int) $rab->jumlah_peserta),
            'total' => $nilai,
            default => 0,
        };
    }

    /**
     * Ringkasan lengkap sebuah RAB.
     *
     * @return array<string, mixed>
     */
    public static function ringkas(Rab $rab): array
    {
        $rab->loadMissing('biaya.master');
        $peserta = max(1, (int) $rab->jumlah_peserta);

        $baris = $rab->biaya->map(function (RabBiaya $b) use ($rab) {
            $subtotal = self::subtotal($b, $rab);

            return [
                'id' => $b->id,
                'master_harga_id' => $b->master_harga_id,
                'kategori' => $b->kategori,
                'kategori_label' => config("orcha.rab.kategori.{$b->kategori}") ?? ucfirst($b->kategori),
                'nama' => $b->nama,
                'satuan' => $b->satuan,
                'satuan_label' => config("orcha.rab.satuan.{$b->satuan}.label") ?? $b->satuan,
                'harga_satuan' => $b->harga_satuan,
                'kapasitas' => $b->kapasitas,
                'jumlah' => $b->jumlah,
                'faktor' => self::faktor($b->satuan, $b->kapasitas, $rab),
                'penjelasan' => self::penjelasan($b, $rab),
                'subtotal' => $subtotal,
                'subtotal_teks' => RincianBiaya::rupiah($subtotal),
                'variabel' => self::variabel($b->satuan),
                /*
                 | Harga master yang sekarang, bila sudah berbeda dari yang
                 | dibekukan. Harganya TIDAK ikut berubah sendiri — penawaran
                 | yang sudah dikirim tidak boleh bergeser diam-diam — tetapi
                 | admin berhak tahu sebelum mengirim penawaran berikutnya
                 | dengan harga tiket bulan lalu.
                 */
                'harga_master_kini' => $b->master && (int) $b->master->harga !== (int) $b->harga_satuan
                    ? (int) $b->master->harga
                    : null,
            ];
        })->values();

        $modalVariabel = (int) $baris->where('variabel', true)->sum('subtotal');
        $modalTetap = (int) $baris->where('variabel', false)->sum('subtotal');
        $modal = $modalVariabel + $modalTetap;

        $marginDiminta = self::margin($rab, $modal);
        $bulat = max(1, (int) ($rab->pembulatan ?: 1));

        // Dibulatkan KE ATAS: margin boleh sedikit bertambah karena
        // pembulatan, tetapi tidak boleh berkurang diam-diam di bawah yang
        // diminta admin.
        $hargaPerOrang = (int) (ceil(($modal + $marginDiminta) / $peserta / $bulat) * $bulat);
        $hargaTotal = $hargaPerOrang * $peserta;
        $untung = $hargaTotal - $modal;

        /*
         | Pemecahan untuk pendaftaran, dirakit supaya modal totalnya SAMA
         | PERSIS dengan RAB.
         |
         | Pendaftaran menghitung modal = harga_modal × peserta + biaya_tetap.
         | Membagi modal variabel dengan peserta hampir tidak pernah bulat, dan
         | sisa pembagian yang dibuang membuat laporan keuntungan berbeda
         | beberapa rupiah dari RAB yang disetujui pelanggan. Sisanya
         | dipindahkan ke biaya tetap, jadi selisihnya nol.
         */
        $modalPerOrang = intdiv($modalVariabel, $peserta);
        $biayaTetap = $modal - $modalPerOrang * $peserta;

        $perKategori = $baris->groupBy('kategori')->map(fn ($isi, $k) => [
            'kategori' => $k,
            'label' => $isi->first()['kategori_label'],
            'total' => (int) $isi->sum('subtotal'),
            'total_teks' => RincianBiaya::rupiah((int) $isi->sum('subtotal')),
        ])->values();

        $angka = [
            'modal_variabel' => $modalVariabel,
            'modal_tetap' => $modalTetap,
            'modal_total' => $modal,
            'modal_per_kepala' => (int) round($modal / $peserta),
            'margin_diminta' => $marginDiminta,
            'harga_per_orang' => $hargaPerOrang,
            'harga_total' => $hargaTotal,
            'untung' => $untung,
        ];

        return $angka + [
            'baris' => $baris->all(),
            'per_kategori' => $perKategori->all(),
            'persen_untung' => $modal > 0 ? round($untung / $modal * 100, 1) : null,
            'untuk_pendaftaran' => [
                'harga_jual' => $hargaPerOrang,
                'harga_modal' => $modalPerOrang,
                'biaya_tetap' => $biayaTetap,
            ],
            'ada_harga_basi' => $baris->contains(fn ($b) => $b['harga_master_kini'] !== null),
        ] + collect($angka)->mapWithKeys(fn ($v, $k) => [$k.'_teks' => RincianBiaya::rupiah($v)])->all();
    }
}
