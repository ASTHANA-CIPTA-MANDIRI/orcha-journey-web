<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Models\Blog\Artikel;
use App\Models\Blog\KategoriArtikel;
use App\Support\Blog\GambarIsiArtikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Blog Orcha untuk dashboard lemon.
 *
 * Dipakai layar "Blog" di mode Orcha pada lemon. Bentuk balasannya sengaja
 * memakai nama kolom Orcha apa adanya (judul, isi, terbit_pada) — bukan
 * diterjemahkan ke nama kolom Phoenix. Penerjemahan dilakukan sekali di sisi
 * lemon; menerjemahkannya di dua tempat berarti suatu saat keduanya berbeda dan
 * tidak ada yang tahu mana yang benar.
 */
class ArtikelController extends ApiController
{
    use MenyimpanGambar;

    /**
     * Daftar artikel untuk layar admin — termasuk draf.
     *
     * TIDAK memakai scopeTayang(): admin justru perlu melihat draf dan artikel
     * terjadwal, karena itulah yang sedang ia kerjakan. Penyaring "tayang" ada
     * sebagai pilihan, bukan sebagai penyaring bawaan yang tersembunyi.
     */
    public function index(Request $request): JsonResponse
    {
        $daftar = Artikel::query()
            ->cari($request->string('cari')->toString() ?: null)
            ->kategori($request->string('kategori')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            // Urutan admin berbeda dari urutan pembaca: yang baru DISENTUH di
            // atas, bukan yang terbit paling akhir. Draf yang belum punya
            // tanggal terbit pun tetap muncul di tempat yang masuk akal.
            ->latest('updated_at')
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta($daftar, fn () => $daftar->getCollection()
            ->map(fn (Artikel $a) => $this->ringkas($a))->all());
    }

    public function show(Artikel $artikel): JsonResponse
    {
        return response()->json([
            'data' => $this->ringkas($artikel) + [
                'isi' => $artikel->isi,
                'ringkasan' => $artikel->ringkasan,
                // Nilai MENTAH, bukan yang sudah dicadangkan: formulir harus
                // membedakan "admin mengosongkannya" dari "terisi otomatis",
                // supaya kotaknya tidak tampak sudah diisi padahal belum.
                'meta_title' => $artikel->meta_title,
                'meta_description' => $artikel->meta_description,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $artikel = Artikel::create($this->siapkan($data, $request));

        $this->catat($request, 'tambah artikel blog', ['id' => $artikel->id, 'judul' => $artikel->judul]);

        return response()->json([
            'pesan' => 'Artikel disimpan.',
            'data' => $this->ringkas($artikel),
        ], 201);
    }

    public function update(Artikel $artikel, Request $request): JsonResponse
    {
        $data = $this->validasi($request, $artikel);

        $artikel->update($this->siapkan($data, $request, $artikel));

        $this->catat($request, 'ubah artikel blog', ['id' => $artikel->id, 'judul' => $artikel->judul]);

        return response()->json([
            'pesan' => 'Artikel diperbarui.',
            'data' => $this->ringkas($artikel->fresh()),
        ]);
    }

    public function destroy(Artikel $artikel, Request $request): JsonResponse
    {
        $this->hapusGambar($artikel->sampul);
        $artikel->delete();

        $this->catat($request, 'hapus artikel blog', ['id' => $artikel->id, 'judul' => $artikel->judul]);

        return response()->json(['pesan' => 'Artikel dihapus.']);
    }

    /**
     * Usulan slug untuk judul yang sedang diketik admin.
     *
     * Dipakai pratinjau alamat di formulir lemon. Dihitung DI SINI, bukan di
     * lemon, karena yang menentukan bentrok atau tidak adalah isi tabel artikel
     * Orcha — lemon tidak memilikinya, dan menebak dari sana berarti admin baru
     * tahu slugnya berubah setelah menekan simpan.
     */
    public function slug(Request $request): JsonResponse
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'kecuali' => 'nullable|integer',
        ]);

        return response()->json([
            'slug' => Artikel::slugUnik($request->string('judul'), $request->integer('kecuali') ?: null),
        ]);
    }

    /* ------------------------------------------------------------- Bantuan */

    private function validasi(Request $request, ?Artikel $artikel = null): array
    {
        return $request->validate([
            'judul' => 'required|string|max:255',
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('tbl_artikel', 'slug')->ignore($artikel?->id),
            ],
            'ringkasan' => 'nullable|string|max:500',
            'isi' => 'required|string',
            'kategori' => ['nullable', Rule::in(array_keys(KategoriArtikel::daftar()))],
            'penulis' => 'nullable|string|max:100',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:300',
            'status' => ['required', Rule::in(['draf', 'tayang'])],
            'terbit_pada' => 'nullable|date',
            'gambar' => 'nullable|image|max:4096',
            // Dikirim lemon saat admin menekan tombol hapus sampul; dibedakan
            // dari "tidak mengunggah apa-apa", yang artinya sampul lama tetap.
            'hapus_sampul' => 'nullable|boolean',
        ]);
    }

