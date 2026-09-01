<?php

namespace App\Http\Controllers\Api\PaketWisata;

use App\Http\Controllers\Controller;
use App\Models\JejakAudit;
use App\Models\PaketWisata\PromoRombonganTingkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tingkat promo rombongan, dikelola dari panel admin.
 *
 * Angka-angka ini yang paling sering diutak-atik — mengikuti musim liburan,
 * sisa kursi, dan tawaran pesaing. Selama masih di berkas config, tiap
 * perubahan kecil berarti menunggu ada yang menyunting kode.
 */
class PromoRombonganController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PromoRombonganTingkat::orderBy('min_peserta')->get()->map(fn ($t) => [
                'id' => $t->id,
                'min_peserta' => $t->min_peserta,
                'potongan_persen' => $t->potongan_persen,
                'gratis_orang' => $t->gratis_orang,
                'label' => $t->label,
                'ajakan' => $t->ajakan,
                'aktif' => $t->aktif,
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tingkat = PromoRombonganTingkat::create($this->periksa($request));

        $this->catat($request, 'tambah tingkat promo', $tingkat);

        return response()->json(['data' => $tingkat], 201);
    }

    public function update(PromoRombonganTingkat $tingkat, Request $request): JsonResponse
    {
        $tingkat->update($this->periksa($request, $tingkat->id));

        $this->catat($request, 'ubah tingkat promo', $tingkat);

        return response()->json(['data' => $tingkat->fresh()]);
    }

    public function destroy(PromoRombonganTingkat $tingkat, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus tingkat promo', $tingkat);

        $tingkat->delete();

        return response()->json(['pesan' => 'Tingkat promo dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function periksa(Request $request, ?int $kecuali = null): array
    {
        $data = $request->validate([
            /*
             | Minimal peserta unik: dua tingkat dengan syarat yang sama membuat
             | "tingkat terbaik" jadi tidak menentu — yang menang tergantung
             | urutan baris, dan itu berubah sendiri saat salah satunya
             | disunting.
             */
            'min_peserta' => ['required', 'integer', 'min:2', 'max:100',
                Rule::unique('tbl_promo_rombongan')->ignore($kecuali)],
            'potongan_persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'gratis_orang' => ['nullable', 'integer', 'min:0', 'max:20'],
            'label' => ['required', 'string', 'max:120'],
            'ajakan' => ['nullable', 'string', 'max:160'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $data['potongan_persen'] = (int) ($data['potongan_persen'] ?? 0);
        $data['gratis_orang'] = (int) ($data['gratis_orang'] ?? 0);

        /*
         | Tingkat tanpa keuntungan apa pun ditolak.
         |
         | Ia lolos seluruh aturan di atas, tersimpan rapi, dan tampil di daftar
         | — tetapi tidak mengubah harga sepeser pun. Yang membuatnya berbahaya:
         | ia MENGGESER tingkat di bawahnya. Rombongan 10 orang yang seharusnya
         | dapat gratis 1 akan berhenti di tingkat kosong ini kalau
         | min_peserta-nya lebih tinggi, dan tidak ada yang tahu sampai ada
         | pelanggan menghitung sendiri.
         */
        if ($data['potongan_persen'] === 0 && $data['gratis_orang'] === 0) {
            abort(422, 'Tingkat ini tidak memberi potongan maupun orang gratis, jadi tidak mengubah harga apa pun.');
        }

        return $data;
    }

    private function catat(Request $request, string $aksi, PromoRombonganTingkat $tingkat): void
    {
        JejakAudit::catat(
            $request,
            $aksi,
            $aksi.' — minimal '.$tingkat->min_peserta.' orang: '
                .($tingkat->gratis_orang > 0 ? 'gratis '.$tingkat->gratis_orang.' orang' : '')
                .($tingkat->potongan_persen > 0 ? ' potongan '.$tingkat->potongan_persen.'%' : ''),
        );
    }
}
