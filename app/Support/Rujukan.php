<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\Rujukan\KodeRujukan;

/**
 * Memeriksa kode rujukan dan menghitung dua angkanya.
 *
 * Dua angka, bukan satu, dan besarnya boleh berbeda:
 *
 *   potongan — yang dipotong dari tagihan orang yang MEMAKAI kodenya
 *   imbalan  — yang menjadi hak orang yang MEMILIKI kodenya
 *
 * Menyamakannya terlihat rapi tetapi salah arah. Potongan harus cukup terasa
 * supaya orang mau mengetik kodenya alih-alih melewatinya; imbalan harus
 * sepadan dengan usaha membujuk seseorang berangkat, dan itu usaha yang jauh
 * lebih besar. Keduanya juga bergerak sendiri-sendiri mengikuti musim.
 *
 * PENOLAKANNYA SELALU MENYEBUT SEBAB. Kode yang ditolak tanpa keterangan
 * membuat orang mengetik ulang tiga kali lalu menyerah — dan yang menyerah di
 * langkah ini adalah pendaftaran yang sudah hampir jadi.
 */
class Rujukan
{
    /**
     * Memeriksa satu kode untuk seorang calon pendaftar.
     *
     * @return array{sah: bool, kode: ?KodeRujukan, sebab: ?string}
     */
    public static function periksa(?string $kode, ?string $whatsappPendaftar = null): array
    {
        $kode = trim((string) $kode);

        if ($kode === '') {
            return ['sah' => false, 'kode' => null, 'sebab' => null];
        }

        if (! config('orcha.rujukan.aktif', true)) {
            return ['sah' => false, 'kode' => null, 'sebab' => 'Program rujukan sedang tidak berjalan.'];
        }

        $rujukan = KodeRujukan::whereRaw('UPPER(kode) = ?', [mb_strtoupper($kode)])->first();

        if (! $rujukan) {
            return ['sah' => false, 'kode' => null, 'sebab' => 'Kode rujukan ini tidak dikenali. Periksa lagi ejaannya.'];
        }

        if (! $rujukan->aktif) {
            return ['sah' => false, 'kode' => null, 'sebab' => 'Kode rujukan ini sudah tidak berlaku.'];
        }

        /*
         | Kode tidak bisa dipakai pemiliknya sendiri.
         |
         | Tanpa penjagaan ini, setiap orang yang punya kode mendapat potongan
         | tetap untuk dirinya sendiri selamanya — dan ikut menagih imbalan
         | atas pendaftarannya sendiri. Yang menemukan celah ini bukan orang
         | jahat; ia cuma mencoba, berhasil, lalu memberi tahu temannya.
         |
         | Dicocokkan lewat nomor yang sudah dirapikan, bukan apa adanya:
         | "+62812..." dan "0812..." adalah orang yang sama, dan perbandingan
         | mentah menganggapnya berbeda.
         */
        if (filled($whatsappPendaftar)
            && NomorTelepon::angka($whatsappPendaftar) === NomorTelepon::angka($rujukan->whatsapp)) {
            return [
                'sah' => false,
                'kode' => null,
                'sebab' => 'Kode ini milik Anda sendiri — bagikan ke teman supaya Anda yang mendapat imbalannya.',
            ];
        }

        return ['sah' => true, 'kode' => $rujukan, 'sebab' => null];
    }

    /** Potongan untuk yang memakai kodenya. */
    public static function potongan(): int
    {
        return (int) config('orcha.rujukan.potongan', 0);
    }

    /** Imbalan untuk yang memiliki kodenya. */
    public static function imbalan(): int
    {
        return (int) config('orcha.rujukan.imbalan', 0);
    }

    /**
     * Membuatkan kode untuk peserta yang baru pulang.
     *
     | Dipanggil sesudah trip selesai, bukan saat mendaftar. Kode yang diberikan
     | kepada orang yang belum berangkat tidak punya cerita untuk dijual —
     | ia belum tahu apakah tripnya bagus.
     */
    public static function untukAlumni(PendaftaranOpenTrip $daftar): KodeRujukan
    {
        // Satu orang satu kode, dikenali dari nomornya. Membuatkan kode kedua
        // untuk orang yang sama memecah imbalannya jadi dua catatan terpisah,
        // dan yang menagih nanti menagih keduanya.
        $ada = KodeRujukan::whereRaw('? = whatsapp', [NomorTelepon::angka($daftar->whatsapp)])->first();

        if ($ada) {
            return $ada;
        }

        return KodeRujukan::create([
            'nama' => $daftar->nama,
            'whatsapp' => $daftar->whatsapp,
            'email' => $daftar->email,
            'kode_pendaftaran_asal' => $daftar->kode,
        ]);
    }
}
