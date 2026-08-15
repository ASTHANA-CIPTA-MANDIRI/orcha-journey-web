<?php

namespace App\Http\Controllers\Api\Kontak;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Kontak\PesanResource;
use App\Models\Kontak\PesanKontak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PesanKontak::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('pesan', 'like', "%{$cari}%")
            ))
            ->when($request->string('keperluan')->toString(), fn ($q, $keperluan) => $q->where('keperluan', $keperluan))
            ->when($request->boolean('belum_dibaca'), fn ($q) => $q->belumDibaca())
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PesanResource::class);
    }

    public function show(PesanKontak $pesan): JsonResponse
    {
        return response()->json([
            'data' => (new PesanResource($pesan))->resolve(),
        ]);
    }

    public function tandaiDibaca(PesanKontak $pesan, Request $request): JsonResponse
    {
        $pesan->update(['dibaca_pada' => $pesan->dibaca_pada ?? now()]);

        $this->catat($request, 'tandai pesan dibaca', ['id' => $pesan->id]);

        return response()->json([
            'data' => (new PesanResource($pesan->fresh()))->resolve(),
            'pesan' => 'Pesan ditandai sudah dibaca.',
        ]);
    }
}
