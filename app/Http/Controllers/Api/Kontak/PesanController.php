<?php

namespace App\Http\Controllers\Api\Kontak;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Kontak\PesanResource;
use App\Models\Kontak\PesanKontak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = PesanKontak::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('nama', 'like', "%{$cari}%")
                    ->orWhere('whatsapp', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('pesan', 'like', "%{$cari}%")
            ))
            ->when($request->string('keperluan')->toString(), fn ($q, $keperluan) => $q->where('keperluan', $keperluan))
            ->when($request->boolean('belum_dibaca'), fn ($q) => $q->belumDibaca())
            ->latest('id')
            ->paginate($this->perHalaman($request));

        $balasan = $this->halaman($daftar, PesanResource::class);

        // Jumlah yang belum dibaca ikut dikirim, terlepas dari saringan yang
        // sedang dipakai. Penyaring "belum dibaca" tanpa angka tidak memberi
        // tahu apa pun sebelum ditekan — dan yang ingin diketahui admin justru
        // sebelum menekannya: masih ada berapa yang menunggu.
        $isi = $balasan->getData(true);
        $isi['meta']['belum_dibaca'] = PesanKontak::belumDibaca()->count();

        return response()->json($isi);
    }

    /**
     * Angka untuk penanda di bilah samping dan lonceng lemon.
     *
     * Dihitung dari yang BELUM DIBACA, bukan dari yang belum dibalas.
     *
     * Bukan karena itu ukuran yang paling tepat — yang sebenarnya ingin
     * diketahui admin adalah mana yang belum dijawab — melainkan karena
     * balasannya dikirim lewat WhatsApp, di luar sistem ini. Orcha tidak pernah
     * tahu sebuah pesan sudah dibalas atau belum. Satu-satunya penutup yang
     * benar-benar tercatat adalah "sudah dibaca", jadi itu yang dipakai, dan
     * labelnya di lemon menyebutnya apa adanya.
     *
     * Dipecah menurut umur, meniru penanda sewa: yang baru masuk hari ini
     * wajar masih menunggu, yang menganggur lewat sehari itulah yang menyakiti
     * — dan keduanya perlu dibedakan supaya admin tahu mana yang didahulukan.
     */
    public function perhatian(): JsonResponse
    {
        $batas = now()->subDay();

        $hitung = PesanKontak::belumDibaca()
            ->selectRaw('sum(case when created_at < ? then 1 else 0 end) as lama, count(*) as semua', [$batas])
            ->first();

        $semua = (int) ($hitung->semua ?? 0);
        $lama = (int) ($hitung->lama ?? 0);

        return response()->json(['data' => [
            'belum_dibaca' => $semua,
            // Sisanya, supaya lemon tidak perlu mengurangi sendiri.
            'baru' => $semua - $lama,
            'lama' => $lama,
        ]]);
    }

    public function show(PesanKontak $pesan): JsonResponse
    {
        // Versi rinci: ikut membawa pesanan milik pengirim dan pesan-pesan
        // sebelumnya. Query tambahannya hanya sepadan untuk satu pesan yang
        // sedang dibuka, bukan untuk sepuluh baris daftar.
        return response()->json([
            'data' => PesanResource::rinci($pesan)->resolve(),
        ]);
    }

    public function tandaiDibaca(PesanKontak $pesan, Request $request): JsonResponse
    {
        $pesan->update(['dibaca_pada' => $pesan->dibaca_pada ?? now()]);

        $this->catat($request, 'tandai pesan dibaca', ['id' => $pesan->id]);

        return response()->json([
            'data' => (new PesanResource($pesan->fresh()))->resolve(),
            'pesan' => 'Pesan ditandai sudah dibaca.',
        ]);
    }
}
