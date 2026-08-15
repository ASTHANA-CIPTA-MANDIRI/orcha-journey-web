<?php

namespace App\Http\Controllers\Api\Umum;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OpenTrip\PendaftaranResource;
use App\Http\Resources\SewaKendaraan\PenyewaanResource;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use App\Models\Kontak\PesanKontak;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\TravelPackage;
use App\Models\SewaKendaraan\Car;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Isi dashboard Orcha yang digambar ulang di admin Phoenix.
 */
class DashboardController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'kartu' => $this->kartu(),
                'paket_per_kategori' => $this->paketPerKategori(),
                'kendaraan_per_jenis' => $this->kendaraanPerJenis(),
                'pendaftaran_terbaru' => PendaftaranResource::collection(
                    PendaftaranOpenTrip::latest('id')->limit(5)->get()
                )->resolve(),
                'penyewaan_terbaru' => PenyewaanResource::collection(
                    PenyewaanKendaraan::latest('id')->limit(5)->get()
                )->resolve(),
                'perlu_ditindak' => [
                    'pendaftaran_baru' => PendaftaranOpenTrip::where('status', 'baru')->count(),
                    'penyewaan_baru' => PenyewaanKendaraan::where('status', 'baru')->count(),
                    'pembatalan_diajukan' => Pembatalan::where('status', 'diajukan')->count(),
                    'pembayaran_menunggu' => KonfirmasiPembayaran::menunggu()->count(),
                    'pesan_belum_dibaca' => PesanKontak::belumDibaca()->count(),
                ],
            ],
            'meta' => [
                'diperbarui_pada' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Kartu ringkasan. `tautan` memakai jalur relatif supaya Phoenix bebas
     * memasangnya di bawah awalan apa pun, misalnya /admin/orcha/pendaftaran.
     */
    private function kartu(): array
    {
        return [
            [
                'kunci' => 'pendaftaran_baru',
                'label' => 'Pendaftaran baru',
                'nilai' => PendaftaranOpenTrip::where('status', 'baru')->count(),
                'ikon' => 'clipboard-document-list',
                'tautan' => 'pendaftaran',
            ],
            [
                'kunci' => 'penyewaan_baru',
                'label' => 'Sewa kendaraan baru',
                'nilai' => PenyewaanKendaraan::where('status', 'baru')->count(),
                'ikon' => 'truck',
                'tautan' => 'penyewaan',
            ],
            [
                'kunci' => 'pembatalan_diajukan',
                'label' => 'Pembatalan diajukan',
                'nilai' => Pembatalan::where('status', 'diajukan')->count(),
                'ikon' => 'x-circle',
                'tautan' => 'pembatalan',
            ],
            [
                'kunci' => 'pembayaran_menunggu',
                'label' => 'Bukti bayar menunggu',
                'nilai' => KonfirmasiPembayaran::menunggu()->count(),
                'ikon' => 'banknotes',
                'tautan' => 'pembayaran',
            ],
            [
                'kunci' => 'pesan_belum_dibaca',
                'label' => 'Pesan belum dibaca',
                'nilai' => PesanKontak::belumDibaca()->count(),
                'ikon' => 'inbox',
                'tautan' => 'pesan',
            ],
            [
                'kunci' => 'paket',
                'label' => 'Paket wisata',
                'nilai' => TravelPackage::count(),
                'ikon' => 'map',
                'tautan' => 'paket-wisata',
            ],
            [
                'kunci' => 'kendaraan',
                'label' => 'Kendaraan',
                'nilai' => Car::count(),
                'ikon' => 'truck',
                'tautan' => 'kendaraan',
            ],
            [
                'kunci' => 'destinasi',
                'label' => 'Destinasi populer',
                'nilai' => DestinationPopuler::count(),
                'ikon' => 'map-pin',
                'tautan' => 'destinasi',
            ],
            [
                'kunci' => 'testimoni',
                'label' => 'Testimoni',
                'nilai' => Testimoni::count(),
                'ikon' => 'chat-bubble-left-right',
                'tautan' => 'testimoni',
            ],
            [
                'kunci' => 'partner',
                'label' => 'Partner',
                'nilai' => Partner::count(),
                'ikon' => 'building-office-2',
                'tautan' => 'partner',
            ],
        ];
    }

    private function paketPerKategori(): array
    {
        return collect(config('orcha.kategori_paket'))
            ->map(fn ($label, $kunci) => [
                'kunci' => $kunci,
                'label' => $label,
                'jumlah' => TravelPackage::where('category', $kunci)->count(),
            ])
            ->values()
            ->all();
    }

    private function kendaraanPerJenis(): array
    {
        return collect(config('orcha.jenis_kendaraan'))
            ->map(fn ($label, $kunci) => [
                'kunci' => $kunci,
                'label' => $label,
                'jumlah' => Car::where('type', $kunci)->count(),
                'tersedia' => Car::where('type', $kunci)->where('is_available', true)->count(),
            ])
            ->values()
            ->all();
    }
}