    /**
     * Rakit nilai yang benar-benar disimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function siapkan(array $data, Request $request, ?Artikel $artikel = null): array
    {
        $sampulLama = $artikel?->sampul;

        if ($request->boolean('hapus_sampul')) {
            $this->hapusGambar($sampulLama);
            $sampulLama = null;
        }

        return [
            'judul' => $data['judul'],
            // Slug kosong dibiarkan kosong: model yang mengisinya dari judul
            // sekaligus menjaga keunikannya. Menghitungnya di sini juga berarti
            // dua tempat memutuskan hal yang sama.
            'slug' => $data['slug'] ?? null,
            'ringkasan' => $data['ringkasan'] ?? null,
            // Gambar yang ditempel admin masuk sebagai base64 di dalam HTML.
            // Diselamatkan jadi berkas WebP di sini — lihat GambarIsiArtikel
            // untuk kenapa membiarkannya berbahaya.
            'isi' => GambarIsiArtikel::proses($data['isi']),
            'kategori' => $data['kategori'] ?? null,
            'penulis' => $data['penulis'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status' => $data['status'],
            /*
             | Artikel yang ditayangkan tanpa tanggal diberi tanggal sekarang.
             |
             | Tanpa ini admin yang memilih "tayang" lalu menyimpan akan
             | mendapati artikelnya tetap tidak muncul di mana pun — sebab
             | scopeTayang() menuntut terbit_pada terisi — dan tidak ada satu
             | pun pesan yang menjelaskan kenapa.
             */
            'terbit_pada' => $data['terbit_pada']
                ?? ($data['status'] === 'tayang' ? ($artikel?->terbit_pada ?? now()) : $artikel?->terbit_pada),
            'sampul' => $this->simpanGambar($request, 'artikel', $sampulLama),
        ];
    }

    /** @return array<string, mixed> */
    private function ringkas(Artikel $artikel): array
    {
        return [
            'id' => $artikel->id,
            'judul' => $artikel->judul,
            'slug' => $artikel->slug,
            'kategori' => $artikel->kategori,
            'kategori_label' => $artikel->kategori_label,
            'penulis' => $artikel->penulis,
            'status' => $artikel->status,
            'sedang_tayang' => $artikel->sedang_tayang,
            'terbit_pada' => $artikel->terbit_pada?->toIso8601String(),
            'tanggal_terbit' => $artikel->tanggal_terbit,
            'sampul' => $artikel->sampul,
            'sampul_tampil' => $artikel->sampul_tampil,
            'ringkasan_tampil' => $artikel->ringkasan_tampil,
            'meta_title_tampil' => $artikel->meta_title_tampil,
            'meta_description_tampil' => $artikel->meta_description_tampil,
            'lama_baca' => $artikel->lama_baca,
            'dilihat' => $artikel->dilihat,
            // Dipakai tombol "Lihat di situs" pada layar admin.
            'tautan' => route('blog.detail', $artikel),
            'diperbarui' => $artikel->updated_at?->toIso8601String(),
        ];
    }
}
