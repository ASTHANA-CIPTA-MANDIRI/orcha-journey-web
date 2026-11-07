<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\HargaMusim;
use App\Models\Rab\MasterHarga;
use App\Models\Rab\MusimHarga;
use App\Support\RincianBiaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Musim yang punya tarifnya sendiri: Nataru, Lebaran, long weekend.
 *
 * Bedanya dengan Hari Libur — dan ini satu-satunya hal yang perlu dipahami
 * sebelum memakai kedua layar: Hari Libur hanya berkata "perlakukan tanggal
 * ini seperti akhir pekan" dan memakai kolom harga akhir pekan. Musim punya
 * ANGKANYA SENDIRI per baris harga.
 *
 * Jadi Nataru TIDAK boleh diisi lewat Hari Libur. Hotel yang Rp 550.000 di
 * Sabtu bisa Rp 900.000 di Nataru, dan memasukkannya sebagai tanggal merah
 * membuat seluruh rombongan Nataru dibayari tarif hari Sabtu.
 */
class MusimHargaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tahun = (int) $request->integer('tahun');

        $daftar = MusimHarga::query()
            ->withCount('harga')
            /*
             | Disaring dengan RENTANG yang menyentuh tahun itu, bukan dengan
             | whereYear(tanggal_mulai).
             |
             | Nataru mulai 20 Desember 2026 dan berakhir 5 Januari 2027.
             | Menyaring menurut tanggal mulai membuatnya hilang dari daftar
             | tahun 2027 — yaitu tahun saat separuh rombongannya berjalan.
             */
            ->when($tahun > 0, fn ($q) => $q
                ->whereDate('tanggal_mulai', '<=', $tahun.'-12-31')
                ->whereDate('tanggal_sampai', '>=', $tahun.'-01-01'))
            ->orderBy('tanggal_mulai')
            ->get();

        return response()->json([
            'data' => $daftar->map(fn (MusimHarga $m) => $this->baris($m))->all(),
            'meta' => [
                'tahun' => $daftar
                    ->flatMap(fn (MusimHarga $m) => range((int) $m->tanggal_mulai->year, (int) $m->tanggal_sampai->year))
                    ->unique()->sort()->values()->all(),
                /*
                 | Berapa harga yang BENAR-BENAR punya tarif musim.
                 |
                 | Nol menjawab sesuatu: musimnya sudah terdaftar tetapi belum
                 | ada satu pun harga yang naik di dalamnya — artinya seluruh
                 | rombongan Nataru masih dibayari tarif hari biasa, dan
                 | daftarnya belum mengubah apa pun.
                 */
                'harga_terpasang' => HargaMusim::query()->distinct('master_harga_id')->count('master_harga_id'),
                'lewat' => $daftar->filter(fn (MusimHarga $m) => $m->lewat)->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->periksa($request);

        $m = MusimHarga::create($data + ['oleh' => $request->attributes->get('admin_pemanggil')]);

        $this->catat($request, 'tambah musim harga', [
            'nama' => $m->nama, 'rentang' => $m->rentang_teks,
        ]);

        return response()->json([
            'data' => $this->baris($m->loadCount('harga')),
            'pesan' => 'Musim "'.$m->nama.'" ditambahkan. Sekarang isi harga musimnya di Master Harga — selama belum ada yang diisi, tarifnya masih sama seperti hari biasa.',
        ], 201);
    }

    public function update(MusimHarga $musim, Request $request): JsonResponse
    {
        $data = $this->periksa($request, $musim);

        $musim->update($data);

        $this->catat($request, 'ubah musim harga', ['nama' => $musim->nama]);

        return response()->json([
            'data' => $this->baris($musim->fresh()->loadCount('harga')),
            /*
             | Rentang yang digeser TIDAK menyentuh RAB yang sudah menarik
             | biaya — harganya dibekukan per baris. Yang berubah hanya
             | penarikan berikutnya, dan penjaga "harga beku sudah tidak
             | cocok" akan menyebut baris lama yang jadi salah tingkat.
             */
            'pesan' => 'Musim diperbarui. RAB yang sudah dibuat tidak berubah — harganya sudah dibekukan, tetapi RAB yang tanggalnya masuk/keluar rentang baru akan ditandai.',
        ]);
    }

    public function destroy(MusimHarga $musim, Request $request): JsonResponse
    {
        $jumlah = $musim->harga()->count();

        /*
         | Musim yang masih dipakai TIDAK dihapus, melainkan ditawarkan untuk
         | dimatikan.
         |
         | Menghapusnya membawa seluruh harga musimnya (cascade) — 40 tarif
         | Nataru yang dirapikan sepanjang November hilang dalam satu kali
         | tekan, dan tidak ada cara memulihkannya. Riwayat baris biaya lama
         | pun kehilangan nama musimnya.
         */
        if ($jumlah > 0) {
            return response()->json([
                'pesan' => 'Musim ini masih dipakai '.$jumlah.' harga. Menghapusnya ikut menghapus '
                    .$jumlah.' tarif musim itu dan tidak bisa dibatalkan. Matikan saja musimnya '
                    .'— tarifnya tersimpan, dan tanggalnya berhenti dipakai RAB baru.',
            ], 422);
        }

        $this->catat($request, 'hapus musim harga', ['nama' => $musim->nama, 'rentang' => $musim->rentang_teks]);

        $musim->delete();

        return response()->json([
            'pesan' => 'Musim dihapus. RAB yang sudah dibuat tidak berubah — harganya sudah dibekukan.',
        ]);
    }

    /**
     * Harga-harga yang terpasang di satu musim.
     *
     * Menjawab "Nataru menyentuh apa saja" dalam satu layar. Tanpa ini
     * jawabannya hanya bisa dikumpulkan dengan membuka tiap baris master satu
     * per satu — pekerjaan yang karena itu tidak dikerjakan, dan tarif yang
     * lupa diisi tidak pernah ketemu.
     */
    public function isi(MusimHarga $musim): JsonResponse
    {
        $daftar = $musim->harga()->with('master.vendor')->get()
            ->filter(fn (HargaMusim $h) => $h->master !== null)
            ->sortBy(fn (HargaMusim $h) => [$h->master->kategori, $h->master->nama])
            ->values();

        return response()->json([
            'data' => [
                'musim' => $this->baris($musim->loadCount('harga')),
                'harga' => $daftar->map(fn (HargaMusim $h) => [
                    'id' => $h->id,
                    'master_harga_id' => $h->master_harga_id,
                    'nama' => $h->master->nama,
                    'kategori' => $h->master->kategori,
                    'kategori_label' => $h->master->kategori_label,
                    'vendor' => $h->master->vendor?->nama,
                    'satuan_label' => $h->master->satuan_label,
                    'harga_biasa' => (int) $h->master->harga,
                    'harga_biasa_teks' => RincianBiaya::rupiah($h->master->harga),
                    'harga' => (int) $h->harga,
                    'harga_teks' => RincianBiaya::rupiah($h->harga),
                    /*
                     | Selisihnya BERTANDA dan dalam persen.
                     |
                     | Tarif musim yang ternyata lebih MURAH daripada harga
                     | biasa hampir selalu salah ketik — nol yang kurang, atau
                     | angka yang tertukar dengan harga biasa. Tanpa tandanya
                     | terlihat, ia akan dipakai seluruh musim.
                     */
                    'selisih' => (int) $h->harga - (int) $h->master->harga,
                    'persen' => (int) $h->master->harga > 0
                        ? round(((int) $h->harga - (int) $h->master->harga) / (int) $h->master->harga * 100, 1)
                        : null,
                ])->all(),
            ],
        ]);
    }

    /**
     * Mengisi tarif musim untuk BANYAK baris master sekaligus.
     *
     * Mengisi 40 tarif Nataru satu per satu lewat borang master harga berarti
     * 40 kali buka-isi-simpan — pekerjaan yang karena itu dikerjakan separuh,
     * dan separuh sisanya diam-diam memakai tarif hari biasa.
     *
     * Baris dengan harga KOSONG dihapus, bukan disimpan sebagai nol: nol
     * adalah harga yang sah, dan satu-satunya cara mengatakan "harga ini tidak
     * naik di musim itu" adalah tidak adanya barisnya.
     */
    public function pasang(MusimHarga $musim, Request $request): JsonResponse
    {
        $data = $request->validate([
            'baris' => ['required', 'array', 'min:1', 'max:500'],
            'baris.*.master_harga_id' => ['required', 'integer', 'exists:tbl_master_harga,id'],
            'baris.*.harga' => ['present', 'nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $masuk = 0;
        $hapus = 0;

        foreach ($data['baris'] as $b) {
            $harga = $b['harga'];

            if ($harga === null || $harga === '') {
                $hapus += (int) $musim->harga()->where('master_harga_id', $b['master_harga_id'])->delete();

                continue;
            }

            HargaMusim::updateOrCreate(
                ['musim_id' => $musim->id, 'master_harga_id' => $b['master_harga_id']],
                ['harga' => (int) $harga],
            );
            $masuk++;
        }

        $this->catat($request, 'pasang harga musim', [
            'musim' => $musim->nama, 'masuk' => $masuk, 'hapus' => $hapus,
        ]);

        return response()->json([
            'data' => $this->baris($musim->fresh()->loadCount('harga')),
            'pesan' => trim(($masuk > 0 ? $masuk.' tarif '.$musim->nama.' disimpan. ' : '')
                .($hapus > 0 ? $hapus.' dikosongkan — kembali memakai tarif hari biasa. ' : '')
                .'RAB yang sudah dibuat tidak berubah.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periksa(Request $request, ?MusimHarga $musim = null): array
    {
        $wajib = $musim ? 'sometimes' : 'required';

        $data = $request->validate([
            'nama' => [$wajib, 'string', 'min:2', 'max:120'],
            'tanggal_mulai' => [$wajib, 'date'],
            /*
             | Akhir TIDAK boleh sebelum awal, tetapi BOLEH sama.
             |
             | Musim sehari — 1 Januari — ditulis dengan kedua tanggal yang
             | sama. Memakai 'after' membuatnya mustahil ditulis, dan admin
             | akan menuliskannya sebagai 1–2 Januari: satu hari tarif tinggi
             | yang tidak diminta siapa pun.
             */
            'tanggal_sampai' => [$wajib, 'date', 'after_or_equal:'.($request->input('tanggal_mulai')
                ?? $musim?->tanggal_mulai?->toDateString() ?? '1900-01-01'),
                /*
                 | Rentang yang terlalu panjang DITOLAK, dan batasnya bukan
                 | angka asal.
                 |
                 | Musim 400 hari berarti seluruh tahun bertarif tinggi — yang
                 | artinya bukan musim, melainkan kenaikan harga yang
                 | seharusnya ditulis di kolom harga biasa. Salah ketik tahun
                 | (2026 jadi 2027 di salah satu ujung) menghasilkan tepat
                 | itu, dan tidak ada apa pun selain ini yang akan berbunyi.
                 |
                 | Ditulis sebagai aturan validasi, bukan abort_if: galat yang
                 | datang dengan bentuk berbeda tidak akan menempel di medan
                 | mana pun di layar, dan borang terlihat gagal tanpa sebab.
                 */
                function (string $medan, $nilai, $gagal) use ($request, $musim) {
                    $mulai = $request->input('tanggal_mulai') ?? $musim?->tanggal_mulai?->toDateString();

                    if (! $mulai || ! $nilai) {
                        return;
                    }

                    $hari = Carbon::parse($mulai)->diffInDays(Carbon::parse($nilai)) + 1;

                    if ($hari > 120) {
                        $gagal('Rentangnya '.$hari.' hari — periksa tahun di kedua tanggalnya. '
                            .'Kenaikan yang berlaku sepanjang tahun sebaiknya ditulis sebagai harga biasa, bukan musim.');
                    }
                },
            ],
            'prioritas' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'aktif' => ['sometimes', 'boolean'],
        ], [
            'tanggal_sampai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        if (array_key_exists('prioritas', $data)) {
            $data['prioritas'] = (int) $data['prioritas'];
        }

        if (array_key_exists('aktif', $data)) {
            $data['aktif'] = (bool) $data['aktif'];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function baris(MusimHarga $m): array
    {
        return [
            'id' => $m->id,
            'nama' => $m->nama,
            'tanggal_mulai' => $m->tanggal_mulai->toDateString(),
            'tanggal_sampai' => $m->tanggal_sampai->toDateString(),
            'rentang_teks' => $m->rentang_teks,
            'jumlah_hari' => $m->jumlah_hari,
            'prioritas' => $m->prioritas,
            'aktif' => $m->aktif,
            'oleh' => $m->oleh,
            'lewat' => $m->lewat,
            'berjalan' => $m->berjalan,
            // Berapa harga yang punya tarif di musim ini — menjawab "musim
            // ini sudah diisi atau baru nama".
            'jumlah_harga' => (int) ($m->harga_count ?? 0),
            /*
             | Musim lain yang rentangnya BERTUMPUK dengan musim ini.
             |
             | Bukan kesalahan: cuti bersama di dalam Nataru itu hal yang
             | nyata, dan prioritas memang disediakan untuk itu. Tetapi dua
             | musim bertumpuk dengan prioritas yang sama berarti pemenangnya
             | diputuskan panjang rentang — aturan yang benar, tetapi tidak
             | akan diduga siapa pun yang tidak membacanya.
             */
            'tumpang' => MusimHarga::query()
                ->where('id', '!=', $m->id)
                ->where('aktif', true)
                ->whereDate('tanggal_mulai', '<=', $m->tanggal_sampai->toDateString())
                ->whereDate('tanggal_sampai', '>=', $m->tanggal_mulai->toDateString())
                ->orderBy('tanggal_mulai')
                ->get()
                ->map(fn (MusimHarga $l) => [
                    'id' => $l->id,
                    'nama' => $l->nama,
                    'rentang_teks' => $l->rentang_teks,
                    'menang' => $l->prioritas > $m->prioritas
                        || ($l->prioritas === $m->prioritas && $l->jumlah_hari < $m->jumlah_hari),
                ])->all(),
        ];
    }

    /**
     * Baris master yang PANTAS punya tarif musim, untuk mengisinya sekaligus.
     *
     * Disaring ke yang satuannya menginap/per hari dan kategori yang memang
     * naik di musim — akomodasi, transportasi, konsumsi. Tiket masuk candi
     * tidak naik di Nataru, dan menampilkannya di antara 200 baris lain
     * membuat layar ini terlalu panjang untuk dipakai.
     */
    public function calon(MusimHarga $musim, Request $request): JsonResponse
    {
        $terpasang = $musim->harga()->pluck('harga', 'master_harga_id');

        $daftar = MasterHarga::query()
            ->with('vendor')
            ->where('aktif', true)
            ->when($request->string('provinsi')->toString(), fn ($q, $p) => $q->where('provinsi', $p))
            ->when($request->string('kategori')->toString(), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->string('cari')->toString(), fn ($q, $c) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$c}%")->orWhere('destinasi', 'like', "%{$c}%")))
            ->orderBy('kategori')->orderBy('nama')
            ->get();

        return response()->json([
            'data' => $daftar->map(fn (MasterHarga $m) => [
                'id' => $m->id,
                'nama' => $m->nama,
                'kategori' => $m->kategori,
                'kategori_label' => $m->kategori_label,
                'provinsi' => $m->provinsi,
                'vendor' => $m->vendor?->nama,
                'satuan_label' => $m->satuan_label,
                'harga' => (int) $m->harga,
                'harga_teks' => RincianBiaya::rupiah($m->harga),
                'harga_akhir_pekan' => $m->harga_akhir_pekan,
                // Tarif musim yang SUDAH ada ikut, supaya layarnya bisa jadi
                // satu borang berisi — bukan daftar kosong yang harus diisi
                // ulang tiap kali dibuka.
                'harga_musim' => $terpasang[$m->id] ?? null,
            ])->all(),
        ]);
    }
}
