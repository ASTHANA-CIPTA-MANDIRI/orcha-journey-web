<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Models\PaketWisata\Keberangkatan;
use App\Support\PembagianKelompok;
use App\Support\TataKursi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pembagian kursi kendaraan dan kamar untuk SATU ROMBONGAN.
 *
 * Diletakkan di keberangkatan, bukan di pendaftaran, karena itulah satuan
 * sebenarnya: satu bus mengangkut peserta dari banyak pemesanan sekaligus, dan
 * satu kamar twin diisi dua orang yang bisa saja mendaftar terpisah. Dikerjakan
 * per pendaftaran, dua admin yang membuka dua pemesanan berbeda akan
 * sama-sama menaruh orangnya di "Bus 1 kursi 5" tanpa ada yang bisa melihat
 * bentrokannya — dan yang pertama tahu adalah orang yang berdiri di lorong bus.
 *
 * Yang ditulis balik tetap `daftar_peserta` milik masing-masing pendaftaran.
 * Tidak ada tabel penempatan tersendiri: nama, titik jemput, bus, kursi, dan
 * kamar adalah keterangan tentang orang yang sama, dan memecahnya ke dua tempat
 * berarti satu nama yang diperbaiki ejaannya meninggalkan penempatan yatim.
 */
class PembagianController extends ApiController
{
    /**
     * Keadaan pembagian satu rombongan: siapa saja, di mana, dan apa yang
     * belum beres.
     */
    public function index(Keberangkatan $keberangkatan): JsonResponse
    {
        $peserta = $this->pesertaRombongan($keberangkatan);

        return response()->json([
            'data' => [
                'keberangkatan' => [
                    'id' => $keberangkatan->id,
                    'rentang_tanggal' => $keberangkatan->rentang_tanggal,
                    'tanggal_berangkat' => $keberangkatan->tanggal_berangkat?->toDateString(),
                    'kuota' => $keberangkatan->kuota_dipakai,
                    'paket' => [
                        'id' => $keberangkatan->travel_package_id,
                        'nama' => $keberangkatan->paket?->name,
                    ],
                ],
                'kendaraan' => $keberangkatan->kendaraan ?? [],
                'kamar' => $keberangkatan->kamar ?? [],
                // Petaknya dirakit di sini, bukan di lemon: aturan "sisa kursi
                // jadi bangku belakang" hanya boleh ada di satu tempat.
                'petak' => TataKursi::semua($keberangkatan->kendaraan),
                'pilihan_tata' => TataKursi::pilihan(),
                'peserta' => $peserta,
                'ringkasan' => $this->ringkasan($peserta, $keberangkatan),
                'peringatan' => PembagianKelompok::periksa($peserta),
            ],
        ]);
    }

