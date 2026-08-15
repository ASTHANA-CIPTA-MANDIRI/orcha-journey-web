<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\KendaraanResource;
use App\Http\Resources\PaketWisataResource;
use App\Models\Car;
use App\Models\DestinationPopuler;
use App\Models\Partner;
use App\Models\Testimoni;
use App\Models\TravelPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data etalase: paket, armada, destinasi, testimoni, partner.
 *
 * Baca saja. Pengubahannya untuk sekarang tetap lewat admin Orcha karena
 * melibatkan unggah gambar; kalau nanti mau dipindah ke Phoenix, tambahkan
 * store/update di sini.
 */
class KatalogController extends ApiController
{
    public function paket(Request $request): JsonResponse
    {
        $daftar = TravelPackage::query()
            ->withCount('pendaftaran')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where('name', 'like', "%{$cari}%"))
            ->ofCategory($request->string('kategori')->toString() ?: null)
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PaketWisataResource::class);
    }

    public function paketDetail(TravelPackage $paket): JsonResponse
    {
        return response()->json([
            'data' => (new PaketWisataResource($paket->loadCount('pendaftaran')))->resolve(),
        ]);
    }

    public function kendaraan(Request $request): JsonResponse
    {
        $daftar = Car::query()
            ->withCount('penyewaan')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$cari}%")->orWhere('brand', 'like', "%{$cari}%")
            ))
            ->ofType($request->string('jenis')->toString() ?: null)
            ->orderBy('price_per_day')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, KendaraanResource::class);
    }

    public function kendaraanDetail(Car $kendaraan): JsonResponse
    {
        return response()->json([
            'data' => (new KendaraanResource($kendaraan->loadCount('penyewaan')))->resolve(),
        ]);
    }

    public function destinasi(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DestinationPopuler::query()
                ->when($request->string('wilayah')->toString(), fn ($q, $wilayah) => $q->where('wilayah', $wilayah))
                ->orderBy('destination_name')
                ->get()
                ->map(fn ($destinasi) => [
                    'id' => $destinasi->id,
                    'nama' => $destinasi->destination_name,
                    'provinsi' => $destinasi->provinsi,
                    'wilayah' => $destinasi->wilayah,
                    'wilayah_label' => $destinasi->wilayah_label,
                    'deskripsi' => $destinasi->deskripsi,
                    'total_pengunjung' => $destinasi->total_visitor,
                    'foto' => $destinasi->main_photo,
                ])
                ->all(),
        ]);
    }

    public function testimoni(): JsonResponse
    {
        return response()->json([
            'data' => Testimoni::latest('id')->get()->map(fn ($testimoni) => [
                'id' => $testimoni->id,
                'nama' => $testimoni->customer_name,
                'isi' => $testimoni->testimonial,
                'rating' => $testimoni->rating,
                'foto' => $testimoni->avatar,
            ])->all(),
        ]);
    }

    public function partner(): JsonResponse
    {
        return response()->json([
            'data' => Partner::orderBy('partner_name')->get()->map(fn ($partner) => [
                'id' => $partner->id,
                'nama' => $partner->partner_name,
                'logo' => $partner->foto,
            ])->all(),
        ]);
    }
}
