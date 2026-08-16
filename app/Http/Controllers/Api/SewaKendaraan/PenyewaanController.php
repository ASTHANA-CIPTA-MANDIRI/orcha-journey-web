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

    /**
     * Serah terima unit: saat diserahkan, dan saat kembali.
     *
     * Satu jalur untuk dua kejadian, karena bentuk datanya sama — kilometer,
     * bahan bakar, dan hasil pemeriksaan per bagian. Yang membedakan hanya
     * kapan diisinya.
     *
     * Denda TIDAK dihitung sendiri lalu langsung ditagihkan. Sistem hanya
     * mengusulkan angkanya; yang menetapkan tetap admin, karena alasan telat
     * kadang memang di luar kuasa penyewa. Yang penting angkanya bisa
     * dijelaskan asal-usulnya, bukan muncul begitu saja.
     */
    public function serahTerima(PenyewaanKendaraan $penyewaan, Request $request): JsonResponse
    {
        $bagian = implode(',', array_keys(config('orcha.pemeriksaan_kendaraan')));
        $kondisi = implode(',', array_keys(config('orcha.kondisi_pemeriksaan')));

        $data = $request->validate([
            'diserahkan_pada' => 'nullable|date',
            'dikembalikan_pada' => 'nullable|date',
            'kilometer_awal' => 'nullable|integer|min:0|max:9999999',
            'kilometer_akhir' => 'nullable|integer|min:0|max:9999999',
            'bahan_bakar_awal' => 'nullable|string|max:20',
            'bahan_bakar_akhir' => 'nullable|string|max:20',
            'jaminan' => 'nullable|string|max:191',
            'kondisi_awal' => 'nullable|array',
            'kondisi_awal.*' => 'in:'.$kondisi,
            'kondisi_akhir' => 'nullable|array',
            'kondisi_akhir.*' => 'in:'.$kondisi,
            'denda_keterlambatan' => 'nullable|integer|min:0',
            'denda_kerusakan' => 'nullable|integer|min:0',
            'denda_lain' => 'nullable|integer|min:0',
            'catatan_denda' => 'nullable|string|max:1000',
        ]);

        // Bagian yang tidak dikenal ditolak diam-diam, supaya perbandingan
        // kondisi awal dan akhir selalu memakai daftar yang sama.
        foreach (['kondisi_awal', 'kondisi_akhir'] as $kunci) {
            if (isset($data[$kunci])) {
                $data[$kunci] = array_intersect_key($data[$kunci], array_flip(explode(',', $bagian)));
            }
        }

        $penyewaan->update(array_filter($data, fn ($nilai) => $nilai !== null));

        // Unit membawa keadaannya sendiri ke sewa berikutnya. Tanpa ini, admin
        // mengetik ulang daftar lecet lama setiap kali unit disewakan — dan
        // yang lupa diketik akan tertagih ke penyewa berikutnya.
        if (filled($data['kondisi_akhir'] ?? null) && $penyewaan->kendaraan) {
            $penyewaan->kendaraan->update([
                'kondisi_terkini' => $data['kondisi_akhir'],
                'kondisi_diperiksa_pada' => now(),
            ]);
        }

        // Unit yang sudah kembali berarti sewanya selesai. Status yang harus
        // diingat sendiri adalah status yang paling sering tertinggal.
        if (filled($data['dikembalikan_pada'] ?? null) && ! in_array($penyewaan->status, ['selesai', 'batal'], true)) {
            $penyewaan->update(['status' => 'selesai']);
        }

        $this->catat($request, 'catat serah terima kendaraan', [
            'kode' => $penyewaan->kode,
            'dikembalikan' => $penyewaan->dikembalikan_pada?->toDateTimeString(),
            'total_denda' => $penyewaan->fresh()->total_denda,
        ]);

        return response()->json([
            'data' => (new PenyewaanResource($penyewaan->fresh()))->resolve(),
            'pesan' => 'Catatan serah terima kendaraan tersimpan.',
        ]);
    }
}
