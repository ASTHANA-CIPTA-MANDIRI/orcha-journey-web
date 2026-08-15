<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PembayaranResource;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = KonfirmasiPembayaran::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('kode', 'like', "%{$cari}%")
                    ->orWhere('atas_nama_pengirim', 'like', "%{$cari}%")
                    ->orWhere('bank_pengirim', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PembayaranResource::class);
    }

    public function show(KonfirmasiPembayaran $pembayaran): JsonResponse
    {
        return response()->json([
            'data' => (new PembayaranResource($pembayaran))->resolve(),
        ]);
    }

    public function ubahStatus(KonfirmasiPembayaran $pembayaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_pembayaran'))),
            'catatan_admin' => 'nullable|string|max:1000',
        ]);

        $sebelum = $pembayaran->status;
        $pembayaran->update($data);

        $this->catat($request, 'ubah status pembayaran', [
            'kode' => $pembayaran->kode,
            'nominal' => $pembayaran->nominal,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PembayaranResource($pembayaran->fresh()))->resolve(),
            'pesan' => 'Status pembayaran diperbarui.',
        ]);
    }
}
