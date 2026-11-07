<?php

namespace App\Support\Rab;

use App\Models\Rab\Rab;
use App\Models\Rab\RabBiaya;
use App\Support\RincianBiaya;
use Illuminate\Support\Collection;

/**
 * Rekap pengeluaran sesudah trip jalan: RAB vs tagihan vs yang dibayar.
 *
 * Satu-satunya tempat yang memutuskan arti ketiga angka itu. Tersebar di
 * beberapa tempat, "sisa" akan berbeda tafsir antara layar dan laporan — dan
 * selisihnya adalah uang.
 *
 * ================= KENAPA TIGA ANGKA, BUKAN DUA =================
 *
 * Godaan terbesarnya adalah menyimpan RAB dan "yang dibayar" saja, lalu
 * menyebut selisihnya sisa. Itu membuat DUA HAL YANG BERLAWANAN terlihat
 * sama persis:
 *
 *   RAB 8jt, tagihan 7jt, dibayar 7jt  ->  HEMAT 1jt   (untung bertambah)
 *   RAB 8jt, tagihan 8jt, dibayar 7jt  ->  KURANG 1jt  (masih utang)
 *
 * Keduanya "RAB − dibayar = 1jt". Hotel yang lupa dilunasi akan tampil
 * sebagai cashback satu juta, dan laporan menjanjikan untung yang uangnya
 * justru masih harus keluar.
 *
 * Dengan tagihan dicatat terpisah, tandanya JATUH SENDIRI — tidak ada yang
 * perlu memutuskan "ini plus atau minus".
 */
class RekapRab
{
    /**
     * Rekap lengkap sebuah RAB: per baris, per kategori, dan totalnya.
     *
     * @return array<string, mixed>
     */
    public static function untuk(Rab $rab): array
    {
        $rab->loadMissing('biaya.realisasi.bayar', 'biaya.vendor');

        $hitung = HitungRab::ringkas($rab);
        $baris = $rab->biaya->map(fn (RabBiaya $b) => self::baris($b, $rab))->values();

        $rabTotal = (int) $baris->sum('rab');
        $tagihan = (int) $baris->sum(fn ($r) => $r['tagihan'] ?? 0);
        $dibayar = (int) $baris->sum('dibayar');
        $belum = $baris->where('sudah_direkap', false);

        /*
         | Tagihan baris yang BELUM direkap dianggap sebesar RAB-nya.
         |
         | Nol akan membuat untung nyata melonjak seolah biaya itu gratis —
         | laporan setengah jadi yang terbaca seperti kabar baik, dan
         | dipercaya justru karena angkanya menyenangkan. Memakai RAB membuat
         | rekap yang baru separuh menampilkan untung yang mendekati rencana,
         | lalu bergerak begitu angka sebenarnya masuk.
         */
        $tagihanLengkap = $tagihan + (int) $belum->sum('rab');

        $hargaJual = (int) ($hitung['harga_total'] ?? 0);

        return [
            'baris' => $baris->all(),
            'per_kategori' => self::perKategori($baris),
            'rab_total' => $rabTotal,
            'tagihan_total' => $tagihan,
            'dibayar_total' => $dibayar,
            /*
             | Selisih BERTANDA, dan ini angka yang dicari orang: positif
             | berarti vendor menagih lebih murah daripada RAB (diskon,
             | cashback) — uang yang tidak jadi keluar.
             */
            'hemat' => $rabTotal - $tagihanLengkap,
            // Masih harus dibayar ke vendor. Negatif = kelebihan bayar.
            'kurang_bayar' => $tagihanLengkap - $dibayar,
            'harga_jual' => $hargaJual,
            // Untung menurut RENCANA — angka yang dipakai saat menawar.
            'untung_rencana' => $hargaJual - $rabTotal,
            /*
             | Untung NYATA: harga jual dikurangi tagihan yang sebenarnya.
             |
             | Dihitung dari TAGIHAN, bukan dari yang sudah dibayar. Trip
             | yang belum melunasi hotelnya belum untung lebih banyak — ia
             | cuma belum mengeluarkan uangnya.
             */
            'untung_nyata' => $hargaJual - $tagihanLengkap,
            'jumlah_baris' => $baris->count(),
            'belum_direkap' => $belum->pluck('nama')->values()->all(),
            // Pembayaran yang tidak punya bukti sama sekali — baris yang
            // tidak bisa dipertanggungjawabkan saat ditanya.
            'bayar_tanpa_bukti' => (int) $baris->sum('tanpa_bukti'),
            'lengkap' => $belum->isEmpty(),
        ];
    }

