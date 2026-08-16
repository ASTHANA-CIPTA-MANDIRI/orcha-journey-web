<?php

namespace App\Support;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Mengabari pelanggan setelah buktinya diperiksa admin.
 *
 * Selama ini pelanggan hanya menerima surat saat MENGIRIM bukti, lalu
 * menunggu tanpa kabar sampai ada yang menghubunginya lewat WhatsApp — dan
 * "pembayaran saya sudah diterima belum?" adalah pertanyaan yang paling
 * sering masuk.
 *
 * Kwitansinya dibuat ulang di sini dengan cap yang sudah berubah, jadi yang
 * dipegang pelanggan setelah diterima bukan lagi berkas bercap "Menunggu
 * Dicek".
 */
class KabarPembayaran
{
    public static function kirim(KonfirmasiPembayaran $bayar, PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): void
    {
        $email = $pesanan?->email;

        if (blank($email)) {
            return;
        }

        $tagihan = TagihanPesanan::untuk($pesanan);

        [$judul, $langkah, $cap] = match ($bayar->status) {
            'diterima' => [
                'Pembayaran Anda Sudah Diterima',
                self::langkahDiterima($tagihan, $pesanan),
                'Diterima',
            ],
            'ditolak' => [
                'Bukti Pembayaran Perlu Diperiksa Ulang',
                'Bukti yang Anda kirim belum bisa kami cocokkan dengan mutasi rekening. '
                    .($bayar->catatan_admin ? 'Catatan tim kami: '.$bayar->catatan_admin.' ' : '')
                    .'Silakan kirim ulang buktinya, atau hubungi kami lewat WhatsApp bila '
                    .'transfernya sudah benar-benar keluar — uang yang sudah berpindah tidak hilang.',
                'Ditolak',
            ],
            default => [
                'Bukti Pembayaran Sedang Dicek',
                'Bukti Anda sedang diperiksa tim kami. Kami kabari lagi setelah dicocokkan '
                    .'dengan mutasi rekening.',
                'Menunggu Dicek',
            ],
        };

        $rincian = [
            'Kode pesanan' => $bayar->kode,
            'Jenis' => $bayar->jenis_label,
            'Nominal' => $bayar->nominal_formatted,
            'Tanggal transfer' => $bayar->tanggal_transfer?->translatedFormat('j F Y'),
            'Status' => $bayar->status_label,
        ];

        $berkas = BerkasKwitansi::buat(
            'Tanda Terima Pembayaran',
            $bayar->kode,
            $rincian,
            $bayar->catatan_admin,
            $bayar->nominal_formatted,
            'Nominal diterima',
            $cap,
            tagihan: $tagihan,
        );

        KirimPemberitahuan::kirim(
            'Status Pembayaran Diperbarui',
            $bayar->kode,
            $rincian,
            $bayar->catatan_admin,
            [],
            $berkas ? [BerkasKwitansi::namaBerkas('tanda-terima', $bayar->kode) => $berkas] : [],
            pelanggan: new SalinanPelanggan(
                email: $email,
                judul: $judul,
                langkah: $langkah,
                tautan: $bayar->status === 'ditolak'
                    ? route('konfirmasi-pembayaran', ['kode' => $bayar->kode])
                    : null,
                labelTautan: 'Kirim Ulang Bukti',
            ),
        );
    }

    private static function langkahDiterima(array $tagihan, PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): string
    {
        if ($tagihan === []) {
            return 'Terima kasih, pembayaran Anda sudah kami terima dan tercatat.';
        }

        if ($tagihan['lunas']) {
            return 'Terima kasih, pembayaran Anda sudah lunas. Tidak ada sisa yang perlu dibayar lagi.';
        }

        // Tenggat pelunasannya berbeda menurut jenis pesanan: open trip harus
        // lunas beberapa hari sebelum berangkat, sedangkan sewa kendaraan
        // dilunasi saat unitnya diambil. Kalimat "paling lambat H-5" pada sewa
        // kendaraan menyesatkan — tidak ada H-5 di sana.
        $tenggat = $pesanan instanceof PenyewaanKendaraan
            ? config('orcha.pembayaran.pelunasan_sewa_kendaraan')
            : 'paling lambat H-'.config('orcha.pembayaran.pelunasan_hari_sebelum').' sebelum berangkat';

        return 'Terima kasih, pembayaran Anda sudah kami terima. Sisa yang perlu dilunasi '
            .$tagihan['sisa_teks'].', '.$tenggat.'.';
    }
}
