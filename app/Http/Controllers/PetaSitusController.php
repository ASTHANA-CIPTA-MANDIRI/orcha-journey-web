<?php

namespace App\Http\Controllers;

use App\Models\PaketWisata\TravelPackage;
use Illuminate\Http\Response;

/**
 * Peta situs untuk mesin pencari.
 *
 * Dibuat saat diminta, bukan berkas statis yang harus diingat untuk dibuat
 * ulang. Paket wisata datang dan pergi tiap bulan; peta situs yang ditulis
 * tangan akan menunjuk paket yang sudah tidak ada — dan alamat yang menjawab
 * 404 membuat mesin pencari mengunjungi situs ini lebih jarang.
 */
class PetaSitusController extends Controller
{
    /**
     * Halaman tetap, beserta seberapa sering isinya berubah.
     *
     * Prioritasnya BUKAN janji kepada mesin pencari melainkan urutan
     * kepentingan menurut kita sendiri: beranda dan dua halaman jualan di
     * atas, halaman ketentuan di bawah.
     */
    private const HALAMAN = [
        ['home', 'daily', '1.0'],
        ['paket-wisata', 'daily', '0.9'],
        ['sewa-kendaraan', 'daily', '0.9'],
        ['destinasi', 'weekly', '0.8'],
        ['tentang-kami', 'monthly', '0.6'],
        ['testimoni', 'weekly', '0.6'],
        ['kontak', 'monthly', '0.6'],
        ['faq', 'monthly', '0.5'],
        ['syarat-ketentuan', 'yearly', '0.3'],
        ['ketentuan-pembayaran', 'yearly', '0.3'],
        ['kebijakan-pengembalian', 'yearly', '0.3'],
        ['kebijakan-privasi', 'yearly', '0.3'],
    ];

    public function __invoke(): Response
    {
        $baris = [];

        foreach (self::HALAMAN as [$rute, $ubah, $prioritas]) {
            $baris[] = [route($rute), null, $ubah, $prioritas];
        }

        /*
         | Hanya paket yang benar-benar tayang — lewat scopeTayang(), penyaring
         | yang SAMA dengan yang dipakai halaman publiknya.
         |
         | Menulis penyaringnya sendiri di sini berarti dua tempat memutuskan
         | "tayang" masing-masing, dan suatu saat peta situs akan menunjuk
         | paket yang halamannya menjawab 404 — alamat mati membuat mesin
         | pencari mengunjungi situs ini lebih jarang.
         */
        foreach (TravelPackage::query()->tayang()->get() as $paket) {
            $baris[] = [
                route('paket-detail', $paket->uuid),
                $paket->updated_at,
                'weekly',
                '0.8',
            ];
        }

        // Halaman kategori: dicari orang dengan kata yang berbeda-beda
        // ("open trip jogja", "study tour"), jadi masing-masing perlu alamatnya
        // sendiri di peta situs.
        foreach (array_keys(config('orcha.kategori_paket', [])) as $kategori) {
            $baris[] = [route('paket-wisata', $kategori), null, 'weekly', '0.7'];
        }

        foreach (array_keys(config('orcha.jenis_kendaraan', [])) as $jenis) {
            $baris[] = [route('sewa-kendaraan', $jenis), null, 'weekly', '0.7'];
        }

        return response()
            ->view('peta-situs', ['baris' => $baris])
            ->header('Content-Type', 'application/xml');
    }
}
