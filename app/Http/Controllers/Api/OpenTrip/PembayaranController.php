<?php

namespace App\Http\Controllers\Api\OpenTrip;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PembayaranResource;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\KabarPembayaran;
use App\Support\StatusPendaftaran;
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

    /**
     * Berapa bukti yang masih menunggu dicek.
     *
     * Jalur tersendiri, bukan menghitung dari daftar: yang dibutuhkan cuma satu
     * bilangan, dan mengambil sehalaman penuh bukti berikut pesanan tiap
     * barisnya hanya untuk membaca meta.total adalah pekerjaan yang jauh lebih
     * berat daripada jawabannya.
     *
     * Dipanggil bilah samping lemon di SETIAP halaman admin, jadi murahnya
     * bukan kemewahan.
     */
    public function menunggu(): JsonResponse
    {
        $menunggu = KonfirmasiPembayaran::menunggu();

        return response()->json(['data' => [
            'jumlah' => (int) (clone $menunggu)->count(),
            'nominal' => (int) $menunggu->sum('nominal'),
        ]]);
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

        // Satu kejadian, satu langkah: menyetujui bukti transfer sekaligus
        // memajukan status pendaftarannya. Dijalankan di sini, bukan di lemon,
        // supaya berlaku dari mana pun statusnya diubah.
        $pesan = 'Status pembayaran diperbarui.';
        $pesanan = $pembayaran->pesanan();
        $statusBaru = StatusPendaftaran::selaraskan($pesanan);

        if ($statusBaru) {
            $rujukan = $pesanan instanceof PendaftaranOpenTrip
                ? config('orcha.status_pendaftaran')
                : config('orcha.status_penyewaan');

            $label = $rujukan[$statusBaru] ?? $statusBaru;
            $pesan .= " Status pesanan {$pesanan->kode} ikut menjadi {$label}.";

            $this->catat($request, 'status pesanan menyesuaikan pembayaran', [
                'kode' => $pesanan->kode,
                'ke' => $statusBaru,
            ]);
        }

        // Pelanggan diberi tahu begitu pembayarannya diperiksa. Sebelumnya ia
        // hanya diberi tahu saat MENGIRIM bukti, lalu menunggu tanpa kabar —
        // dan yang paling sering ditanyakan lewat WhatsApp justru ini.
        if ($sebelum !== $data['status']) {
            KabarPembayaran::kirim($pembayaran->fresh(), $pesanan);
        }

        return response()->json([
            'data' => (new PembayaranResource($pembayaran->fresh()))->resolve(),
            'pesan' => $pesan,
        ]);
    }
}