    /**
     * Satu baris rekap.
     *
     * @return array<string, mixed>
     */
    private static function baris(RabBiaya $b, Rab $rab): array
    {
        $realisasi = $b->realisasi;
        $rabNilai = HitungRab::subtotal($b, $rab);
        $tagihan = $realisasi?->tagihan;
        $dibayar = $realisasi ? $realisasi->dibayar : 0;

        return [
            'biaya_id' => $b->id,
            'realisasi_id' => $realisasi?->id,
            'kategori' => $b->kategori,
            'kategori_label' => config("orcha.rab.kategori.{$b->kategori}") ?? ucfirst($b->kategori),
            'nama' => $b->nama,
            'vendor' => $b->vendor?->nama,
            'vendor_kontak' => $b->vendor?->kontak,
            'penjelasan' => HitungRab::penjelasan($b, $rab),
            'rab' => $rabNilai,
            'rab_teks' => RincianBiaya::rupiah($rabNilai),
            'tagihan' => $tagihan,
            'tagihan_teks' => $tagihan === null ? null : RincianBiaya::rupiah($tagihan),
            'dibayar' => $dibayar,
            'dibayar_teks' => RincianBiaya::rupiah($dibayar),
            /*
             | Dua selisih yang BERBEDA, dan memisahkannya adalah seluruh
             | gunanya rekap ini:
             |   hemat        = RAB − tagihan   (vendor menagih lebih murah)
             |   kurang_bayar = tagihan − dibayar (kita belum melunasi)
             | Digabung jadi satu angka, keduanya tidak bisa dibedakan.
             */
            'hemat' => $tagihan === null ? null : $rabNilai - (int) $tagihan,
            'kurang_bayar' => $realisasi?->kurang_bayar,
            'lunas' => $tagihan !== null && $realisasi->kurang_bayar === 0,
            'sudah_direkap' => (bool) $realisasi?->sudah_direkap,
            'pic' => $realisasi?->pic,
            'catatan' => $realisasi?->catatan,
            'tanpa_bukti' => $realisasi?->tanpa_bukti ?? 0,
            'bayar' => $realisasi
                ? $realisasi->bayar->map(fn ($p) => [
                    'id' => $p->id,
                    'jenis' => $p->jenis,
                    'jenis_label' => $p->jenis_label,
                    'nominal' => (int) $p->nominal,
                    'nominal_teks' => RincianBiaya::rupiah($p->nominal),
                    'arah' => $p->arah,
                    'tanggal' => $p->tanggal?->toDateString(),
                    'tanggal_teks' => $p->tanggal_teks,
                    'bukti' => $p->bukti,
                    // Berapa kali buktinya pernah diganti — pertanyaan yang
                    // selalu muncul saat catatan uang dipersoalkan.
                    'bukti_diganti' => count($p->bukti_riwayat ?? []),
                    'oleh' => $p->oleh,
                    'catatan' => $p->catatan,
                ])->all()
                : [],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private static function perKategori($baris): array
    {
        return $baris
            ->groupBy('kategori')
            ->map(fn ($grup, $kategori) => [
                'kategori' => $kategori,
                'label' => $grup->first()['kategori_label'],
                'rab' => (int) $grup->sum('rab'),
                'rab_teks' => RincianBiaya::rupiah((int) $grup->sum('rab')),
                'tagihan' => (int) $grup->sum(fn ($r) => $r['tagihan'] ?? $r['rab']),
                'tagihan_teks' => RincianBiaya::rupiah((int) $grup->sum(fn ($r) => $r['tagihan'] ?? $r['rab'])),
                'dibayar' => (int) $grup->sum('dibayar'),
                'dibayar_teks' => RincianBiaya::rupiah((int) $grup->sum('dibayar')),
                'hemat' => (int) $grup->sum('rab') - (int) $grup->sum(fn ($r) => $r['tagihan'] ?? $r['rab']),
                'belum_direkap' => $grup->where('sudah_direkap', false)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * RAB yang sudah selesai tetapi rekapnya belum beres.
     *
     * Pertanyaan operasionalnya bukan "trip ini untung berapa" melainkan
     * "trip mana yang uangnya masih menggantung" — dan yang terlupa selalu
     * yang paling lama menggantung, karena tidak ada yang mengingatkannya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function tunggakan()
    {
        return Rab::query()
            ->where('status', 'selesai')
            ->with('biaya.realisasi')
            ->orderBy('tanggal_mulai')
            ->get()
            ->map(function (Rab $rab) {
                $total = $rab->biaya->count();
                $sudah = $rab->biaya->filter(fn (RabBiaya $b) => $b->realisasi?->sudah_direkap)->count();

                return [
                    'id' => $rab->id,
                    'kode' => $rab->kode,
                    'judul' => $rab->judul,
                    'tanggal_mulai' => $rab->tanggal_mulai?->toDateString(),
                    'tanggal_teks' => $rab->tanggal_mulai?->locale('id')->translatedFormat('j M Y'),
                    'jumlah_baris' => $total,
                    'sudah_direkap' => $sudah,
                    'lengkap' => $total > 0 && $sudah === $total,
                ];
            })
            ->filter(fn ($r) => ! $r['lengkap'])
            ->values();
    }
}
