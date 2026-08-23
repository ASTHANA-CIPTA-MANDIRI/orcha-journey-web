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
     * Ringkasan untuk satu pasang titik.
     *
     * @return array{
     *     jemput: array{lat: float, lon: float, nama: string}|null,
     *     tujuan: array{lat: float, lon: float, nama: string}|null,
     *     jarak_km: float|null,
     *     durasi_menit: int|null,
     *     garis: list<array{0: float, 1: float}>|null,
     * }
     */
    public function rangkum(string $teksJemput, string $teksTujuan): array
    {
        $jemput = $this->titik($teksJemput);
        $tujuan = $this->titik($teksTujuan);

        $rute = ($jemput && $tujuan) ? $this->rute($jemput, $tujuan) : null;

        return [
            'jemput' => $jemput,
            'tujuan' => $tujuan,
            'jarak_km' => $rute['jarak_km'] ?? null,
            'durasi_menit' => $rute['durasi_menit'] ?? null,
            'garis' => $rute['garis'] ?? null,
        ];
    }

    /**
     * Koordinat sebuah tulisan bebas, atau null bila tidak ketemu.
     *
     * @return array{lat: float, lon: float, nama: string}|null
     */
    public function titik(string $teks): ?array
    {
        $teks = trim(preg_replace('/\s+/', ' ', $teks));

        if (mb_strlen($teks) < self::HURUF_MINIMAL || ! config('orcha.peta.aktif', true)) {
            return null;
        }

        return Cache::remember(
            'orcha.titik.v'.self::VERSI.'.'.md5(mb_strtolower($teks)),
            now()->addDays(self::SIMPAN_HARI),
            fn () => $this->tanyaNominatim($teks),
        );
    }

    /**
     * @return array{lat: float, lon: float, nama: string}|null
     */
    private function tanyaNominatim(string $teks): ?array
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
                    'limit' => 1,
                ]);

            $baris = $balasan->successful() ? ($balasan->json('0') ?? null) : null;

            if (! $baris || ! isset($baris['lat'], $baris['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $baris['lat'],
                'lon' => (float) $baris['lon'],
                // Ruas pertama display_name: nama tempatnya, bukan alamat
                // lengkap sampai negaranya.
                'nama' => trim(explode(',', (string) ($baris['display_name'] ?? $teks))[0]),
            ];
        } catch (\Throwable $e) {
            Log::info('Pencarian titik gagal', ['teks' => $teks, 'pesan' => $e->getMessage()]);

            return null;
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
