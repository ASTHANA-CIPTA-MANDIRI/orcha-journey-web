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

    /**
     * Pengajuan pembatalan yang menuntut tindakan admin.
     *
     * Dihitung PER STATUS, bukan digabung jadi satu angka. Tiap status menuntut
     * perbuatan yang berbeda, dan admin yang membaca penandanya perlu tahu
     * langsung mana yang harus dikerjakan lebih dulu:
     *
     *   diajukan  — belum disentuh siapa pun; pemohon menunggu dijawab.
     *   diproses  — sudah dipegang, keputusannya belum selesai.
     *   disetujui — sudah diputuskan tetapi dananya BELUM dikirim. Ini yang
     *               paling mahal dibiarkan: uang pelanggan sudah dinyatakan
     *               kembali tetapi belum berangkat ke mana-mana. Yang menunggu
     *               bukan lagi jawaban, melainkan uangnya sendiri.
     *
     * dana_dikirim dan ditolak tidak dihitung: keduanya sudah selesai.
     *
     * Jalur tersendiri dan semurah mungkin: dipanggil bilah samping lemon di
     * setiap halaman admin. Satu query untuk ketiganya, bukan tiga.
     */
    public function perhatian(): JsonResponse
    {
        $hitung = Pembatalan::selectRaw('status, count(*) as jumlah')
            ->whereIn('status', ['diajukan', 'diproses', 'disetujui'])
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return response()->json(['data' => [
            'diajukan' => (int) $hitung->get('diajukan', 0),
            'diproses' => (int) $hitung->get('diproses', 0),
            'disetujui' => (int) $hitung->get('disetujui', 0),
        ]]);
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
            // Potongan yang ditetapkan admin. Boleh berbeda dari usulan
            // sistem: ada biaya yang sudah terlanjur dibayarkan ke pihak
            // ketiga, dan ada kelonggaran yang memang layak diberikan.
            'potongan_ditetapkan' => 'nullable|integer|min:0',
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
            'data' => (new PembatalanResource($pembatalan->fresh()->load(['pendaftaran.paket', 'penyewaan'])))->resolve(),
            'pesan' => 'Status pembatalan diperbarui.',
        ]);
    }
}
