<?php

namespace App\Support\SewaKendaraan;

use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Support\Collection;

/**
 * Laporan keuntungan SEWA KENDARAAN.
 *
 * Paket wisata sudah punya laporannya sejak lama; sewa belum, karena sampai
 * 18 Nov 2026 tidak ada tempat mencatat modalnya. Sekarang tiap pesanan
 * menyimpan modal dan vendornya, jadi pertanyaan yang selama ini hanya bisa
 * dijawab dengan kalkulator — "bulan ini sewa untung berapa", "unit mana yang
 * paling menguntungkan", "vendor mana yang marginnya paling tipis" — bisa
 * dijawab dari data yang sudah ada.
 *
 * ATURAN YANG MENENTUKAN SELURUH ANGKA DI SINI:
 *
 * 1. Pesanan yang MODALNYA BELUM DICATAT tidak dihitung sebagai untung nol.
 *    Ia dikeluarkan dari hitungan dan disebut jumlahnya tersendiri. Kalau
 *    ikut dihitung, laporan ini akan melaporkan untung sebesar seluruh
 *    pendapatan pada hari pertama dipakai — angka yang mustahil tetapi tampil
 *    meyakinkan.
 *
 * 2. Pesanan BATAL tidak ikut. Unitnya tidak jadi dipakai, uangnya tidak jadi
 *    berpindah.
 *
 * 3. DENDA tidak dihitung sebagai untung, sama seperti di halaman detail:
 *    denda kerusakan hampir selalu habis untuk perbaikan di bengkel vendor.
 */
class KeuntunganSewa
{
    /**
     * Dasar tanggal yang bisa dipilih.
     *
     * Dua pertanyaan yang berbeda: "sewa yang JALAN bulan ini" dipakai menilai
     * kesibukan armada, "sewa yang DIPESAN bulan ini" dipakai menilai
     * pemasaran. Satu dasar saja membuat salah satunya tidak terjawab.
     */
    public const DASAR = [
        'mulai' => 'Tanggal mulai sewa',
        'dibuat' => 'Tanggal pesanan masuk',
    ];

    /**
     * @param  array<string, mixed>  $saring
     * @return array<string, mixed>
     */
    public static function laporan(array $saring = []): array
    {
        $semua = self::kueri($saring)->get();

        $bermodal = $semua->whereNotNull('modal');

        /*
         | PENDAPATAN dihitung dari SELURUH pesanan, modalnya dicatat atau
         | tidak. Harga sewanya sudah pasti sejak pesanan dibuat — tidak ada
         | alasan menyembunyikannya hanya karena modalnya belum diisi.
         |
         | Sempat dihitung dari pesanan bermodal saja, dan hasilnya ringkasan
         | menulis "Pendapatan Rp 0" tepat di atas tabel yang menampilkan
         | Rp 15.000.000. Dua angka yang saling membantah di satu layar lebih
         | buruk daripada satu angka yang belum lengkap.
         |
         | UNTUNG tetap hanya dari pesanan yang modalnya sudah dicatat, dan
         | karena itu pembaginya pun pendapatan dari subset yang sama —
         | membaginya dengan pendapatan seluruhnya menghasilkan margin yang
         | terbaca jauh lebih kecil daripada yang sebenarnya.
         */
        $pendapatanSemua = (int) $semua->sum('estimasi_biaya');
        $pendapatanDihitung = (int) $bermodal->sum('estimasi_biaya');
        $modal = (int) $bermodal->sum('modal');
        $untung = $pendapatanDihitung - $modal;

        return [
            'ringkas' => [
                'pesanan' => $semua->count(),
                'pesanan_dihitung' => $bermodal->count(),
                // Disebut terpisah, bukan disembunyikan: selama angkanya belum
                // nol, untung di atas masih kurang dari yang sebenarnya.
                'tanpa_modal' => $semua->count() - $bermodal->count(),
                'pendapatan' => $pendapatanSemua,
                'pendapatan_dihitung' => $pendapatanDihitung,
                'modal' => $modal,
                'untung' => $bermodal->isEmpty() ? null : $untung,
                'margin_persen' => $pendapatanDihitung > 0
                    ? round($untung / $pendapatanDihitung * 100, 1)
                    : null,
            ],
            'per_unit' => self::kelompok($bermodal, fn (PenyewaanKendaraan $s) => $s->nama_kendaraan ?: 'Tanpa nama unit'),
            'per_vendor' => self::kelompok($bermodal, fn (PenyewaanKendaraan $s) => $s->vendor?->nama ?: 'Vendor belum dicatat'),
            'dasar_tanggal' => self::DASAR,
        ];
    }

