<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\Vendor;
use App\Support\SewaKendaraan\KeuntunganSewa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Laporan keuntungan sewa kendaraan untuk lemon.
 *
 * Bentuknya sengaja dibuat sama dengan laporan keuntungan paket wisata: dua
 * jalur, ringkasan dan rincian. Ringkasan menjawab "bulan ini untung berapa
 * dan dari unit/vendor mana", rincian menjawab "pesanan mana saja yang
 * membentuk angka itu". Yang kedua berhalaman, yang pertama tidak — rekap
 * yang terpotong halaman bukan rekap.
 */
class KeuntunganSewaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => KeuntunganSewa::laporan($this->saringan($request)) + [
                // Daftar vendor untuk pemilih di lemon.
                'vendor' => Vendor::orderBy('nama')->get(['id', 'nama', 'jenis'])
                    ->map(fn (Vendor $v) => [
                        'id' => $v->id,
                        'nama' => $v->nama,
                        'jenis' => $v->jenis,
                    ])->all(),
            ],
        ]);
    }

    public function rincian(Request $request): JsonResponse
    {
        $halaman = KeuntunganSewa::rincian($this->saringan($request), $this->perHalaman($request));

        return $this->halamanDipeta(
            $halaman,
            fn () => $halaman->getCollection()->map(fn ($sewa) => KeuntunganSewa::satuBaris($sewa))->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function saringan(Request $request): array
    {
        $data = $request->validate([
            'dari' => 'nullable|date',
            'sampai' => 'nullable|date|after_or_equal:dari',
            'vendor_id' => 'nullable|integer',
            'dasar' => 'nullable|in:'.implode(',', array_keys(KeuntunganSewa::DASAR)),
        ]);

        return array_filter($data, fn ($nilai) => $nilai !== null && $nilai !== '');
    }
}
