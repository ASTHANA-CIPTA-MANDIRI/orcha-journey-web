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
     * Seberapa mirip nama tempat yang ditemukan harus dengan yang ditanyakan.
     *
     * Tanpa ambang ini jawaban teratas dipakai apa adanya, sekuat apa pun
     * kaitannya. Terukur pada nama sungguhan: mengetik "Pula" menjawab
     * "Kahyangan Jagat Pula Ulun Swi" di Jimbaran — dan formulir pun terisi
     * BALI untuk sebuah pulau di Jawa Tengah, semata karena admin baru
     * mengetik empat huruf pertama.
     */
    private const AMBANG_MIRIP = 0.6;

    /**
     * Banyaknya bentuk pertanyaan yang dicoba untuk satu nama.
     *
     * Dibatasi karena tiap bentuk satu permintaan ke layanan gratis yang kita
     * menumpang padanya.
     */
    private const VARIAN_MAKS = 3;

    /**
     * Versi bentuk jawaban yang disimpan.
     *
     * Dinaikkan setiap kali isi jawabannya bertambah atau berubah bentuk.
     * Tanpa ini, simpanan tiga puluh hari berbentuk lama tetap terpakai dan
     * diam-diam kehilangan medan baru — persis yang terjadi ketika daerah
     * ditambahkan: provinsi terisi, daerah tidak, dan tidak ada satu pun tanda
     * bahwa penyebabnya cuma jawaban lama yang masih tersimpan.
     *
     * Dinaikkan juga ketika MUTU jawabannya berubah, bukan hanya bentuknya:
     * jawaban keliru yang sudah tersimpan akan hidup tiga puluh hari lagi
     * walaupun sebabnya sudah diperbaiki hari ini.
     */
    private const VERSI = 3;

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
        $calon = DestinationPopuler::query()
            ->whereNotNull('provinsi')
            ->where('provinsi', '!=', '')
            ->where(fn ($q) => $q->where('destination_name', 'like', "%{$nama}%")
                ->orWhereRaw('? like concat("%", destination_name, "%")', [$nama]))
            ->orderByDesc('total_visitor')
            ->get();

        // Penyaringan kedua, di sini dan bukan di SQL: LIKE mencocokkan
        // POTONGAN HURUF, bukan kata. Mengetik "Pula" mencocokkan "KePULAuan
        // Derawan", dan formulir terisi Kalimantan Timur untuk pulau di Jawa
        // Tengah — semata karena admin baru mengetik empat huruf pertama.
        $baris = $calon->first(fn ($b) => $this->sekata($nama, (string) $b->destination_name));

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

    /**
     * Benarkah keduanya berbagi setidaknya satu KATA utuh?
     *
     * Longgarnya pencocokan di sini memang disengaja — "Bromo" harus tetap
     * menemukan "Bromo Tengger Semeru" — tetapi longgar pada kata, bukan pada
     * huruf. Kata pendek diabaikan: "di" dan "ke" ada di mana-mana dan tidak
     * menerangkan apa pun.
     */
    private function sekata(string $satu, string $dua): bool
    {
        $pecah = function (string $teks): array {
            $teks = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks)));

            return array_values(array_filter(
                preg_split('/\s+/', $teks) ?: [],
                fn ($kata) => mb_strlen($kata) >= self::HURUF_MINIMAL,
            ));
        };

        foreach ($pecah($satu) as $kata) {
            foreach ($pecah($dua) as $banding) {
                similar_text($kata, $banding, $persen);

                if ($persen >= 80) {
                    return true;
                }
            }
        }

        return false;
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
     * Nama destinasi tidak selalu berbentuk nama tempat di peta, jadi yang
     * ditanyakan bukan hanya apa adanya — lihat varianPertanyaan(). Berhenti
     * pada bentuk pertama yang jawabannya cukup mirip.
     */
    private function tanyaNominatim(string $nama): ?array
    {
        foreach ($this->varianPertanyaan($nama) as $pertanyaan) {
            $jawaban = $this->tanyaSekali($pertanyaan);

            if ($jawaban !== null) {
                return $jawaban;
            }
        }

        return null;
    }

    /**
     * Bentuk-bentuk pertanyaan untuk satu nama destinasi, dari yang paling
     * setia sampai yang paling longgar.
     *
     * Nama yang dipakai orang sering bukan nama yang ada di peta. "Pulau
     * Cemara Kecil & Besar" adalah DUA pulau — peta tidak mengenal satu tempat
     * dengan nama itu dan menjawab kosong, padahal "Pulau Cemara Kecil" ada
     * dan tercatat rapi di Jepara, Jawa Tengah.
     *
     * Pemenggalannya berhenti di separuh kata, dan tidak pernah menyisakan
     * kurang dari dua kata: "Pulau" sendirian akan menemukan desa bernama
     * Pulau di kabupaten mana pun, dan jawaban seperti itu lebih buruk
     * daripada tidak menjawab.
     *
     * @return list<string>
     */
    private function varianPertanyaan(string $nama): array
    {
        $varian = [$nama];

        // Dipenggal di "&", "dan", "+", dan "/" — penanda bahwa yang ditulis
        // sebenarnya dua tempat sekaligus.
        $pecah = preg_split('/\s*(?:&|\bdan\b|\+|\/)\s*/iu', $nama, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $utama = trim($pecah[0] ?? '');

        if ($utama !== '' && $utama !== $nama) {
            $varian[] = $utama;
        }

        $kata = preg_split('/\s+/', $utama !== '' ? $utama : $nama, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lantai = max(2, (int) ceil(count($kata) / 2));

        for ($n = count($kata) - 1; $n >= $lantai; $n--) {
            $varian[] = implode(' ', array_slice($kata, 0, $n));
        }

        return array_slice(array_values(array_unique($varian)), 0, self::VARIAN_MAKS);
    }

    /**
     * Satu pertanyaan ke Nominatim, dan jawaban terbaik yang cukup mirip.
     *
     * Diminta lima calon, bukan satu: jawaban teratas menurut peta belum tentu
     * jawaban yang namanya paling mendekati yang ditanyakan.
     *
     * Sengaja dibungkus rescue: layanan gratis boleh saja mati, lambat, atau
     * berubah bentuk jawabannya — dan tidak satu pun dari itu pantas membuat
     * admin gagal menyimpan destinasi.
     */
    private function tanyaSekali(string $pertanyaan): ?array
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
                    'q' => $pertanyaan,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'countrycodes' => 'id',
                    'limit' => 5,
                ]);

            if ($balasan->failed()) {
                return null;
            }

            $terbaik = null;
            $nilaiTerbaik = 0.0;

            foreach ($balasan->json() ?? [] as $calon) {
                $alamat = $calon['address'] ?? [];

                // Nominatim menyebut provinsi sebagai "state"; sebagian tempat
                // hanya punya "region" atau "county".
                $provinsi = $alamat['state'] ?? $alamat['region'] ?? null;

                if (! $provinsi) {
                    continue;
                }

                $nilai = $this->kemiripan($pertanyaan, $this->namaTempat($calon));

                if ($nilai < self::AMBANG_MIRIP || $nilai <= $nilaiTerbaik) {
                    continue;
                }

                // Daerah: kabupaten/kota lebih dulu, lalu satuan yang lebih
                // kecil. OSM tidak selalu menyebut ketiganya, dan yang paling
                // berguna bagi penyewa adalah nama yang dikenalnya —
                // "Banyuwangi", bukan nama desa yang tidak pernah didengarnya.
                $daerah = $alamat['county'] ?? $alamat['city'] ?? $alamat['town']
                    ?? $alamat['municipality'] ?? null;

                $nilaiTerbaik = $nilai;
                $terbaik = [$this->samakan($provinsi), $this->rapikanDaerah($daerah)];
            }

            return $terbaik;
        } catch (\Throwable $e) {
            Log::info('Pencarian lokasi gagal', ['nama' => $pertanyaan, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Nama tempat menurut jawaban peta.
     *
     * display_name berisi alamat lengkap sampai negaranya; yang dibandingkan
     * hanya nama tempatnya sendiri — ruas pertama.
     */
    private function namaTempat(array $calon): string
    {
        if (! blank($calon['name'] ?? null)) {
            return (string) $calon['name'];
        }

        return trim(explode(',', (string) ($calon['display_name'] ?? ''))[0]);
    }

    /**
     * Seberapa besar bagian nama TEMUAN yang terwakili di nama yang ditanyakan.
     *
     * Arahnya penting, dan sempat terbalik dalam pikiran saya: yang diukur
     * bagian temuan, bukan bagian pertanyaan. Kalau yang diukur pertanyaannya,
     * "Pula" akan dinilai cocok sempurna dengan "Kahyangan Jagat Pula Ulun
     * Swi" — kata satu-satunya memang ada di sana. Diukur dari sisi temuan,
     * nilainya 1 dari 5, dan jawabannya ditolak sebagaimana mestinya.
     *
     * Perbandingan per kata dibuat longgar supaya ejaan peta yang berbeda
     * tetap lolos: peta menulis "Pulau Cemoro Kecil" untuk yang oleh orang
     * disebut "Pulau Cemara Kecil".
     */
    private function kemiripan(string $ditanya, string $ditemukan): float
    {
        $pecah = function (string $teks): array {
            $teks = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks)));

            return array_values(array_filter(preg_split('/\s+/', $teks) ?: []));
        };

        $tanya = $pecah($ditanya);
        $temu = $pecah($ditemukan);

        if (! $tanya || ! $temu) {
            return 0.0;
        }

        $cocok = 0;

        foreach ($temu as $kata) {
            foreach ($tanya as $banding) {
                similar_text($kata, $banding, $persen);

                if ($persen >= 80) {
                    $cocok++;

                    break;
                }
            }
        }

        return $cocok / count($temu);
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
