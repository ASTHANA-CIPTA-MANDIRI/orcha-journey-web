<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Models\Etalase\ProvinsiTambahan;
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
            'wilayah' => ['required', 'in:'.implode(',', array_keys(config('orcha.wilayah')))],
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
