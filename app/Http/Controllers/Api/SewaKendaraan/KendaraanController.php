<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Http\Resources\SewaKendaraan\KendaraanResource;
use App\Models\SewaKendaraan\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Armada: dibaca dan ditulis dari dashboard lemon.
 *
 * Tarif per jam dan per 12 jam boleh kosong — bus memang tidak dilepas per
 * jam, dan kosong berarti "tidak dijual per satuan itu".
 */
class KendaraanController extends ApiController
{
    use MenyimpanGambar;

    public function index(Request $request): JsonResponse
    {
        $daftar = Car::query()
            ->withCount('penyewaan')
            // Ringkasan jadwal per unit membaca penyewaannya; dimuat di muka
            // supaya satu halaman daftar tidak menembak query per baris.
            ->with('penyewaan')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$cari}%")->orWhere('brand', 'like', "%{$cari}%")
            ))
            ->ofType($request->string('jenis')->toString() ?: null)
            ->orderBy('price_per_day')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, KendaraanResource::class);
    }

    /**
     * Mencatat kondisi unit di luar serah terima — biasanya sesudah perbaikan.
     *
     * Selama ini kondisi hanya bisa berubah saat penyewa mengembalikan unitnya.
     * Setelah pemilik membawa mobilnya ke bengkel dan kacanya diganti, tidak
     * ada tempat untuk menyatakan unit itu sudah baik lagi: ia terus terbaca
     * "rusak" sampai ada penyewa berikutnya yang mengembalikannya — dan selama
     * itu pula halaman armada menyuruh admin memperbaiki yang sudah diperbaiki.
     *
     * Catatan pentingnya: ini TIDAK menghapus jejak kerusakan sebelumnya.
     * Denda dan rincian kerusakan tersimpan pada penyewaannya masing-masing,
     * bukan pada unitnya. Yang diubah di sini hanya "keadaan unit sekarang".
     */
    public function ubahKondisi(Car $kendaraan, Request $request): JsonResponse
    {
        $bagian = array_keys(config('orcha.pemeriksaan_kendaraan'));
        $kondisi = implode(',', array_keys(config('orcha.kondisi_pemeriksaan')));

        $data = $request->validate([
            'kondisi' => 'required|array',
            'kondisi.*' => 'in:'.$kondisi,
            'catatan' => 'nullable|string|max:500',
        ]);

        // Bagian yang tidak dikenal ditolak diam-diam, supaya perbandingan
        // kondisi pada serah terima berikutnya tetap memakai daftar yang sama.
        $bersih = array_intersect_key($data['kondisi'], array_flip($bagian));

        $kendaraan->update([
            'kondisi_terkini' => $bersih,
            'kondisi_diperiksa_pada' => now(),
            'kondisi_catatan' => $data['catatan'] ?? null,
        ]);

        $this->catat($request, 'catat kondisi kendaraan', [
            'unit' => $kendaraan->name,
            'catatan' => $data['catatan'] ?? null,
        ]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan->fresh()->loadCount('penyewaan')->load('penyewaan')))->resolve(),
            'pesan' => 'Kondisi unit tersimpan.',
        ]);
    }

    public function show(Car $kendaraan): JsonResponse
    {
        return response()->json([
            'data' => (new KendaraanResource($kendaraan->loadCount('penyewaan')->load('penyewaan')))->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $kendaraan = Car::create($this->siapkan($data, $request));

        $this->catat($request, 'tambah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan))->resolve(),
            'pesan' => 'Kendaraan ditambahkan.',
        ], 201);
    }

    public function update(Car $kendaraan, Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $kendaraan->update($this->siapkan($data, $request, $kendaraan->image));

        $this->catat($request, 'ubah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan->fresh()))->resolve(),
            'pesan' => 'Kendaraan diperbarui.',
        ]);
    }

    public function destroy(Car $kendaraan, Request $request): JsonResponse
    {
        if ($kendaraan->penyewaan()->exists()) {
            return response()->json([
                'pesan' => 'Unit ini sudah pernah disewa, jadi tidak bisa dihapus. Nonaktifkan saja lewat status.',
            ], 422);
        }

        $this->hapusGambar($kendaraan->image);
        $kendaraan->delete();

        $this->catat($request, 'hapus kendaraan', ['nama' => $kendaraan->name]);

        return response()->json(['pesan' => 'Kendaraan dihapus.']);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:255',
            'merek' => 'required|string|max:100',
            'jenis' => 'required|in:'.implode(',', array_keys(config('orcha.jenis_kendaraan'))),
            'nopol' => 'nullable|string|max:20',
            'kapasitas' => 'required|integer|min:1|max:80',
            'transmisi_tersedia' => 'required|array|min:1',
            'transmisi_tersedia.*' => 'in:Manual,Matic',
            'tarif_hari' => 'required|numeric|min:0',
            'tarif_jam' => 'nullable|numeric|min:0',
            'tarif_12jam' => 'nullable|numeric|min:0',
            'tarif_sopir' => 'nullable|numeric|min:0',
            'tersedia' => 'nullable|boolean',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    private function siapkan(array $data, Request $request, ?string $gambarLama = null): array
    {
        $transmisi = array_values(array_unique($data['transmisi_tersedia']));

        return [
            'name' => $data['nama'],
            'brand' => $data['merek'],
            'type' => $data['jenis'],
            'nopol' => $data['nopol'] ?? null,
            'capacity' => $data['kapasitas'],
            'transmission' => $transmisi[0],
            'transmisi_tersedia' => $transmisi,
            'price_per_day' => $data['tarif_hari'],
            'harga_per_jam' => $data['tarif_jam'] ?: null,
            'harga_12_jam' => $data['tarif_12jam'] ?: null,
            'harga_sopir' => $data['tarif_sopir'] ?: null,
            'is_available' => (bool) ($data['tersedia'] ?? true),
            'image' => $this->simpanGambar($request, 'cars', $gambarLama),
        ];
    }
}
