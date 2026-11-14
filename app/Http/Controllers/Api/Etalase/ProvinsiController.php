<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Models\Etalase\DaerahTambahan;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\Etalase\ProvinsiTambahan;
use App\Models\Etalase\WilayahTambahan;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\RabItinerary;
use App\Support\Etalase\CariLokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Provinsi yang ditambahkan admin sendiri.
 *
 * Daftar bawaan 38 provinsi sudah cukup untuk hari ini, tetapi provinsi bisa
 * dimekarkan — 2022 saja bertambah empat sekaligus. Tanpa jalur ini, admin
 * harus menunggu rilis kode hanya untuk mencatat destinasi di provinsi baru.
 */
class ProvinsiController extends ApiController
{
    /**
     * Menebak provinsi dan wilayah dari nama destinasi.
     *
     * Jawaban kosong bukan kegagalan: banyak nama memang tidak dikenali, dan
     * yang benar untuk itu adalah admin mengisi sendiri — bukan galat yang
     * menghentikannya.
     */
    public function cariLokasi(Request $request, CariLokasi $peta): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:191'],
        ], [], ['nama' => 'nama destinasi']);

        return response()->json(['data' => $peta->cari($data['nama'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            // Wilayah tambahan ikut sah: provinsi baru boleh ditempatkan di
            // wilayah yang juga baru ditambahkan admin.
            'wilayah' => ['required', 'in:'.implode(',', array_keys(WilayahTambahan::gabungan()))],
        ], [], ['nama' => 'nama provinsi']);

        $nama = ProvinsiTambahan::rapikan($data['nama']);

        // Yang sudah ada tidak ditambahkan lagi, dan itu bukan kegagalan:
        // admin memang menginginkan provinsi itu ada di daftar, dan ia sudah ada.
        if (array_key_exists($nama, ProvinsiTambahan::gabungan())) {
            return response()->json([
                'pesan' => "{$nama} sudah ada di daftar.",
                'data' => ProvinsiTambahan::kustom(),
            ]);
        }

        ProvinsiTambahan::create(['nama' => $nama, 'wilayah' => $data['wilayah']]);

        return response()->json([
            'pesan' => "{$nama} ditambahkan ke daftar provinsi.",
            'data' => ProvinsiTambahan::kustom(),
        ], 201);
    }

    /* ------------------------------- DAERAH ------------------------------ */

    public function simpanDaerah(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            // Provinsi wajib: daerah dipilih SESUDAH provinsi, dan daftar yang
            // tidak tahu provinsinya tidak bisa disaring.
            'provinsi' => ['required', 'string', 'max:100'],
        ], [], ['nama' => 'nama daerah']);

        $nama = trim(preg_replace('/\s+/', ' ', $data['nama']));
        $provinsi = trim($data['provinsi']);

        $sudahAda = collect(DaerahTambahan::gabungan())
            ->filter(fn ($prov, $daerah) => $daerah === $nama && $prov === $provinsi)
            ->isNotEmpty();

        if ($sudahAda) {
            return response()->json([
                'pesan' => "{$nama} sudah ada di daftar daerah.",
                'data' => DaerahTambahan::kustom(),
            ]);
        }

        DaerahTambahan::create(['nama' => $nama, 'provinsi' => $provinsi]);

        return response()->json([
            'pesan' => "{$nama} ditambahkan ke daftar daerah.",
            'data' => DaerahTambahan::kustom(),
        ], 201);
    }

    /**
     * Menghapus satu daerah tambahan.
     *
     * TIDAK menyentuh destinasi mana pun: daerah pada destinasi tersimpan
     * sebagai tulisan, dan yang hilang hanya pilihannya di formulir.
     */
    public function hapusDaerah(DaerahTambahan $daerah): JsonResponse
    {
        $nama = $daerah->nama;
        $daerah->delete();

        return response()->json([
            'pesan' => "{$nama} dihapus dari daftar daerah.",
            'data' => DaerahTambahan::kustom(),
        ]);
    }

    /* -------------------------- KATALOG DESTINASI ------------------------- */

    /**
     * Menambahkan nama destinasi ke katalog pilihan.
     *
     * Provinsinya dicari sendiri bila tidak disebut: gunanya katalog ini bukan
     * sekadar melengkapi nama, melainkan mengisi provinsi dan wilayah sekaligus.
     * Nama tanpa provinsi tetap disimpan — separuh bantuan lebih baik daripada
     * menolak menyimpan.
     */
    public function simpanKatalogDestinasi(Request $request, CariLokasi $peta): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:191'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'daerah' => ['nullable', 'string', 'max:100'],
        ], [], ['nama' => 'nama destinasi']);

        $nama = trim(preg_replace('/\s+/', ' ', $data['nama']));

        // Peta ditanya sekali saja untuk keduanya: dua panggilan untuk satu
        // pertanyaan hanya memperlambat admin dan membebani layanan gratisnya.
        $usulan = (blank($data['provinsi'] ?? null) || blank($data['daerah'] ?? null))
            ? $peta->cari($nama)
            : null;

        $provinsi = trim((string) ($data['provinsi'] ?? '')) ?: ($usulan['provinsi'] ?? null);
        $daerah = trim((string) ($data['daerah'] ?? '')) ?: ($usulan['daerah'] ?? null);

        /*
         | Nama yang PERNAH dihapus dipulihkan, bukan dibuat ulang.
         |
         | Barisnya masih ada — cuma tidak ikut terbaca — berikut provinsi &
         | daerah yang dulu diisikan pencari lokasi. Membuat baris baru
         | berarti menebak ulang keduanya dari nol dan meninggalkan baris
         | lama menggantung sebagai kembaran yang tak terlihat.
         |
         | Diperiksa SEBELUM gabungan(): yang terhapus memang tidak ada di
         | sana, jadi pemeriksa "sudah ada" tidak akan pernah melihatnya.
         */
        $terhapus = KatalogDestinasi::onlyTrashed()
            ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])
            ->first();

        if ($terhapus) {
            $terhapus->restore();

            return response()->json([
                'pesan' => "{$terhapus->nama} dikembalikan ke daftar destinasi.",
                'data' => KatalogDestinasi::kustom(),
            ]);
        }

        if (array_key_exists($nama, KatalogDestinasi::gabungan())) {
            return response()->json([
                'pesan' => "{$nama} sudah ada di daftar.",
                'data' => KatalogDestinasi::kustom(),
            ]);
        }

        KatalogDestinasi::create(['nama' => $nama, 'provinsi' => $provinsi, 'daerah' => $daerah]);

        return response()->json([
            'pesan' => "{$nama} ditambahkan ke daftar destinasi.",
            'data' => KatalogDestinasi::kustom(),
        ], 201);
    }

    /**
     * Mengganti NAMA satu entri katalog, berikut seluruh pemakainya.
     *
     * Baris harga, RAB, dan itinerary menyimpan NAMA destinasinya, bukan id
     * katalog ini (lihat catatan panjang di hapusKatalogDestinasi). Jadi
     * mengganti nama di sini saja akan memutus jodoh mereka diam-diam:
     * tiketnya terus hidup dengan nama lama yang sudah tidak ada di kotak
     * pilih, dan yang menyuntingnya berikutnya melihat "tidak ada di
     * katalog" tanpa tahu sebabnya.
     *
     * Karena itu namanya dirambatkan — harga utama, destinasi tambahan di
     * dalam JSON, dan itinerary RAB — dalam SATU transaksi. Setengah
     * berganti lebih buruk daripada tidak berganti sama sekali.
     */
    public function ubahKatalogDestinasi(KatalogDestinasi $katalog, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:191'],
        ], [], ['nama' => 'nama destinasi']);

        $lama = $katalog->nama;
        $baru = trim(preg_replace('/\s+/', ' ', $data['nama']));
        $rapiLama = mb_strtolower(trim($lama));
        $rapiBaru = mb_strtolower($baru);

        if ($rapiBaru === $rapiLama && $baru === $lama) {
            return response()->json([
                'pesan' => 'Namanya tidak berubah.',
                'data' => KatalogDestinasi::kustom(),
            ]);
        }

        // Bentrok dengan nama LAIN yang sudah ada — termasuk destinasi bawaan,
        // karena keduanya duduk di kotak pilih yang sama. Dua nama kembar di
        // sana membuat admin memilih dengan menebak.
        $bentrok = collect(KatalogDestinasi::gabungan())->keys()
            ->contains(fn ($n) => mb_strtolower(trim((string) $n)) === $rapiBaru
                && mb_strtolower(trim((string) $n)) !== $rapiLama);

        if ($bentrok) {
            return response()->json([
                'pesan' => "Sudah ada destinasi bernama \"{$baru}\" di daftar.",
            ], 422);
        }

        $diHarga = 0;
        $diItinerary = 0;

        DB::transaction(function () use ($katalog, $lama, $baru, $rapiLama, &$diHarga, &$diItinerary) {
            $katalog->update(['nama' => $baru]);

            // Destinasi utama.
            $diHarga += MasterHarga::query()
                ->whereRaw('LOWER(TRIM(destinasi)) = ?', [$rapiLama])
                ->update(['destinasi' => $baru]);

            /*
             | Destinasi tambahan hidup di kolom JSON, jadi tidak bisa disapu
             | satu UPDATE. Barisnya dibaca lalu ditulis ulang satu per satu —
             | jumlahnya puluhan, bukan ribuan.
             */
            MasterHarga::query()->whereNotNull('destinasi_lain')->get()
                ->each(function (MasterHarga $m) use ($lama, $rapiLama, $baru, &$diHarga) {
                    $daftar = (array) ($m->destinasi_lain ?? []);
                    $ubah = false;

                    foreach ($daftar as $i => $d) {
                        if (mb_strtolower(trim((string) $d)) === $rapiLama) {
                            $daftar[$i] = $baru;
                            $ubah = true;
                        }
                    }

                    if ($ubah) {
                        $m->update(['destinasi_lain' => array_values($daftar)]);
                        $diHarga++;
                    }
                });

            $diItinerary += RabItinerary::query()
                ->whereRaw('LOWER(TRIM(destinasi)) = ?', [$rapiLama])
                ->update(['destinasi' => $baru]);
        });

        $ikut = collect([
            $diHarga ? "{$diHarga} baris harga" : null,
            $diItinerary ? "{$diItinerary} baris itinerary" : null,
        ])->filter()->join(' dan ');

        return response()->json([
            'pesan' => "{$lama} diganti jadi {$baru}.".($ikut ? " {$ikut} ikut diperbarui." : ''),
            'dari' => $lama,
            'ke' => $baru,
            'ikut' => ['harga' => $diHarga, 'itinerary' => $diItinerary],
            'data' => KatalogDestinasi::kustom(),
        ]);
    }

    /**
     * Menghapus satu entri katalog — DITOLAK bila namanya masih dipakai.
     *
     * TIDAK menghapus destinasi apa pun. Baris harga, RAB, dan itinerary
     * menyimpan NAMA destinasinya, bukan id katalog ini, jadi menghapus
     * entri katalog tidak pernah menyentuh satu pun dari mereka.
     *
     * Justru itulah bahayanya, dan sebab penjaga di bawah ada: tanpa
     * penjaga, menghapus "Candi Arjuna" dari katalog membuat baris harga
     * tiketnya terus hidup sambil namanya hilang dari kotak pilih. Yang
     * menyunting baris itu berikutnya melihat "— tidak ada di katalog", dan
     * sekali ia menyimpan dengan destinasi lain, jodoh harga ke itinerary
     * putus tanpa satu pun galat.
     *
     * Hapusnya sendiri LEMBUT: barisnya tetap ada dan kembali utuh begitu
     * namanya ditambahkan lagi lewat jalan biasa.
     */
    public function hapusKatalogDestinasi(KatalogDestinasi $katalog): JsonResponse
    {
        $nama = $katalog->nama;
        $rapi = mb_strtolower(trim($nama));

        $diHarga = MasterHarga::query()->get(['destinasi', 'destinasi_lain'])
            ->filter(fn (MasterHarga $m) => collect($m->semuaDestinasi())
                ->contains(fn ($d) => mb_strtolower(trim((string) $d)) === $rapi))
            ->count();

        $diItinerary = RabItinerary::query()
            ->whereRaw('LOWER(TRIM(destinasi)) = ?', [$rapi])
            ->count();

        if ($diHarga || $diItinerary) {
            $sebab = collect([
                $diHarga ? "{$diHarga} baris harga" : null,
                $diItinerary ? "{$diItinerary} baris itinerary RAB" : null,
            ])->filter()->join(' dan ');

            return response()->json([
                'pesan' => "{$nama} masih dipakai {$sebab}, jadi tidak dihapus. "
                    .'Lepaskan dulu dari sana, atau biarkan — menghapusnya di sini '
                    .'tidak akan menghapusnya dari RAB & itinerary, hanya membuat '
                    .'namanya hilang dari kotak pilih.',
                'dipakai' => ['harga' => $diHarga, 'itinerary' => $diItinerary],
            ], 422);
        }

        $katalog->delete();

        return response()->json([
            'pesan' => "{$nama} dihapus dari daftar. Tambahkan lagi dengan nama yang sama untuk mengembalikannya.",
            'data' => KatalogDestinasi::kustom(),
        ]);
    }

    /* ------------------------------ WILAYAH ------------------------------ */

    public function simpanWilayah(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ], [], ['label' => 'nama wilayah']);

        $label = trim(preg_replace('/\s+/', ' ', $data['label']));
        $kunci = WilayahTambahan::kunciDari($label);

        if ($kunci === '') {
            return response()->json(['pesan' => 'Nama wilayah tidak bisa dipakai.'], 422);
        }

        // Yang sudah ada tidak digandakan, dan itu bukan kegagalan.
        if (array_key_exists($kunci, WilayahTambahan::gabungan())) {
            return response()->json([
                'pesan' => "{$label} sudah ada di daftar wilayah.",
                'data' => WilayahTambahan::kustom(),
            ]);
        }

        WilayahTambahan::create(['kunci' => $kunci, 'label' => $label]);

        return response()->json([
            'pesan' => "{$label} ditambahkan ke daftar wilayah.",
            'data' => WilayahTambahan::kustom(),
        ], 201);
    }

    /**
     * Menghapus satu wilayah tambahan.
     *
     * DITOLAK bila masih dipakai destinasi. Berbeda dari provinsi yang sekadar
     * tulisan di kartu, wilayah adalah pengelompokan: destinasi yang wilayahnya
     * dihapus kehilangan tabnya di halaman publik dan tidak ketemu oleh
     * penyaring mana pun — hilang tanpa ada yang memberitahu.
     */
    public function hapusWilayah(WilayahTambahan $wilayah): JsonResponse
    {
        $terpakai = DestinationPopuler::where('wilayah', $wilayah->kunci)->count();

        if ($terpakai > 0) {
            return response()->json([
                'pesan' => "{$wilayah->label} masih dipakai {$terpakai} destinasi. "
                    .'Pindahkan destinasinya dulu sebelum menghapus wilayah ini.',
            ], 422);
        }

        $label = $wilayah->label;
        $wilayah->delete();

        return response()->json([
            'pesan' => "{$label} dihapus dari daftar wilayah.",
            'data' => WilayahTambahan::kustom(),
        ]);
    }

    /**
     * Menghapus satu provinsi tambahan.
     *
     * TIDAK menyentuh destinasi mana pun. Destinasi yang sudah tercatat di
     * provinsi itu tetap utuh beserta nama provinsinya — yang hilang hanya
     * pilihannya di formulir.
     */
    public function destroy(ProvinsiTambahan $provinsi): JsonResponse
    {
        $nama = $provinsi->nama;
        $provinsi->delete();

        return response()->json([
            'pesan' => "{$nama} dihapus dari daftar provinsi.",
            'data' => ProvinsiTambahan::kustom(),
        ]);
    }
}
