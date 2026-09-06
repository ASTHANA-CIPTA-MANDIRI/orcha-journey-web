<?php

namespace App\Http\Controllers\Pembayaran;

use App\Http\Controllers\Controller;
use App\Services\DokuCheckout;
use App\Support\TerimaNotifikasiDoku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pintu masuk HTTP Notification dari DOKU.
 *
 * Satu-satunya jalur yang boleh menyatakan sebuah pembayaran lunas tanpa
 * campur tangan manusia, dan karena itu satu-satunya yang perlu dijaga
 * seketat ini. Alamatnya terbuka untuk umum — DOKU memanggilnya dari luar,
 * jadi tidak ada login yang bisa dipasang — sehingga yang memisahkan
 * pembayaran sungguhan dari JSON buatan siapa pun hanyalah tanda tangan di
 * headernya.
 *
 * Dikecualikan dari CSRF di bootstrap/app.php: token CSRF milik sesi
 * peramban, dan yang memanggil di sini adalah server DOKU yang tidak punya
 * sesi apa pun.
 */
class DokuNotifikasiController extends Controller
{
    public function __invoke(Request $request, DokuCheckout $doku): JsonResponse
    {
        if (! $doku->notifikasiSah($request)) {
            /*
             | Yang dicatat hanya jejak teknisnya, bukan isi badannya.
             |
             | Notifikasi palsu pun bisa memuat apa saja, dan menuliskan
             | badannya ke log berarti menyerahkan isi log kepada pengirimnya.
             | Yang benar-benar dibutuhkan saat menelusuri adalah dari mana
             | datangnya dan nomor tagihan mana yang diklaim.
             */
            Log::warning('Notifikasi DOKU ditolak: tanda tangan tidak sah', [
                'ip' => $request->ip(),
                'invoice' => (string) $request->input('order.invoice_number'),
            ]);

            return response()->json(['pesan' => 'Tanda tangan tidak sah.'], 401);
        }

        $dikenal = TerimaNotifikasiDoku::proses((array) $request->json()->all());

        /*
         | Nomor tagihan yang tidak dikenal tetap dijawab 200.
         |
         | DOKU mengulang notifikasi yang tidak dijawab 200, dan pengulangan
         | itu berguna untuk kegagalan yang sementara. Tagihan yang tidak kita
         | kenal bukan kegagalan sementara: mengulangnya seribu kali tidak akan
         | membuatnya dikenal, yang terjadi hanyalah antrean DOKU penuh oleh
         | pesan yang tidak pernah bisa selesai. Yang perlu terjadi sudah
         | terjadi — barisnya masuk log sebagai error untuk ditelusuri orang.
         */
        return response()->json([
            'pesan' => $dikenal ? 'Diterima.' : 'Nomor tagihan tidak dikenal.',
        ]);
    }
}
