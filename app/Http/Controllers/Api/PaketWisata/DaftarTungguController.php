<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaketWisata\DaftarTunggu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Peminat yang menunggu kursi terbuka.
 *
 * Sistem mengabari mereka sendiri saat kursi dilepas, tetapi admin tetap perlu
 * melihat daftarnya: untuk tahu seberapa besar permintaan yang tertahan pada
 * satu trip, dan untuk menghubungi yang tidak mencantumkan surel — nomor
 * WhatsApp yang wajib di formulir, bukan surelnya.
 */
class DaftarTungguController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = DaftarTunggu::query()
            ->with('paket:id,name,uuid')
            ->when($request->integer('paket_id'), fn ($q, $id) => $q->where('travel_package_id', $id))
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($w) => $w->where('nama', 'like', "%{$cari}%")->orWhere('whatsapp', 'like', "%{$cari}%")
            ))
            /*
             | Yang BELUM dikabari didahulukan, lalu yang paling lama menunggu.
             |
             | Urutan ini yang dipakai admin bekerja: yang di atas adalah orang
             | yang masih menunggu jawaban, bukan yang sudah selesai diurus.
             */
            ->orderByRaw('dikabari_pada IS NOT NULL')
            ->orderBy('created_at')
            ->paginate($this->perHalaman($request));

        return response()->json([
            'data' => collect($daftar->items())->map(fn (DaftarTunggu $a) => [
                'id' => $a->id,
                'nama' => $a->nama,
                'whatsapp' => $a->whatsapp,
                'email' => $a->email,
                'jumlah_peserta' => $a->jumlah_peserta,
                'paket' => $a->paket?->name,
                'paket_id' => $a->travel_package_id,
                'menunggu_sejak' => $a->created_at?->toIso8601String(),
                'dikabari_pada' => $a->dikabari_pada?->toIso8601String(),
            ])->all(),
            'meta' => [
                'halaman' => $daftar->currentPage(),
                'per_halaman' => $daftar->perPage(),
                'total' => $daftar->total(),
                'halaman_terakhir' => $daftar->lastPage(),
                // Dipakai penyaring di layar admin.
                'paket' => \App\Models\PaketWisata\TravelPackage::query()
                    ->whereIn('id', DaftarTunggu::select('travel_package_id'))
                    ->pluck('name', 'id'),
            ],
        ]);
    }

    /**
     * Mengeluarkan satu orang dari antrean.
     *
     * Dipakai saat orangnya sudah jadi mendaftar, atau menyatakan batal lewat
     * WhatsApp. Tanpa ini antreannya cuma menumpuk, dan kabar kursi terbuka
     * dikirim ke orang yang sudah tidak menunggu.
     */
    public function destroy(DaftarTunggu $tunggu, Request $request): JsonResponse
    {
        // Lewat catat() milik ApiController, bukan JejakAudit langsung —
        // supaya bentuk jejaknya seragam dengan pemanggilan lain di API ini.
        $this->catat($request, 'keluarkan dari daftar tunggu', [
            'nama' => $tunggu->nama,
            'jumlah' => $tunggu->jumlah_peserta.' orang',
            'trip' => $tunggu->paket?->name ?? '—',
        ]);

        $tunggu->delete();

        return response()->json(['pesan' => 'Dikeluarkan dari daftar tunggu.']);
    }
}
