<?php

namespace App\Support\Etalase;

use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\ProvinsiTambahan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Menebak provinsi dan wilayah dari nama destinasi.
 *
 * Berbeda dari daftar provinsi yang sengaja disimpan sendiri: nama tempat itu
 * terbuka — tidak bisa didaftar habis — sehingga peta luar memang alat yang
 * tepat. Sifatnya USULAN: hasilnya mengisi isian yang masih kosong dan tetap
 * bisa diubah admin. Bila layanannya mati atau lambat, formulir tetap jalan dan
 * admin mengisi manual seperti biasa.
 *
 * Dua sumber, berurutan:
 *
 *   1. Destinasi yang SUDAH tercatat. Gratis, seketika, dan mencerminkan
 *      keputusan admin sendiri — "Bromo" yang dulu ditempatkan di Jawa Timur
 *      tidak perlu ditanyakan lagi ke siapa pun.
 *   2. Nominatim (OpenStreetMap), untuk nama yang belum pernah dicatat.
 *
 * Hasilnya disimpan lama: provinsi sebuah tempat tidak berubah, dan menembak
 * layanan yang sama untuk pertanyaan yang sama hanya memperlambat admin sekaligus
 * membebani layanan gratis yang kita menumpang padanya.
 */
class CariLokasi
{
    /** Cukup panjang untuk membedakan tempat; di bawah ini hasilnya asal-asalan. */
    private const HURUF_MINIMAL = 4;

    private const SIMPAN_HARI = 30;

    /**
     * Versi bentuk jawaban yang disimpan.
     *
     * Dinaikkan setiap kali isi jawabannya bertambah atau berubah bentuk.
     * Tanpa ini, simpanan tiga puluh hari berbentuk lama tetap terpakai dan
     * diam-diam kehilangan medan baru — persis yang terjadi ketika daerah
     * ditambahkan: provinsi terisi, daerah tidak, dan tidak ada satu pun tanda
     * bahwa penyebabnya cuma jawaban lama yang masih tersimpan.
     */
    private const VERSI = 2;

