<?php

namespace App\Support\PaketWisata;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\RincianBiaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Keuntungan paket wisata: selisih harga jual dan modal, dikalikan peserta.
 *
 * Satu paket punya dua angka yang selama ini hanya salah satunya tercatat.
 * Harga jual ada di aplikasi, modalnya ada di catatan admin — dan karena
 * keduanya tidak pernah bertemu, pertanyaan "trip kemarin untung berapa"
 * dijawab dengan menghitung ulang dari awal tiap kali ditanya.
 *
 * Yang dihitung sebagai keuntungan HANYA pendaftaran yang sudah lunas.
 * Alasannya bukan kehati-hatian akuntansi semata: pesanan ber-DP masih bisa
 * batal, dan potongan pembatalannya mengikuti tangga di config — jadi
 * keuntungannya belum tentu ada, bahkan belum tentu positif. Pesanan yang
 * belum lunas tetap dilaporkan, tapi di kolomnya sendiri sebagai potensi,
 * supaya tidak pernah tertukar dengan uang yang sudah utuh diterima.
 *
 * Modal yang belum diisi TIDAK dianggap nol. Paket tanpa modal dihitung
 * terpisah sebagai "belum lengkap" — laporan yang mengaku untung penuh untuk
 * paket yang biayanya belum pernah dimasukkan jauh lebih menyesatkan daripada
 * laporan yang mengakui ada yang belum terisi.
 */
class Keuntungan
{
    /** Dasar tanggal yang boleh dipakai menyaring dan mengelompokkan bulan. */
    public const DASAR = [
        'daftar' => 'Tanggal pendaftaran masuk',
        'berangkat' => 'Tanggal keberangkatan',
    ];

    /**
     * Laporan lengkap: ringkasan, rekap per paket, per kategori, per bulan.
     *
     * @param  array<string, mixed>  $saring  dari, sampai, kategori, paket_id, dasar
     * @return array<string, mixed>
     */
    public static function laporan(array $saring = []): array
    {
        $lunas = self::baris(self::query($saring)->sudahUntung());
        $potensi = self::baris(self::query($saring)->masihPotensi());

        return [
            'saringan' => self::saringanTerpakai($saring),
            'ringkasan' => self::ringkasan($lunas, $potensi),
            'per_paket' => self::kelompok($lunas, fn ($baris) => $baris['paket_id'] ?? 0, [
                'nama' => fn ($isi) => $isi->first()['paket'],
                'kategori' => fn ($isi) => $isi->first()['kategori'],
                'kategori_label' => fn ($isi) => $isi->first()['kategori_label'],
                'harga_jual' => fn ($isi) => $isi->first()['jual_satuan'],
                'harga_modal' => fn ($isi) => $isi->first()['modal_satuan'],
                'margin_per_orang' => fn ($isi) => $isi->first()['margin_satuan'],
            ]),
            'per_kategori' => self::kelompok($lunas, fn ($baris) => $baris['kategori'], [
                'label' => fn ($isi) => $isi->first()['kategori_label'],
            ]),
            'per_bulan' => self::perBulan($lunas, self::dasar($saring)),
        ];
    }

    /**
     * Rincian per pendaftaran, untuk tabel yang bisa ditelusuri baris demi
     * baris. Termasuk yang belum lunas, dengan penanda status apa adanya —
     * admin yang menutup buku perlu melihat keduanya di satu tempat.
     */
    public static function rincian(array $saring = [], int $perHalaman = 25)
    {
        $query = self::query($saring);

        if (($saring['hanya_lunas'] ?? false)) {
            $query->sudahUntung();
        } else {
            $query->where('status', '!=', 'batal');
        }

        return $query->latest('id')->paginate($perHalaman);
    }

