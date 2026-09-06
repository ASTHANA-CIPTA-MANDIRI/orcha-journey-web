<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'kunci.orcha' => \App\Http\Middleware\PeriksaKunciApi::class,
        ]);

        // Header keamanan untuk SELURUH jawaban, termasuk berkas dan API —
        // bukan hanya halaman web. Lihat alasannya di kelasnya.
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);

        /*
         | Notifikasi DOKU dikecualikan dari CSRF.
         |
         | Token CSRF melekat pada sesi peramban, dan yang memanggil alamat ini
         | adalah server DOKU dari luar — ia tidak punya sesi, tidak pernah
         | memuat halaman kita, dan tidak mungkin membawa token apa pun.
         |
         | Yang menggantikan perlindungannya bukan ketiadaan: tiap notifikasi
         | membawa tanda tangan HMAC-SHA256 yang dihitung dengan Secret Key
         | DOKU, dan DokuNotifikasiController menolak apa pun yang tanda
         | tangannya tidak cocok sebelum satu baris pun ditulis.
         */
        $middleware->validateCsrfTokens(except: [
            'pembayaran/doku/notifikasi',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
