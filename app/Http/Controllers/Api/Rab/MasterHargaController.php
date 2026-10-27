<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\BatchMassal;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\RabBiaya;
use App\Models\Rab\RiwayatHarga;
use App\Support\RincianBiaya;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * Saringan daftar harga, dipakai bersama oleh daftar, PDF, dan
     * "pilih semua yang cocok".
     *
     * Satu tempat, bukan tiga salinan: saringan yang menyimpang satu baris
     * saja membuat "pilih semua yang cocok" mencentang baris yang tidak
     * terlihat di layar — dan perintah massal berikutnya menyentuhnya.
     */
    private function saring(Request $request): Builder
    {
        return MasterHarga::query()
            ->when($request->string('cari')->toString(), fn ($q, $cari) => $q->where(
                fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('destinasi', 'like', "%{$cari}%")
                    ->orWhere('daerah', 'like', "%{$cari}%")
                    // Vendornya ikut dicari: "siapa yang memasok Hiace" sama
                    // seringnya ditanya dengan "berapa harga Hiace".
                    //
                    // Berikut NOMORNYA, lewat pencarian yang sama dengan layar
                    // Vendor: yang dipegang admin saat bertanya sering cuma
                    // nomor di WhatsApp, bukan nama yang tercatat di sini.
                    ->orWhereHas('vendor', fn ($v) => $v->cari($cari))
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
            /*
             | Harga yang dimatikan tidak punya kolom sendiri di layar, jadi
             | tanpa saringan ini satu-satunya cara menemukannya adalah
             | memelototi daftar sampai ketemu baris yang pudar.
             |
             | Bawaannya tetap MEMUAT SEMUANYA. Menyembunyikan yang mati
             | secara diam-diam menukar satu keluhan dengan keluhan yang
             | lebih buruk — "harga saya hilang" — dan barisnya memang sudah
             | ditandai pudar berikut lencana MATI.
             */
            ->when($request->string('status')->toString(), fn ($q, $s) => match ($s) {
                'aktif' => $q->where('aktif', true),
                'mati' => $q->where('aktif', false),
                'kedaluwarsa' => $q->kedaluwarsa(),
                default => $q,
            });
    }

    public function index(Request $request): JsonResponse
    {
        $daftar = $this->saring($request)
            ->with('vendor')->withCount('biaya')
            ->tap(fn ($q) => $this->urutkan($q, $request->string('urut')->toString()))
            ->paginate($this->perHalaman($request));

        return $this->halamanDipeta(
            $daftar,
            fn () => $daftar->getCollection()->map(fn (MasterHarga $m) => $this->baris($m))->all(),
            ['kosakata' => $this->kosakata(), 'ringkas' => $this->ringkas()],
        );
    }

    /**
     * Urutan daftar.
     *
     * Bawaannya mengelompokkan: provinsi, lalu kategori, lalu nama — supaya
     * harga sejenis berdekatan. Yang lain menjawab satu pertanyaan masing
     * masing, dan karena itu diberi nama menurut pertanyaannya.
     */
    private function urutkan(Builder $q, string $urut): void
    {
        match ($urut) {
            'termahal' => $q->orderByDesc('harga'),
            'termurah' => $q->orderBy('harga'),
            'terbaru' => $q->orderByDesc('updated_at'),
            // Yang paling lama tidak disentuh: calon harga basi.
            'terlama' => $q->orderBy('updated_at'),
            'tersering' => $q->orderByDesc('biaya_count'),
            default => $q->orderBy('provinsi')->orderBy('kategori')->orderBy('nama'),
        };
    }

    /**
     * Angka yang sudah dihitung untuk PDF, kini ikut ke layar.
     *
     * Dihitung di atas SELURUH tabel, bukan di atas halaman yang sedang
     * terlihat: "berapa yang belum punya vendor" yang hanya menghitung
     * sepuluh baris pertama menjawab pertanyaan yang tidak ditanyakan.
     */
    private function ringkas(): array
    {
        return [
            'jumlah' => MasterHarga::count(),
            'dipakai' => MasterHarga::has('biaya')->count(),
            'tanpa_vendor' => MasterHarga::whereNull('vendor_id')->count(),
            'mati' => MasterHarga::where('aktif', false)->count(),
            'kedaluwarsa' => MasterHarga::kedaluwarsa()->count(),
        ];
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

        $this->catatRiwayat($master, $lama, $request);

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
        // Saringan yang sama persis dengan daftarnya — berkas yang isinya
        // berbeda dari layar saat itu adalah berkas yang menyesatkan.
        $daftar = $this->saring($request)->with('vendor')->withCount('biaya')
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
            'Status:' => match ($request->string('status')->toString()) {
                'aktif' => 'hanya yang aktif',
                'mati' => 'hanya yang dimatikan',
                'kedaluwarsa' => 'hanya yang masa berlakunya lewat',
                default => '',
            },
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
    /**
     * Menyimpan satu baris riwayat — hanya bila ANGKANYA berubah.
     *
     * Mengganti nama, vendor, atau catatan tidak meninggalkan jejak di
     * sini. Riwayat yang penuh baris "harga tetap" tidak bisa dibaca, dan
     * yang dicari orang saat membukanya selalu uangnya.
     */
    private function catatRiwayat(MasterHarga $master, int $hargaLama, Request $request, ?string $catatan = null, ?BatchMassal $batch = null): void
    {
        RiwayatHarga::rekam(
            $master,
            $hargaLama,
            $request->attributes->get('admin_pemanggil'),
            $catatan,
            $batch?->id,
        );
    }

    /**
     * Id SELURUH baris yang cocok saringan — bukan halaman yang terlihat.
     *
     * Mencentang halaman demi halaman untuk menaikkan harga satu kategori
     * adalah dua puluh kali menekan "centang semua" dan satu kali lupa.
     * Yang dipulangkan hanya id-nya, jadi ringan walau barisnya ribuan.
     */
    public function idCocok(Request $request): JsonResponse
    {
        $id = $this->saring($request)->pluck('id')->all();

        return response()->json(['data' => $id, 'meta' => ['jumlah' => count($id)]]);
    }

    /**
     * Menyalin seluruh harga sebuah provinsi ke provinsi lain.
     *
     * Membuka kota baru berarti daftar yang sama dengan angka yang mirip.
     * Mengetiknya ulang satu per satu bukan hanya lama — ia juga tempat
     * selisih kecil masuk tanpa ada yang menyadarinya.
     *
     * Yang sudah ada di provinsi tujuan DILEWATI, bukan ditimpa: menimpa
     * harga yang sudah disesuaikan tangan adalah kerugian yang tak terlihat.
     */
    public function salinProvinsi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['required', 'string', 'max:80'],
            'ke' => ['required', 'string', 'max:80', 'different:dari'],
        ]);

        $sumber = MasterHarga::where('provinsi', $data['dari'])->get();

        if ($sumber->isEmpty()) {
            return response()->json(['pesan' => "Tidak ada harga di {$data['dari']} untuk disalin."], 422);
        }

        $adaDiTujuan = MasterHarga::where('provinsi', $data['ke'])
            ->get()->map(fn (MasterHarga $m) => mb_strtolower(trim($m->nama)))->all();

        $masuk = 0;
        $lewat = 0;

        foreach ($sumber as $m) {
            if (in_array(mb_strtolower(trim($m->nama)), $adaDiTujuan, true)) {
                $lewat++;

                continue;
            }

            $salinan = $m->replicate(['created_at', 'updated_at']);
            $salinan->provinsi = $data['ke'];
            // Daerah ikut provinsi lamanya, jadi dikosongkan — "Sleman" di
            // Bali adalah data yang salah yang terlihat benar.
            $salinan->daerah = null;
            $salinan->save();

            $masuk++;
        }

        $this->catat($request, 'salin harga antar provinsi', [
            'dari' => $data['dari'], 'ke' => $data['ke'], 'masuk' => $masuk,
        ]);

        return response()->json([
            'pesan' => "{$masuk} harga disalin ke {$data['ke']}."
                .($lewat ? " {$lewat} dilewati karena namanya sudah ada di sana." : ''),
            'masuk' => $masuk,
            'lewat' => $lewat,
        ], $masuk > 0 ? 201 : 422);
    }

    /**
     * Menambah banyak harga sekaligus dari tempelan spreadsheet.
     *
     * Memasukkan lima puluh tiket destinasi berarti lima puluh kali
     * buka-isi-simpan, dan di tengah pengulangan sebanyak itu salah ketik
     * berhenti terasa seperti kemungkinan. Yang dikirim ke sini sudah
     * dipecah per baris oleh layar; di sini ia diperiksa satu per satu.
     *
     * Baris yang ditolak TIDAK membatalkan yang lain — lima puluh baris
     * yang gugur gara-gara satu baris salah adalah hukuman yang tidak
     * sebanding. Yang gagal dipulangkan berikut alasannya, per baris.
     */
    public function banyak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.nama' => ['required', 'string', 'max:150'],
            'baris.*.harga' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'baris.*.destinasi' => ['nullable', 'string', 'max:150'],
            'kategori' => ['required', Rule::in(array_keys(config('orcha.rab.kategori')))],
            'satuan' => ['required', Rule::in(array_keys(config('orcha.rab.satuan')))],
            'provinsi' => ['nullable', 'string', 'max:80'],
            'daerah' => ['nullable', 'string', 'max:80'],
            'vendor_id' => ['nullable', 'integer', 'exists:tbl_vendor,id'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $masuk = 0;
        $gagal = [];

        foreach ($data['baris'] as $i => $b) {
            $nama = trim($b['nama']);

            $bentrok = MasterHarga::query()
                ->where(fn ($q) => ($data['provinsi'] ?? null) === null
                    ? $q->whereNull('provinsi')
                    : $q->where('provinsi', $data['provinsi']))
                ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])
                ->exists();

            if ($bentrok) {
                $gagal[] = ['baris' => $i + 1, 'nama' => $nama, 'sebab' => 'sudah ada di wilayah yang sama'];

                continue;
            }

            MasterHarga::create([
                'kategori' => $data['kategori'],
                'satuan' => $data['satuan'],
                'provinsi' => $data['provinsi'] ?? null,
                'daerah' => $data['daerah'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'kapasitas' => $data['kapasitas'] ?? null,
                'nama' => $nama,
                'destinasi' => trim((string) ($b['destinasi'] ?? '')) ?: null,
                'harga' => (int) $b['harga'],
                'otomatis' => false,
                'aktif' => true,
            ]);

            $masuk++;
        }

        $this->catat($request, 'tambah banyak master harga', ['masuk' => $masuk, 'gagal' => count($gagal)]);

        return response()->json([
            'pesan' => $masuk > 0
                ? "{$masuk} harga ditambahkan.".(count($gagal) ? ' '.count($gagal).' baris dilewati.' : '')
                : 'Tidak ada baris yang bisa ditambahkan.',
            'masuk' => $masuk,
            'gagal' => $gagal,
        ], $masuk > 0 ? 201 : 422);
    }

    /**
     * Perubahan harga TERAKHIR di seluruh daftar, bukan per baris.
     *
     * "Apa saja yang berubah minggu ini" selama ini hanya bisa dijawab
     * dengan membuka harga satu per satu — padahal datanya sudah ada sejak
     * baris pertama dicatat.
     */
    public function riwayatSemua(Request $request): JsonResponse
    {
        $hari = (int) $request->integer('hari');

        $kueri = RiwayatHarga::with('harga:id,nama,kategori')
            // Periode dihitung dari HARI INI ke belakang. Tanpa penyaring,
            // "apa yang berubah minggu ini" harus dicari di antara
            // perubahan setahun lalu.
            ->when($hari > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($hari)))
            ->orderByDesc('id');

        $jumlah = (clone $kueri)->count();
        $batas = min(max((int) $request->integer('batas', 50) ?: 50, 1), 500);

        return response()->json([
            'data' => $kueri->limit($batas)->get()->map(fn (RiwayatHarga $r) => $this->barisRiwayat($r) + [
                'harga_nama' => $r->harga?->nama,
                'harga_id' => $r->master_harga_id,
            ])->all(),
            // Supaya layar bisa mengatakan "100 dari 340", bukan diam-diam
            // memotong daftarnya.
            'meta' => ['jumlah' => $jumlah, 'batas' => $batas],
        ]);
    }

    /**
     * RAB mana saja yang memakai sebuah harga.
     *
     * Kolom "Dipakai" memberi angkanya; angka yang tidak bisa dibuka
     * menyuruh orang mencari sendiri ke daftar RAB dan mencocokkan nama.
     */
    public function dipakaiDi(MasterHarga $master): JsonResponse
    {
        $total = RabBiaya::where('master_harga_id', $master->id)->count();

        $biaya = RabBiaya::with('rab:id,kode,judul,nama_pelanggan,tanggal_mulai,status')
            ->where('master_harga_id', $master->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $biaya->filter(fn (RabBiaya $b) => $b->rab !== null)->map(fn (RabBiaya $b) => [
                'rab_id' => $b->rab->id,
                'kode' => $b->rab->kode,
                'judul' => $b->rab->judul ?: $b->rab->nama_pelanggan,
                'status' => $b->rab->status,
                'tanggal' => $b->rab->tanggal_mulai?->locale('id')->translatedFormat('j M Y'),
                // Harga yang DIBEKUKAN di RAB itu — bukan harga hari ini.
                'harga_beku' => RincianBiaya::rupiah($b->harga_satuan),
                'beda' => $b->harga_satuan === $master->harga
                    ? null
                    : ($b->harga_satuan < $master->harga ? 'lebih murah dari sekarang' : 'lebih mahal dari sekarang'),
            ])->values()->all(),
            'meta' => [
                'nama' => $master->nama,
                'harga_kini' => RincianBiaya::rupiah($master->harga),
                // Dikatakan apa adanya kalau daftarnya dipotong.
                'jumlah' => $total,
                'dipotong' => $total > 100,
            ],
        ]);
    }

    /**
     * Riwayat satu harga: kapan berubah, dari berapa ke berapa, oleh siapa.
     */
    public function riwayat(MasterHarga $master): JsonResponse
    {
        return response()->json([
            'data' => $master->riwayat->map(fn (RiwayatHarga $r) => $this->barisRiwayat($r))->all(),
            'meta' => ['nama' => $master->nama, 'harga_kini' => RincianBiaya::rupiah($master->harga)],
        ]);
    }

    /** @return array<string, mixed> */
    private function barisRiwayat(RiwayatHarga $r): array
    {
        return [
            'id' => $r->id,
            'harga_lama' => RincianBiaya::rupiah($r->harga_lama),
            'harga_baru' => RincianBiaya::rupiah($r->harga_baru),
            'selisih' => ($r->selisih > 0 ? '+' : '-').RincianBiaya::rupiah(abs($r->selisih)),
            'naik' => $r->selisih > 0,
            'persen' => $r->persen,
            'oleh' => $r->oleh,
            'catatan' => $r->catatan,
            'pada' => $r->created_at?->locale('id')->translatedFormat('j M Y, H:i'),
            'pada_teks' => $r->created_at?->locale('id')->diffForHumans(),
        ];
    }

    /**
     * Satu perintah untuk banyak baris sekaligus.
     *
     * Kenaikan harga vendor datang serentak — seluruh armadanya naik di
     * tanggal yang sama. Mengerjakannya satu per satu berarti membuka dan
     * menyimpan belasan formulir, dan setiap formulir adalah satu
     * kesempatan salah ketik.
     *
     * Yang dikembalikan BUKAN sekadar "berhasil": jumlah baris yang benar
     * benar tersentuh ikut dipulangkan, supaya layar bisa mengatakan
     * berapa yang berubah alih-alih menjanjikan sesuatu yang tidak terjadi.
     */
    public function massal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'array', 'min:1'],
            'id.*' => ['integer'],
            'aksi' => ['required', Rule::in(['matikan', 'hidupkan', 'hapus', 'naik', 'turun', 'vendor', 'berlaku'])],
            // Hanya untuk aksi naik/turun. Dibatasi 90% supaya salah ketik
            // satu angka nol tidak menghapus harga seisi kategori.
            'persen' => ['nullable', 'numeric', 'min:0.1', 'max:90'],
            'vendor_id' => ['nullable', 'integer', 'exists:tbl_vendor,id'],
            'berlaku_sampai' => ['nullable', 'date'],
        ]);

        $daftar = MasterHarga::whereIn('id', $data['id'])->get();

        if ($daftar->isEmpty()) {
            return response()->json(['pesan' => 'Tidak ada harga yang cocok.'], 422);
        }

        if (in_array($data['aksi'], ['naik', 'turun'], true) && empty($data['persen'])) {
            return response()->json(['pesan' => 'Berapa persen kenaikannya belum diisi.'], 422);
        }

        /*
         | Keadaan SEBELUM disimpan lebih dulu, bukan perintahnya.
         |
         | Menaikkan 10% lalu menurunkan 9,09% tidak mengembalikan angkanya:
         | pembulatan ke ratusan membuat sebagian baris meleset. Yang bisa
         | dipercaya hanya nilai lamanya sendiri.
         */
        $batch = BatchMassal::create([
            'aksi' => $data['aksi'],
            'jumlah' => $daftar->count(),
            'sebelum' => $daftar->map(fn (MasterHarga $m) => [
                'id' => $m->id,
                'harga' => $m->harga,
                'aktif' => $m->aktif,
                'vendor_id' => $m->vendor_id,
                'berlaku_sampai' => $m->berlaku_sampai?->toDateString(),
            ])->all(),
            'oleh' => $request->attributes->get('admin_pemanggil'),
        ]);

        $tersentuh = 0;

        foreach ($daftar as $m) {
            $tersentuh += $this->terapkanMassal($m, $data, $request, $batch) ? 1 : 0;
        }

        $batch->update(['jumlah' => $tersentuh]);

        $this->catat($request, 'ubah massal master harga', [
            'aksi' => $data['aksi'], 'jumlah' => $tersentuh,
        ]);

        return response()->json([
            'pesan' => $this->pesanMassal($data['aksi'], $tersentuh),
            'jumlah' => $tersentuh,
            // Dipulangkan supaya layar bisa menawarkan "batalkan" tepat
            // sesudahnya — tawaran yang datang belakangan tidak pernah dipakai.
            'batch' => $batch->bisaDibatalkan() ? $batch->id : null,
        ]);
    }

    /**
     * Mengembalikan seluruh baris sebuah perintah massal ke nilai lamanya.
     *
     * Bukan dengan menjalankan kebalikannya — "turunkan 9,09%" tidak
     * mengembalikan angka yang sudah dibulatkan — melainkan dengan menulis
     * ulang nilai yang tersimpan sebelum perintahnya jalan.
     */
    public function batalkanMassal(BatchMassal $batch, Request $request): JsonResponse
    {
        if (! $batch->bisaDibatalkan()) {
            return response()->json([
                'pesan' => $batch->aksi === 'hapus'
                    ? 'Penghapusan tidak bisa dibatalkan — barisnya sudah tidak ada.'
                    : 'Perintah ini sudah dibatalkan sebelumnya.',
            ], 422);
        }

        $kembali = 0;

        foreach ($batch->sebelum as $lama) {
            $m = MasterHarga::find($lama['id']);

            if (! $m) {
                continue;
            }

            $hargaKini = $m->harga;
            $m->update([
                'harga' => $lama['harga'],
                'aktif' => $lama['aktif'],
                'vendor_id' => $lama['vendor_id'],
                'berlaku_sampai' => $lama['berlaku_sampai'],
            ]);

            $this->catatRiwayat($m, $hargaKini, $request, 'pembatalan perubahan massal');
            $kembali++;
        }

        $batch->update(['dibatalkan_pada' => now()]);

        $this->catat($request, 'batalkan ubah massal master harga', ['jumlah' => $kembali]);

        return response()->json(['pesan' => "{$kembali} harga dikembalikan seperti semula.", 'jumlah' => $kembali]);
    }

    /** Satu baris, satu aksi. Memulangkan true bila barisnya benar berubah. */
    private function terapkanMassal(MasterHarga $m, array $data, Request $request, ?BatchMassal $batch = null): bool
    {
        if ($data['aksi'] === 'hapus') {
            $m->delete();

            return true;
        }

        if (in_array($data['aksi'], ['naik', 'turun'], true)) {
            $lama = $m->harga;
            $faktor = 1 + ($data['aksi'] === 'naik' ? 1 : -1) * ((float) $data['persen'] / 100);
            // Dibulatkan ke ratusan rupiah: harga hasil perkalian persen
            // seperti "Rp 60.500,25" tidak pernah ditawarkan vendor mana pun.
            $m->harga = max(0, (int) (round($lama * $faktor / 100) * 100));
            $m->save();
            $this->catatRiwayat($m, $lama, $request, 'perubahan massal '.$data['persen'].'%', $batch);

            return $m->harga !== $lama;
        }

        $m->update(match ($data['aksi']) {
            'matikan' => ['aktif' => false],
            'hidupkan' => ['aktif' => true],
            'vendor' => ['vendor_id' => $data['vendor_id'] ?? null],
            'berlaku' => ['berlaku_sampai' => $data['berlaku_sampai'] ?? null],
        });

        return true;
    }

    private function pesanMassal(string $aksi, int $jumlah): string
    {
        return match ($aksi) {
            'matikan' => "{$jumlah} harga dimatikan. RAB yang sudah memakainya tidak berubah.",
            'hidupkan' => "{$jumlah} harga dihidupkan lagi.",
            'hapus' => "{$jumlah} harga dihapus. RAB yang sudah memakainya tidak berubah.",
            'naik' => "{$jumlah} harga dinaikkan.",
            'turun' => "{$jumlah} harga diturunkan.",
            'vendor' => "{$jumlah} harga dipasangkan ke vendor.",
            'berlaku' => "Masa berlaku {$jumlah} harga diperbarui.",
        };
    }

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
            // Kosong berarti "tidak ada batas", bukan "belum diisi".
            'berlaku_sampai' => ['nullable', 'date'],
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
            'berlaku_sampai' => $m->berlaku_sampai?->toDateString(),
            'berlaku_sampai_teks' => $m->berlaku_sampai_teks,
            'kedaluwarsa' => $m->kedaluwarsa,
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
