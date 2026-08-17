<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Models\SewaKendaraan\KatalogTambahan;
use App\Support\SewaKendaraan\KatalogKendaraan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merek dan model kendaraan yang ditambahkan admin sendiri.
 *
 * Sebelum ini, merek yang ditulis manual di formulir armada hanya bertahan bila
 * unitnya benar-benar tersimpan, dan tidak ada cara memperbaiki entri yang salah
 * tulis. Kedua jalur di sini menutup kekurangan itu: sekali ditulis langsung
 * terdaftar, dan yang salah bisa dibuang.
 */
class KatalogKendaraanController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merek' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'varian' => ['nullable', 'string', 'max:60'],
        ], [], ['merek' => 'merek', 'model' => 'nama unit', 'varian' => 'tipe']);

        $merek = KatalogTambahan::rapikanMerek($data['merek']);
        $model = trim((string) ($data['model'] ?? '')) ?: null;
        $varian = trim((string) ($data['varian'] ?? '')) ?: null;

        // Tipe tanpa model tidak berarti apa-apa: ia harus menempel pada modelnya.
        if ($varian !== null && $model === null) {
            return response()->json([
                'pesan' => 'Tipe harus disertai nama unitnya.',
            ], 422);
        }

        // Yang sudah ada — baik di katalog bawaan, di tambahan sebelumnya,
        // maupun terbaca dari armada — tidak ditambahkan lagi. Dianggap berhasil
        // supaya admin tidak dihadapkan pada galat untuk sesuatu yang justru
        // sudah sesuai keinginannya.
        $sebutan = trim($merek.' '.$model.' '.$varian);

        if ($this->sudahAda($merek, $model, $varian)) {
            return response()->json([
                'pesan' => ($model === null ? "Merek {$merek}" : $sebutan).' sudah ada di daftar.',
                'data' => KatalogKendaraan::kustom(),
            ]);
        }

        KatalogTambahan::create(['merek' => $merek, 'model' => $model, 'varian' => $varian]);

        return response()->json([
            'pesan' => ($model === null ? "Merek {$merek}" : $sebutan).' ditambahkan ke daftar.',
            'data' => KatalogKendaraan::kustom(),
        ], 201);
    }

    /**
     * Menghapus satu entri tambahan.
     *
     * TIDAK menghapus kendaraan apa pun. Unit yang memakai merek atau model itu
     * tetap utuh, dan namanya tetap muncul di daftar pilihan karena ikut dibaca
     * dari armada — menghapus entri katalog tidak pernah membuat unit yang sudah
     * ada kehilangan mereknya.
     */
    public function destroy(KatalogTambahan $katalog): JsonResponse
    {
        $sebutan = $katalog->model === null
            ? "Merek {$katalog->merek}"
            : trim("{$katalog->merek} {$katalog->model} {$katalog->varian}");

        $katalog->delete();

        return response()->json([
            'pesan' => "{$sebutan} dihapus dari daftar.",
            'data' => KatalogKendaraan::kustom(),
        ]);
    }

    private function sudahAda(string $merek, ?string $model, ?string $varian = null): bool
    {
        if ($varian !== null) {
            return in_array($varian, KatalogKendaraan::varian()[$merek][$model] ?? [], true);
        }

        $katalog = KatalogKendaraan::pilihan();

        if ($model === null) {
            return array_key_exists($merek, $katalog);
        }

        return in_array($model, $katalog[$merek] ?? [], true);
    }
}
