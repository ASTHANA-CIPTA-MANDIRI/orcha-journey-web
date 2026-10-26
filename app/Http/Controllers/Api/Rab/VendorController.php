<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor: dari siapa sebuah harga dibeli, dan ke nomor mana meneleponnya.
 *
 * Satu vendor memasok beberapa harga sekaligus, jadi nomornya hidup di satu
 * tempat. Nomor yang berganti cukup diubah di sini dan seluruh harga yang
 * memakainya — termasuk yang sudah terpakai di RAB lama — ikut menunjuk nomor
 * yang benar.
 */
class VendorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $daftar = Vendor::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kontak', 'like', "%{$cari}%")
                    ->orWhere('jenis', 'like', "%{$cari}%")
            ))
            ->when($request->boolean('hanya_aktif'), fn ($q) => $q->aktif())
            ->when($request->string('jenis')->toString(), fn ($q, $j) => $j === '-'
                ? $q->whereNull('jenis')
                : $q->where('jenis', $j))
            ->when($request->string('status')->toString(), fn ($q, $st) => $q->where('aktif', $st === 'aktif'))
            ->withCount('harga')
            ->orderBy('nama')
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => $daftar->getCollection()->map(fn (Vendor $v) => $this->baris($v))->all(),
            /*
             | Dua angka yang SELALU dihitung dari seluruh tabel, bukan dari
             | halaman yang sedang dilihat: vendor tanpa nomor adalah
             | pekerjaan yang belum selesai, dan memindainya halaman demi
             | halaman berarti tidak akan pernah dipindai.
             */
            [
                'ringkas' => [
                    'tanpa_kontak' => Vendor::whereNull('kontak')->count(),
                    'nonaktif' => Vendor::where('aktif', false)->count(),
                    'jenis' => Vendor::whereNotNull('jenis')->distinct()->orderBy('jenis')->pluck('jenis'),
                ],
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->periksa($request);

        /*
         | Nama yang sama tidak dibuat dua kali.
         |
         | Vendor ganda membelah kontaknya: separuh harga menunjuk baris
         | pertama, separuhnya baris kedua, dan memperbarui nomor di salah
         | satunya meninggalkan nomor lama hidup di yang lain — persis
         | keadaan yang hendak dihindari tabel ini.
         */
        $ada = Vendor::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($data['nama'])])->first();

        if ($ada) {
            return response()->json([
                'data' => $this->baris($ada->loadCount('harga')),
                'pesan' => "{$ada->nama} sudah ada di daftar vendor.",
            ]);
        }

        $v = Vendor::create($data);

        $this->catat($request, 'tambah vendor', ['nama' => $v->nama]);

        return response()->json([
            'data' => $this->baris($v->loadCount('harga')),
            'pesan' => 'Vendor ditambahkan.',
        ], 201);
    }

    public function update(Vendor $vendor, Request $request): JsonResponse
    {
        $vendor->update($this->periksa($request));

        $this->catat($request, 'ubah vendor', ['nama' => $vendor->nama]);

        return response()->json([
            'data' => $this->baris($vendor->fresh()->loadCount('harga')),
            'pesan' => 'Vendor diperbarui.',
        ]);
    }

    /**
     * Menyatukan dua vendor yang ternyata orang yang sama.
     *
     * Pemeriksaan kembar saat menambah hanya menangkap nama yang PERSIS sama
     * sesudah dirapikan; "PO Sumber Jaya" dan "Sumber Jaya" lolos jadi dua
     * baris. Begitu terlanjur, keduanya membelah kontaknya — dan tanpa jalan
     * ini satu-satunya cara menyatukannya adalah membuka tiap harga lalu
     * memindahkannya satu per satu.
     *
     * Yang dipindahkan rujukannya saja. Harga tidak disentuh, dan baris biaya
     * RAB pun tidak: yang berubah cuma ke siapa ia menunjuk.
     */
    public function gabung(Vendor $vendor, Request $request): JsonResponse
    {
        $data = $request->validate([
            'ke' => ['required', 'integer', 'exists:tbl_vendor,id'],
        ]);

        $tujuan = Vendor::findOrFail((int) $data['ke']);

        abort_if($tujuan->id === $vendor->id, 422, 'Vendor tidak bisa digabungkan ke dirinya sendiri.');

        $harga = MasterHarga::where('vendor_id', $vendor->id)->update(['vendor_id' => $tujuan->id]);
        $biaya = RabBiaya::where('vendor_id', $vendor->id)->update(['vendor_id' => $tujuan->id]);

        $this->catat($request, 'gabung vendor', [
            'dari' => $vendor->nama, 'ke' => $tujuan->nama, 'harga' => $harga,
        ]);

        $vendor->delete();

        return response()->json([
            'data' => $this->baris($tujuan->fresh()->loadCount('harga')),
            'pesan' => "{$harga} harga dipindahkan ke {$tujuan->nama}.",
        ]);
    }

    /**
     * Master harga yang kontaknya masih tercecer di kolom catatan.
     *
     * Sebelum ada tabel vendor, nomor ditulis di catatan — dan catatan itu
     * tidak ikut ke mana-mana. Daftar ini yang membuat sisa data lama
     * terlihat, bukan menunggu diingat.
     *
     * Disaring di PHP, bukan lewat REGEXP di kueri: REGEXP ada di MySQL dan
     * tidak ada di SQLite yang dipakai uji, sehingga aturannya akan terbukti
     * hijau di tempat yang tidak pernah menjalankannya.
     */
    public function kontakTercecer(): JsonResponse
    {
        $calon = MasterHarga::whereNull('vendor_id')->whereNotNull('catatan')
            ->orderBy('nama')->limit(200)->get(['id', 'nama', 'catatan']);

        $data = $calon
            // Tujuh angka berurutan: cukup untuk nomor telepon terpendek,
            // dan menyingkirkan "DP 30%" atau "berlaku sampai 2026".
            ->filter(fn (MasterHarga $m) => preg_match('/\d[\d\s().-]{6,}/', (string) $m->catatan))
            ->map(fn (MasterHarga $m) => ['id' => $m->id, 'nama' => $m->nama, 'catatan' => $m->catatan])
            ->values();

        return response()->json(['data' => $data, 'meta' => ['total' => $data->count()]]);
    }

    public function destroy(Vendor $vendor, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus vendor', ['nama' => $vendor->nama]);

        // Harga yang memakainya tetap utuh — vendor_id-nya saja yang jadi
        // kosong (nullOnDelete). Menghapus vendor tidak boleh menghapus
        // harganya, apalagi mengubah penawaran yang sudah dikirim.
        $vendor->delete();

        return response()->json(['pesan' => 'Vendor dihapus. Harga yang memakainya tetap ada, hanya kehilangan kontaknya.']);
    }

    /** @return array<string, mixed> */
    private function periksa(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:120'],
            // Nomor TIDAK dipaksa berformat angka: "0812xxx (Pak Budi)"
            // justru bentuk yang paling berguna saat menelepon.
            'kontak' => ['nullable', 'string', 'max:120'],
            'jenis' => ['nullable', 'string', 'max:30'],
            'catatan' => ['nullable', 'string', 'max:300'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $data['aktif'] = array_key_exists('aktif', $data) ? (bool) $data['aktif'] : true;

        // Dirapikan di sini, bukan hanya di hook model: pencarian kembaran di
        // store() membandingkan nama ini dengan yang SUDAH tersimpan, dan yang
        // tersimpan selalu sudah rapi. Tanpa ini "po sumber   jaya" lolos
        // sebagai vendor baru di sebelah "PO Sumber Jaya".
        $data['nama'] = trim(preg_replace('/\s+/', ' ', $data['nama']));

        return $data;
    }

    /** @return array<string, mixed> */
    private function baris(Vendor $v): array
    {
        return [
            'id' => $v->id,
            'nama' => $v->nama,
            'kontak' => $v->kontak,
            'jenis' => $v->jenis,
            'catatan' => $v->catatan,
            'aktif' => $v->aktif,
            // Berapa harga yang akan kehilangan kontaknya bila vendor ini
            // dihapus — angka yang perlu dilihat SEBELUM menekan hapus.
            'jumlah_harga' => (int) ($v->harga_count ?? 0),
            'diubah_pada' => $v->updated_at?->toIso8601String(),
        ];
    }
}
