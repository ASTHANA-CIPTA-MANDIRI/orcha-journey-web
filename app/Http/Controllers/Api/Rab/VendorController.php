<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\Vendor;
use App\Support\Rab\HitungRab;
use App\Support\RincianBiaya;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->cari($request->string('cari')->toString())
            ->when($request->boolean('hanya_aktif'), fn ($q) => $q->aktif())
            ->when($request->string('jenis')->toString(), fn ($q, $j) => $j === '-'
                ? $q->whereNull('jenis')
                : $q->where('jenis', $j))
            ->when($request->string('status')->toString(), fn ($q, $st) => $q->where('aktif', $st === 'aktif'))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $p === '-'
                ? $q->whereNull('provinsi')
                : $q->untukProvinsi($p))
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
                    'provinsi' => Vendor::whereNotNull('provinsi')->distinct()->orderBy('provinsi')->pluck('provinsi'),
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
        /*
         | Vendor yang masih dipakai tidak dihapus begitu saja.
         |
         | Menghapusnya memang tidak merusak harganya — vendor_id-nya saja
         | yang jadi kosong — tetapi kontaknya hilang dari seluruh RAB yang
         | memakainya, dan itu tidak bisa dibatalkan. Yang hampir selalu
         | dimaksud admin adalah "jangan tawarkan lagi", dan itu namanya
         | mematikan, bukan menghapus.
         |
         | Masih bisa dipaksa, karena baris salah ketik memang pantas
         | dibuang — tetapi dipaksa dengan sadar, bukan tanpa sengaja.
         */
        $dipakai = $vendor->harga()->count() + $vendor->biaya()->count();

        if ($dipakai > 0 && ! $request->boolean('paksa')) {
            return response()->json([
                'pesan' => "{$vendor->nama} masih dipakai {$dipakai} harga/biaya. Matikan saja supaya tidak ditawarkan lagi, atau hapus paksa bila memang salah ketik.",
                'dipakai' => $dipakai,
            ], 422);
        }

        $this->catat($request, 'hapus vendor', ['nama' => $vendor->nama, 'dipakai' => $dipakai]);

        // Harga yang memakainya tetap utuh — vendor_id-nya saja yang jadi
        // kosong (nullOnDelete). Menghapus vendor tidak boleh menghapus
        // harganya, apalagi mengubah penawaran yang sudah dikirim.
        $vendor->delete();

        return response()->json(['pesan' => 'Vendor dihapus. Harga yang memakainya tetap ada, hanya kehilangan kontaknya.']);
    }

    /**
     * Daftar vendor sebagai PDF, mengikuti saringan yang sedang dipakai.
     *
     * Tanpa halaman: berkas yang cuma memuat lima belas baris pertama bukan
     * daftar, melainkan potongan — dan tidak ada yang memberitahu
     * pembacanya bahwa itu potongan.
     *
     * Saringannya ikut tercetak di berkasnya, karena berkas yang isinya
     * sebagian tanpa menyebutkan sebagiannya apa terbaca sebagai daftar
     * lengkap yang ternyata bukan.
     */
    public function pdf(Request $request)
    {
        $daftar = Vendor::query()
            ->cari($request->string('cari')->toString())
            ->when($request->string('jenis')->toString(), fn ($q, $j) => $j === '-'
                ? $q->whereNull('jenis')
                : $q->where('jenis', $j))
            ->when($request->string('status')->toString(), fn ($q, $st) => $q->where('aktif', $st === 'aktif'))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $p === '-'
                ? $q->whereNull('provinsi')
                : $q->untukProvinsi($p))
            ->withCount('harga')
            ->orderBy('nama')
            ->get();

        $saringan = array_filter([
            'Kata kunci:' => $request->string('cari')->toString(),
            'Jenis:' => $request->string('jenis')->toString() === '-'
                ? 'belum berjenis'
                : $request->string('jenis')->toString(),
            'Wilayah:' => $request->string('provinsi')->toString() === '-'
                ? 'tanpa wilayah'
                : $request->string('provinsi')->toString(),
            'Status:' => $request->string('status')->toString(),
        ]);

        $isi = Pdf::loadView('pdf.vendor', [
            'baris' => $daftar->map(fn (Vendor $v) => $this->baris($v))->all(),
            'saringan' => $saringan,
            'terbit' => now()->locale('id')->translatedFormat('j F Y, H:i'),
            'kontak' => [
                'whatsapp' => config('orcha.whatsapp'),
                'instagram' => config('orcha.instagram'),
                'alamat' => config('orcha.alamat'),
            ],
            'logo' => file_exists(public_path('orcha-logo-surat.png')) ? public_path('orcha-logo-surat.png') : null,
        ])->setPaper('a4')->output();

        return response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="vendor-orcha-'.now()->format('Ymd-Hi').'.pdf"',
        ]);
    }

    /**
     * Satu vendor beserta jejaknya: harga apa saja, RAB mana saja, dan sudah
     * berapa yang dibelanjakan ke sana.
     *
     * Angka belanjanya dihitung dengan rumus yang SAMA dengan rincian biaya
     * RAB (HitungRab::subtotal), bukan sekadar menjumlahkan harga satuan —
     * satu baris bus berharga 1,1 juta yang jadi 2 unit selama 3 hari
     * bernilai 6,6 juta, dan angka yang dipakai menawar harus angka itu.
     */
    public function rincian(Vendor $vendor, Request $request): JsonResponse
    {
        $harga = $vendor->harga()->orderBy('kategori')->orderBy('nama')->get()
            ->map(fn (MasterHarga $m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'kategori_label' => $m->kategori_label,
                'satuan_label' => $m->satuan_label,
                'harga' => $m->harga,
                'harga_teks' => RincianBiaya::rupiah($m->harga),
                'aktif' => $m->aktif,
            ]);

        $baris = $vendor->biaya()->with('rab')->get()->filter(fn (RabBiaya $b) => $b->rab !== null);

        $perRab = $baris->groupBy('rab_id')->map(function ($isi) {
            $rab = $isi->first()->rab;
            $nilai = $isi->sum(fn (RabBiaya $b) => HitungRab::subtotal($b, $rab));

            return [
                'id' => $rab->id,
                'kode' => $rab->kode,
                'judul' => $rab->judul,
                'status' => $rab->status,
                'status_label' => config("orcha.rab.status.{$rab->status}") ?? $rab->status,
                'tanggal_mulai' => $rab->tanggal_mulai?->toDateString(),
                'jumlah_baris' => $isi->count(),
                'nilai' => $nilai,
                'nilai_teks' => RincianBiaya::rupiah($nilai),
            ];
        })->sortByDesc('id')->values();

        /*
         | Belanjanya dijumlahkan dari SELURUH RAB, daftarnya dipotong per
         | halaman.
         |
         | Dua hal yang berbeda, dan menyamakannya adalah cacat yang paling
         | mudah lolos: "sudah dibelanjakan Rp 6 juta" yang ternyata cuma
         | halaman pertama adalah angka yang salah tanpa kelihatan salah.
         */
        $belanja = (int) $perRab->sum('nilai');

        $perHalaman = $this->perHalaman($request);
        $halaman = max(1, (int) $request->integer('halaman', 1));
        $potong = $perRab->forPage($halaman, $perHalaman)->values();

        return response()->json(['data' => [
            'vendor' => $this->baris($vendor->loadCount('harga')),
            'harga' => $harga,
            'rab' => $potong,
            'rab_meta' => [
                'halaman' => $halaman,
                'halaman_terakhir' => max(1, (int) ceil($perRab->count() / $perHalaman)),
                'total' => $perRab->count(),
                'per_halaman' => $perHalaman,
            ],
            'belanja' => $belanja,
            'belanja_teks' => RincianBiaya::rupiah($belanja),
        ]]);
    }

    /**
     * Memasang vendor ini ke beberapa master harga sekaligus.
     *
     * Dipakai saat memindahkan nomor yang dulu ditulis di kolom catatan:
     * satu vendor biasanya menempel pada beberapa baris harga sekaligus —
     * Hiace, Elf, dan bus besar dari pemilik armada yang sama — dan
     * memasangnya satu per satu lewat formulir harga berarti membuka,
     * menyimpan, dan menutup tiga kali untuk satu keputusan yang sama.
     *
     * Hanya kolom vendor_id yang disentuh. Harga, satuan, dan kapasitasnya
     * tidak ikut terkirim, jadi tidak ada nilai yang bisa berubah diam-diam
     * karena payload-nya kurang lengkap.
     */
    public function pasang(Vendor $vendor, Request $request): JsonResponse
    {
        $data = $request->validate([
            'harga' => ['required', 'array', 'min:1', 'max:200'],
            'harga.*' => ['integer', 'exists:tbl_master_harga,id'],
        ]);

        $jumlah = MasterHarga::whereIn('id', $data['harga'])->update(['vendor_id' => $vendor->id]);

        $this->catat($request, 'pasang vendor ke harga', ['nama' => $vendor->nama, 'jumlah' => $jumlah]);

        return response()->json([
            'data' => $this->baris($vendor->fresh()->loadCount('harga')),
            'pesan' => "{$jumlah} harga dipasangkan ke {$vendor->nama}.",
        ]);
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
            // Kosong berarti BERLAKU DI MANA SAJA, bukan belum diisi.
            'provinsi' => ['nullable', 'string', 'max:80'],
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
            'provinsi' => $v->provinsi,
            'catatan' => $v->catatan,
            'aktif' => $v->aktif,
            // Berapa harga yang akan kehilangan kontaknya bila vendor ini
            // dihapus — angka yang perlu dilihat SEBELUM menekan hapus.
            'jumlah_harga' => (int) ($v->harga_count ?? 0),
            'diubah_pada' => $v->updated_at?->toIso8601String(),
        ];
    }
}
