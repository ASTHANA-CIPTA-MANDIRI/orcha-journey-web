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

        /*
         | Promo rombongan dihitung DI SINI, tempat satu-satunya total dibentuk.
         |
         | Kalau dihitung di halaman, angka yang dilihat pelanggan dan angka
         | yang ditagih sistem akan berbeda suatu saat — dan yang ketahuan
         | belakangan selalu saat orangnya sudah mentransfer.
         */
        /*
         | Promo hanya berlaku pada paket yang ditandai ikut.
         |
         | Tingkatnya seragam untuk seluruh perusahaan, tetapi tidak setiap trip
         | ikut: sebagian sudah tipis marginnya, sebagian sedang musim ramai dan
         | tidak perlu didorong. Yang memutuskan penanda di paketnya, bukan
         | tingkatnya.
         */
        $promo = $paket->promo_rombongan
            ? PromoRombongan::hitung($satuan, $orang)
            : PromoRombongan::tanpaPromo($satuan, $orang);

        $totalSebelumPromo = $satuan * $orang;

        // Tetap float seperti sebelum ada promo. Bukan soal ketelitian —
        // angkanya bulat — melainkan supaya pemanggil yang membandingkan
        // dp + sisa dengan total tidak mendadak berbeda tipe.
        $total = (float) $promo['total'];
        $dp = round($total * $persen / 100);

        return [
            'satuan' => $satuan,
            'satuan_teks' => self::rupiah($satuan),
            'orang' => $orang,
            'total' => $total,
            'total_teks' => self::rupiah($total),

            // Rincian promonya dipisah supaya tampilan bisa menyebut bentuknya
            // apa adanya — "1 orang gratis", bukan "potongan 10%".
            'promo_label' => $promo['label'],
            'promo_gratis_orang' => $promo['gratis_orang'],
            'promo_orang_dibayar' => $promo['orang_dibayar'],
            'promo_potongan' => $promo['potongan'],
            'promo_potongan_teks' => self::rupiah($promo['potongan']),
            'total_sebelum_promo' => $totalSebelumPromo,
            'total_sebelum_promo_teks' => self::rupiah($totalSebelumPromo),
            // Ajakannya pun hanya muncul pada paket yang memang ikut — mengajak
            // menambah teman untuk potongan yang tidak akan datang justru
            // membuat orang merasa dibohongi saat totalnya tidak berubah.
            'promo_ajakan' => $paket->promo_rombongan
                ? PromoRombongan::ajakanBerikutnya($orang)
                : null,
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
