<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh jawaban.
 *
 * Sebelum ini tidak ada satu pun. Untuk situs yang menampilkan formulir
 * pembayaran dan menyimpan data medis peserta, tiga yang paling terasa:
 *
 *   HSTS          — peramban menolak turun ke http setelah kunjungan pertama,
 *                   sehingga tautan http:// yang beredar tidak lagi bisa
 *                   disadap di jaringan wifi umum.
 *   X-Frame       — halaman tidak bisa disematkan di bingkai situs lain, cara
 *                   klasik menipu orang agar menekan tombol yang tak terlihat.
 *   X-Content-Type— peramban berhenti menebak jenis berkas, sehingga unggahan
 *                   yang menyamar tidak dijalankan sebagai skrip.
 *
 * CSP sengaja BELUM dipasang. Ia yang paling kuat sekaligus paling mudah
 * mematahkan halaman diam-diam — satu skrip pihak ketiga yang lupa didaftarkan
 * dan bagian halaman berhenti bekerja tanpa pesan apa pun. Pemasangannya perlu
 * penelusuran sendiri, dimulai dari mode laporan.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $jawaban = $next($request);

        $jawaban->headers->set('X-Content-Type-Options', 'nosniff');
        $jawaban->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $jawaban->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        /*
         | HSTS hanya saat sambungannya memang sudah https.
         |
         | Mengirimkannya lewat http tidak ada gunanya — peramban mengabaikan —
         | dan di lingkungan pengembangan yang berjalan di http, memasangnya
         | justru bisa mengunci localhost ke https di peramban yang sama sampai
         | disetel ulang manual.
         */
        if ($request->secure()) {
            $jawaban->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $jawaban;
    }
}
