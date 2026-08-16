<?php

namespace App\Support;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Posisi tagihan sebuah pesanan: berapa totalnya, berapa yang sudah
 * dilaporkan masuk, dan berapa sisanya.
 *
 * Dipakai formulir konfirmasi pembayaran untuk mengisikan nominalnya
 * sendiri. Sebelumnya pelanggan mengetik angkanya dari ingatan, dan
 * salah ketik satu digit membuat pembayaran tidak cocok dengan mutasi
 * rekening — pekerjaan yang berakhir di WhatsApp admin.
 *
 * Yang dihitung "sudah dilaporkan" adalah bukti yang belum ditolak,
 * termasuk yang masih menunggu dicek. Alasannya sederhana: pelanggan
 * yang baru mengirim bukti DP satu jam lalu memang sedang menunggu, dan
 * kalau ia kembali untuk melunasi, yang ditawarkan seharusnya sisanya —
 * bukan DP yang sama untuk kedua kalinya.
 */
class TagihanPesanan
{
    /**
     * @return array<string, mixed> kosong bila pesanannya tidak dikenal
     *                              atau harganya memang belum ada
     */
    public static function untuk(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): array
    {
        if (! $pesanan) {
            return [];
        }

        $total = self::total($pesanan);

        if ($total <= 0) {
            return [];
        }

        $sudah = (int) KonfirmasiPembayaran::where('kode', $pesanan->kode)
            ->where('status', '!=', 'ditolak')
            ->sum('nominal');

        $sisa = max(0, $total - $sudah);
        $persenDp = self::persenDp($pesanan);
        $dp = (int) round($total * $persenDp / 100);

        // Belum ada yang masuk → yang ditagih uang mukanya. Sudah ada →
        // yang ditagih sisanya, berapa pun yang sudah terkirim sebelumnya.
        $jenis = $sudah <= 0 ? 'dp' : 'pelunasan';
        $nominal = $sudah <= 0 ? $dp : $sisa;

        return [
            'total' => $total,
            'total_teks' => RincianBiaya::rupiah($total),
            'sudah' => $sudah,
            'sudah_teks' => RincianBiaya::rupiah($sudah),
            'sisa' => $sisa,
            'sisa_teks' => RincianBiaya::rupiah($sisa),
            'dp' => $dp,
            'dp_teks' => RincianBiaya::rupiah($dp),
            'dp_persen' => $persenDp,
            'lunas' => $sisa <= 0,
            'jenis_disarankan' => $jenis,
            'nominal_disarankan' => $sisa <= 0 ? 0 : $nominal,
        ];
    }

    /** Nominal yang pantas ditawarkan untuk satu jenis pembayaran. */
    public static function nominalUntukJenis(array $tagihan, string $jenis): ?int
    {
        if ($tagihan === [] || $tagihan['lunas']) {
            return null;
        }

        return match ($jenis) {
            // DP yang sudah pernah dilaporkan tidak ditawarkan dua kali.
            'dp' => $tagihan['sudah'] > 0 ? $tagihan['sisa'] : $tagihan['dp'],
            'pelunasan', 'sewa' => $tagihan['sisa'],
            default => null,
        };
    }

    private static function total(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): int
    {
        if ($pesanan instanceof PenyewaanKendaraan) {
            return (int) $pesanan->estimasi_biaya;
        }

        return (int) (RincianBiaya::untuk($pesanan->paket, (int) $pesanan->jumlah_peserta)['total'] ?? 0);
    }

    private static function persenDp(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): int
    {
        if ($pesanan instanceof PendaftaranOpenTrip && $pesanan->paket?->category === 'study_tour') {
            return (int) config('orcha.pembayaran.dp_persen_study_tour');
        }

        return (int) config('orcha.pembayaran.dp_persen');
    }
}
