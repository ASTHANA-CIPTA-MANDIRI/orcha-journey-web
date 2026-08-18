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
        return $this->halamanDipeta(
            $paginator,
            fn () => $resource::collection($paginator->getCollection())->resolve(),
        );
    }

    /**
     * Sama, untuk daftar yang barisnya dirakit tanpa kelas Resource.
     *
     * Bentuk meta-nya ditulis SEKALI di sini. Menyalinnya ke controller yang
     * kebetulan memakai pemeta sendiri berarti dua bentuk sejajar untuk hal
     * yang sama — dan penomoran halaman di lemon membaca meta itu apa adanya,
     * jadi selisih sekecil apa pun langsung terasa di layar admin.
     *
     * @param  callable(): array<int, mixed>  $petakan
     */
    protected function halamanDipeta(LengthAwarePaginator $paginator, callable $petakan): JsonResponse
    {
        return response()->json([
            'data' => $petakan(),
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
