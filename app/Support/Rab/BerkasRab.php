<?php

namespace App\Support\Rab;

use App\Models\Rab\Rab;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * PDF penawaran (untuk pelanggan) dan RAB internal (untuk kantor).
 *
 * DUA BERKAS, BUKAN SATU BERKAS DENGAN BAGIAN TERSEMBUNYI. Angka modal yang
 * bocor ke pelanggan adalah kesalahan yang tidak bisa ditarik: sekali ia tahu
 * modal bus Rp 850.000 sehari, setiap penawaran berikutnya menjadi tawar-
 * menawar margin. Templat penawaran karena itu TIDAK PERNAH menerima angka
 * modal — yang dikirim ke sana hanya harga jual dan nama-nama barang.
 *
 * Keduanya dirakit dari satu HitungRab::ringkas(), jadi harga di penawaran
 * dan di RAB internal tidak mungkin berbeda.
 */
class BerkasRab
{
    public static function buat(Rab $rab, string $jenis): string
    {
        [$view, $data] = self::bahan($rab, $jenis);

        return Pdf::loadView($view, $data)->setPaper('a4')->output();
    }

    /**
     * Nama templat beserta datanya. Terpisah dari buat() supaya uji bisa
     * membaca HTML-nya langsung — teks di dalam PDF termampatkan.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function bahan(Rab $rab, string $jenis): array
    {
        $rab->loadMissing('itinerary', 'biaya.master');
        $r = HitungRab::ringkas($rab);

        $data = [
            'rab' => $rab,
            'hari' => self::hariDenganTanggal($rab),
            'rentang' => self::rentangTanggal($rab),
            'durasi' => self::durasi($rab),
            'tujuan' => self::tujuan($rab),
            'terbit' => now()->locale('id')->translatedFormat('j F Y'),
            'berlaku' => $rab->berlaku_sampai?->locale('id')->translatedFormat('j F Y'),
            'kontak' => [
                'whatsapp' => config('orcha.whatsapp'),
                'email' => config('orcha.email'),
                'instagram' => config('orcha.instagram'),
                'alamat' => config('orcha.alamat'),
            ],
            'logo' => file_exists(public_path('orcha-logo-surat.png')) ? public_path('orcha-logo-surat.png') : null,
        ];

        if ($jenis === 'internal') {
            $data['r'] = $r;
            $view = 'pdf.rab-internal';
        } else {
            // Yang diserahkan ke templat penawaran HANYA harga jual dan nama
            // barang. Tidak ada modal, tidak ada margin, tidak ada harga
            // satuan — templatnya bahkan tidak bisa membocorkannya karena
            // angkanya tidak pernah sampai ke sana.
            $data['harga'] = [
                'per_orang_teks' => $r['harga_per_orang_teks'],
                'total_teks' => $r['harga_total_teks'],
            ];
            $data['termasuk'] = collect($r['baris'])
                ->groupBy('kategori_label')
                ->map(fn ($isi) => $isi->pluck('nama')->unique()->values()->all())
                ->all();
            $data['dp_persen'] = (int) config('orcha.pembayaran.dp_persen', 30);
            $data['pelunasan_hari'] = (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5);
            $data['atas_nama'] = config('orcha.pembayaran.atas_nama', 'PT ASTHANA CIPTA MANDIRI');
            $view = 'pdf.rab-penawaran';
        }

        return [$view, $data];
    }

    /**
     * Itinerary dikelompokkan per hari, lengkap dengan tanggalnya bila tanggal
     * mulai sudah diketahui. Hari tanpa kegiatan TETAP ikut: "Hari 2 — bebas"
     * lebih jujur daripada hari yang hilang dari jadwal.
     *
     * @return array<int, array{hari_ke:int, tanggal:?string, kegiatan:array}>
     */
    private static function hariDenganTanggal(Rab $rab): array
    {
        $kelompok = $rab->itinerary->groupBy('hari_ke');
        $hasil = [];

        for ($h = 1; $h <= max(1, $rab->jumlah_hari); $h++) {
            $tanggal = $rab->tanggal_mulai
                ? Carbon::parse($rab->tanggal_mulai)->addDays($h - 1)->locale('id')->translatedFormat('l, j F Y')
                : null;

            $hasil[] = [
                'hari_ke' => $h,
                'tanggal' => $tanggal,
                'kegiatan' => ($kelompok[$h] ?? collect())->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * "Yogyakarta, DI Yogyakarta" terbaca gagap; bila nama daerah sudah
     * terkandung di nama provinsi, cukup provinsinya.
     */
    private static function tujuan(Rab $rab): string
    {
        $daerah = trim((string) $rab->daerah);

        if ($daerah === '' || str_contains(mb_strtolower($rab->provinsi), mb_strtolower($daerah))) {
            return $rab->provinsi;
        }

        return $daerah.', '.$rab->provinsi;
    }

    private static function rentangTanggal(Rab $rab): ?string
    {
        if (! $rab->tanggal_mulai) {
            return null;
        }

        $mulai = Carbon::parse($rab->tanggal_mulai)->locale('id');
        $selesai = $mulai->copy()->addDays(max(1, $rab->jumlah_hari) - 1);

        return $mulai->isSameDay($selesai)
            ? $mulai->translatedFormat('j F Y')
            : $mulai->translatedFormat('j M').' – '.$selesai->translatedFormat('j M Y');
    }

    private static function durasi(Rab $rab): string
    {
        $teks = $rab->jumlah_hari.' Hari';

        return $rab->jumlah_malam > 0 ? $teks.' '.$rab->jumlah_malam.' Malam' : $teks;
    }
}
