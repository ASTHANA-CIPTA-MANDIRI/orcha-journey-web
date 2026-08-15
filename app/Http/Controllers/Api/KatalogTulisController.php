<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\KendaraanResource;
use App\Http\Resources\PaketWisataResource;
use App\Models\Car;
use App\Models\DestinationPopuler;
use App\Models\Partner;
use App\Models\SaranPaket;
use App\Models\Testimoni;
use App\Models\TravelPackage;
use App\Support\ItineraryTeks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pembuatan dan pengubahan data etalase Orcha dari dashboard lemon.
 *
 * Gambar dikirim sebagai multipart pada permintaan yang sama; berkasnya
 * disimpan di server Orcha (disk publik), bukan di lemon, supaya website Orcha
 * tetap bisa menampilkannya tanpa bergantung pada aplikasi tetangga.
 */
class KatalogTulisController extends ApiController
{
    /* ---------------------------- PAKET WISATA ---------------------------- */

    public function simpanPaket(Request $request): JsonResponse
    {
        $data = $this->validasiPaket($request);

        $paket = TravelPackage::create($this->siapkanPaket($data, $request));

        $this->catatSaranPaket($data);
        $this->catat($request, 'tambah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket))->resolve(),
            'pesan' => 'Paket wisata ditambahkan.',
        ], 201);
    }

    public function perbaruiPaket(TravelPackage $paket, Request $request): JsonResponse
    {
        $data = $this->validasiPaket($request);

        $paket->update($this->siapkanPaket($data, $request, $paket->foto));

        $this->catatSaranPaket($data);
        $this->catat($request, 'ubah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket->fresh()))->resolve(),
            'pesan' => 'Paket wisata diperbarui.',
        ]);
    }

    public function hapusPaket(TravelPackage $paket, Request $request): JsonResponse
    {
        // Paket yang sudah punya pendaftar tidak boleh hilang — datanya masih
        // dipakai untuk melayani peserta yang sudah membayar.
        if ($paket->pendaftaran()->exists()) {
            return response()->json([
                'pesan' => 'Paket ini sudah punya pendaftar, jadi tidak bisa dihapus. Ubah saja isinya.',
            ], 422);
        }

        $this->hapusGambar($paket->foto);
        $paket->delete();

        $this->catat($request, 'hapus paket wisata', ['nama' => $paket->name]);

        return response()->json(['pesan' => 'Paket wisata dihapus.']);
    }

    private function validasiPaket(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:'.implode(',', array_keys(config('orcha.kategori_paket'))),
            'durasi' => 'nullable|string|max:60',
            'tanggal_berangkat' => 'nullable|date',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat',
            'titik_jemput' => 'nullable|string|max:191',
            'minimal_peserta' => 'required|integer|min:1|max:200',
            'catatan_promo' => 'nullable|string|max:191',
            'harga' => 'required|numeric|min:0',
            'harga_asli' => 'nullable|numeric|min:0',
            'diskon_persen' => 'nullable|numeric|min:0|max:100',
            'pilihan_terbaik' => 'nullable|boolean',
            'destinasi' => 'nullable|array',
            'destinasi.*' => 'string|max:191',
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => 'string|max:191',
            'itinerary_teks' => 'nullable|string|max:4000',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    private function siapkanPaket(array $data, Request $request, ?string $fotoLama = null): array
    {
        return [
            'name' => $data['nama'],
            'category' => $data['kategori'],
            'duration' => $data['durasi'] ?? null,
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'tanggal_pulang' => $data['tanggal_pulang'] ?? null,
            'titik_jemput' => $data['titik_jemput'] ?? null,
            'minimal_peserta' => $data['minimal_peserta'],
            'catatan_promo' => $data['catatan_promo'] ?? null,
            'price' => $data['harga'],
            'original_price' => $data['harga_asli'] ?? $data['harga'],
            'discount_percentage' => $data['diskon_persen'] ?? 0,
            'is_best_choice' => (bool) ($data['pilihan_terbaik'] ?? false),
            'destination_list' => $data['destinasi'] ?? null,
            'fasilitas' => $data['fasilitas'] ?? null,
            'itinerary' => ItineraryTeks::keArray($data['itinerary_teks'] ?? '') ?: null,
            'foto' => $this->simpanGambar($request, 'paket', $fotoLama),
        ];
    }

    /**
     * Isian baru ikut masuk daftar saran, jadi paket berikutnya dengan
     * destinasi atau fasilitas yang sama tidak perlu diketik ulang.
     */
    private function catatSaranPaket(array $data): void
    {
        SaranPaket::catat('destinasi', $data['destinasi'] ?? []);
        SaranPaket::catat('fasilitas', $data['fasilitas'] ?? []);
    }

    /* ------------------------------ ARMADA ------------------------------ */

    public function simpanKendaraan(Request $request): JsonResponse
    {
        $data = $this->validasiKendaraan($request);

        $kendaraan = Car::create($this->siapkanKendaraan($data, $request));

        $this->catat($request, 'tambah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan))->resolve(),
            'pesan' => 'Kendaraan ditambahkan.',
        ], 201);
    }

    public function perbaruiKendaraan(Car $kendaraan, Request $request): JsonResponse
    {
        $data = $this->validasiKendaraan($request);

        $kendaraan->update($this->siapkanKendaraan($data, $request, $kendaraan->image));

        $this->catat($request, 'ubah kendaraan', ['nama' => $kendaraan->name]);

        return response()->json([
            'data' => (new KendaraanResource($kendaraan->fresh()))->resolve(),
            'pesan' => 'Kendaraan diperbarui.',
        ]);
    }

    public function hapusKendaraan(Car $kendaraan, Request $request): JsonResponse
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

    private function validasiKendaraan(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:255',
            'merek' => 'required|string|max:100',
            'jenis' => 'required|in:'.implode(',', array_keys(config('orcha.jenis_kendaraan'))),
            'nopol' => 'nullable|string|max:20',
            'kapasitas' => 'required|integer|min:1|max:80',
            'transmisi_tersedia' => 'required|array|min:1',
            'transmisi_tersedia.*' => 'in:Manual,Matic',
            'tarif_hari' => 'required|numeric|min:0',
            'tarif_jam' => 'nullable|numeric|min:0',
            'tarif_12jam' => 'nullable|numeric|min:0',
            'tarif_sopir' => 'nullable|numeric|min:0',
            'tersedia' => 'nullable|boolean',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    private function siapkanKendaraan(array $data, Request $request, ?string $gambarLama = null): array
    {
        $transmisi = array_values(array_unique($data['transmisi_tersedia']));

        return [
            'name' => $data['nama'],
            'brand' => $data['merek'],
            'type' => $data['jenis'],
            'nopol' => $data['nopol'] ?? null,
            'capacity' => $data['kapasitas'],
            'transmission' => $transmisi[0],
            'transmisi_tersedia' => $transmisi,
            'price_per_day' => $data['tarif_hari'],
            'harga_per_jam' => $data['tarif_jam'] ?: null,
            'harga_12_jam' => $data['tarif_12jam'] ?: null,
            'harga_sopir' => $data['tarif_sopir'] ?: null,
            'is_available' => (bool) ($data['tersedia'] ?? true),
            'image' => $this->simpanGambar($request, 'cars', $gambarLama),
        ];
    }

    /* ----------------------------- DESTINASI ----------------------------- */

    public function simpanDestinasi(Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi = DestinationPopuler::create([
            'destination_name' => $data['nama'],
            'wilayah' => $data['wilayah'],
            'provinsi' => $data['provinsi'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
            'total_visitor' => $data['total_pengunjung'] ?? 0,
            'main_photo' => $this->simpanGambar($request, 'destinasi'),
        ]);

        $this->catat($request, 'tambah destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi ditambahkan.'], 201);
    }

    public function perbaruiDestinasi(DestinationPopuler $destinasi, Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi->update([
            'destination_name' => $data['nama'],
            'wilayah' => $data['wilayah'],
            'provinsi' => $data['provinsi'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
            'total_visitor' => $data['total_pengunjung'] ?? 0,
            'main_photo' => $this->simpanGambar($request, 'destinasi', $destinasi->main_photo),
        ]);

        $this->catat($request, 'ubah destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi diperbarui.']);
    }

    public function hapusDestinasi(DestinationPopuler $destinasi, Request $request): JsonResponse
    {
        $this->hapusGambar($destinasi->main_photo);
        $destinasi->delete();

        $this->catat($request, 'hapus destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi dihapus.']);
    }

    private function validasiDestinasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'wilayah' => 'required|in:'.implode(',', array_keys(config('orcha.wilayah'))),
            'provinsi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string|max:1000',
            'total_pengunjung' => 'nullable|integer|min:0',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    /* ----------------------------- TESTIMONI ----------------------------- */

    public function simpanTestimoni(Request $request): JsonResponse
    {
        $data = $this->validasiTestimoni($request);

        Testimoni::create([
            'customer_name' => $data['nama'],
            'rating' => $data['rating'],
            'testimonial' => $data['isi'],
            'avatar' => $this->simpanGambar($request, 'testimoni'),
        ]);

        $this->catat($request, 'tambah testimoni', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Testimoni ditambahkan.'], 201);
    }

    public function perbaruiTestimoni(Testimoni $testimoni, Request $request): JsonResponse
    {
        $data = $this->validasiTestimoni($request);

        $testimoni->update([
            'customer_name' => $data['nama'],
            'rating' => $data['rating'],
            'testimonial' => $data['isi'],
            'avatar' => $this->simpanGambar($request, 'testimoni', $testimoni->avatar),
        ]);

        $this->catat($request, 'ubah testimoni', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Testimoni diperbarui.']);
    }

    public function hapusTestimoni(Testimoni $testimoni, Request $request): JsonResponse
    {
        $this->hapusGambar($testimoni->avatar);
        $testimoni->delete();

        $this->catat($request, 'hapus testimoni', ['id' => $testimoni->id]);

        return response()->json(['pesan' => 'Testimoni dihapus.']);
    }

    private function validasiTestimoni(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'rating' => 'required|integer|min:1|max:5',
            'isi' => 'required|string|max:1000',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }

    /* ------------------------------ PARTNER ------------------------------ */

    public function simpanPartner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:191',
            'gambar' => 'nullable|image|max:4096',
        ]);

        Partner::create([
            'partner_name' => $data['nama'],
            'foto' => $this->simpanGambar($request, 'partner'),
        ]);

        $this->catat($request, 'tambah partner', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Partner ditambahkan.'], 201);
    }

    public function perbaruiPartner(Partner $partner, Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:191',
            'gambar' => 'nullable|image|max:4096',
        ]);

        $partner->update([
            'partner_name' => $data['nama'],
            'foto' => $this->simpanGambar($request, 'partner', $partner->foto),
        ]);

        $this->catat($request, 'ubah partner', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Partner diperbarui.']);
    }

    public function hapusPartner(Partner $partner, Request $request): JsonResponse
    {
        $this->hapusGambar($partner->foto);
        $partner->delete();

        $this->catat($request, 'hapus partner', ['id' => $partner->id]);

        return response()->json(['pesan' => 'Partner dihapus.']);
    }

    /* ------------------------- SARAN ISIAN PAKET ------------------------- */

    public function saran(): JsonResponse
    {
        return response()->json([
            'data' => collect(SaranPaket::JENIS)
                ->mapWithKeys(fn ($jenis) => [
                    $jenis => SaranPaket::jenis($jenis)
                        ->orderBy('nama')
                        ->get(['id', 'nama'])
                        ->all(),
                ])
                ->all(),
        ]);
    }

    public function simpanSaran(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => 'required|in:'.implode(',', SaranPaket::JENIS),
            'nama' => 'required|string|max:191',
        ]);

        $saran = SaranPaket::firstOrCreate([
            'jenis' => $data['jenis'],
            'nama' => trim($data['nama']),
        ]);

        return response()->json([
            'data' => ['id' => $saran->id, 'nama' => $saran->nama],
            'pesan' => 'Ditambahkan ke daftar pilihan.',
        ], $saran->wasRecentlyCreated ? 201 : 200);
    }

    public function hapusSaran(SaranPaket $saran, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus saran paket', ['jenis' => $saran->jenis, 'nama' => $saran->nama]);

        $saran->delete();

        // Sengaja tidak menyentuh paket yang sudah tersimpan: yang hilang hanya
        // pilihan cepatnya, bukan isi paketnya.
        return response()->json(['pesan' => 'Dihapus dari daftar pilihan.']);
    }

    /* ------------------------------ GAMBAR ------------------------------ */

    /**
     * Simpan gambar yang ikut di permintaan. Bila tidak ada berkas baru,
     * kembalikan yang lama — supaya menyunting data tanpa mengganti gambar
     * tidak menghapus gambarnya.
     */
    private function simpanGambar(Request $request, string $folder, ?string $lama = null): ?string
    {
        if (! $request->hasFile('gambar')) {
            return $lama;
        }

        $this->hapusGambar($lama);

        return '/storage/'.$request->file('gambar')->store($folder, 'public');
    }

    private function hapusGambar(?string $jalur): void
    {
        if (blank($jalur) || ! str_starts_with($jalur, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $jalur));
    }
}
