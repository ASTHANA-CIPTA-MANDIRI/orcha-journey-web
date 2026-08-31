<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JejakAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bagian yang dipakai bersama semua controller API dashboard.
 */
abstract class ApiController extends Controller
{
    /**
     * Jumlah baris per halaman, dibatasi supaya satu permintaan tidak menarik
     * seluruh tabel.
     */
    protected function perHalaman(Request $request): int
    {
        $diminta = (int) $request->integer('per_halaman', config('orcha.api.per_halaman'));

        return max(1, min($diminta, (int) config('orcha.api.per_halaman_maks')));
    }

    /**
     * Bungkus hasil paginasi jadi bentuk yang seragam: data + meta.
     */
    protected function halaman(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        return $this->halamanDipeta(
            $paginator,
            fn () => $resource::collection($paginator->getCollection())->resolve(),
        );
    }

    /**
     * Sama, untuk daftar yang barisnya dirakit tanpa kelas Resource.
     *
     * Bentuk meta-nya ditulis SEKALI di sini. Menyalinnya ke controller yang
     * kebetulan memakai pemeta sendiri berarti dua bentuk sejajar untuk hal
     * yang sama — dan penomoran halaman di lemon membaca meta itu apa adanya,
     * jadi selisih sekecil apa pun langsung terasa di layar admin.
     *
     * @param  callable(): array<int, mixed>  $petakan
     */
    protected function halamanDipeta(LengthAwarePaginator $paginator, callable $petakan): JsonResponse
    {
        return response()->json([
            'data' => $petakan(),
            'meta' => [
                'halaman' => $paginator->currentPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'halaman_terakhir' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Catat perubahan data supaya jelas admin Phoenix mana yang melakukannya —
     * Orcha tidak punya sesi login untuk mereka.
     *
     * Bentuk pemanggilannya SENGAJA tidak diubah. Sudah ada 43 pemanggilan di
     * sebelas controller, dan mengubah tanda tangannya berarti menyunting
     * keempat puluh tiganya sekaligus — pekerjaan besar yang tiap barisnya
     * berpeluang salah, demi hasil yang sama.
     *
     * Yang berubah: TUJUANNYA. Sebelumnya hanya berkas log, dan berkas log
     * bukan tempatnya:
     *
     *   - Tidak terbaca siapa pun tanpa akses SSH ke server, sedangkan yang
     *     bertanya "siapa yang mengubah nominal pengembalian ini?" adalah orang
     *     keuangan, bukan pemegang kunci server.
     *   - Log berputar dan terhapus. Sengketa justru datang belakangan.
     *   - Tidak bisa disaring per pesanan, per admin, atau per rentang tanggal.
     *
     * Sekarang keduanya: tabel jejak untuk manusia, berkas log untuk
     * menelusuri kejadian teknis berbarengan yang tidak punya tempat di tabel.
     */
    protected function catat(Request $request, string $peristiwa, array $rincian = []): void
    {
        /*
         | Kunci yang sudah dipakai di seluruh pemanggilan yang ada dikenali
         | apa adanya: 'kode' menunjuk pesanan, 'dari' dan 'ke' menandai
         | perpindahan status. Sisanya dirangkai jadi kalimat.
         |
         | Membaca kunci yang sudah terpakai — alih-alih menuntut pemanggil
         | menyesuaikan diri — membuat keempat puluh tiga pemanggilan langsung
         | menghasilkan jejak yang layak dibaca tanpa disentuh satu pun.
         */
        $kode = $rincian['kode'] ?? null;
        $sebelum = isset($rincian['dari']) ? (string) $rincian['dari'] : null;
        $sesudah = isset($rincian['ke']) ? (string) $rincian['ke'] : null;

        $sisa = collect($rincian)
            ->except(['kode', 'dari', 'ke'])
            ->map(fn ($nilai, $kunci) => $kunci.': '.(is_scalar($nilai) ? $nilai : json_encode($nilai)))
            ->implode(', ');

        $ringkasan = $peristiwa
            .($sebelum !== null || $sesudah !== null ? " ({$sebelum} → {$sesudah})" : '')
            .($sisa !== '' ? ' — '.$sisa : '');

        JejakAudit::catat($request, $peristiwa, $ringkasan, $kode, $sebelum, $sesudah);

        Log::info('API Orcha: '.$peristiwa, $rincian + [
            'admin' => $request->attributes->get('admin_pemanggil'),
            'ip' => $request->ip(),
        ]);
    }
}
