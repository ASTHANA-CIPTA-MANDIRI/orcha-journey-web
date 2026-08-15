<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API dashboard.
 *
 * Pemanggilnya adalah server Phoenix, bukan browser, jadi cukup rahasia bersama
 * yang dikirim lewat header. Perbandingannya memakai hash_equals supaya lama
 * pemeriksaan tidak membocorkan isi kunci.
 *
 * Header yang dibaca:
 *   X-Orcha-Key   : rahasia bersama (wajib)
 *   X-Orcha-Admin : email admin yang sedang bertindak, untuk jejak audit
 */
class PeriksaKunciApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) config('orcha.api.kunci');

        // Kunci kosong berarti integrasinya memang belum disiapkan. Lebih baik
        // menolak semua daripada membuka seluruh data tanpa penjagaan.
        if ($kunci === '') {
            return $this->tolak('Integrasi API belum disiapkan di sisi Orcha.', 503);
        }

        $dikirim = (string) ($request->header('X-Orcha-Key') ?? $request->bearerToken() ?? '');

        if ($dikirim === '' || ! hash_equals($kunci, $dikirim)) {
            Log::warning('API Orcha ditolak: kunci tidak cocok', [
                'ip' => $request->ip(),
                'jalur' => $request->path(),
            ]);

            return $this->tolak('Kunci API tidak sah.', 401);
        }

        $ipDiizinkan = config('orcha.api.ip_diizinkan', []);

        if ($ipDiizinkan !== [] && ! in_array($request->ip(), $ipDiizinkan, true)) {
            Log::warning('API Orcha ditolak: IP di luar daftar', [
                'ip' => $request->ip(),
                'jalur' => $request->path(),
            ]);

            return $this->tolak('IP pemanggil tidak diizinkan.', 403);
        }

        // Dipakai pencatat jejak di controller; tidak memengaruhi hak akses,
        // penentu hak akses tetap Phoenix yang memegang role & permission.
        $request->attributes->set('admin_pemanggil', $request->header('X-Orcha-Admin') ?: 'tidak diketahui');

        return $next($request);
    }

    private function tolak(string $pesan, int $kode): Response
    {
        return response()->json(['pesan' => $pesan], $kode);
    }
}
