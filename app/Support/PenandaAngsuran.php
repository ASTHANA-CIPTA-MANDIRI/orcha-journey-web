<?php

namespace App\Support;

use App\Models\OpenTrip\Angsuran;

/**
 * Penanda ringkas "pesanan ini diangsur", untuk daftar pendaftaran.
 *
 * Terpisah dari RencanaAngsuran::posisi() karena tugasnya berbeda: posisi()
 * menjawab pertanyaan satu pesanan dan boleh membaca basis data sendiri;
 * penanda ini digambar dua puluh kali dalam satu halaman dan TIDAK BOLEH
 * mengueri apa pun. Semua bahannya dititipkan pemanggil — rencananya sudah
 * dimuat bersama terminnya, dan jumlah uang yang diterima sudah dijumlahkan
 * dalam satu kueri untuk seluruh halaman.
 *
 * Aturan lunas-per-terminnya sengaja disalin dari posisi(), bukan dipanggil:
 * yang disalin cuma satu perbandingan kumulatif, dan menyeret pemuatan pesanan
 * ke dalam daftar demi menghindarinya justru mengembalikan N+1 yang ingin
 * dihindari. Uji memastikan keduanya tidak pernah berbeda jawabannya.
 */
class PenandaAngsuran
{
    /**
     * @param  int  $diterima  jumlah pembayaran berstatus diterima
     * @return array{jumlah_termin:int, lunas:int, telat:int, selesai:bool, berikutnya:?string}|null
     */
    public static function untuk(?Angsuran $rencana, int $diterima): ?array
    {
        if (! $rencana || $rencana->dibatalkan_pada) {
            return null;
        }

        $kumulatif = 0;
        $lunas = 0;
        $telat = 0;
        $berikutnya = null;

        foreach ($rencana->termin as $termin) {
            $kumulatif += (int) $termin->nominal;

            if ($diterima >= $kumulatif) {
                $lunas++;

                continue;
            }

            // Termin terdekat yang belum tertutup — yang ditunggu uangnya, dan
            // satu-satunya tanggal yang berguna dibaca dari daftar.
            $berikutnya ??= $termin->jatuh_tempo?->toDateString();

            if ($termin->jatuh_tempo?->isPast()) {
                $telat++;
            }
        }

        $jumlah = $rencana->termin->count();

        return [
            'jumlah_termin' => $jumlah,
            'lunas' => $lunas,
            'telat' => $telat,
            'selesai' => $jumlah > 0 && $lunas >= $jumlah,
            'berikutnya' => $berikutnya,
        ];
    }
}
