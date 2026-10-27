<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\MasterHarga;
use App\Support\RincianBiaya;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->with('vendor')->withCount('biaya')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('destinasi', 'like', "%{$cari}%")
                    ->orWhere('daerah', 'like', "%{$cari}%")
                    // Vendornya ikut dicari: "siapa yang memasok Hiace" sama
                    // seringnya ditanya dengan "berapa harga Hiace".
                    ->orWhereHas('vendor', fn ($v) => $v->where('nama', 'like', "%{$cari}%"))
            ))
            ->when($request->string('kategori')->toString(), fn ($q, $k) => $q->where('kategori', $k))
            // "-" berarti yang BELUM punya vendor — daftar pekerjaan yang
            // tersisa, dan itu yang paling sering dicari sesudah vendor ada.
            ->when($request->string('vendor')->toString(), fn ($q, $v) => $v === '-'
                ? $q->whereNull('vendor_id')
                : $q->where('vendor_id', (int) $v))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $p === '-'
                ? $q->whereNull('provinsi')
                : $q->where('provinsi', $p))
            // "belum" = belum pernah masuk ke satu pun RAB: daftar baris yang
            // boleh dirapikan tanpa menyentuh penawaran mana pun.
            ->when($request->string('dipakai')->toString(), fn ($q, $d) => $d === 'belum'
                ? $q->whereDoesntHave('biaya')
                : $q->whereHas('biaya'))
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
        $data = $this->periksa($request);

        if ($bentrok = $this->kembaran($data, null, $request)) {
            return $bentrok;
        }

        $m = MasterHarga::create($data)->load('vendor')->loadCount('biaya');

        $this->catat($request, 'tambah master harga', ['nama' => $m->nama, 'harga' => $m->harga]);

        return response()->json(['data' => $this->baris($m), 'pesan' => 'Harga ditambahkan.'], 201);
    }

    public function update(MasterHarga $master, Request $request): JsonResponse
    {
        $data = $this->periksa($request);

        if ($bentrok = $this->kembaran($data, $master->id, $request)) {
            return $bentrok;
        }

        $lama = $master->harga;
        $master->update($data);

        $this->catat($request, 'ubah master harga', [
            'nama' => $master->nama, 'dari' => $lama, 'ke' => $master->harga,
        ]);

        return response()->json(['data' => $this->baris($master->fresh()->load('vendor')->loadCount('biaya')), 'pesan' => 'Harga diperbarui.']);
    }

    public function destroy(MasterHarga $master, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus master harga', ['nama' => $master->nama]);

        // Baris biaya RAB yang merujuknya tetap utuh — master_harga_id-nya
        // saja yang jadi kosong (nullOnDelete). Penawaran lama tidak berubah.
        $master->delete();

        return response()->json(['pesan' => 'Harga dihapus. RAB yang sudah memakainya tidak berubah.']);
    }

    /**
     * Daftar harga sebagai PDF, mengikuti saringan yang sedang dipakai.
     *
     * Tanpa halaman, dan saringannya ikut tercetak — sama alasannya dengan
     * PDF vendor: berkas yang isinya sebagian tanpa menyebutkan sebagiannya
     * apa terbaca sebagai daftar lengkap yang ternyata bukan.
     */
    public function pdf(Request $request)
    {
        $daftar = MasterHarga::query()->with('vendor')->withCount('biaya')
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('destinasi', 'like', "%{$cari}%")
                    ->orWhere('daerah', 'like', "%{$cari}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('nama', 'like', "%{$cari}%"))
            ))
            ->when($request->string('kategori')->toString(), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $p === '-'
                ? $q->whereNull('provinsi')
                : $q->where('provinsi', $p))
            ->when($request->string('vendor')->toString(), fn ($q, $v) => $v === '-'
                ? $q->whereNull('vendor_id')
                : $q->where('vendor_id', (int) $v))
            ->when($request->string('dipakai')->toString(), fn ($q, $d) => $d === 'belum'
                ? $q->whereDoesntHave('biaya')
                : $q->whereHas('biaya'))
            ->orderBy('kategori')->orderBy('nama')
            ->get();

        $saringan = array_filter([
            'Kata kunci:' => $request->string('cari')->toString(),
            'Kategori:' => config('orcha.rab.kategori.'.$request->string('kategori')->toString()) ?? '',
            'Wilayah:' => $request->string('provinsi')->toString() === '-'
                ? 'berlaku umum'
                : $request->string('provinsi')->toString(),
            'Pemakaian:' => $request->string('dipakai')->toString() === 'belum'
                ? 'belum pernah dipakai'
                : ($request->string('dipakai')->toString() ? 'sudah dipakai' : ''),
        ]);

        $isi = Pdf::loadView('pdf.master-harga', [
            'baris' => $daftar->map(fn (MasterHarga $m) => $this->baris($m))->all(),
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
            'Content-Disposition' => 'attachment; filename="master-harga-orcha-'.now()->format('Ymd-Hi').'.pdf"',
        ]);
    }

    /**
     * Harga kembar yang akan terhitung DUA KALI.
     *
     * Dua baris dengan destinasi yang sama di provinsi yang sama sama-sama
     * ikut tertarik saat destinasinya dipilih di itinerary — dan modalnya
     * terhitung dua kali tanpa satu pun tanda. Kesalahan yang baru ketahuan
     * saat penawarannya kemahalan dan tidak ada yang tahu sebabnya.
     *
     * Ditahan, bukan ditolak selamanya: dua tiket Prambanan untuk domestik
     * dan turis asing memang sah, dan yang membedakannya cuma namanya. Jadi
     * dipaksa dengan sadar lewat ?paksa=1.
     *
     * Nama kembar di provinsi yang sama juga ditahan — dua baris bernama
     * persis sama tidak bisa dibedakan admin mana pun saat memilihnya di
     * RAB.
     */
    private function kembaran(array $data, ?int $kecuali, Request $request): ?JsonResponse
    {
        if ($request->boolean('paksa')) {
            return null;
        }

        $rapi = fn ($t) => mb_strtolower(trim((string) $t));

        $sama = MasterHarga::query()
            ->when($kecuali, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where(fn ($q) => $data['provinsi'] === null
                ? $q->whereNull('provinsi')
                : $q->where('provinsi', $data['provinsi']))
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(TRIM(nama)) = ?', [$rapi($data['nama'])])
                ->when($data['destinasi'] ?? null, fn ($w, $d) => $w
                    ->orWhereRaw('LOWER(TRIM(destinasi)) = ?', [$rapi($d)])))
            ->first();

        if (! $sama) {
            return null;
        }

        $sebab = $rapi($sama->nama) === $rapi($data['nama'])
            ? "Sudah ada harga bernama \"{$sama->nama}\" di wilayah yang sama."
            : "Sudah ada harga untuk destinasi \"{$sama->destinasi}\" di wilayah yang sama: \"{$sama->nama}\". Keduanya akan sama-sama ikut tertarik ke RAB, dan modalnya terhitung dua kali.";

        return response()->json([
            'pesan' => $sebab.' Beri nama yang membedakan keduanya, atau simpan paksa bila memang dua harga yang berbeda.',
            'kembaran' => ['id' => $sama->id, 'nama' => $sama->nama, 'harga_teks' => RincianBiaya::rupiah($sama->harga)],
        ], 422);
    }

    /** @return array<string, mixed> */
    private function periksa(Request $request): array
    {
        $data = $request->validate([
            'kategori' => ['required', Rule::in(array_keys(config('orcha.rab.kategori')))],
            'vendor_id' => ['nullable', 'integer', 'exists:tbl_vendor,id'],
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
            // Nama & nomor vendornya dibaca HIDUP dari tabel vendor, bukan
            // disalin: nomor yang berganti harus sampai ke seluruh harga
            // yang memakainya tanpa satu pun diubah tangan.
            'vendor_id' => $m->vendor_id,
            'vendor' => $m->vendor?->nama,
            'vendor_kontak' => $m->vendor?->kontak,
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
            // Berapa baris biaya RAB yang berasal dari harga ini — menjawab
            // "menaikkan harga ini menyentuh berapa penawaran".
            'jumlah_rab' => (int) ($m->biaya_count ?? 0),
            'diubah_pada' => $m->updated_at?->toIso8601String(),
            'diubah_teks' => $m->updated_at?->locale('id')->diffForHumans(),
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