    /**
     * @return array{provinsi: string, wilayah: string, daerah: ?string, sumber: string}|null
     */
    public function cari(string $nama): ?array
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama));

        if (mb_strlen($nama) < self::HURUF_MINIMAL) {
            return null;
        }

        return $this->dariDestinasi($nama) ?? $this->dariPeta($nama);
    }

    /**
     * Destinasi yang sudah tercatat — dicocokkan longgar supaya "Bromo" menemukan
     * "Bromo Tengger Semeru", dan sebaliknya.
     */
    private function dariDestinasi(string $nama): ?array
    {
        $baris = DestinationPopuler::query()
            ->whereNotNull('provinsi')
            ->where('provinsi', '!=', '')
            ->where(fn ($q) => $q->where('destination_name', 'like', "%{$nama}%")
                ->orWhereRaw('? like concat("%", destination_name, "%")', [$nama]))
            ->orderByDesc('total_visitor')
            ->first();

        if (! $baris) {
            return null;
        }

        return [
            'provinsi' => $baris->provinsi,
            'wilayah' => $baris->wilayah,
            'daerah' => $baris->daerah,
            'sumber' => 'destinasi',
        ];
    }

    private function dariPeta(string $nama): ?array
    {
        if (! config('orcha.peta.aktif', true)) {
            return null;
        }

        $kunci = 'orcha.lokasi.v'.self::VERSI.'.'.md5(mb_strtolower($nama));

        return Cache::remember($kunci, now()->addDays(self::SIMPAN_HARI), function () use ($nama) {
            $jawaban = $this->tanyaNominatim($nama);

            if ($jawaban === null) {
                return null;
            }

            [$provinsi, $daerah] = $jawaban;
            $wilayah = ProvinsiTambahan::gabungan()[$provinsi] ?? null;

            // Provinsi yang tidak dikenal daftar kita tidak dipakai: mengisinya
            // berarti destinasi tercatat di provinsi yang tidak punya wilayah,
            // dan penyaring di halaman publik tidak akan menemukannya.
            return $wilayah
                ? ['provinsi' => $provinsi, 'wilayah' => $wilayah, 'daerah' => $daerah, 'sumber' => 'peta']
                : null;
        });
    }

    /**
     * Provinsi dan daerah menurut OpenStreetMap, atau null bila tidak ketemu.
     *
     * Sengaja dibungkus rescue: layanan gratis boleh saja mati, lambat, atau
     * berubah bentuk jawabannya — dan tidak satu pun dari itu pantas membuat
     * admin gagal menyimpan destinasi.
     */
    private function tanyaNominatim(string $nama): ?array
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
                ->get(config('orcha.peta.alamat', 'https://nominatim.openstreetmap.org/search'), [
                    'q' => $nama,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'countrycodes' => 'id',
                    'limit' => 1,
                ]);

            if ($balasan->failed()) {
                return null;
            }

            $alamat = $balasan->json('0.address') ?? [];

            // Nominatim menyebut provinsi sebagai "state"; sebagian tempat hanya
            // punya "region" atau "county".
            $provinsi = $alamat['state'] ?? $alamat['region'] ?? null;

            if (! $provinsi) {
                return null;
            }

            // Daerah: kabupaten/kota lebih dulu, lalu satuan yang lebih kecil.
            // OSM tidak selalu menyebut ketiganya, dan yang paling berguna bagi
            // penyewa adalah nama yang dikenalnya — "Banyuwangi", bukan nama
            // desa yang tidak pernah didengarnya.
            $daerah = $alamat['county'] ?? $alamat['city'] ?? $alamat['town']
                ?? $alamat['municipality'] ?? null;

            return [$this->samakan($provinsi), $this->rapikanDaerah($daerah)];
        } catch (\Throwable $e) {
            Log::info('Pencarian lokasi gagal', ['nama' => $nama, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Membuang awalan administratif yang tidak dipakai penyewa.
     *
     * OSM menulis "Kabupaten Banyuwangi" dan "Kota Batu"; yang dicari dan
     * disebut penyewa "Banyuwangi" dan "Kota Batu". Hanya "Kabupaten" yang
     * dibuang — "Kota" ikut membedakan Kota Malang dari Kabupaten Malang.
     */
    private function rapikanDaerah(?string $daerah): ?string
    {
        if (blank($daerah)) {
            return null;
        }

        $daerah = trim(preg_replace('/^Kabupaten\s+/i', '', trim($daerah)));

        return $daerah ?: null;
    }

    /**
     * Menyamakan ejaan OSM dengan daftar provinsi kita.
     *
     * OSM menulis "Daerah Istimewa Yogyakarta" dan "Daerah Khusus Ibukota
     * Jakarta"; daftar kita menulis "DI Yogyakarta" dan "DKI Jakarta". Tanpa
     * penyamaan ini keduanya dianggap provinsi yang berbeda, dan usulannya
     * dibuang justru untuk dua provinsi paling ramai.
     */
    private function samakan(string $provinsi): string
    {
        $provinsi = trim(preg_replace('/\s+/', ' ', $provinsi));

        $padanan = [
            'daerah istimewa yogyakarta' => 'DI Yogyakarta',
            'di yogyakarta' => 'DI Yogyakarta',
            'yogyakarta' => 'DI Yogyakarta',
            'daerah khusus ibukota jakarta' => 'DKI Jakarta',
            'daerah khusus ibu kota jakarta' => 'DKI Jakarta',
            'jakarta' => 'DKI Jakarta',
            'kepulauan bangka belitung' => 'Kepulauan Bangka Belitung',
            'bangka belitung' => 'Kepulauan Bangka Belitung',
        ];

        $kunci = mb_strtolower($provinsi);

        if (isset($padanan[$kunci])) {
            return $padanan[$kunci];
        }

        // Selebihnya dicocokkan tanpa memandang huruf besar-kecil terhadap
        // daftar yang berlaku, supaya "JAWA TIMUR" tetap ketemu.
        foreach (array_keys(ProvinsiTambahan::gabungan()) as $kita) {
            if (mb_strtolower($kita) === $kunci) {
                return $kita;
            }
        }

        return $provinsi;
    }
}