    /** Bentuk satu baris rincian; dipakai laporan maupun API. */
    public static function satuBaris(PendaftaranOpenTrip $daftar): array
    {
        return [
            'id' => $daftar->id,
            'kode' => $daftar->kode,
            'nama' => $daftar->nama,
            'status' => $daftar->status,
            'status_label' => $daftar->status_label,
            'paket_id' => $daftar->travel_package_id,
            'paket' => $daftar->nama_paket ?: $daftar->paket?->name ?: '(paket terhapus)',
            'kategori' => $daftar->paket?->category,
            'kategori_label' => $daftar->paket?->category_label ?? '—',
            'peserta' => max(1, (int) $daftar->jumlah_peserta),
            'tanggal_daftar' => $daftar->created_at?->toDateString(),
            'tanggal_berangkat' => $daftar->tanggal_berangkat?->toDateString(),
            'jual_satuan' => $daftar->jual_satuan,
            'modal_satuan' => $daftar->modal_satuan,
            'margin_satuan' => $daftar->margin_satuan,
            'omzet' => $daftar->omzet,
            'modal' => $daftar->modal_total,
            'keuntungan' => $daftar->keuntungan,
            'modal_terisi' => $daftar->modal_satuan !== null,
        ] + self::rupiahkan([
            'jual_satuan' => $daftar->jual_satuan,
            'modal_satuan' => $daftar->modal_satuan,
            'margin_satuan' => $daftar->margin_satuan,
            'omzet' => $daftar->omzet,
            'modal' => $daftar->modal_total,
            'keuntungan' => $daftar->keuntungan,
        ]);
    }

    /* ------------------------------- DALAMAN ------------------------------- */

