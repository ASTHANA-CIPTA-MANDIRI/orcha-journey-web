<?php

namespace App\Http\Controllers\Api\Rab;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rab\HariLibur;
use App\Models\Rab\MasterHarga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tanggal merah yang harus dihargai seperti akhir pekan.
 *
 * Sabtu & Minggu TIDAK didaftarkan di sini — keduanya terbaca dari tanggalnya
 * sendiri, dan mendaftarkannya berarti 104 baris yang harus diisi ulang tiap
 * tahun. Yang didaftarkan hanya libur yang tidak bisa dihitung: 17 Agustus,
 * Idul Fitri, Nyepi, cuti bersama.
 *
 * Daftar yang kosong bukan kesalahan, dan layar mengatakannya: artinya hanya
 * Sabtu & Minggu yang dihitung akhir pekan.
 */
class HariLiburController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tahun = (int) $request->integer('tahun');

        $daftar = HariLibur::query()
            ->when($tahun > 0, fn ($q) => $q->whereYear('tanggal', $tahun))
            ->orderBy('tanggal')
            ->get();

        return response()->json([
            'data' => $daftar->map(fn (HariLibur $h) => $this->baris($h))->all(),
            'meta' => [
                'tahun' => $daftar->map(fn (HariLibur $h) => (int) $h->tanggal->year)
                    ->unique()->sort()->values()->all(),
                /*
                 | Berapa harga yang BENAR-BENAR terpengaruh daftar ini.
                 |
                 | Tanpa angka ini, layar hari libur terbaca seperti pekerjaan
                 | administratif tanpa akibat — lalu tidak diisi, dan tanggal
                 | merah dihargai tarif hari kerja. Nol pun menjawab sesuatu:
                 | daftarnya tidak berguna sampai ada harga akhir pekan.
                 */
                'harga_terpengaruh' => MasterHarga::query()
                    ->whereNotNull('harga_akhir_pekan')->where('aktif', true)->count(),
                'lewat' => HariLibur::query()->whereDate('tanggal', '<', now()->toDateString())->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'unique:tbl_hari_libur,tanggal'],
            'nama' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'tanggal.unique' => 'Tanggal itu sudah ada di daftar.',
        ]);

        $h = HariLibur::create($data + ['oleh' => $request->attributes->get('admin_pemanggil')]);

        $this->catat($request, 'tambah hari libur', ['tanggal' => $h->tanggal->toDateString(), 'nama' => $h->nama]);

        return response()->json([
            'data' => $this->baris($h),
            'pesan' => $this->pesanDampak($h, 'ditambahkan'),
        ], 201);
    }

    public function update(HariLibur $libur, Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['sometimes', 'date', 'unique:tbl_hari_libur,tanggal,'.$libur->id],
            'nama' => ['sometimes', 'string', 'min:2', 'max:120'],
        ], [
            'tanggal.unique' => 'Tanggal itu sudah ada di daftar.',
        ]);

        $libur->update($data);

        $this->catat($request, 'ubah hari libur', ['tanggal' => $libur->tanggal->toDateString()]);

        return response()->json(['data' => $this->baris($libur->fresh()), 'pesan' => 'Hari libur diperbarui.']);
    }

    public function destroy(HariLibur $libur, Request $request): JsonResponse
    {
        $this->catat($request, 'hapus hari libur', ['tanggal' => $libur->tanggal->toDateString(), 'nama' => $libur->nama]);

        $libur->delete();

        return response()->json([
            // RAB yang sudah menarik biaya TIDAK berubah: harganya dibekukan
            // per baris. Yang terpengaruh hanya penarikan berikutnya.
            'pesan' => 'Hari libur dihapus. RAB yang sudah dibuat tidak berubah — harganya sudah dibekukan.',
        ]);
    }

    /**
     * Mengisi banyak tanggal sekaligus dari tempelan.
     *
     * Mengisi 16 tanggal merah satu per satu berarti 16 kali buka-isi-simpan
     * tiap awal tahun — pekerjaan yang karena itu tidak dikerjakan, dan
     * tanggal merahnya dihargai tarif hari kerja sepanjang tahun.
     *
     * Baris yang gagal TIDAK membatalkan yang lain, dan yang sudah ada
     * dilewati alih-alih menolak seluruh tempelan.
     */
    public function banyak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.tanggal' => ['required', 'date'],
            'baris.*.nama' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $oleh = $request->attributes->get('admin_pemanggil');
        $ada = HariLibur::query()->pluck('tanggal')
            ->map(fn (Carbon $t) => $t->toDateString())->flip();

        $masuk = 0;
        $lewat = [];

        foreach ($data['baris'] as $b) {
            $tanggal = Carbon::parse($b['tanggal'])->toDateString();

            if ($ada->has($tanggal)) {
                $lewat[] = $tanggal;

                continue;
            }

            HariLibur::create(['tanggal' => $tanggal, 'nama' => trim($b['nama']), 'oleh' => $oleh]);
            $ada[$tanggal] = true;
            $masuk++;
        }

        $this->catat($request, 'tambah banyak hari libur', ['masuk' => $masuk, 'lewat' => count($lewat)]);

        return response()->json([
            'masuk' => $masuk,
            'lewat' => $lewat,
            'pesan' => $masuk > 0
                ? "{$masuk} tanggal ditambahkan.".($lewat ? ' '.count($lewat).' sudah ada dan dilewati.' : '')
                : 'Semua tanggal itu sudah ada di daftar.',
        ], $masuk > 0 ? 201 : 422);
    }

    /** @return array<string, mixed> */
    private function baris(HariLibur $h): array
    {
        return [
            'id' => $h->id,
            'tanggal' => $h->tanggal->toDateString(),
            'tanggal_teks' => $h->tanggal->locale('id')->translatedFormat('l, j F Y'),
            'nama' => $h->nama,
            'oleh' => $h->oleh,
            /*
             | Tanggal yang jatuh di Sabtu/Minggu disebut apa adanya.
             |
             | Mendaftarkannya tidak salah dan tidak merusak apa pun, tetapi
             | juga tidak mengubah harga mana pun — dan admin yang mengira
             | sudah mengamankan satu tanggal berhak tahu bahwa tanggal itu
             | memang sudah aman sejak awal.
             */
            'sudah_akhir_pekan' => $h->tanggal->isSaturday() || $h->tanggal->isSunday(),
            'lewat' => $h->tanggal->isBefore(now()->startOfDay()),
        ];
    }

    private function pesanDampak(HariLibur $h, string $aksi): string
    {
        if ($h->tanggal->isSaturday() || $h->tanggal->isSunday()) {
            return 'Tanggal '.$aksi.', tetapi '.$h->tanggal->locale('id')->translatedFormat('j F')
                .' memang sudah jatuh di hari '.$h->tanggal->locale('id')->translatedFormat('l')
                .' — tarif akhir pekannya sudah berlaku sejak awal.';
        }

        $jumlah = MasterHarga::query()->whereNotNull('harga_akhir_pekan')->where('aktif', true)->count();

        return $jumlah > 0
            ? 'Tanggal '.$aksi.". {$jumlah} harga memakai tarif akhir pekan pada tanggal itu."
            : 'Tanggal '.$aksi.'. Belum ada harga yang punya tarif akhir pekan, jadi belum ada yang berubah.';
    }
}
