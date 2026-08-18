<?php

namespace App\Support\Etalase;

use App\Models\Etalase\DaerahTambahan;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\ProvinsiTambahan;
use App\Models\Etalase\WilayahTambahan;
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
 *   2. Ensiklopedia (Wikipedia bahasa Indonesia), yang menyebut daerah
 *      sebagaimana orang menyebutnya.
 *   3. Nominatim (OpenStreetMap), untuk nama yang tidak berartikel.
 *
 * Ensiklopedia didahulukan atas peta KARENA DAERAHNYA, bukan karena provinsinya
 * — keduanya menjawab provinsi dengan sama baik. Pulau Menjangan Kecil
 * contohnya: peta hanya tahu ia di Jepara, sebab batas Kecamatan Karimunjawa
 * memang tidak ada di OpenStreetMap (hierarkinya melompat dari pulau langsung
 * ke kabupaten), sedangkan artikelnya menyebut Karimunjawa apa adanya. Dan
 * "Karimunjawa" itulah yang dicari pengunjung.
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
    private const VERSI = 4;

    /**
     * @return array{provinsi: string, wilayah: string, daerah: ?string, sumber: string}|null
     */
    public function cari(string $nama): ?array
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama));

        if (mb_strlen($nama) < self::HURUF_MINIMAL) {
            return null;
        }

        return $this->dariDestinasi($nama)
            ?? $this->dariEnsiklopedia($nama)
            ?? $this->dariPeta($nama);
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

    /**
     * Provinsi dan daerah menurut ensiklopedia.
     *
     * Judul artikel harus SAMA PERSIS dengan yang ditanyakan — bukan sekadar
     * mirip. Pencarian "Pulau Menjangan Kecil" mengembalikan "Pulau Menjangan"
     * di urutan teratas, pulau yang sama sekali lain di Bali Barat; nama itu
     * seluruhnya termuat di dalam yang ditanyakan, jadi ukuran kemiripan mana
     * pun akan menerimanya. Yang membedakan keduanya hanya kesamaan persis.
     */
    private function dariEnsiklopedia(string $nama): ?array
    {
        if (! config('orcha.ensiklopedia.aktif', true)) {
            return null;
        }

        $kunci = 'orcha.ensiklopedia.v'.self::VERSI.'.'.md5(mb_strtolower($nama));

        return Cache::remember($kunci, now()->addDays(self::SIMPAN_HARI), function () use ($nama) {
            foreach ($this->varianPertanyaan($nama) as $pertanyaan) {
                $jawaban = $this->tanyaEnsiklopedia($pertanyaan);

                if ($jawaban !== null) {
                    return $jawaban;
                }
            }

            return null;
        });
    }

    /**
     * Satu pertanyaan ke ensiklopedia.
     *
     * Pencarian dan pengambilan pembuka artikel digabung dalam SATU permintaan
     * lewat generator, bukan dua: layanan gratis ini kita tumpangi, dan dua
     * kali perjalanan untuk satu pertanyaan juga terasa oleh admin yang sedang
     * mengetik.
     */
    private function tanyaEnsiklopedia(string $pertanyaan): ?array
    {
        try {
            $balasan = Http::withHeaders([
                'User-Agent' => config('orcha.peta.pengenal', 'OrchaJourney/1.0'),
            ])
                ->connectTimeout(3)
                ->timeout(5)
                ->get(config('orcha.ensiklopedia.alamat', 'https://id.wikipedia.org/w/api.php'), [
                    'action' => 'query',
                    'generator' => 'search',
                    'gsrsearch' => $pertanyaan,
                    'gsrlimit' => 5,
                    'prop' => 'extracts',
                    'exintro' => 1,
                    'explaintext' => 1,
                    'format' => 'json',
                ]);

            if ($balasan->failed()) {
                return null;
            }

            $samakan = fn (string $teks) => mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks)));

            foreach ($balasan->json('query.pages') ?? [] as $halaman) {
                if ($samakan((string) ($halaman['title'] ?? '')) !== $samakan($pertanyaan)) {
                    continue;
                }

                return $this->bacaPembuka((string) ($halaman['extract'] ?? ''));
            }

            return null;
        } catch (\Throwable $e) {
            Log::info('Pencarian ensiklopedia gagal', ['nama' => $pertanyaan, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Membaca provinsi dan daerah dari kalimat pembuka artikel.
     *
     * Artikel tempat di Wikipedia Indonesia menyebut kedudukannya dengan
     * susunan yang tetap: "... Desa Karimunjawa, Kecamatan Karimunjawa,
     * Kabupaten Jepara, Provinsi Jawa Tengah."
     *
     * Yang dipakai sebagai daerah KECAMATAN, tetapi hanya bila katalog daerah
     * kita memang mengenalnya. Aturannya bukan selera: katalog itu persis
     * daftar yang ditawarkan pemilih daerah, jadi nama yang tidak ada di sana
     * akan terisi tetapi tidak pernah bisa dipilih ulang — dan kecamatan yang
     * tidak dikenal siapa pun memang lebih buruk daripada nama kabupatennya.
     */
    private function bacaPembuka(string $pembuka): ?array
    {
        $provinsi = $this->petik($pembuka, 'Provinsi');

        if (! $provinsi) {
            return null;
        }

        $provinsi = $this->samakan($provinsi);
        $wilayah = ProvinsiTambahan::gabungan()[$provinsi] ?? null;

        if (! $wilayah) {
            return null;
        }

        $kecamatan = $this->petik($pembuka, 'Kecamatan');
        $kabupaten = $this->petik($pembuka, 'Kabupaten');
        $kota = $this->petik($pembuka, 'Kota');

        $dikenal = ($kecamatan && (DaerahTambahan::gabungan()[$kecamatan] ?? null) === $provinsi)
            ? $kecamatan
            : null;

        $daerah = $dikenal ?? $kabupaten ?? ($kota ? 'Kota '.$kota : null);

        return [
            'provinsi' => $provinsi,
            'wilayah' => $wilayah,
            'daerah' => $daerah,
            'sumber' => 'ensiklopedia',
        ];
    }

    /**
     * Nama yang mengikuti sebuah sebutan administratif, atau null.
     *
     * Berhenti di tanda baca berikutnya — nama daerah bisa lebih dari satu
     * kata ("Jawa Tengah", "Nusa Tenggara Barat") dan memotongnya di spasi
     * pertama akan menghasilkan provinsi yang tidak pernah ada.
     */
    private function petik(string $teks, string $sebutan): ?string
    {
        $pola = '/\b'.preg_quote($sebutan, '/').'\s+(\p{Lu}[\p{L}\x27\- ]{2,40}?)\s*(?=[,.;:)]|$)/u';

        if (! preg_match($pola, $teks, $cocok)) {
            return null;
        }

        return trim($cocok[1]) ?: null;
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
     * Bentuk-bentuk pertanyaan untuk satu nama destinasi.
     *
     * Nama yang dipakai orang sering bukan nama yang ada di sumber luar, dan
     * ada DUA cara nama itu melenceng — hanya satu di antaranya boleh
     * diperbaiki dengan memenggal:
     *
     *   1. dua tempat ditulis sekaligus. "Pulau Cemara Kecil & Besar" adalah
     *      dua pulau; peta menjawab kosong untuk nama itu, padahal "Pulau
     *      Cemara Kecil" ada dan tercatat rapi di Jepara. Dipenggal di "&",
     *      "dan", "+", dan "/" — yang dibuang tempat KEDUA, bukan keterangan
     *      tempat pertama;
     *   2. nama daerah disambungkan di belakang, seperti "Kawah Ijen
     *      Banyuwangi" dan "Pantai Kuta Bali". Ekor itu boleh dibuang, TAPI
     *      hanya bila ia memang nama daerah, provinsi, atau wilayah yang kita
     *      kenal.
     *
     * Yang TIDAK boleh dibuang kata terakhir sembarangan, dan ini pernah saya
     * lakukan: "Pulau Menjangan Kecil" dipenggal jadi "Pulau Menjangan" —
     * pulau yang sama sekali lain, di Bali Barat, lengkap dengan artikelnya
     * sendiri. Kata "Kecil" itu justru yang membedakan keduanya. Keterangan
     * seperti itu bukan embel-embel; ia bagian dari namanya.
     *
     * @return list<string>
     */
    private function varianPertanyaan(string $nama): array
    {
        $varian = [$nama];

        $pecah = preg_split('/\s*(?:&|\bdan\b|\+|\/)\s*/iu', $nama, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $utama = trim($pecah[0] ?? '');

        if ($utama !== '' && $utama !== $nama) {
            $varian[] = $utama;
        }

        $tanpaEkor = $this->buangEkorDaerah($utama !== '' ? $utama : $nama);

        if ($tanpaEkor !== null) {
            $varian[] = $tanpaEkor;
        }

        return array_slice(array_values(array_unique($varian)), 0, self::VARIAN_MAKS);
    }

    /**
     * Membuang nama daerah yang disambungkan di belakang nama tempat.
     *
     * Yang dikenal diambil dari katalog kita sendiri — daerah, provinsi, dan
     * label wilayah — bukan dari daftar kata yang dikarang di sini. Ekornya
     * dicoba sampai tiga kata karena nama daerah bisa panjang ("Nusa Tenggara
     * Barat"), dan tidak pernah menyisakan kurang dari dua kata.
     */
    private function buangEkorDaerah(string $nama): ?string
    {
        $kata = preg_split('/\s+/', $nama, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $dikenal = [];

        foreach (array_merge(
            array_keys(DaerahTambahan::gabungan()),
            array_keys(ProvinsiTambahan::gabungan()),
            array_values(WilayahTambahan::gabungan()),
        ) as $sebutan) {
            $dikenal[mb_strtolower((string) $sebutan)] = true;
        }

        for ($panjang = 3; $panjang >= 1; $panjang--) {
            if (count($kata) - $panjang < 2) {
                continue;
            }

            $ekor = mb_strtolower(implode(' ', array_slice($kata, -$panjang)));

            if (isset($dikenal[$ekor])) {
                return implode(' ', array_slice($kata, 0, -$panjang));
            }
        }

        return null;
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
