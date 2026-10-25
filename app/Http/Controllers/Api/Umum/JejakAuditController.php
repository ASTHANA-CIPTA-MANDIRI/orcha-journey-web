<?php

namespace App\Http\Controllers\Api\Umum;

use App\Http\Controllers\Api\ApiController;
use App\Models\JejakAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jejak audit untuk dibaca dari lemon.
 *
 * Hanya membaca. Catatan audit yang bisa disunting atau dihapus lewat API
 * bukan catatan audit — ia cuma daftar yang kebetulan berisi kejadian, dan
 * yang pertama dihapus justru baris yang paling perlu dibaca.
 */
class JejakAuditController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = JejakAudit::query()
            ->cari($request->query('cari'))

            /*
             | Penyaring per admin dan per rentang tanggal.
             |
             | Pertanyaannya hampir selalu satu dari dua bentuk: "apa yang
             | terjadi pada pesanan ini" (dijawab pencarian kode) atau "apa saja
             | yang dilakukan admin ini pekan lalu" (dijawab kedua penyaring di
             | bawah).
             */
            ->when($request->query('admin'), fn ($q, $admin) => $q->where('admin', $admin))

            /*
             | Penyaring per JENIS AKSI dan per IP.
             |
             | Tanpa keduanya, "siapa saja yang pernah menyentuh pembayaran?"
             | dan "adakah akses dari alamat yang tidak dikenal?" hanya bisa
             | dijawab dengan mengetik kata di pencarian lalu berharap
             | kalimatnya cocok — padahal keduanya pertanyaan audit yang paling
             | sering diajukan.
             */
            ->when($request->query('aksi'), fn ($q, $aksi) => $q->where('aksi', $aksi))
            ->when($request->query('ip'), fn ($q, $ip) => $q->where('ip', 'like', "%{$ip}%"))
            ->when($request->query('dari'), fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($request->query('sampai'), fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->latest()
            ->paginate((int) $request->query('per_halaman', 25));

        return $this->halamanDipeta($daftar, fn () => $daftar->getCollection()
            ->map(fn (JejakAudit $jejak) => [
                'id' => $jejak->id,
                'admin' => $jejak->admin,
                'aksi' => $jejak->aksi,
                'ringkasan' => $jejak->ringkasan,
                'kode' => $jejak->kode,
                'sebelum' => $jejak->sebelum,
                'sesudah' => $jejak->sesudah,
                'ip' => $jejak->ip,
                'waktu' => $jejak->created_at?->toIso8601String(),
                'waktu_teks' => $jejak->created_at?->locale('id')->translatedFormat('j F Y, H:i'),
            ])
            ->all());
    }

    /**
     * Daftar jenis aksi yang pernah tercatat — untuk mengisi penyaringnya.
     *
     * Diambil dari jejaknya sendiri, bukan daftar tetap di kode: aksi baru
     * muncul seiring fitur bertambah, dan daftar tetap akan ketinggalan diam-
     * diam tanpa ada yang menyadarinya.
     */
    public function aksi(): JsonResponse
    {
        return response()->json([
            'data' => JejakAudit::query()
                ->select('aksi')
                ->distinct()
                ->orderBy('aksi')
                ->pluck('aksi'),
        ]);
    }

    /** Daftar nama admin yang pernah tercatat — untuk mengisi penyaringnya. */
    public function admin(): JsonResponse
    {
        return response()->json([
            'data' => JejakAudit::query()
                ->select('admin')
                ->distinct()
                ->orderBy('admin')
                ->pluck('admin'),
        ]);
    }
}
