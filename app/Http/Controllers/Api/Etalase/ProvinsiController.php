<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\Etalase\ProvinsiTambahan;
use App\Models\Etalase\WilayahTambahan;
use App\Support\Etalase\CariLokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ], [], ['nama' => 'nama destinasi']);

        $nama = trim(preg_replace('/\s+/', ' ', $data['nama']));
        $provinsi = trim((string) ($data['provinsi'] ?? '')) ?: ($peta->cari($nama)['provinsi'] ?? null);

        if (array_key_exists($nama, KatalogDestinasi::gabungan())) {
            return response()->json([
                'pesan' => "{$nama} sudah ada di daftar.",
                'data' => KatalogDestinasi::kustom(),
            ]);
        }

        KatalogDestinasi::create(['nama' => $nama, 'provinsi' => $provinsi]);

        return response()->json([
            'pesan' => "{$nama} ditambahkan ke daftar destinasi.",
            'data' => KatalogDestinasi::kustom(),
        ], 201);
    }

    /**
     * Menghapus satu entri katalog.
     *
     * TIDAK menghapus destinasi apa pun. Destinasi yang sudah tercatat tetap
     * utuh, dan namanya tetap muncul di daftar pilihan karena ikut dibaca dari
     * destinasi yang ada.
     */
    public function hapusKatalogDestinasi(KatalogDestinasi $katalog): JsonResponse
    {
        $nama = $katalog->nama;
        $katalog->delete();

        return response()->json([
            'pesan' => "{$nama} dihapus dari daftar.",
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
