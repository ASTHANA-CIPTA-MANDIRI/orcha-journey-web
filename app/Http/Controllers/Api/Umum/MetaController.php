<?php

namespace App\Http\Controllers\Api\Umum;

use App\Http\Controllers\Api\ApiController;
use App\Models\Blog\KategoriArtikel;
use App\Models\Etalase\DaerahTambahan;
use App\Models\Etalase\KatalogDestinasi;
use App\Models\Etalase\ProvinsiTambahan;
use App\Models\Etalase\WilayahTambahan;
use App\Models\PaketWisata\TravelPackage;
use App\Support\Pemeriksaan;
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
                ['jalur' => 'keuntungan', 'label' => 'Keuntungan Paket', 'ikon' => 'chart-bar'],
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
                // Dipakai pemilih kategori di layar Blog pada lemon, supaya
                // daftarnya tidak disalin-tempel ke sana.
                'kategori_artikel' => KategoriArtikel::daftar(),
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
                'wilayah' => WilayahTambahan::gabungan(),
                'wilayah_kustom' => WilayahTambahan::kustom(),
                // Provinsi beserta wilayahnya: admin cukup memilih provinsi,
                // dan wilayah penyaring di halaman publik terisi sendiri.
                // Dikirim dari sini supaya daftarnya satu — bukan disalin ke
                // lemon lalu berbeda diam-diam saat provinsi baru dimekarkan.
                'provinsi_wilayah' => ProvinsiTambahan::gabungan(),
                // Hanya entri tambahan yang boleh dihapus dari daftar pilihan;
                // yang bawaan ikut versi kode.
                'provinsi_kustom' => ProvinsiTambahan::kustom(),
                // Nama destinasi yang sering diminta beserta provinsinya: sekali
                // dipilih, nama dan provinsi terisi — dan wilayah ikut, karena
                // provinsi yang menentukannya.
                // Daerah menyusut mengikuti provinsi, sama seperti provinsi
                // menyusut mengikuti wilayah.
                'katalog_daerah' => DaerahTambahan::gabungan(),
                'katalog_daerah_kustom' => DaerahTambahan::kustom(),
                'katalog_destinasi' => KatalogDestinasi::gabungan(),
                'katalog_destinasi_kustom' => KatalogDestinasi::kustom(),
                'pembayaran' => config('orcha.pembayaran'),
                'fasilitas_umum' => config('orcha.fasilitas_umum'),
                'status_paket' => config('orcha.status_paket'),
                // Daftar paket untuk pemilih saringan di lemon. Dikirim lewat
                // rujukan — yang sudah disimpan sebentar di sisi sana — bukan
                // lewat panggilan sendiri tiap kali halaman digambar.
                'paket_wisata' => TravelPackage::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'category', 'tanggal_berangkat'])
                    ->map(fn ($paket) => [
                        'id' => $paket->id,
                        'nama' => $paket->name,
                        'kategori' => $paket->category,
                        'tanggal_berangkat' => $paket->tanggal_berangkat?->toDateString(),
                    ])
                    ->all(),
                'status_tayang' => config('orcha.status_tayang'),
                /*
                 | Dipakai formulir armada dan lembar serah terima di lemon:
                 | daftar bagian yang diperiksa dan pilihan kondisinya harus
                 | sama persis di kedua sisi, kalau tidak perbandingannya tidak
                 | berarti.
                 |
                 | Dikirim dalam dua bentuk yang berbeda gunanya:
                 |
                 |   pemeriksaan_kendaraan — SELURUH bagian yang pernah ada,
                 |     termasuk yang sudah dinonaktifkan. Untuk MEMBACA nama
                 |     bagian di lembar serah terima lama.
                 |   pemeriksaan_per_jenis — yang DIISI, dipilah per jenis unit.
                 |     Formulir memakai ini.
                 */
                'pemeriksaan_kendaraan' => Pemeriksaan::label(),
                'pemeriksaan_per_jenis' => Pemeriksaan::perJenis(),
                'kondisi_pemeriksaan' => config('orcha.kondisi_pemeriksaan'),
                'biaya_kerusakan' => Pemeriksaan::tarif(),
                'denda_sewa' => config('orcha.denda_sewa'),
            ],
        ]);
    }
}
