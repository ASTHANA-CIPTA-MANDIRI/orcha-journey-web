<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Keterangan sistem: sapaan pembuka, susunan menu, dan daftar pilihan.
 *
 * Tujuannya supaya Phoenix tidak menyalin-tempel daftar status atau kategori.
 * Sekali diubah di config/orcha.php, dashboard Phoenix ikut berubah.
 */
class MetaController extends ApiController
{
    /**
     * Uji sambungan sekaligus penanda bahwa kunci API-nya benar. Dipakai tombol
     * "Ganti ke Orcha" untuk memastikan sisi sana hidup sebelum berpindah.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'aplikasi' => 'Orcha Journey',
                'versi_api' => 'v1',
                'waktu' => now()->toIso8601String(),
                'admin_pemanggil' => $request->attributes->get('admin_pemanggil'),
            ],
        ]);
    }

    /**
     * Susunan menu sisi Orcha. `jalur` sengaja relatif — Phoenix yang memberi
     * awalan, misalnya /admin/orcha/pendaftaran.
     */
    public function menu(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['jalur' => 'dashboard', 'label' => 'Dashboard Orcha', 'ikon' => 'home'],
                ['jalur' => 'pendaftaran', 'label' => 'Pendaftaran Open Trip', 'ikon' => 'clipboard-document-list'],
                ['jalur' => 'penyewaan', 'label' => 'Sewa Masuk', 'ikon' => 'truck'],
                ['jalur' => 'pembatalan', 'label' => 'Pembatalan', 'ikon' => 'x-circle'],
                ['jalur' => 'pesan', 'label' => 'Pesan Kontak', 'ikon' => 'inbox'],
                ['jalur' => 'paket-wisata', 'label' => 'Paket Wisata', 'ikon' => 'map'],
                ['jalur' => 'kendaraan', 'label' => 'Armada', 'ikon' => 'truck'],
                ['jalur' => 'destinasi', 'label' => 'Destinasi Populer', 'ikon' => 'map-pin'],
                ['jalur' => 'testimoni', 'label' => 'Testimoni', 'ikon' => 'chat-bubble-left-right'],
                ['jalur' => 'partner', 'label' => 'Partner', 'ikon' => 'building-office-2'],
            ],
        ]);
    }

    /**
     * Daftar pilihan untuk isian dropdown di Phoenix.
     */
    public function rujukan(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_pendaftaran' => config('orcha.status_pendaftaran'),
                'status_penyewaan' => config('orcha.status_penyewaan'),
                'status_pembatalan' => config('orcha.status_pembatalan'),
                'kategori_paket' => config('orcha.kategori_paket'),
                'jenis_kendaraan' => config('orcha.jenis_kendaraan'),
                'satuan_sewa' => config('orcha.satuan_sewa'),
                'keperluan_kontak' => config('orcha.keperluan_kontak'),
                'alasan_pembatalan' => config('orcha.alasan_pembatalan'),
                'wilayah' => config('orcha.wilayah'),
                'pembayaran' => config('orcha.pembayaran'),
                'fasilitas_umum' => config('orcha.fasilitas_umum'),
                'status_paket' => config('orcha.status_paket'),
                'status_tayang' => config('orcha.status_tayang'),
            ],
        ]);
    }
}
