<?php

namespace App\Http\Controllers;

use App\Support\BerkasRahasia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan bukti transfer dan berkas jaminan lewat alamat bertanda tangan.
 *
 * Berkasnya sendiri pindah ke disk privat, jadi tidak ada lagi jalan langsung
 * ke sana lewat public/storage. Satu-satunya pintu adalah rute ini, dan
 * pintunya hanya terbuka untuk alamat yang tanda tangannya masih berlaku.
 */
class BerkasRahasiaController extends Controller
{
    public function __invoke(Request $request, string $jalur): StreamedResponse
    {
        /*
         | Dua penjagaan, dan keduanya perlu.
         |
         | Tanda tangan sudah dijamin middleware 'signed'. Yang belum dijamin:
         | bahwa jalur di dalamnya benar-benar menunjuk folder rahasia. Tanpa
         | pemeriksaan ini, satu alamat bertanda tangan yang sah bisa dipakai
         | membaca berkas mana pun di disk itu — cukup dengan mengubah jalurnya
         | dan menandatanganinya ulang lewat rute lain yang kebetulan memakai
         | nama parameter yang sama.
         */
        abort_unless(BerkasRahasia::rahasia('/'.$jalur), 404);

        // Jalur yang mencoba keluar dari folder unggahan ditolak sebelum
        // menyentuh disk sama sekali.
        abort_if(str_contains($jalur, '..'), 404);

        abort_unless(Storage::disk('rahasia')->exists($jalur), 404);

        return Storage::disk('rahasia')->response($jalur);
    }
}
