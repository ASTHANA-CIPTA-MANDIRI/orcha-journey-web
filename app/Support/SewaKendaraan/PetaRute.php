<?php

namespace App\Support\SewaKendaraan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Titik jemput, tujuan, dan jarak jalan di antara keduanya.
 *
 * Dipakai formulir sewa kendaraan untuk menggambar peta sungguhan. Sifatnya
 * KETERANGAN, bukan dasar hitungan: tarif sewa dihitung per hari, bukan per
 * kilometer, jadi angka jarak tidak pernah mengubah biaya. Karena itu pula
 * angkanya ditulis dengan tanda kira-kira di tampilan.
 *
 * Semua jawaban disimpan lama. Letak sebuah tempat tidak berubah, dan menembak
 * layanan gratis untuk pertanyaan yang sama hanya memperlambat penyewa sekaligus
 * membebani layanan yang kita menumpang padanya.
 *
 * Tidak satu pun kegagalan di sini boleh menghentikan pemesanan: yang gagal
 * mengembalikan null, dan formulirnya berjalan seperti biasa tanpa peta.
 */
class PetaRute
{
    /** Di bawah ini hasil pencariannya asal-asalan. */
    private const HURUF_MINIMAL = 4;

    private const SIMPAN_HARI = 30;

    /**
     * Versi bentuk jawaban yang disimpan. Dinaikkan bila isinya berubah, supaya
     * simpanan berbentuk lama tidak diam-diam kehilangan medan baru.
     */
    private const VERSI = 1;

    /**
     * Beberapa calon tempat untuk sebuah tulisan, supaya PENYEWA yang memilih.
     *
     * Menebak sendiri tidak pernah benar untuk semua orang. Terukur pada nama
     * yang sama persis: "malioboro" bisa berarti jalan di Yogyakarta atau pusat
     * belanja di Surabaya, 370 km terpisah. Diutamakan yang dekat pangkalan,
     * "Bromo" jatuh ke Jalan Bromo di Yogyakarta; tidak diutamakan, "malioboro"
     * jatuh ke Surabaya. Kedua aturan sama-sama salah untuk separuh kejadian.
     *
     * @return list<array{lat: float, lon: float, nama: string, alamat: string}>
     */
    public function cari(string $teks): array
    {
        $teks = trim(preg_replace('/\s+/', ' ', $teks));

        if (mb_strlen($teks) < self::HURUF_MINIMAL || ! config('orcha.peta.aktif', true)) {
            return [];
        }

        return Cache::remember(
            'orcha.cari.v'.self::VERSI.'.'.md5(mb_strtolower($teks)),
            now()->addDays(self::SIMPAN_HARI),
            fn () => $this->tanyaNominatim($teks),
        );
    }

