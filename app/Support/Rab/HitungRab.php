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

    /**
     * Modal pada jumlah peserta tertentu — BUKAN modal dibagi rata.
     *
     * Biaya tetapnya sendiri ikut berubah saat jumlahnya berubah: peserta
     * ke-9 memaksa kamar ketiga, peserta ke-15 memaksa armada kedua. Membagi
     * modal secara lurus menyembunyikan tangga itu, dan tangga itulah yang
     * membuat satu peserta tambahan kadang justru menambah rugi.
     *
     * @return array{variabel:int, tetap:int, total:int}
     */
    public static function modalPada(Rab $rab, int $peserta): array
    {
        $bayangan = clone $rab;
        $bayangan->jumlah_peserta = max(1, $peserta);

        $variabel = 0;
        $tetap = 0;

        foreach ($rab->biaya as $b) {
            $sub = self::subtotal($b, $bayangan);

            if (self::variabel($b->satuan)) {
                $variabel += $sub;
            } else {
                $tetap += $sub;
            }
        }

        return ['variabel' => $variabel, 'tetap' => $tetap, 'total' => $variabel + $tetap];
    }

    /**
     * Untung-rugi pada tiap jumlah peserta, dengan harga jual yang sudah
     * diumumkan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function simulasi(Rab $rab, int $harga, int $sampai): array
    {
        $hasil = [];

        for ($pax = 1; $pax <= max(1, $sampai); $pax++) {
            $modal = self::modalPada($rab, $pax);
            $masuk = $harga * $pax;

            $hasil[] = [
                'peserta' => $pax,
                'modal' => $modal['total'],
                'modal_tetap' => $modal['tetap'],
                'pemasukan' => $masuk,
                'laba' => $masuk - $modal['total'],
                'modal_teks' => RincianBiaya::rupiah($modal['total']),
                'pemasukan_teks' => RincianBiaya::rupiah($masuk),
                'laba_teks' => RincianBiaya::rupiah($masuk - $modal['total']),
            ];
        }

        return $hasil;
    }

    /**
     * Jumlah peserta terkecil yang sudah tidak rugi, DICARI — bukan dibagi.
     *
     * Biaya tetap ÷ sumbangan per kepala memberi angka yang salah justru di
     * sekitar tangga kamar dan armada: pada contoh tiga hari Banyuwangi ia
     * menjawab 13, padahal pada 12 peserta trip sudah untung.
     *
     * @param  array<int, array<string, mixed>>  $simulasi
     */
    public static function impas(array $simulasi): ?int
    {
        foreach ($simulasi as $baris) {
            if ($baris['laba'] >= 0) {
                return (int) $baris['peserta'];
            }
        }

        return null;
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
    public static function margin(Rab $rab, int $modal, ?int $peserta = null): int
    {
        $nilai = max(0, (int) $rab->margin_nilai);

        return match ($rab->margin_jenis) {
            'persen' => (int) round($modal * $nilai / 100),
            // Per kepala dihitung atas jumlah yang sama dengan pembagi
            // harganya; kalau tidak, "tambah Rp 150.000 per kepala" pada open
            // trip menghasilkan tambahan yang bukan Rp 150.000 per kepala.
            'per_orang' => $nilai * max(1, $peserta ?? (int) $rab->jumlah_peserta),
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
        $rab->loadMissing('biaya.master', 'biaya.vendor');
        $peserta = max(1, (int) $rab->jumlah_peserta);

        $baris = $rab->biaya->map(function (RabBiaya $b) use ($rab) {
            $subtotal = self::subtotal($b, $rab);

            return [
                'id' => $b->id,
                'master_harga_id' => $b->master_harga_id,
                /*
                 | Vendornya dibaca HIDUP, tidak ikut dibekukan seperti
                 | harganya. Yang dibutuhkan saat menelepon selalu nomor yang
                 | berlaku hari ini, bukan nomor yang berlaku saat penawaran
                 | dibuat — dan nomor lama yang tersimpan rapi justru lebih
                 | berbahaya daripada nomor yang kosong.
                 */
                'vendor_id' => $b->vendor_id,
                'vendor' => $b->vendor?->nama,
                'vendor_kontak' => $b->vendor?->kontak,
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
                /*
                 | Selisihnya BERTANDA: negatif berarti baris ini lebih murah
                 | daripada master. Arahnya yang menentukan tawaran mana yang
                 | masuk akal — lebih mahal berarti harga master sudah usang
                 | di RAB ini, lebih murah berarti tim menemukan sesuatu yang
                 | layak dibawa pulang ke master.
                 */
                'selisih_master' => $b->master ? (int) $b->harga_satuan - (int) $b->master->harga : null,
                'catatan' => $b->catatan,
            ];
        })
            /*
             | Dikelompokkan menurut urutan kategori di config — tiket,
             | transportasi, akomodasi, … — bukan urutan baris ditambahkan.
             | Tanpa ini "Pemandu" (biaya wajib, masuk paling dulu) memimpin
             | rincian di layar dan PDF internal, padahal yang dicari mata
             | pertama kali adalah tiket dan armada. sortBy stabil, jadi urutan
             | di dalam satu kategori tetap urutan admin.
             */
            ->sortBy(function ($b) {
                $i = array_search($b['kategori'], array_keys(config('orcha.rab.kategori', [])), true);

                return $i === false ? PHP_INT_MAX : $i;
            })
            ->values();

        $modalVariabel = (int) $baris->where('variabel', true)->sum('subtotal');
        $modalTetap = (int) $baris->where('variabel', false)->sum('subtotal');
        $modal = $modalVariabel + $modalTetap;

        /*
         | Harga dihitung dari jumlah DASAR, modalnya dari jumlah NYATA.
         |
         | Pada private trip keduanya sama — orangnya sudah pasti, dan tidak
         | ada satu rupiah pun yang bergeser dari cara lama.
         |
         | Pada open trip keduanya sengaja berbeda: harga diumumkan lebih dulu
         | atas dasar jumlah paling rendah yang masih mau diberangkatkan,
         | sementara modal yang ditampilkan adalah modal pada jumlah yang
         | ditargetkan. Menyamakan keduanya berarti harga ikut turun tiap kali
         | targetnya dinaikkan — persis kebalikan dari yang dimaksud.
         */
        $pesertaDasar = $rab->pesertaDasar();
        $modalDasar = $pesertaDasar === $peserta
            ? $modal
            : self::modalPada($rab, $pesertaDasar)['total'];

        $marginDiminta = self::margin($rab, $modalDasar, $pesertaDasar);
        $bulat = max(1, (int) ($rab->pembulatan ?: 1));

        // Dibulatkan KE ATAS: margin boleh sedikit bertambah karena
        // pembulatan, tetapi tidak boleh berkurang diam-diam di bawah yang
        // diminta admin.
        $hargaAsli = (int) (ceil(($modalDasar + $marginDiminta) / $pesertaDasar / $bulat) * $bulat);

        /*
         | Harga yang DISEPAKATI menang atas harga yang dihitung.
         |
         | Saat pelanggan menawar, yang diketahui admin adalah angkanya —
         | bukan margin berapa persen yang kebetulan menghasilkan angka itu.
         | Jadi arahnya dibalik: harga nego dipakai apa adanya, TANPA
         | dibulatkan (angka hasil kesepakatan tidak boleh digeser sistem),
         | dan untungnya menjadi hasil hitungan, bukan sebabnya.
         |
         | Nego yang membuat rugi TIDAK ditolak di sini. Kadang memang itu
         | keputusannya — menutup kerugian lewat trip lain, atau menjaga
         | pelanggan lama. Yang wajib adalah angkanya terlihat, dan itu
         | tugas 'nego_rugi' serta 'harga_batas' di bawah.
         */
        $negoAktif = $rab->harga_nego !== null && (int) $rab->harga_nego > 0;
        $hargaPerOrang = $negoAktif ? (int) $rab->harga_nego : $hargaAsli;
        $hargaTotal = $hargaPerOrang * $peserta;
        $untung = $hargaTotal - $modal;

        /*
         | Harga per orang terendah yang masih belum rugi.
         |
         | Dihitung atas jumlah DASAR, sama seperti harga jualnya: pada open
         | trip yang perlu dijaga adalah keadaan saat hanya peserta minimum
         | yang datang, bukan saat targetnya tercapai. Dibulatkan ke atas,
         | karena membulatkan ke bawah menghasilkan batas yang justru sudah
         | rugi beberapa ratus rupiah.
         */
        $hargaBatas = (int) (ceil($modalDasar / $pesertaDasar / $bulat) * $bulat);

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

        /*
         | Titik impas: berapa peserta minimal supaya trip tidak rugi.
         |
         | DICARI dengan menghitung ulang di tiap jumlah, bukan dengan membagi
         | biaya tetap dengan sumbangan per kepala. Pembagian itu memberi
         | angka yang salah justru di sekitar tangga kamar dan armada: pada
         | contoh tiga hari Banyuwangi ia menjawab 13, padahal pada 12 peserta
         | trip sudah untung — dan selisih satu kursi itulah yang dipakai
         | memutuskan berangkat atau tidak.
         |
         | Simulasinya ikut dikirim: angka impas tanpa tabelnya menuntut
         | pembacanya percaya begitu saja.
         */
        $batasSimulasi = max($peserta, $rab->pesertaDasar()) + 2;
        $simulasi = self::simulasi($rab, $hargaPerOrang, $batasSimulasi);
        $bep = self::impas($simulasi);

        $variabelPerOrang = (int) ceil($modalVariabel / $peserta);
        $sumbanganPerOrang = $hargaPerOrang - $variabelPerOrang;

        $angka = [
            'modal_variabel' => $modalVariabel,
            'modal_tetap' => $modalTetap,
            'modal_total' => $modal,
            'modal_per_kepala' => (int) round($modal / $peserta),
            'modal_variabel_per_orang' => $variabelPerOrang,
            'margin_diminta' => $marginDiminta,
            'harga_per_orang' => $hargaPerOrang,
            'harga_total' => $hargaTotal,
            // Harga yang SEHARUSNYA, sebelum kesepakatan. Tetap dikirim walau
            // tidak ada nego: layar memakainya untuk memperlihatkan berapa
            // yang dilepas, dan tanpanya potongan itu tidak punya pembanding.
            'harga_asli' => $hargaAsli,
            'harga_batas' => $hargaBatas,
            'nego_selisih' => $hargaPerOrang - $hargaAsli,
            'untung' => $untung,
            'untung_per_orang' => (int) floor($untung / $peserta),
            'sumbangan_per_orang' => $sumbanganPerOrang,
        ];

        return $angka + [
            'baris' => $baris->all(),
            'per_kategori' => $perKategori->all(),
            'persen_untung' => $modal > 0 ? round($untung / $modal * 100, 1) : null,
            'peserta_dasar' => $rab->pesertaDasar(),
            'simulasi' => $simulasi,
            'bep_peserta' => $bep,
            // Rombongan sekecil ini sudah menutup biaya tetapnya? Pertanyaan
            // yang ditanyakan admin adalah ini, bukan angka BEP-nya sendiri.
            'bep_tercapai' => $bep !== null && $peserta >= $bep,
            'untuk_pendaftaran' => [
                'harga_jual' => $hargaPerOrang,
                'harga_modal' => $modalPerOrang,
                'biaya_tetap' => $biayaTetap,
            ],
            'ada_harga_basi' => $baris->contains(fn ($b) => $b['harga_master_kini'] !== null),
            'nego_aktif' => $negoAktif,
            'alasan_nego' => $rab->alasan_nego,
            // Kesepakatan yang sudah di bawah modal. Bukan larangan — tanda.
            'nego_rugi' => $untung < 0,
        ] + collect($angka)->mapWithKeys(fn ($v, $k) => [$k.'_teks' => RincianBiaya::rupiah($v)])->all();
    }
}
