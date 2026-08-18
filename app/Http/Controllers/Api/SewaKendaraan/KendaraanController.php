<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Http\Resources\SewaKendaraan\KendaraanResource;
use App\Models\SewaKendaraan\Car;
use App\Support\SewaKendaraan\NomorPolisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Armada: dibaca dan ditulis dari dashboard lemon.
 *
 * Tarif per jam dan per 12 jam boleh kosong — bus memang tidak dilepas per
 * jam, dan kosong berarti "tidak dijual per satuan itu".
 */
class KendaraanController extends ApiController
{
    use MenyimpanGambar;

    public function index(Request $request): JsonResponse
    {
        $daftar = Car::query()
            ->withCount('penyewaan')
            // Ringkasan jadwal per unit membaca penyewaannya; dimuat di muka
            // supaya satu halaman daftar tidak menembak query per baris.
            ->with('penyewaan')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$cari}%")->orWhere('brand', 'like', "%{$cari}%")
            ))
            ->ofType($request->string('jenis')->toString() ?: null)
            ->orderBy('price_per_day')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, KendaraanResource::class);
    }

    /**
     * Mencatat kondisi unit di luar serah terima — biasanya sesudah perbaikan.
     *
     * Selama ini kondisi hanya bisa berubah saat penyewa mengembalikan unitnya.
     * Setelah pemilik membawa mobilnya ke bengkel dan kacanya diganti, tidak
     * ada tempat untuk menyatakan unit itu sudah baik lagi: ia terus terbaca
     * "rusak" sampai ada penyewa berikutnya yang mengembalikannya — dan selama
     * itu pula halaman armada menyuruh admin memperbaiki yang sudah diperbaiki.
     *
     * Catatan pentingnya: ini TIDAK menghapus jejak kerusakan sebelumnya.
     * Denda dan rincian kerusakan tersimpan pada penyewaannya masing-masing,
     * bukan pada unitnya. Yang diubah di sini hanya "keadaan unit sekarang".
     */
    public function ubahKondisi(Car $kendaraan, Request $request): JsonResponse
    {
        $bagian = array_keys(config('orcha.pemeriksaan_kendaraan'));
        $kondisi = implode(',', array_keys(config('orcha.kondisi_pemeriksaan')));

        $data = $request->validate([
            'kondisi' => 'required|array',
            'kondisi.*' => 'in:'.$kondisi,
            'catatan' => 'nullable|string|max:500',
        ]);

        // Bagian yang tidak dikenal ditolak diam-diam, supaya perbandingan
        // kondisi pada serah terima berikutnya tetap memakai daftar yang sama.
        $bersih = array_intersect_key($data['kondisi'], array_flip($bagian));

        $kendaraan->update([
            'kondisi_terkini' => $bersih,
            'kondisi_diperiksa_pada' => now(),
            'kondisi_catatan' => $data['catatan'] ?? null,
        ]);

        $this->catat($request, 'catat kondisi kendaraan', [
            'unit' => $kendaraan->name,
            'catatan' => $data['catatan'] ?? null,
        ]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan->fresh()->loadCount('penyewaan')->load('penyewaan')))->resolve(),
            'pesan' => 'Kondisi unit tersimpan.',
        ]);
    }

    public function show(Car $kendaraan): JsonResponse
    {
        return response()->json([
            'data' => (new KendaraanResource($kendaraan->loadCount('penyewaan')->load('penyewaan')))->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $kendaraan = Car::create($this->siapkan($data, $request));

        $this->catat($request, 'tambah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan))->resolve(),
            'pesan' => 'Kendaraan ditambahkan.',
        ], 201);
    }

    public function update(Car $kendaraan, Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $kendaraan->update($this->siapkan($data, $request, $kendaraan->image));

        $this->catat($request, 'ubah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan->fresh()))->resolve(),
            'pesan' => 'Kendaraan diperbarui.',
        ]);
    }

    public function destroy(Car $kendaraan, Request $request): JsonResponse
    {
        if ($kendaraan->penyewaan()->exists()) {
            return response()->json([
                'pesan' => 'Unit ini sudah pernah disewa, jadi tidak bisa dihapus. Nonaktifkan saja lewat status.',
            ], 422);
        }

        $this->hapusGambar($kendaraan->image);
        $kendaraan->delete();

        $this->catat($request, 'hapus kendaraan', ['nama' => $kendaraan->name]);

        return response()->json(['pesan' => 'Kendaraan dihapus.']);
    }

    /**
     * Penanda dan biaya tiap pos operasional.
     *
     * Nominal hanya disimpan bila posnya memang termasuk. Angka yang tertinggal
     * pada pos yang ditanggung penyewa adalah biaya siluman: ia ikut terpakai
     * begitu penandanya dinyalakan lagi, dan pemiliknya tidak ingat pernah
     * mengisinya.
     */
    /**
     * Aturan sopir untuk perjalanan luar kota.
     *
     * Sama seperti pos biaya: permintaan yang tidak menyebutnya mewarisi aturan
     * dalam kota. Unit yang selalu dengan sopir tidak boleh sampai kehilangan
     * keterangan biaya sopirnya hanya karena pemanggilnya versi lama.
     */
    private function sopirLuarKota(array $data): array
    {
        if (! $this->menyebutAturanLuarKota($data)) {
            return [
                'luar_termasuk_sopir' => (bool) ($data['termasuk_sopir'] ?? false),
                'luar_harga_sopir' => ($data['termasuk_sopir'] ?? false)
                    ? null
                    : (($data['tarif_sopir'] ?? null) ?: null),
            ];
        }

        return [
            'luar_termasuk_sopir' => (bool) ($data['luar_termasuk_sopir'] ?? false),
            'luar_harga_sopir' => ($data['luar_termasuk_sopir'] ?? false)
                ? null
                : (($data['luar_tarif_sopir'] ?? null) ?: null),
        ];
    }

    private function posOperasional(array $data): array
    {
        $hasil = [];
        $sebutLuar = $this->menyebutAturanLuarKota($data);

        foreach (array_keys((array) config('orcha.pos_operasional', [])) as $pos) {
            $termasuk = (bool) ($data["termasuk_{$pos}"] ?? false);

            $hasil["termasuk_{$pos}"] = $termasuk;
            $hasil["biaya_{$pos}"] = $termasuk ? (($data["biaya_{$pos}"] ?? null) ?: null) : null;

            // Permintaan yang TIDAK menyebut aturan luar kota sama sekali
            // mewarisi aturan dalam kotanya, bukan dikosongkan. Mengosongkannya
            // berarti tiap pemanggil lama diam-diam mengubah unitnya menjadi
            // "semua ditanggung penyewa di luar kota" — perubahan harga yang
            // tidak pernah diminta siapa pun.
            if (! $sebutLuar) {
                $hasil["luar_termasuk_{$pos}"] = $hasil["termasuk_{$pos}"];
                $hasil["luar_biaya_{$pos}"] = $hasil["biaya_{$pos}"];

                continue;
            }

            $termasukLuar = (bool) ($data["luar_termasuk_{$pos}"] ?? false);

            $hasil["luar_termasuk_{$pos}"] = $termasukLuar;
            $hasil["luar_biaya_{$pos}"] = $termasukLuar
                ? (($data["luar_biaya_{$pos}"] ?? null) ?: null)
                : null;
        }

        return $hasil;
    }

    /**
     * Apakah permintaan ini memang berbicara soal aturan biaya luar kota.
     *
     * Dibedakan dari "menyebutnya sebagai tidak termasuk". Tidak menyebut sama
     * sekali berarti pemanggilnya belum mengenal pemisahan ini, dan jawaban yang
     * benar untuk itu adalah mengikuti aturan dalam kota — bukan menganggapnya
     * kosong.
     */
    private function menyebutAturanLuarKota(array $data): bool
    {
        foreach (array_keys($data) as $kunci) {
            if (str_starts_with($kunci, 'luar_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unit yang SELALU dengan sopir harus menyatakan biaya sopirnya.
     *
     * Salah satu dari dua: tarifnya sudah termasuk sopir, atau berapa
     * tambahannya. Tanpa keduanya, halaman publik menampilkan unit yang pasti
     * bersopir tanpa keterangan biaya sopirnya sama sekali.
     *
     * Diperiksa DI SINI, bukan sebagai aturan closure pada termasuk_sopir:
     * Laravel melewatkan aturan non-implisit untuk medan yang tidak ada di
     * permintaan, jadi closure di sana tidak pernah berjalan pada kasus yang
     * paling perlu dijaga — ketika medannya memang tidak dikirim sama sekali.
     */
    private function periksaSopir(array $data): void
    {
        $selaluBersopir = ! ($data['lepas_kunci'] ?? true);

        if ($selaluBersopir && ! ($data['termasuk_sopir'] ?? false) && ! ($data['tarif_sopir'] ?? null)) {
            throw ValidationException::withMessages([
                'termasuk_sopir' => 'Unit yang selalu dengan sopir harus menyebut tarif sopirnya, '
                    .'atau ditandai tarifnya sudah termasuk sopir.',
            ]);
        }

        // Aturan yang sama berlaku untuk perjalanan luar kota — tetapi hanya
        // bila permintaannya memang menyebut aturan luar kota. Yang tidak
        // menyebutnya mewarisi aturan dalam kota, yang sudah lolos di atas.
        if ($selaluBersopir && $this->menyebutAturanLuarKota($data)
            && ! ($data['luar_termasuk_sopir'] ?? false) && ! ($data['luar_tarif_sopir'] ?? null)) {
            throw ValidationException::withMessages([
                'luar_termasuk_sopir' => 'Unit yang selalu dengan sopir harus menyebut tarif sopirnya '
                    .'untuk perjalanan luar kota juga.',
            ]);
        }
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => 'required|string|max:255',
            'merek' => 'required|string|max:100',
            'varian' => 'nullable|string|max:60',
            // Batas tahun sengaja longgar ke belakang (Kijang Kapsul 1997 masih
            // disewakan) tetapi hanya satu tahun ke depan: unit tahun 2035 pasti
            // salah ketik, dan salah ketik tahun tidak pernah kelihatan salah.
            'tahun' => 'nullable|integer|min:1980|max:'.(date('Y') + 1),
            'cc' => 'nullable|integer|min:500|max:20000',
            'jenis' => 'required|in:'.implode(',', array_keys(config('orcha.jenis_kendaraan'))),
            'nopol' => ['nullable', 'string', 'max:20', function ($atribut, $nilai, $gagal) {
                if (! NomorPolisi::sah($nilai)) {
                    $gagal('Nomor polisi belum benar. Contoh: AB 4169 TE.');
                }
            }],
            'kapasitas' => 'required|integer|min:1|max:80',
            'lepas_kunci' => 'nullable|boolean',
            'termasuk_sopir' => 'nullable|boolean',
            'transmisi_tersedia' => 'required|array|min:1',
            'transmisi_tersedia.*' => 'in:Manual,Matic',
            'tarif_hari' => 'required|numeric|min:0',
            // Hanya harian: sewa luar kota tidak dijual per jam atau paket 12 jam.
            'tarif_luar_kota' => 'nullable|numeric|min:0',
            'tarif_jam' => 'nullable|numeric|min:0',
            'tarif_12jam' => 'nullable|numeric|min:0',
            'tarif_sopir' => 'nullable|numeric|min:0',
            'termasuk_bbm' => 'nullable|boolean',
            'biaya_bbm' => 'nullable|numeric|min:0|max:100000000',
            'termasuk_tol' => 'nullable|boolean',
            'biaya_tol' => 'nullable|numeric|min:0|max:100000000',
            'termasuk_parkir' => 'nullable|boolean',
            'biaya_parkir' => 'nullable|numeric|min:0|max:100000000',
            // Aturan biaya untuk perjalanan LUAR kota. Terpisah karena memang
            // berbeda di lapangan: unit yang dalam kota diserahkan apa adanya
            // sering ditawarkan sepaket bersama sopir dan BBM untuk jalan jauh.
            'luar_termasuk_sopir' => 'nullable|boolean',
            'luar_tarif_sopir' => 'nullable|numeric|min:0',
            'luar_termasuk_bbm' => 'nullable|boolean',
            'luar_biaya_bbm' => 'nullable|numeric|min:0|max:100000000',
            'luar_termasuk_tol' => 'nullable|boolean',
            'luar_biaya_tol' => 'nullable|numeric|min:0|max:100000000',
            'luar_termasuk_parkir' => 'nullable|boolean',
            'luar_biaya_parkir' => 'nullable|numeric|min:0|max:100000000',
            'tersedia' => 'nullable|boolean',
            'gambar' => 'nullable|image|max:4096',
        ]);

        $this->periksaSopir($data);

        return $data;
    }

    private function siapkan(array $data, Request $request, ?string $gambarLama = null): array
    {
        $transmisi = array_values(array_unique($data['transmisi_tersedia']));

        return [
            'name' => $data['nama'],
            'brand' => $data['merek'],
            'varian' => trim((string) ($data['varian'] ?? '')) ?: null,
            'tahun' => $data['tahun'] ?? null,
            'cc' => $data['cc'] ?? null,
            'type' => $data['jenis'],
            'nopol' => $data['nopol'] ?? null,
            'capacity' => $data['kapasitas'],
            'transmission' => $transmisi[0],
            'transmisi_tersedia' => $transmisi,
            // Bawaannya true supaya mobil biasa tidak perlu disebut satu-satu,
            // dan formulir lemon menandai unit besar sebagai false.
            'lepas_kunci' => (bool) ($data['lepas_kunci'] ?? true),
            'price_per_day' => $data['tarif_hari'],
            'harga_luar_kota' => ($data['tarif_luar_kota'] ?? null) ?: null,
            // ?? mendahului ?: karena tarif opsional yang TIDAK dikirim sama
            // sekali tidak ada kuncinya di data tervalidasi — sebelumnya itu
            // membuat permintaan gagal dengan galat 500, bukan tersimpan tanpa
            // tarif jam. lemon selalu mengirim ketiganya, jadi tidak pernah
            // terlihat sampai ada pemanggil yang mengirim yang wajib saja.
            'harga_per_jam' => ($data['tarif_jam'] ?? null) ?: null,
            'harga_12_jam' => ($data['tarif_12jam'] ?? null) ?: null,
            'termasuk_sopir' => (bool) ($data['termasuk_sopir'] ?? false),
            // Tarif sopir tidak disimpan bila sudah termasuk: angka yang
            // tertinggal di sana ikut ditagihkan begitu penandanya dimatikan.
            'harga_sopir' => ($data['termasuk_sopir'] ?? false)
                ? null
                : (($data['tarif_sopir'] ?? null) ?: null),
            ...$this->sopirLuarKota($data),
            ...$this->posOperasional($data),
            'is_available' => (bool) ($data['tersedia'] ?? true),
            'image' => $this->simpanGambar($request, 'cars', $gambarLama),
        ];
    }
}