    /**
     * Mendaftarkan kendaraan dan kamar yang disiapkan rombongan ini.
     *
     * Dipisahkan dari penempatan orangnya. Armada dan blok kamar ditentukan
     * sekali saat dipesan ke vendor; penempatan orangnya berubah sampai hari
     * terakhir. Satu jalur untuk keduanya berarti setiap pergeseran satu orang
     * mengirim ulang seluruh daftar armada — dan satu kesalahan di sana
     * menghapus kendaraan yang pesertanya sudah ditempatkan.
     */
    public function fasilitas(Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'kendaraan' => 'present|array|max:20',
            'kendaraan.*.nama' => 'required|string|max:60',
            'kendaraan.*.tata' => 'required|in:'.implode(',', array_keys(TataKursi::TATA)),
            'kendaraan.*.jumlah_kursi' => 'required|integer|min:1|max:80',
            'kamar' => 'present|array|max:120',
            'kamar.*.nama' => 'required|string|max:60',
            'kamar.*.kapasitas' => 'required|integer|min:1|max:12',
        ], [], [
            'kendaraan.*.nama' => 'nama kendaraan',
            'kendaraan.*.tata' => 'tata tempat duduk',
            'kendaraan.*.jumlah_kursi' => 'jumlah kursi',
            'kamar.*.nama' => 'nama kamar',
            'kamar.*.kapasitas' => 'kapasitas kamar',
        ]);

        $kendaraan = collect($data['kendaraan'])
            ->map(fn ($satu) => [
                'nama' => trim($satu['nama']),
                'tata' => $satu['tata'],
                'jumlah_kursi' => (int) $satu['jumlah_kursi'],
            ])
            ->values()
            ->all();

        $kamar = collect($data['kamar'])
            ->map(fn ($satu) => [
                'nama' => trim($satu['nama']),
                'kapasitas' => (int) $satu['kapasitas'],
            ])
            ->values()
            ->all();

        /*
         | Nama kamar kembar ditolak, bukan dibiarkan.
         |
         | Dua baris bernama "201" membuat pembagian otomatis mengisi kamar 201
         | dua kali dengan jenis kelamin yang berbeda — tepat kesalahan yang
         | seluruh fitur ini dibuat untuk mencegah, dan layarnya tetap terlihat
         | benar karena masing-masing kamar sendirian tidak tercampur.
         */
        $kembarKamar = collect($kamar)
            ->countBy(fn ($satu) => mb_strtolower($satu['nama']))
            ->filter(fn ($jumlah) => $jumlah > 1)
            ->keys();

        if ($kembarKamar->isNotEmpty()) {
            return response()->json([
                'pesan' => 'Ada nama kamar yang dipakai dua kali: '
                    .$kembarKamar->implode(', ').'. Beri nama yang berbeda.',
            ], 422);
        }

        $kembarKendaraan = collect($kendaraan)
            ->countBy(fn ($satu) => mb_strtolower($satu['nama']))
            ->filter(fn ($jumlah) => $jumlah > 1)
            ->keys();

        if ($kembarKendaraan->isNotEmpty()) {
            return response()->json([
                'pesan' => 'Ada nama kendaraan yang dipakai dua kali: '
                    .$kembarKendaraan->implode(', ').'. Beri nama yang berbeda.',
            ], 422);
        }

        $keberangkatan->update(['kendaraan' => $kendaraan, 'kamar' => $kamar]);

        $this->catat($request, 'ubah kendaraan & kamar rombongan', [
            'kode' => $keberangkatan->rentang_tanggal,
            'jumlah_kendaraan' => count($kendaraan),
            'jumlah_kursi' => collect($kendaraan)->sum('jumlah_kursi'),
            'jumlah_kamar' => count($kamar),
            'daya_tampung_kamar' => collect($kamar)->sum('kapasitas'),
        ]);

        return response()->json([
            'data' => [
                'kendaraan' => $kendaraan,
                'kamar' => $kamar,
                'petak' => TataKursi::semua($kendaraan),
            ],
            'pesan' => 'Kendaraan dan kamar rombongan tersimpan.',
        ]);
    }

    /**
     * Menyimpan penempatan yang disusun admin dengan tangan.
     *
     * Bentrokan TIDAK ditolak di sini, hanya dilaporkan balik. Admin yang
     * sedang menukar dua orang pasti melewati keadaan bentrok di tengah
     * jalan, dan penolakan membuatnya harus mengosongkan satu kursi dulu
     * lewat penyimpanan tersendiri — dua kali kerja untuk satu pertukaran.
     */
    public function simpan(Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'peserta' => 'present|array|max:500',
            'peserta.*.kunci' => 'required|string|max:40',
            'peserta.*.bus' => 'nullable|string|max:60',
            'peserta.*.kursi' => 'nullable|string|max:10',
            'peserta.*.kamar' => 'nullable|string|max:60',
            'peserta.*.jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ]);

        $penempatan = collect($data['peserta'])
            ->keyBy(fn ($satu) => (string) $satu['kunci'])
            ->all();

        $jumlah = $this->tulisPenempatan($keberangkatan, $penempatan);

        $this->catat($request, 'ubah pembagian kursi & kamar', [
            'kode' => $keberangkatan->rentang_tanggal,
            'baris_tersentuh' => $jumlah,
        ]);

        $peserta = $this->pesertaRombongan($keberangkatan->fresh());

        return response()->json([
            'data' => [
                'peserta' => $peserta,
                'ringkasan' => $this->ringkasan($peserta, $keberangkatan),
                'peringatan' => PembagianKelompok::periksa($peserta),
            ],
            'pesan' => 'Pembagian tersimpan.',
        ]);
    }

    /**
     * Membagi otomatis: kamar menurut jenis kelamin, kursi menurut pemesanan.
     *
     * Yang SUDAH ditempatkan admin dengan tangan tidak ditimpa kecuali diminta
     * (`timpa=1`). Admin yang sudah memindahkan tiga orang atas permintaan
     * khusus lalu menekan "bagi otomatis" untuk sisanya tidak boleh kehilangan
     * ketiga perpindahan itu — dan kehilangannya tidak kelihatan, karena
     * hasilnya tetap berupa pembagian yang rapi.
     */
    public function otomatis(Keberangkatan $keberangkatan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'apa' => 'required|in:kamar,kursi,keduanya',
            'timpa' => 'sometimes|boolean',
        ], [], ['apa' => 'yang dibagi']);

        $timpa = (bool) ($data['timpa'] ?? false);
        $semua = $this->pesertaRombongan($keberangkatan);

        $penempatan = [];
        $catatan = [];
        $sisa = [];

        if (in_array($data['apa'], ['kamar', 'keduanya'], true)) {
            $calon = $timpa
                ? $semua
                : array_values(array_filter($semua, fn ($satu) => blank($satu['kamar'])));

            // Kamar yang sedang dipakai orang yang TIDAK ikut dibagi harus
            // dikeluarkan dari kolam, kalau tidak yang baru ditaruh menumpuk
            // di atas penghuni yang sengaja ditempatkan admin.
            $terpakai = $timpa
                ? []
                : collect($semua)->pluck('kamar')->filter()
                    ->map(fn ($nama) => mb_strtolower(trim((string) $nama)))->unique()->all();

            $kolam = collect($keberangkatan->kamar ?? [])
                ->reject(fn ($satu) => in_array(mb_strtolower(trim((string) ($satu['nama'] ?? ''))), $terpakai, true))
                ->values()
                ->all();

            $hasil = PembagianKelompok::kamar($calon, $kolam);

            foreach ($hasil['isi'] as $kunci => $nama) {
                $penempatan[$kunci]['kamar'] = $nama;
            }

            $catatan = array_merge($catatan, $hasil['catatan']);
            $sisa = array_merge($sisa, $hasil['sisa']);
        }

        if (in_array($data['apa'], ['kursi', 'keduanya'], true)) {
            $calon = $timpa
                ? $semua
                : array_values(array_filter($semua, fn ($satu) => blank($satu['kursi'])));

            $petak = TataKursi::semua($keberangkatan->kendaraan);

            if (! $timpa) {
                // Kursi yang sudah terpakai dibuang dari petaknya, bukan dari
                // daftar orangnya — supaya yang dibagi mengisi celah yang
                // benar-benar kosong, bukan menumpuk di kursi yang ada
                // penghuninya.
                $sudah = collect($semua)
                    ->filter(fn ($satu) => filled($satu['kursi']))
                    ->map(fn ($satu) => mb_strtolower(trim((string) $satu['bus'])).'|'.trim((string) $satu['kursi']))
                    ->all();

                $petak = collect($petak)
                    ->map(function ($kendaraan) use ($sudah) {
                        $kendaraan['kursi'] = array_values(array_filter(
                            $kendaraan['kursi'],
                            fn ($nomor) => ! in_array(mb_strtolower($kendaraan['nama']).'|'.$nomor, $sudah, true),
                        ));

                        return $kendaraan;
                    })
                    ->all();
            }

            $hasil = PembagianKelompok::kursi($calon, $petak);

            foreach ($hasil['isi'] as $kunci => $tempat) {
                $penempatan[$kunci]['bus'] = $tempat['bus'];
                $penempatan[$kunci]['kursi'] = $tempat['kursi'];
            }

            $catatan = array_merge($catatan, $hasil['catatan']);
            $sisa = array_merge($sisa, $hasil['sisa']);
        }

        $this->tulisPenempatan($keberangkatan, $penempatan, gabung: true);

        $this->catat($request, 'bagi otomatis kursi & kamar', [
            'kode' => $keberangkatan->rentang_tanggal,
            'yang_dibagi' => $data['apa'],
            'menimpa_yang_sudah_ada' => $timpa ? 'ya' : 'tidak',
            'jumlah_ditempatkan' => count($penempatan),
            'jumlah_sisa' => count($sisa),
        ]);

        $peserta = $this->pesertaRombongan($keberangkatan->fresh());

        $pesan = count($penempatan) === 0
            ? 'Tidak ada yang bisa dibagi.'
            : count($penempatan).' peserta ditempatkan.';

        return response()->json([
            'data' => [
                'peserta' => $peserta,
                'ringkasan' => $this->ringkasan($peserta, $keberangkatan),
                'peringatan' => PembagianKelompok::periksa($peserta),
                'sisa' => $sisa,
                'catatan' => array_values(array_unique($catatan)),
            ],
            'pesan' => $pesan,
        ]);
    }

    /**
     * Seluruh peserta rombongan ini, dari semua pendaftaran, dalam satu daftar.
     *
     * Pendaftaran yang BATAL tidak ikut. Kursinya sudah dilepas dan kamarnya
     * sudah dikembalikan ke hotel; membiarkannya di daftar membuat pembagian
     * otomatis memesan tempat untuk orang yang tidak berangkat, lalu melaporkan
     * kursi kurang.
     *
     * Jenis kelamin yang belum terisi di baris pesertanya diambilkan dari
     * riwayat kesehatan — DITANDAI sebagai "dari kesehatan", bukan
     * disenyapkan. Admin yang melihat kolomnya terisi perlu tahu bahwa isinya
     * datang dari borang yang diisi pesertanya sendiri, bukan dari dirinya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pesertaRombongan(Keberangkatan $keberangkatan): array
    {
        $pendaftaran = $keberangkatan->pendaftaran()
            ->where('status', '!=', 'batal')
            ->orderBy('id')
            ->get();

        // Satu kueri untuk seluruh rombongan, bukan satu per pendaftaran.
        $kelaminKesehatan = RiwayatKesehatan::whereIn('kode_pendaftaran', $pendaftaran->pluck('kode'))
            ->whereNotNull('jenis_kelamin')
            ->get(['kode_pendaftaran', 'nama_peserta', 'jenis_kelamin'])
            ->mapWithKeys(fn ($satu) => [
                $satu->kode_pendaftaran.'|'.mb_strtolower(trim((string) $satu->nama_peserta)) => $satu->jenis_kelamin,
            ]);

        $hasil = [];

        foreach ($pendaftaran as $satu) {
            foreach ($satu->peserta as $urutan => $orang) {
                $dariKesehatan = $kelaminKesehatan[
                    $satu->kode.'|'.mb_strtolower(trim((string) $orang['nama']))
                ] ?? null;

                $hasil[] = [
                    // Kunci dirakit dari id pendaftaran + urutan di daftarnya.
                    // Bukan dari nama: dua peserta bernama sama dalam satu
                    // rombongan bukan hal aneh (ayah dan anak), dan kunci
                    // bernama membuat keduanya menempati satu kursi.
                    'kunci' => $satu->id.':'.$urutan,
                    'pendaftaran_id' => $satu->id,
                    'urutan' => $urutan,
                    'kode' => $satu->kode,
                    'pemesan' => $satu->nama,
                    'status' => $satu->status,
                    'nama' => $orang['nama'],
                    'titik_jemput' => $orang['titik_jemput'],
                    'bus' => $orang['bus'],
                    'kursi' => $orang['kursi'] ?? null,
                    'kamar' => $orang['kamar'],
                    'jenis_kelamin' => $orang['jenis_kelamin'] ?? $dariKesehatan,
                    // true bila isinya datang dari borang kesehatan, bukan
                    // diketik admin di layar ini.
                    'kelamin_dari_kesehatan' => blank($orang['jenis_kelamin'] ?? null) && filled($dariKesehatan),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Menulis penempatan balik ke `daftar_peserta` masing-masing pendaftaran.
     *
     * Satu penyimpanan per pendaftaran yang BERUBAH, bukan per peserta: satu
     * rombongan 44 orang berasal dari belasan pemesanan, dan menyimpan per
     * orang berarti belasan tulisan untuk pembagian yang satu kali tekan.
     *
     * @param  array<string, array<string, mixed>>  $penempatan  kunci => medan yang diubah
     * @param  bool  $gabung  true: hanya medan yang disebut yang diubah
     */
    private function tulisPenempatan(Keberangkatan $keberangkatan, array $penempatan, bool $gabung = false): int
    {
        if ($penempatan === []) {
            return 0;
        }

        /*
         | preserveKeys: true — WAJIB, bukan selera.
         |
         | groupBy() membuang kunci aslinya secara bawaan dan menomori ulang
         | tiap kelompok dari nol. Kunci itulah yang menyimpan urutan peserta
         | ("12:3"), jadi tanpanya setiap baris dibaca sebagai urutan -1,
         | semuanya dilewati, dan penyimpanannya tetap berhasil dengan data
         | yang tidak berubah — berhasil yang tidak mengubah apa pun, tanpa
         | satu pun galat.
         */
        $perPendaftaran = collect($penempatan)
            ->groupBy(fn ($satu, $kunci) => explode(':', (string) $kunci)[0], preserveKeys: true);

        $tersentuh = 0;

        foreach ($perPendaftaran as $pendaftaranId => $baris) {
            $pendaftaran = $keberangkatan->pendaftaran()->find((int) $pendaftaranId);

            if (! $pendaftaran) {
                continue;
            }

            // Dibaca lewat aksesor supaya bentuknya seragam, termasuk untuk
            // data lama yang menyimpan nama sebagai teks telanjang.
            $daftar = $pendaftaran->peserta;

            foreach ($baris as $kunci => $isi) {
                $urutan = (int) (explode(':', (string) $kunci)[1] ?? -1);

                if (! isset($daftar[$urutan])) {
                    continue;
                }

                foreach (['bus', 'kursi', 'kamar', 'jenis_kelamin'] as $medan) {
                    if ($gabung && ! array_key_exists($medan, $isi)) {
                        continue;
                    }

                    $nilai = trim((string) ($isi[$medan] ?? ''));
                    $daftar[$urutan][$medan] = $nilai !== '' ? $nilai : null;
                }
            }

            $pendaftaran->update(['daftar_peserta' => $daftar ?: null]);
            $tersentuh++;
        }

        return $tersentuh;
    }

    /**
     * Angka yang dibaca admin sebelum menyentuh apa pun: berapa orang, berapa
     * yang sudah dapat kursi, berapa yang jenis kelaminnya belum diisi.
     *
     * Yang terakhir itu yang paling penting, karena ia satu-satunya yang
     * MENGHALANGI pembagian kamar — dan tanpa angkanya di muka, admin baru
     * tahu setelah menekan "bagi otomatis" dan mendapat hasil separuh.
     *
     * @param  array<int, array<string, mixed>>  $peserta
     */
    private function ringkasan(array $peserta, Keberangkatan $keberangkatan): array
    {
        $daftar = collect($peserta);

        return [
            'total_peserta' => $daftar->count(),
            'laki' => $daftar->where('jenis_kelamin', PembagianKelompok::LAKI)->count(),
            'perempuan' => $daftar->where('jenis_kelamin', PembagianKelompok::PEREMPUAN)->count(),
            'belum_jenis_kelamin' => $daftar->filter(
                fn ($satu) => ! in_array($satu['jenis_kelamin'], [PembagianKelompok::LAKI, PembagianKelompok::PEREMPUAN], true)
            )->count(),
            'sudah_kursi' => $daftar->filter(fn ($satu) => filled($satu['kursi']))->count(),
            'sudah_kamar' => $daftar->filter(fn ($satu) => filled($satu['kamar']))->count(),
            'jumlah_kursi_tersedia' => collect($keberangkatan->kendaraan ?? [])->sum('jumlah_kursi'),
            'daya_tampung_kamar' => collect($keberangkatan->kamar ?? [])->sum('kapasitas'),
        ];
    }
}
