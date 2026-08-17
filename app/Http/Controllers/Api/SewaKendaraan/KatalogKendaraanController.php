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
        ], [], ['merek' => 'merek', 'model' => 'nama unit']);

        $merek = KatalogTambahan::rapikanMerek($data['merek']);
        $model = trim((string) ($data['model'] ?? '')) ?: null;

        // Yang sudah ada — baik di katalog bawaan, di tambahan sebelumnya,
        // maupun terbaca dari armada — tidak ditambahkan lagi. Dianggap berhasil
        // supaya admin tidak dihadapkan pada galat untuk sesuatu yang justru
        // sudah sesuai keinginannya.
        if ($this->sudahAda($merek, $model)) {
            return response()->json([
                'pesan' => $model === null
                    ? "Merek {$merek} sudah ada di daftar."
                    : "{$merek} {$model} sudah ada di daftar.",
                'data' => KatalogKendaraan::kustom(),
            ]);
        }

        KatalogTambahan::create(['merek' => $merek, 'model' => $model]);

        return response()->json([
            'pesan' => $model === null
                ? "Merek {$merek} ditambahkan ke daftar."
                : "{$merek} {$model} ditambahkan ke daftar.",
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
            : "{$katalog->merek} {$katalog->model}";

        $katalog->delete();

        return response()->json([
            'pesan' => "{$sebutan} dihapus dari daftar.",
            'data' => KatalogKendaraan::kustom(),
        ]);
    }

    private function sudahAda(string $merek, ?string $model): bool
    {
        $katalog = KatalogKendaraan::pilihan();

        if ($model === null) {
            return array_key_exists($merek, $katalog);
        }

        return in_array($model, $katalog[$merek] ?? [], true);
    }
}
