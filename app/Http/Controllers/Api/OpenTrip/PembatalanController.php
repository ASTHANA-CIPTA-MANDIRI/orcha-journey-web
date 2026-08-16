<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PembatalanResource;
use App\Models\OpenTrip\Pembatalan;
use App\Support\SelaraskanPembatalan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembatalanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        // Kedua jenis pesanan dimuat di muka. Perkiraan potongan pada tiap
        // baris butuh pesanannya, dan tanpa ini satu halaman daftar akan
        // menembak dua query per baris.
        $daftar = Pembatalan::query()
            ->with(['pendaftaran.paket', 'penyewaan'])
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama_pemohon', 'like', "%{$cari}%")
                    ->orWhere('kode_pendaftaran', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PembatalanResource::class);
    }

    public function show(Pembatalan $pembatalan): JsonResponse
    {
        return response()->json([
            'data' => (new PembatalanResource($pembatalan->load(['pendaftaran.paket', 'penyewaan'])))->resolve(),
        ]);
    }

    public function ubahStatus(Pembatalan $pembatalan, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_pembatalan'))),
            'catatan_admin' => 'nullable|string|max:1000',
        ]);

        $sebelum = $pembatalan->status;
        $pembatalan->update($data);

        // Keputusannya dijalarkan ke pesanan dan bukti bayarnya. Tanpa ini
        // admin mengubah tiga tempat sendiri, dan yang paling sering
        // tertinggal adalah status pesanannya.
        SelaraskanPembatalan::jalankan($pembatalan->fresh());

        $this->catat($request, 'ubah status pembatalan', [
            'kode_pendaftaran' => $pembatalan->kode_pendaftaran,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PembatalanResource($pembatalan->fresh()->load('pendaftaran')))->resolve(),
            'pesan' => 'Status pembatalan diperbarui.',
        ]);
    }
}
