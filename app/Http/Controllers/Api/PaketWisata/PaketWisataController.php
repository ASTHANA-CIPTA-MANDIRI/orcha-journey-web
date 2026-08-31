<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Http\Resources\PaketWisata\PaketWisataResource;
use App\Models\PaketWisata\SaranPaket;
use App\Models\PaketWisata\TravelPackage;
use App\Support\PaketWisata\ItineraryTeks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paket wisata: dibaca dan ditulis dari dashboard lemon.
 */
class PaketWisataController extends ApiController
{
    use MenyimpanGambar;

    public function index(Request $request): JsonResponse
    {
        $daftar = TravelPackage::query()
            ->withCount('pendaftaran')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where('name', 'like', "%{$cari}%"))
            ->ofCategory($request->string('kategori')->toString() ?: null)
            ->latest('id')
            ->paginate($this->perHalaman($request));

        return $this->halaman($daftar, PaketWisataResource::class);
    }

    public function show(TravelPackage $paket): JsonResponse
    {
        return response()->json([
            'data' => (new PaketWisataResource($paket->loadCount('pendaftaran')))->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $paket = TravelPackage::create($this->siapkan($data, $request));

        $this->catatSaran($data);
        $this->catat($request, 'tambah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket))->resolve(),
            'pesan' => 'Paket wisata ditambahkan.',
        ], 201);
    }

    public function update(TravelPackage $paket, Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $paket->update($this->siapkan($data, $request, $paket->foto));

        $this->catatSaran($data);
        $this->catat($request, 'ubah paket wisata', ['nama' => $paket->name]);

        return response()->json([
            'data' => (new PaketWisataResource($paket->fresh()))->resolve(),
            'pesan' => 'Paket wisata diperbarui.',
        ]);
    }

    public function destroy(TravelPackage $paket, Request $request): JsonResponse
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

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|in:'.implode(',', array_keys(config('orcha.kategori_paket'))),
            'status' => 'nullable|in:'.implode(',', array_keys(config('orcha.status_paket'))),
            'tayang_mulai' => 'nullable|date',
            'tayang_sampai' => 'nullable|date|after_or_equal:tayang_mulai',
            'berakhir_otomatis' => 'nullable|boolean',
            'durasi' => 'nullable|string|max:60',
            'tanggal_berangkat' => 'nullable|date',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat',
            'titik_jemput' => 'nullable|string|max:191',
            'minimal_peserta' => 'required|integer|min:1|max:200',

            /*
             | Kuota boleh kosong, dan kosong berarti "belum ditetapkan" —
             | bukan nol. Paket lama seluruhnya belum punya angka ini, dan
             | memperlakukan kosong sebagai nol akan menutup pendaftaran semua
             | paket yang sedang tayang.
             |
             | Batas bawahnya minimal_peserta: kuota yang lebih kecil daripada
             | jumlah minimum keberangkatan berarti tripnya tidak akan pernah
             | bisa berangkat, dan itu pasti salah ketik.
             */
            'kuota' => 'nullable|integer|min:1|max:500|gte:minimal_peserta',
            'catatan_promo' => 'nullable|string|max:191',
            'harga' => 'required|numeric|min:0',
            'harga_asli' => 'nullable|numeric|min:0',
            // Modal boleh kosong: sebagian paket masih dihitung manual saat
            // dibuat. Kosong berarti belum dihitung, dan laporan keuntungan
            // menyebutnya begitu alih-alih menganggapnya nol.
            'harga_modal' => 'nullable|numeric|min:0',
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

    private function siapkan(array $data, Request $request, ?string $fotoLama = null): array
    {
        return [
            'name' => $data['nama'],
            'category' => $data['kategori'],
            'status' => $data['status'] ?? 'terbit',
            'tayang_mulai' => $data['tayang_mulai'] ?? null,
            'tayang_sampai' => $data['tayang_sampai'] ?? null,
            'berakhir_otomatis' => (bool) ($data['berakhir_otomatis'] ?? true),
            'duration' => $data['durasi'] ?? null,
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'tanggal_pulang' => $data['tanggal_pulang'] ?? null,
            'titik_jemput' => $data['titik_jemput'] ?? null,
            'minimal_peserta' => $data['minimal_peserta'],
            'kuota' => ($data['kuota'] ?? null) === null ? null : (int) $data['kuota'],
            'catatan_promo' => $data['catatan_promo'] ?? null,
            'price' => $data['harga'],
            'original_price' => $data['harga_asli'] ?? $data['harga'],
            'harga_modal' => ($data['harga_modal'] ?? null) === null ? null : (int) $data['harga_modal'],
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
    private function catatSaran(array $data): void
    {
        SaranPaket::catat('destinasi', $data['destinasi'] ?? []);
        SaranPaket::catat('fasilitas', $data['fasilitas'] ?? []);
    }

    /* ------------------------- SARAN ISIAN PAKET ------------------------- */

    public function saran(): JsonResponse
    {
        return response()->json([
            'data' => collect(SaranPaket::JENIS)
                ->mapWithKeys(fn ($jenis) => [
                    $jenis => SaranPaket::jenis($jenis)->orderBy('nama')->get(['id', 'nama'])->all(),
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
}