    /**
     * @return list<array{lat: float, lon: float, nama: string, alamat: string}>
     */
    private function tanyaNominatim(string $teks): array
    {
        try {
            $balasan = Http::withHeaders([
                // Wajib menurut ketentuan pemakaian Nominatim: layanan gratis
                // yang tidak tahu siapa pemanggilnya berhak memblokirnya.
                'User-Agent' => config('orcha.peta.pengenal', 'OrchaJourney/1.0'),
                'Accept-Language' => 'id',
            ])
                ->connectTimeout(3)
                ->timeout(5)
                ->get(config('orcha.peta.alamat'), [
                    'q' => $teks,
                    'format' => 'jsonv2',
                    'countrycodes' => 'id',
                    'limit' => 6,
                    // TANPA pengutamaan wilayah, dan itu disengaja.
                    //
                    // Selama jawabannya cuma satu, mengutamakan yang dekat
                    // pangkalan memang menolong. Begitu jawabannya berupa
                    // daftar pilihan, pengutamaan itu berbalik merugikan:
                    // terukur, "Bromo" dengan pengutamaan menghasilkan lima
                    // Jalan Bromo di Yogyakarta dan Surakarta — gunungnya
                    // TERSINGKIR dari daftar, jadi penyewa tidak punya cara
                    // memilihnya sama sekali.
                ]);

            if (! $balasan->successful()) {
                return [];
            }

            return collect($balasan->json() ?? [])
                ->filter(fn ($baris) => isset($baris['lat'], $baris['lon']))
                ->map(function ($baris) {
                    $penuh = (string) ($baris['display_name'] ?? '');
                    $ruas = array_map('trim', explode(',', $penuh));

                    return [
                        'lat' => (float) $baris['lat'],
                        'lon' => (float) $baris['lon'],
                        // Nama tempatnya sendiri, lalu sisa alamatnya sebagai
                        // pembeda — tanpa itu dua "Malioboro" di daftar terlihat
                        // sama persis dan tidak bisa dipilih dengan yakin.
                        'nama' => $ruas[0] ?? $penuh,
                        'alamat' => implode(', ', array_slice($ruas, 1, 3)),
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::info('Pencarian titik gagal', ['teks' => $teks, 'pesan' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Jarak dan lama tempuh menurut jalan, beserta garis rutenya.
     *
     * @param  array{lat: float, lon: float}  $dari
     * @param  array{lat: float, lon: float}  $ke
     * @return array{jarak_km: float, durasi_menit: int, garis: list<array{0: float, 1: float}>}|null
     */
    public function rute(array $dari, array $ke): ?array
    {
        $kunci = config('orcha.rute.kunci');

        // Tanpa kunci, petanya tetap berguna: dua penanda di letak yang benar.
        // Yang tidak ada hanya angkanya.
        if (! $kunci || ! config('orcha.rute.aktif', true)) {
            return null;
        }

        $sidik = md5(implode(',', [$dari['lat'], $dari['lon'], $ke['lat'], $ke['lon']]));

        return Cache::remember(
            'orcha.rute.v'.self::VERSI.'.'.$sidik,
            now()->addDays(self::SIMPAN_HARI),
            fn () => $this->tanyaRute($dari, $ke, $kunci),
        );
    }

    /**
     * @return array{jarak_km: float, durasi_menit: int, garis: list<array{0: float, 1: float}>}|null
     */
    private function tanyaRute(array $dari, array $ke, string $kunci): ?array
    {
        try {
            $balasan = Http::withHeaders(['Authorization' => $kunci])
                ->connectTimeout(3)
                ->timeout(8)
                ->post(config('orcha.rute.alamat').'/geojson', [
                    // OpenRouteService memakai urutan [lon, lat] — kebalikan
                    // dari kebiasaan lat/lon. Tertukar, rutenya mendarat di
                    // laut lepas dan jaraknya jadi ribuan kilometer.
                    'coordinates' => [
                        [$dari['lon'], $dari['lat']],
                        [$ke['lon'], $ke['lat']],
                    ],
                    // Jarak pencarian jalan terdekat dari titik yang diberikan.
                    //
                    // Bawaannya 350 meter, dan itu terlalu rapat untuk titik
                    // yang datang dari pencarian nama: "bali" menghasilkan
                    // TITIK TENGAH provinsinya, yang jatuh jauh dari jalan mana
                    // pun. Terukur — dengan bawaannya layanan menjawab 404
                    // "could not find routable point", dengan 5 km rutenya
                    // ketemu: Yogyakarta ke Bali 702,5 km, 9,5 jam, lengkap
                    // dengan penyeberangannya.
                    //
                    // Dibatasi 5 km, tidak dibuat tanpa batas: titik yang salah
                    // sama sekali lebih baik gagal daripada diam-diam
                    // disambungkan ke jalan puluhan kilometer di seberang.
                    'radiuses' => [5000, 5000],
                ]);

            if (! $balasan->successful()) {
                Log::info('Rute gagal', ['status' => $balasan->status()]);

                return null;
            }

            $ringkas = $balasan->json('features.0.properties.summary');
            $garis = $balasan->json('features.0.geometry.coordinates');

            if (! isset($ringkas['distance'])) {
                return null;
            }

            return [
                'jarak_km' => round($ringkas['distance'] / 1000, 1),
                'durasi_menit' => (int) round(($ringkas['duration'] ?? 0) / 60),
                // Dibalik jadi [lat, lon] di sini, sekali, supaya sisi tampilan
                // tidak perlu tahu urutan milik siapa.
                'garis' => collect($garis ?? [])
                    ->map(fn ($titik) => [(float) $titik[1], (float) $titik[0]])
                    ->all(),
            ];
        } catch (\Throwable $e) {
            Log::info('Rute gagal', ['pesan' => $e->getMessage()]);

            return null;
        }
    }
}
