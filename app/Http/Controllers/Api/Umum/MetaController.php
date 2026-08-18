<?php

namespace App\Http\Controllers\Api\Umum;

use App\Http\Controllers\Api\ApiController;
use App\Support\SewaKendaraan\KatalogKendaraan;
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
                ['jalur' => 'pembayaran', 'label' => 'Bukti Pembayaran', 'ikon' => 'banknotes'],
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
                'status_pembayaran' => config('orcha.status_pembayaran'),
                'jenis_pembayaran' => config('orcha.jenis_pembayaran'),
                'kategori_paket' => config('orcha.kategori_paket'),
                'jenis_kendaraan' => config('orcha.jenis_kendaraan'),
                // Merek & model untuk dropdown formulir armada, digabung dengan
                // yang sudah dipakai armada sendiri supaya unit lama tidak
                // kehilangan mereknya saat disunting.
                'katalog_kendaraan' => KatalogKendaraan::pilihan(),
                // Entri tambahan admin, beserta id-nya: hanya inilah yang boleh
                // dihapus dari daftar pilihan di lemon.
                'katalog_kustom' => KatalogKendaraan::kustom(),
                // Rincian per model: mengisi kapasitas, jenis, dan cc secara
                // otomatis saat model dipilih, serta daftar pilihan tipenya.
                'kapasitas_kendaraan' => KatalogKendaraan::kapasitas(),
                'jenis_per_model' => KatalogKendaraan::jenis(),
                'cc_per_model' => KatalogKendaraan::mesin(),
                'varian_per_model' => KatalogKendaraan::varian(),
                'lepas_kunci_per_model' => KatalogKendaraan::lepasKunci(),
                // Pos biaya perjalanan: urutan dan labelnya menentukan isian di
                // formulir armada lemon. Tanpa kunci ini daftarnya kosong dan
                // isiannya tidak ter-render sama sekali.
                'pos_operasional' => config('orcha.pos_operasional'),
                'satuan_sewa' => config('orcha.satuan_sewa'),
                'keperluan_kontak' => config('orcha.keperluan_kontak'),
                'alasan_pembatalan' => config('orcha.alasan_pembatalan'),
                'wilayah' => \App\Models\Etalase\WilayahTambahan::gabungan(),
                'wilayah_kustom' => \App\Models\Etalase\WilayahTambahan::kustom(),
                // Provinsi beserta wilayahnya: admin cukup memilih provinsi,
                // dan wilayah penyaring di halaman publik terisi sendiri.
                // Dikirim dari sini supaya daftarnya satu — bukan disalin ke
                // lemon lalu berbeda diam-diam saat provinsi baru dimekarkan.
                'provinsi_wilayah' => \App\Models\Etalase\ProvinsiTambahan::gabungan(),
                // Hanya entri tambahan yang boleh dihapus dari daftar pilihan;
                // yang bawaan ikut versi kode.
                'provinsi_kustom' => \App\Models\Etalase\ProvinsiTambahan::kustom(),
                // Nama destinasi yang sering diminta beserta provinsinya: sekali
                // dipilih, nama dan provinsi terisi — dan wilayah ikut, karena
                // provinsi yang menentukannya.
                'katalog_destinasi' => \App\Models\Etalase\KatalogDestinasi::gabungan(),
                'katalog_destinasi_kustom' => \App\Models\Etalase\KatalogDestinasi::kustom(),
                'pembayaran' => config('orcha.pembayaran'),
                'fasilitas_umum' => config('orcha.fasilitas_umum'),
                'status_paket' => config('orcha.status_paket'),
                'status_tayang' => config('orcha.status_tayang'),
                // Dipakai lembar serah terima kendaraan di lemon: daftar bagian
                // yang diperiksa dan pilihan kondisinya harus sama persis di
                // kedua sisi, kalau tidak perbandingannya tidak berarti.
                'pemeriksaan_kendaraan' => config('orcha.pemeriksaan_kendaraan'),
                'kondisi_pemeriksaan' => config('orcha.kondisi_pemeriksaan'),
                'biaya_kerusakan' => config('orcha.biaya_kerusakan'),
                'denda_sewa' => config('orcha.denda_sewa'),
            ],
        ]);
    }
}
