<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bagian yang dipakai bersama semua controller API dashboard.
 */
abstract class ApiController extends Controller
{
    /**
     * Jumlah baris per halaman, dibatasi supaya satu permintaan tidak menarik
     * seluruh tabel.
     */
    protected function perHalaman(Request $request): int
    {
        $diminta = (int) $request->integer('per_halaman', config('orcha.api.per_halaman'));

        return max(1, min($diminta, (int) config('orcha.api.per_halaman_maks')));
    }

    /**
     * Bungkus hasil paginasi jadi bentuk yang seragam: data + meta.
     */
    protected function halaman(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'halaman' => $paginator->currentPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'halaman_terakhir' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Catat perubahan data supaya jelas admin Phoenix mana yang melakukannya —
     * Orcha tidak punya sesi login untuk mereka.
     */
    protected function catat(Request $request, string $peristiwa, array $rincian = []): void
    {
        Log::info('API Orcha: '.$peristiwa, $rincian + [
            'admin' => $request->attributes->get('admin_pemanggil'),
            'ip' => $request->ip(),
        ]);
    }
}
