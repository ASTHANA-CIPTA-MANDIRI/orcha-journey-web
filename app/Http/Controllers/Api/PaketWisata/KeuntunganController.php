<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\Keuntungan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Laporan keuntungan paket wisata untuk dashboard lemon.
 *
 * Dua jalur karena dua pertanyaan yang berbeda: ringkasan menjawab "bulan ini
 * untung berapa dan dari paket mana", rincian menjawab "baris mana saja yang
 * membentuk angka itu". Yang kedua bisa panjang, jadi ia yang berhalaman —
 * ringkasannya tidak, karena rekap yang terpotong halaman bukan rekap.
 */
class KeuntunganController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $saring = $this->saringan($request);

        return response()->json([
            'data' => Keuntungan::laporan($saring) + [
                // Daftar paket untuk pemilih di lemon, beserta modalnya —
                // sekaligus penanda paket mana yang modalnya belum diisi.
                'paket' => TravelPackage::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'category', 'price', 'harga_modal'])
                    ->map(fn (TravelPackage $paket) => [
                        'id' => $paket->id,
                        'nama' => $paket->name,
                        'kategori' => $paket->category,
                        'harga_jual' => (int) $paket->price,
                        'harga_modal' => $paket->harga_modal,
                        'margin_per_orang' => $paket->margin_per_orang,
                        'margin_persen' => $paket->margin_persen,
                        'modal_terisi' => $paket->modal_terisi,
                    ])
                    ->all(),
                'dasar_tanggal' => Keuntungan::DASAR,
            ],
        ]);
    }

    public function rincian(Request $request): JsonResponse
    {
        $halaman = Keuntungan::rincian(
            $this->saringan($request) + ['hanya_lunas' => $request->boolean('hanya_lunas')],
            $this->perHalaman($request),
        );

        return $this->halamanDipeta(
            $halaman,
            fn () => $halaman->getCollection()->map(fn ($daftar) => Keuntungan::satuBaris($daftar))->all(),
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
            'kategori' => 'nullable|in:'.implode(',', array_keys(config('orcha.kategori_paket'))),
            'paket_id' => 'nullable|integer',
            'dasar' => 'nullable|in:'.implode(',', array_keys(Keuntungan::DASAR)),
        ]);

        return array_filter($data, fn ($nilai) => $nilai !== null && $nilai !== '');
    }
}