    private static function query(array $saring): Builder
    {
        $dasar = self::dasar($saring);

        // COALESCE, bukan dua cabang: sebagian pendaftaran lama tidak menyimpan
        // tanggal berangkat, dan membuangnya dari laporan hanya karena kolom
        // itu kosong membuat totalnya diam-diam tidak cocok dengan daftarnya.
        $kolom = $dasar === 'berangkat'
            ? 'DATE(COALESCE(tanggal_berangkat, created_at))'
            : 'DATE(created_at)';

        return PendaftaranOpenTrip::query()
            ->with('paket')
            ->when(filled($saring['dari'] ?? null), fn ($q) => $q->whereRaw("{$kolom} >= ?", [$saring['dari']]))
            ->when(filled($saring['sampai'] ?? null), fn ($q) => $q->whereRaw("{$kolom} <= ?", [$saring['sampai']]))
            ->when(filled($saring['paket_id'] ?? null), fn ($q) => $q->where('travel_package_id', $saring['paket_id']))
            ->when(filled($saring['kategori'] ?? null), fn ($q) => $q->whereHas(
                'paket', fn ($p) => $p->where('category', $saring['kategori'])
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function baris(Builder $query): Collection
    {
        return $query->get()->map(fn (PendaftaranOpenTrip $daftar) => self::satuBaris($daftar));
    }

    private static function ringkasan(Collection $lunas, Collection $potensi): array
    {
        $lengkap = $lunas->where('modal_terisi', true);
        $peserta = (int) $lengkap->sum('peserta');

        $angka = [
            'pendaftaran' => $lunas->count(),
            'peserta' => (int) $lunas->sum('peserta'),
            'omzet' => (int) $lunas->sum('omzet'),
            'modal' => (int) $lengkap->sum('modal'),
            'keuntungan' => (int) $lengkap->sum('keuntungan'),
            // Rata-rata dari peserta yang modalnya diketahui saja, kalau tidak
            // angkanya turun sendiri tiap kali ada paket yang belum diisi.
            'margin_rata_per_orang' => $peserta > 0 ? (int) round($lengkap->sum('keuntungan') / $peserta) : 0,
            'potensi_pendaftaran' => $potensi->count(),
            'potensi_peserta' => (int) $potensi->sum('peserta'),
            'potensi_omzet' => (int) $potensi->sum('omzet'),
            'potensi_keuntungan' => (int) $potensi->where('modal_terisi', true)->sum('keuntungan'),
            // Berapa banyak yang tidak bisa dihitung. Angka inilah yang
            // memberi tahu admin bahwa laporannya belum utuh.
            'belum_lengkap' => $lunas->where('modal_terisi', false)->count(),
            'paket_belum_lengkap' => $lunas->where('modal_terisi', false)
                ->pluck('paket')->unique()->values()->all(),
        ];

        return $angka + self::rupiahkan($angka);
    }

    /**
     * Rekap berdasar satu kunci, ditambah kolom keterangan yang diambil dari
     * baris pertama tiap kelompok.
     *
     * @param  array<string, callable>  $keterangan
     */
    private static function kelompok(Collection $baris, callable $kunci, array $keterangan): array
    {
        return $baris->groupBy($kunci)
            ->map(function (Collection $isi, $kunciNilai) use ($keterangan) {
                $lengkap = $isi->where('modal_terisi', true);

                $angka = [
                    'kunci' => $kunciNilai,
                    'pendaftaran' => $isi->count(),
                    'peserta' => (int) $isi->sum('peserta'),
                    'omzet' => (int) $isi->sum('omzet'),
                    'modal' => (int) $lengkap->sum('modal'),
                    'keuntungan' => (int) $lengkap->sum('keuntungan'),
                    'belum_lengkap' => $isi->where('modal_terisi', false)->count(),
                ];

                foreach ($keterangan as $nama => $ambil) {
                    $angka[$nama] = $ambil($isi);
                }

                return $angka + self::rupiahkan($angka);
            })
            ->sortByDesc('keuntungan')
            ->values()
            ->all();
    }

    /**
     * Keuntungan per bulan, urut maju supaya grafiknya terbaca kiri ke kanan.
     *
     * Bulannya mengikuti dasar tanggal yang sedang dipakai menyaring. Kalau
     * tidak, admin yang menyaring per keberangkatan tetap melihat grafik yang
     * disusun menurut tanggal pendaftaran — dua hal yang bisa terpaut
     * berbulan-bulan untuk trip yang dijual jauh hari.
     */
    private static function perBulan(Collection $lunas, string $dasar): array
    {
        $kolom = $dasar === 'berangkat' ? 'tanggal_berangkat' : 'tanggal_daftar';

        return $lunas->groupBy(fn ($baris) => substr(
            (string) ($baris[$kolom] ?? $baris['tanggal_daftar'] ?? ''), 0, 7
        ))
            ->filter(fn ($isi, $bulan) => $bulan !== '')
            ->map(function (Collection $isi, string $bulan) {
                $lengkap = $isi->where('modal_terisi', true);

                $angka = [
                    'bulan' => $bulan,
                    'bulan_label' => \Carbon\Carbon::createFromFormat('Y-m', $bulan)
                        ->locale('id')->translatedFormat('M Y'),
                    'pendaftaran' => $isi->count(),
                    'peserta' => (int) $isi->sum('peserta'),
                    'omzet' => (int) $isi->sum('omzet'),
                    'modal' => (int) $lengkap->sum('modal'),
                    'keuntungan' => (int) $lengkap->sum('keuntungan'),
                ];

                return $angka + self::rupiahkan($angka);
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Bentuk rupiah untuk tiap angka uang, bernama `<kunci>_teks`.
     *
     * Dikerjakan di sini supaya lemon tidak menulis ulang aturan formatnya —
     * dan supaya nilai null tetap terbaca "belum dihitung", bukan "Rp 0" yang
     * berarti hal yang sama sekali berbeda.
     */
    private static function rupiahkan(array $angka): array
    {
        $uang = ['omzet', 'modal', 'keuntungan', 'jual_satuan', 'modal_satuan', 'margin_satuan',
            'margin_rata_per_orang', 'harga_jual', 'harga_modal', 'margin_per_orang',
            'potensi_omzet', 'potensi_keuntungan'];

        $hasil = [];

        foreach ($angka as $kunci => $nilai) {
            if (in_array($kunci, $uang, true)) {
                $hasil[$kunci.'_teks'] = $nilai === null ? 'Belum dihitung' : RincianBiaya::rupiah($nilai);
            }
        }

        return $hasil;
    }

    private static function dasar(array $saring): string
    {
        $diminta = (string) ($saring['dasar'] ?? 'daftar');

        return array_key_exists($diminta, self::DASAR) ? $diminta : 'daftar';
    }

    private static function saringanTerpakai(array $saring): array
    {
        return [
            'dari' => $saring['dari'] ?? null,
            'sampai' => $saring['sampai'] ?? null,
            'kategori' => $saring['kategori'] ?? null,
            'paket_id' => $saring['paket_id'] ?? null,
            'dasar' => self::dasar($saring),
            'dasar_label' => self::DASAR[self::dasar($saring)],
        ];
    }
}
