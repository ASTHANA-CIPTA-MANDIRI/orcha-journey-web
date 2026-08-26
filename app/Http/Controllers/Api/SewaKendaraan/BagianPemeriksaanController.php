<?php

namespace App\Http\Controllers\Api\SewaKendaraan;

use App\Http\Controllers\Api\ApiController;
use App\Models\SewaKendaraan\BagianPemeriksaan;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan bagian kendaraan yang diperiksa saat serah terima.
 *
 * Daftarnya dulu dipatok di config; pemilik armada yang mulai menyewakan bus
 * tidak punya cara menambahkan "pintu bagasi" sendiri tanpa menunggu deploy.
 */
class BagianPemeriksaanController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = BagianPemeriksaan::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($sub) => $sub->where('label', 'like', "%{$cari}%")
                    ->orWhere('kunci', 'like', "%{$cari}%")
            ))
            /*
             | Terbaru lebih dulu — HANYA di daftar pengelolaan ini.
             |
             | Yang dicari admin saat membuka halaman ini adalah bagian yang
             | baru saja ia tambahkan atau ubah, bukan yang sudah setahun
             | tenang di bawah.
             |
             | Ceklis serah terima TIDAK ikut dibalik: di sana urutannya
             | mengikuti jalan memeriksa unit — bodi depan, belakang, kanan,
             | kiri, lalu kaca dan lampu — dan membaliknya membuat petugas
             | melompat-lompat mengelilingi mobil. Urutan itu tetap dibaca
             | lewat Pemeriksaan::untuk(), yang memakai kolom `urutan`.
             */
            ->orderByDesc('id')
            ->get();

        // Penyaringan jenis dikerjakan di PHP, sebangun dengan Pemeriksaan:
        // perilaku penyaringan JSON berbeda antara MySQL dan SQLite yang
        // dipakai pengujian, dan daftarnya belasan baris.
        if ($jenis = $request->string('jenis')->toString()) {
            $daftar = $daftar->filter(fn (BagianPemeriksaan $b) => in_array($jenis, $b->jenis ?? [], true))->values();
        }

        $perHalaman = $this->perHalaman($request);
        $halaman = max(1, (int) $request->integer('page', 1));
        $total = $daftar->count();

        return response()->json([
            'data' => $daftar->forPage($halaman, $perHalaman)
                ->map(fn (BagianPemeriksaan $b) => $this->baris($b))->values()->all(),
            'meta' => [
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => $total,
                'halaman_terakhir' => max(1, (int) ceil($total / $perHalaman)),
                'jenis_kendaraan' => config('orcha.jenis_kendaraan'),
                'kondisi_pemeriksaan' => config('orcha.kondisi_pemeriksaan'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->periksa($request);

        $bagian = BagianPemeriksaan::create($data);

        $this->catat($request, 'tambah bagian pemeriksaan', ['kunci' => $bagian->kunci]);

        return response()->json([
            'data' => $this->baris($bagian),
            'pesan' => 'Bagian pemeriksaan ditambahkan.',
        ], 201);
    }

    public function update(BagianPemeriksaan $bagian, Request $request): JsonResponse
    {
        $data = $this->periksa($request, $bagian);

        // Kuncinya TIDAK pernah ikut berubah. Ribuan baris kondisi unit dan
        // lembar serah terima menunjuk ke sana; labelnya boleh diperbaiki
        // ejaannya kapan saja, kuncinya tidak.
        unset($data['kunci']);

        $bagian->update($data);

        $this->catat($request, 'ubah bagian pemeriksaan', ['kunci' => $bagian->kunci]);

        return response()->json([
            'data' => $this->baris($bagian->fresh()),
            'pesan' => 'Bagian pemeriksaan disimpan.',
        ]);
    }

    /**
     * Dihapus hanya bila benar-benar belum pernah dipakai.
     *
     * Yang sudah menempel di lembar serah terima tidak dihapus melainkan
     * dinonaktifkan — menghapus barisnya membuat namanya hilang dari lembar
     * itu dan tersisa kunci mentahnya, tepat pada dokumen yang dipakai
     * berbantahan dengan penyewa.
     */
    public function destroy(BagianPemeriksaan $bagian, Request $request): JsonResponse
    {
        if ($this->pernahDipakai($bagian->kunci)) {
            return response()->json([
                'pesan' => 'Bagian ini sudah tercatat di lembar serah terima, jadi tidak bisa dihapus. '
                    .'Nonaktifkan saja — namanya tetap terbaca di lembar lama, tetapi berhenti muncul di pemeriksaan baru.',
            ], 422);
        }

        $bagian->delete();

        $this->catat($request, 'hapus bagian pemeriksaan', ['kunci' => $bagian->kunci]);

        return response()->json(['pesan' => 'Bagian pemeriksaan dihapus.']);
    }

    /** @return array<string, mixed> */
    private function periksa(Request $request, ?BagianPemeriksaan $bagian = null): array
    {
        $jenis = array_keys(config('orcha.jenis_kendaraan'));

        return $request->validate([
            'kunci' => [
                'nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('bagian_pemeriksaan', 'kunci')->ignore($bagian?->id),
            ],
            'label' => 'required|string|max:120',

            // Minimal satu jenis: bagian yang tidak berlaku untuk jenis mana
            // pun tidak akan pernah muncul di formulir siapa pun, dan admin
            // yang menyimpannya akan mengira ia sudah terpasang.
            'jenis' => 'required|array|min:1',
            'jenis.*' => 'in:'.implode(',', $jenis),

            /*
             | Tarif WAJIB, walau boleh nol.
             |
             | Bagian tanpa tarif membuat usulan denda kerusakan diam-diam
             | melewatinya: perhitungannya tetap jalan, angkanya kurang, dan
             | tidak ada yang memberi tahu. Nol yang ditulis sadar berbeda dari
             | nol yang muncul karena lupa.
             */
            'biaya_lecet' => 'required|integer|min:0|max:999999999',
            'biaya_rusak' => 'required|integer|min:0|max:999999999',
            'biaya_hilang' => 'required|integer|min:0|max:999999999',

            'urutan' => 'nullable|integer|min:0|max:9999',
            'aktif' => 'boolean',
        ]);
    }

    /** @var array<string, true>|null */
    private ?array $terpakai = null;

    /**
     * Kunci yang sudah tercatat di lembar serah terima mana pun.
     *
     * Dihitung SEKALI per permintaan, bukan sekali per baris. Bentuk semula
     * menyapu seluruh tabel penyewaan untuk tiap bagian yang ditampilkan —
     * dua belas bagian berarti dua belas kali sapuan penuh, dan itu tumbuh
     * mengikuti banyaknya penyewaan, bukan banyaknya bagian.
     *
     * @return array<string, true>
     */
    private function kunciTerpakai(): array
    {
        if ($this->terpakai !== null) {
            return $this->terpakai;
        }

        $this->terpakai = [];

        foreach (PenyewaanKendaraan::query()->select(['kondisi_awal', 'kondisi_akhir'])->cursor() as $sewa) {
            // array_merge, BUKAN "+": pada array berindeks angka, "+" menahan
            // elemen kiri untuk indeks yang bertabrakan — kunci pertama milik
            // kondisi_akhir hilang diam-diam selama kondisi_awal lebih panjang.
            $kunci = array_merge(
                array_keys($sewa->kondisi_awal ?? []),
                array_keys($sewa->kondisi_akhir ?? []),
            );

            foreach ($kunci as $satu) {
                $this->terpakai[$satu] = true;
            }
        }

        return $this->terpakai;
    }

    private function pernahDipakai(string $kunci): bool
    {
        return isset($this->kunciTerpakai()[$kunci]);
    }

    /** @return array<string, mixed> */
    private function baris(BagianPemeriksaan $b): array
    {
        return [
            'id' => $b->id,
            'kunci' => $b->kunci,
            'label' => $b->label,
            'jenis' => $b->jenis ?? [],
            'jenis_label' => collect($b->jenis ?? [])
                ->map(fn ($j) => config('orcha.jenis_kendaraan')[$j] ?? $j)->implode(', '),
            'biaya_lecet' => $b->biaya_lecet,
            'biaya_rusak' => $b->biaya_rusak,
            'biaya_hilang' => $b->biaya_hilang,
            'urutan' => $b->urutan,
            'aktif' => $b->aktif,
            'pernah_dipakai' => $this->pernahDipakai($b->kunci),
        ];
    }
}
