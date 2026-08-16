<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Carbon\Carbon;

/**
 * Memperkirakan potongan pembatalan dari tangga yang berlaku.
 *
 * PERKIRAAN, bukan penetapan. Yang menetapkan tetap tim, karena ada hal yang
 * tidak diketahui sistem: biaya yang sudah terlanjur dibayarkan ke pihak
 * ketiga, kesepakatan menjadwal ulang, atau pembatalan yang datang dari pihak
 * kami sendiri. Angka di sini menjawab pertanyaan pertama yang selalu muncul —
 * "kalau saya batal sekarang, kembali berapa?" — supaya pelanggan tidak
 * menunggu sehari hanya untuk mengetahuinya, dan admin tidak menghitungnya
 * ulang satu per satu.
 *
 * Dua hal yang membuat hitungan ini bisa dipercaya:
 *
 * 1. Potongan dihitung dari TOTAL biaya, bukan uang muka. Melunasi lebih awal
 *    tidak menghapus potongan.
 * 2. Potongan tidak pernah melebihi yang sudah dibayarkan. Pelanggan yang baru
 *    membayar uang muka tidak berutang saat membatalkan mendadak.
 */
class PerkiraanPotongan
{
    /**
     * @return array{
     *     jenis: string, batas: string, persen: int, jam_tersisa: int|null,
     *     total: int, dibayar: int, potongan: int, kembali: int,
     *     total_teks: string, dibayar_teks: string, potongan_teks: string,
     *     kembali_teks: string, lewat: bool
     * }|null
     */
    public static function untuk(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): ?array
    {
        if (! $pesanan) {
            return null;
        }

        $sewa = $pesanan instanceof PenyewaanKendaraan;
        $mulai = self::waktuMulai($pesanan, $sewa);

        // Tanpa tanggal mulai tidak ada jarak yang bisa dihitung, dan menebak
        // angka pengembalian lebih buruk daripada tidak menampilkannya.
        if (! $mulai) {
            return null;
        }

        $tagihan = TagihanPesanan::untuk($pesanan, hanyaDiterima: true);
        $total = (int) ($tagihan['total'] ?? 0);
        $dibayar = (int) ($tagihan['sudah'] ?? 0);

        if ($total <= 0) {
            return null;
        }

        // Jam, bukan hari: selisih tangga sewa yang terpenting justru ada di
        // bawah 24 jam, dan pembulatan hari akan meratakannya.
        $jamTersisa = (int) floor(now()->diffInMinutes($mulai, false) / 60);
        $baris = self::baris($sewa, $jamTersisa);

        $persen = (int) ($baris['persen'] ?? 100);
        $potongan = min((int) round($total * $persen / 100), $dibayar);
        $kembali = max(0, $dibayar - $potongan);

        return [
            'jenis' => $sewa ? 'sewa_kendaraan' : 'open_trip',
            'batas' => $baris['batas'] ?? '—',
            'persen' => $persen,
            'jam_tersisa' => $jamTersisa,
            'lewat' => $jamTersisa < 0,
            'total' => $total,
            'total_teks' => RincianBiaya::rupiah($total),
            'dibayar' => $dibayar,
            'dibayar_teks' => RincianBiaya::rupiah($dibayar),
            'potongan' => $potongan,
            'potongan_teks' => RincianBiaya::rupiah($potongan),
            'kembali' => $kembali,
            'kembali_teks' => RincianBiaya::rupiah($kembali),
        ];
    }

    /**
     * Baris tangga yang berlaku untuk sisa waktu ini.
     *
     * Tangganya diurutkan dari yang paling jauh; yang dipakai adalah baris
     * pertama yang jam_min-nya masih terpenuhi. Baris ber-jam_min null adalah
     * keadaan "sudah lewat" — tidak datang tanpa kabar.
     */
    private static function baris(bool $sewa, int $jamTersisa): array
    {
        $tangga = config($sewa ? 'orcha.pengembalian.tangga_sewa' : 'orcha.pengembalian.tangga', []);

        if ($jamTersisa < 0) {
            foreach ($tangga as $baris) {
                if (($baris['jam_min'] ?? 0) === null) {
                    return $baris;
                }
            }
        }

        foreach ($tangga as $baris) {
            $min = $baris['jam_min'] ?? null;

            if ($min !== null && $jamTersisa >= $min) {
                return $baris;
            }
        }

        return end($tangga) ?: [];
    }

    private static function waktuMulai(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan, bool $sewa): ?Carbon
    {
        if ($sewa) {
            return $pesanan->jadwal_mulai;
        }

        // Tanggal paketnya lebih dipercaya daripada salinan di pendaftaran:
        // jadwal yang digeser panitia diperbarui di paket, tidak di tiap baris
        // pendaftaran yang sudah masuk.
        $tanggal = $pesanan->paket?->tanggal_berangkat ?? $pesanan->tanggal_berangkat;

        return $tanggal ? Carbon::parse($tanggal)->startOfDay() : null;
    }
}
