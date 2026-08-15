<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SewaKendaraan\PenyewaanResource;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenyewaanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PenyewaanKendaraan::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_kendaraan', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PenyewaanResource::class);
    }

    public function show(PenyewaanKendaraan $penyewaan): JsonResponse
    {
        return response()->json([
            'data' => (new PenyewaanResource($penyewaan))->resolve(),
        ]);
    }

    public function ubahStatus(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_penyewaan'))),
        ]);

        $sebelum = $penyewaan->status;
        $penyewaan->update($data);

        $this->catat($request, 'ubah status penyewaan', [
            'kode' => $penyewaan->kode,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Status pemesanan sewa diperbarui.',
        ]);
    }
}