    /**
     * Rincian baris demi baris — yang membentuk angka di ringkasannya.
     */
    public static function rincian(array $saring, int $perHalaman)
    {
        return self::kueri($saring)->with('vendor')->orderByDesc('id')->paginate($perHalaman);
    }

    /**
     * @return array<string, mixed>
     */
    public static function satuBaris(PenyewaanKendaraan $sewa): array
    {
        return [
            'id' => $sewa->id,
            'kode' => $sewa->kode,
            'nama' => $sewa->nama,
            'kendaraan' => $sewa->nama_kendaraan,
            'vendor' => $sewa->vendor?->nama,
            'tanggal_mulai' => $sewa->tanggal_mulai?->toDateString(),
            'durasi_label' => $sewa->durasi_label,
            'status' => $sewa->status,
            'status_label' => config("orcha.status_penyewaan.{$sewa->status}") ?? $sewa->status,
            'pendapatan' => (int) $sewa->estimasi_biaya,
            'modal' => $sewa->modal,
            'untung' => $sewa->untung,
            'margin_persen' => $sewa->margin_persen,
        ];
    }

    /**
     * Margin rata-rata tertimbang — dipakai halaman detail sebagai PEMBANDING.
     *
     * Tertimbang, bukan rata-rata dari persentase tiap pesanan: sewa sehari
     * Rp 300 ribu dan sewa sepekan Rp 10 juta tidak sama beratnya, dan
     * merata-ratakan persennya membuat yang kecil ikut menentukan sebanyak
     * yang besar.
     *
     * null bila belum ada satu pun pesanan bermodal — dan halaman yang
     * memakainya harus diam, bukan menampilkan 0%.
     */
    public static function marginRataRata(): ?float
    {
        $bermodal = self::kueri([])->whereNotNull('modal')->get(['estimasi_biaya', 'modal']);

        $pendapatan = (int) $bermodal->sum('estimasi_biaya');

        if ($bermodal->isEmpty() || $pendapatan < 1) {
            return null;
        }

        return round(($pendapatan - (int) $bermodal->sum('modal')) / $pendapatan * 100, 1);
    }

    private static function kueri(array $saring)
    {
        $kolomTanggal = ($saring['dasar'] ?? 'mulai') === 'dibuat' ? 'created_at' : 'tanggal_mulai';

        return PenyewaanKendaraan::query()
            ->where('status', '!=', 'batal')
            ->when($saring['dari'] ?? null, fn ($q, $v) => $q->whereDate($kolomTanggal, '>=', $v))
            ->when($saring['sampai'] ?? null, fn ($q, $v) => $q->whereDate($kolomTanggal, '<=', $v))
            ->when($saring['vendor_id'] ?? null, fn ($q, $v) => $q->where('vendor_id', $v));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function kelompok(Collection $baris, callable $kunci): array
    {
        return $baris->groupBy($kunci)
            ->map(function (Collection $isi, string $nama) {
                $pendapatan = (int) $isi->sum('estimasi_biaya');
                $modal = (int) $isi->sum('modal');

                return [
                    'nama' => $nama,
                    'pesanan' => $isi->count(),
                    'pendapatan' => $pendapatan,
                    'modal' => $modal,
                    'untung' => $pendapatan - $modal,
                    'margin_persen' => $pendapatan > 0
                        ? round(($pendapatan - $modal) / $pendapatan * 100, 1)
                        : null,
                ];
            })
            ->sortByDesc('untung')
            ->values()
            ->all();
    }
}
