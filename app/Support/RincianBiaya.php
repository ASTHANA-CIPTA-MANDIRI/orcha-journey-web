<?php

namespace App\Support;

use App\Models\PaketWisata\TravelPackage;

/**
 * Hitungan biaya satu pendaftaran.
 *
 * Angka inilah yang paling ditunggu pelanggan begitu formulir terkirim:
 * berapa yang harus ditransfer sekarang, dan berapa sisanya. Selama ini
 * angkanya hanya terpampang di layar lalu hilang saat halaman ditutup,
 * sehingga pelanggan menghitung sendiri — dan itulah asal pertanyaan
 * "harusnya saya bayar berapa?" yang berulang di WhatsApp.
 *
 * Dikumpulkan di satu kelas supaya surat, PDF, dan halaman apa pun yang
 * menampilkannya memakai hitungan yang sama persis.
 */
class RincianBiaya
{
    /**
     * @return array<string, mixed> kosong bila paketnya memang belum berharga
     */
    public static function untuk(?TravelPackage $paket, int $orang): array
    {
        $satuan = (float) ($paket?->price ?? 0);
        $orang = max(1, $orang);

        // Paket yang harganya belum diisi (mis. private trip yang masih
        // dihitung manual) sengaja tidak dikarang-karang angkanya.
        if ($satuan <= 0) {
            return [];
        }

        // Study tour memakai persentase DP-nya sendiri.
        $persen = $paket->category === 'study_tour'
            ? (int) config('orcha.pembayaran.dp_persen_study_tour')
            : (int) config('orcha.pembayaran.dp_persen');

        $total = $satuan * $orang;
        $dp = round($total * $persen / 100);

        return [
            'satuan' => $satuan,
            'satuan_teks' => self::rupiah($satuan),
            'orang' => $orang,
            'total' => $total,
            'total_teks' => self::rupiah($total),
            'dp_persen' => $persen,
            'dp' => $dp,
            'dp_teks' => self::rupiah($dp),
            'sisa' => $total - $dp,
            'sisa_teks' => self::rupiah($total - $dp),
            'pelunasan_hari' => (int) config('orcha.pembayaran.pelunasan_hari_sebelum'),
            'dp_batas_jam' => (int) config('orcha.pembayaran.dp_batas_jam'),
        ];
    }

    public static function rupiah(float|int $angka): string
    {
        return 'Rp '.number_format((float) $angka, 0, ',', '.');
    }
}
