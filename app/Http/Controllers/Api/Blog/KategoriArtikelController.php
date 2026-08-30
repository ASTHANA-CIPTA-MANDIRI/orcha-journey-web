<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Api\ApiController;
use App\Models\Blog\KategoriArtikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rubrik blog yang dikelola admin dari lemon.
 *
 * Bentuknya sengaja sama dengan pengelola kategori di blog Phoenix: satu daftar
 * pendek, tambah dan hapus saja, dan yang masih dipakai artikel tidak bisa
 * dihapus.
 */
class KategoriArtikelController extends ApiController
{
    /**
     * Daftar rubrik beserta jumlah artikel yang memakainya.
     *
     * Jumlahnya ikut dikirim karena itulah yang menentukan boleh-tidaknya
     * dihapus — kalau lemon harus menanyakannya terpisah, daftar sepuluh rubrik
     * jadi sebelas permintaan.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => KategoriArtikel::query()->orderBy('nama')->get()
                ->map(fn (KategoriArtikel $k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'slug' => $k->slug,
                    'jumlah' => $k->jumlahArtikel(),
                    'dipakai' => $k->jumlahArtikel() > 0,
                ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|min:2|max:60|unique:tbl_kategori_artikel,nama',
        ], [], ['nama' => 'nama kategori']);

        $kategori = KategoriArtikel::create([
            'nama' => $data['nama'],
            'slug' => KategoriArtikel::slugUnik($data['nama']),
        ]);

        $this->catat($request, 'tambah kategori artikel', ['nama' => $kategori->nama]);

        return response()->json([
            'pesan' => 'Kategori ditambahkan.',
            'data' => ['id' => $kategori->id, 'nama' => $kategori->nama, 'slug' => $kategori->slug],
        ], 201);
    }

    public function destroy(KategoriArtikel $kategori, Request $request): JsonResponse
    {
        /*
         | Rubrik yang masih dipakai TIDAK bisa dihapus.
         |
         | Artikel menyimpan slug rubriknya sebagai teks, bukan relasi — jadi
         | menghapus rubriknya tidak menimbulkan galat apa pun. Yang terjadi
         | lebih buruk: artikelnya tetap ada, tetapi rubriknya berubah jadi
         | tanda hubung dan tautan /blog?kategori=... miliknya menjawab daftar
         | kosong. Tidak ada satu pun pesan yang muncul.
         */
        if ($kategori->jumlahArtikel() > 0) {
            return response()->json([
                'pesan' => 'Kategori masih dipakai artikel, tidak bisa dihapus.',
            ], 422);
        }

        $nama = $kategori->nama;
        $kategori->delete();

        $this->catat($request, 'hapus kategori artikel', ['nama' => $nama]);

        return response()->json(['pesan' => 'Kategori dihapus.']);
    }
}
