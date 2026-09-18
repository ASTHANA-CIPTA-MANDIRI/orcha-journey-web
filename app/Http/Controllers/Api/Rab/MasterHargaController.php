<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\MasterHarga;
use App\Support\RincianBiaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master harga RAB — dirawat admin.
 *
 * Mengubah harga di sini TIDAK mengubah RAB yang sudah dibuat: baris biaya RAB
 * membekukan harganya sendiri. RAB lama hanya diberi tanda bahwa harga
 * masternya sudah bergeser.
 */
class MasterHargaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = MasterHarga::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('destinasi', 'like', "%{$cari}%")
                    ->orWhere('daerah', 'like', "%{$cari}%")
            ))
            ->when($request->string('kategori')->toString(), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $p === '-'
                ? $q->whereNull('provinsi')
                : $q->where('provinsi', $p))
            ->orderBy('provinsi')->orderBy('kategori')->orderBy('nama')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => $daftar->getCollection()->map(fn (MasterHarga $m) => $this->baris($m))->all(),
            ['kosakata' => $this->kosakata()],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $m = MasterHarga::create($this->periksa($request));

        $this->catat($request, 'tambah master harga', ['nama' => $m->nama, 'harga' => $m->harga]);

        return response()->json(['data' => $this->baris($m), 'pesan' => 'Harga ditambahkan.'], 201);
    }

    public function update(MasterHarga $master, Request $request): JsonResponse
    {
        $lama = $master->harga;
        $master->update($this->periksa($request));

        $this->catat($request, 'ubah master harga', [
            'nama' => $master->nama, 'dari' => $lama, 'ke' => $master->harga,
        ]);

        return response()->json(['data' => $this->baris($master->fresh()), 'pesan' => 'Harga diperbarui.']);
    }

    public function destroy(MasterHarga $master, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus master harga', ['nama' => $master->nama]);

        // Baris biaya RAB yang merujuknya tetap utuh — master_harga_id-nya
        // saja yang jadi kosong (nullOnDelete). Penawaran lama tidak berubah.
        $master->delete();

        return response()->json(['pesan' => 'Harga dihapus. RAB yang sudah memakainya tidak berubah.']);
    }

    /** @return array<string, mixed> */
    private function periksa(Request $request): array
    {
        $data = $request->validate([
            'kategori' => ['required', Rule::in(array_keys(config('orcha.rab.kategori')))],
            'nama' => ['required', 'string', 'min:2', 'max:150'],
            'provinsi' => ['nullable', 'string', 'max:80'],
            'daerah' => ['nullable', 'string', 'max:80'],
            'destinasi' => ['nullable', 'string', 'max:150'],
            'satuan' => ['required', Rule::in(array_keys(config('orcha.rab.satuan')))],
            'harga' => ['required', 'integer', 'min:0', 'max:1000000000'],
            /*
             | Kapasitas WAJIB untuk satuan yang dihitung per unit.
             |
             | Tanpanya, 40 peserta dihitung naik SATU bus — dan penawarannya
             | kekurangan dua bus penuh. Kesalahan yang tidak berbunyi apa pun
             | sampai rombongannya berdiri di pinggir jalan.
             */
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:1000',
                Rule::requiredIf(in_array($request->input('satuan'), ['unit_hari', 'kamar_malam'], true))],
            'otomatis' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'kapasitas.required' => 'Kapasitas wajib diisi untuk harga per unit atau per kamar — tanpanya jumlah unit tidak bisa dihitung.',
        ], [
            'kapasitas' => 'kapasitas',
        ]);

        $data['otomatis'] = (bool) ($data['otomatis'] ?? false);
        $data['aktif'] = array_key_exists('aktif', $data) ? (bool) $data['aktif'] : true;

        return $data;
    }

    /** @return array<string, mixed> */
    private function baris(MasterHarga $m): array
    {
        return [
            'id' => $m->id,
            'kategori' => $m->kategori,
            'kategori_label' => $m->kategori_label,
            'nama' => $m->nama,
            'provinsi' => $m->provinsi,
            'daerah' => $m->daerah,
            'destinasi' => $m->destinasi,
            'satuan' => $m->satuan,
            'satuan_label' => $m->satuan_label,
            'harga' => $m->harga,
            'harga_teks' => RincianBiaya::rupiah($m->harga),
            'kapasitas' => $m->kapasitas,
            'otomatis' => $m->otomatis,
            'aktif' => $m->aktif,
            'catatan' => $m->catatan,
            'diubah_pada' => $m->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function kosakataRab(): array
    {
        return [
            'kategori' => config('orcha.rab.kategori'),
            'satuan' => collect(config('orcha.rab.satuan'))->map(fn ($s) => $s['label'])->all(),
            'satuan_berkapasitas' => ['unit_hari', 'kamar_malam'],
            'margin_jenis' => config('orcha.rab.margin_jenis'),
            'status' => config('orcha.rab.status'),
        ];
    }

    private function kosakata(): array
    {
        return self::kosakataRab();
    }
}
