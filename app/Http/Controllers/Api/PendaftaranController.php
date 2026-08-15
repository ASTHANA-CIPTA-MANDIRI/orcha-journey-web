<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PendaftaranResource;
use App\Models\PendaftaranOpenTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendaftaranController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PendaftaranOpenTrip::query()
            ->withCount('riwayatKesehatan')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('nama_paket', 'like', "%{$cari}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PendaftaranResource::class);
    }

    public function show(PendaftaranOpenTrip $pendaftaran): JsonResponse
    {
        return response()->json([
            'data' => (new PendaftaranResource($pendaftaran->loadCount('riwayatKesehatan')))->resolve(),
        ]);
    }

    /**
     * Riwayat kesehatan sengaja dipisah ke jalur sendiri. Data ini sensitif,
     * jadi Phoenix harus memintanya secara khusus, bukan ikut terbawa daftar.
     */
    public function riwayatKesehatan(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $this->catat($request, 'membuka riwayat kesehatan', ['kode' => $pendaftaran->kode]);

        return response()->json([
            'data' => $pendaftaran->riwayatKesehatan->map(fn ($riwayat) => [
                'id' => $riwayat->id,
                'nama_peserta' => $riwayat->nama_peserta,
                'usia' => $riwayat->usia,
                'jenis_kelamin' => $riwayat->jenis_kelamin,
                'tinggi_badan' => $riwayat->tinggi_badan,
                'berat_badan' => $riwayat->berat_badan,
                'golongan_darah' => $riwayat->golongan_darah,
                'riwayat_penyakit' => $riwayat->riwayat_penyakit,
                'kondisi_khusus' => $riwayat->kondisi_khusus ?? [],
                'riwayat_operasi' => $riwayat->riwayat_operasi,
                'alergi' => $riwayat->alergi,
                'pantangan_makanan' => $riwayat->pantangan_makanan,
                'obat_rutin' => $riwayat->obat_rutin,
                'pantangan_kegiatan' => $riwayat->pantangan_kegiatan,
                'kemampuan_renang' => $riwayat->kemampuan_renang,
                'asuransi' => $riwayat->asuransi,
                'kontak_darurat' => [
                    'nama' => $riwayat->kontak_darurat_nama,
                    'hubungan' => $riwayat->kontak_darurat_hubungan,
                    'hp' => $riwayat->kontak_darurat_hp,
                ],
                'catatan_tambahan' => $riwayat->catatan_tambahan,
                'ada_catatan_khusus' => $riwayat->ada_catatan_khusus,
                'dibuat_pada' => $riwayat->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function ubahStatus(PendaftaranOpenTrip $pendaftaran, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(config('orcha.status_pendaftaran'))),
        ]);

        $sebelum = $pendaftaran->status;
        $pendaftaran->update($data);

        $this->catat($request, 'ubah status pendaftaran', [
            'kode' => $pendaftaran->kode,
            'dari' => $sebelum,
            'ke' => $data['status'],
        ]);

        return response()->json([
            'data' => (new PendaftaranResource($pendaftaran->fresh()))->resolve(),
            'pesan' => 'Status pendaftaran diperbarui.',
        ]);
    }
}
