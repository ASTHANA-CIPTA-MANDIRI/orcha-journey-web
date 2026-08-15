<?php

namespace App\Http\Controllers\Api\Etalase;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\MenyimpanGambar;
use App\Models\Etalase\DestinationPopuler;
use App\Models\Etalase\Partner;
use App\Models\Etalase\Testimoni;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Etalase pelengkap website: destinasi populer, testimoni, dan partner.
 *
 * Ketiganya isiannya sedikit dan bentuknya mirip, jadi ditangani satu berkas
 * — memecahnya jadi tiga controller hanya menyalin kerangka yang sama.
 */
class EtalaseController extends ApiController
{
    use MenyimpanGambar;

    /* ----------------------------- DESTINASI ----------------------------- */

    public function destinasi(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DestinationPopuler::query()
                ->when($request->string('wilayah')->toString(), fn ($q, $wilayah) => $q->where('wilayah', $wilayah))
                ->orderBy('destination_name')
                ->get()
                ->map(fn ($destinasi) => [
                    'id' => $destinasi->id,
                    'nama' => $destinasi->destination_name,
                    'provinsi' => $destinasi->provinsi,
                    'wilayah' => $destinasi->wilayah,
                    'wilayah_label' => $destinasi->wilayah_label,
                    'deskripsi' => $destinasi->deskripsi,
                    'total_pengunjung' => $destinasi->total_visitor,
                    'foto' => $destinasi->main_photo,
                ])
                ->all(),
        ]);
    }

    public function simpanDestinasi(Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi = DestinationPopuler::create($this->siapkanDestinasi($data, $request));

        $this->catat($request, 'tambah destinasi', ['nama' => $destinasi->destination_name]);

        return response()->json(['pesan' => 'Destinasi ditambahkan.'], 201);
    }

    public function perbaruiDestinasi(DestinationPopuler $destinasi, Request $request): JsonResponse
    {
        $data = $this->validasiDestinasi($request);

        $destinasi->update($this->siapkanDestinasi($data, $request, $destinasi->main_photo));

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

    private function siapkanDestinasi(array $data, Request $request, ?string $fotoLama = null): array
    {
        return [
            'destination_name' => $data['nama'],
            'wilayah' => $data['wilayah'],
            'provinsi' => $data['provinsi'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
            'total_visitor' => $data['total_pengunjung'] ?? 0,
            'main_photo' => $this->simpanGambar($request, 'destinasi', $fotoLama),
        ];
    }

    /* ----------------------------- TESTIMONI ----------------------------- */

    public function testimoni(): JsonResponse
    {
        return response()->json([
            'data' => Testimoni::latest('id')->get()->map(fn ($testimoni) => [
                'id' => $testimoni->id,
                'nama' => $testimoni->customer_name,
                'isi' => $testimoni->testimonial,
                'rating' => $testimoni->rating,
                'foto' => $testimoni->avatar,
            ])->all(),
        ]);
    }

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

    public function partner(): JsonResponse
    {
        return response()->json([
            'data' => Partner::orderBy('partner_name')->get()->map(fn ($partner) => [
                'id' => $partner->id,
                'nama' => $partner->partner_name,
                'logo' => $partner->foto,
            ])->all(),
        ]);
    }

    public function simpanPartner(Request $request): JsonResponse
    {
        $data = $this->validasiPartner($request);

        Partner::create([
            'partner_name' => $data['nama'],
            'foto' => $this->simpanGambar($request, 'partner'),
        ]);

        $this->catat($request, 'tambah partner', ['nama' => $data['nama']]);

        return response()->json(['pesan' => 'Partner ditambahkan.'], 201);
    }

    public function perbaruiPartner(Partner $partner, Request $request): JsonResponse
    {
        $data = $this->validasiPartner($request);

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

    private function validasiPartner(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:191',
            'gambar' => 'nullable|image|max:4096',
        ]);
    }
}
