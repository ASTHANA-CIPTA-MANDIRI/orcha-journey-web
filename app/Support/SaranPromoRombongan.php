<?php

namespace App\Support;

use App\Models\PaketWisata\TravelPackage;

/**
 * Berapa besar potongan rombongan yang masih menyisakan untung.
 *
 * Angka promo selama ini ditebak: "lima persen terdengar wajar". Tebakan
 * itu aman pada paket bermargin tebal dan diam-diam merugikan pada yang
 * tipis — dan tingkatnya SERAGAM untuk seluruh perusahaan, jadi satu angka
 * yang terlalu murah hati menggerus paket yang paling rapuh lebih dulu.
 *
 * Yang dihitung di sini batas amannya, bukan keputusannya. Admin tetap
 * boleh memilih angka lain; yang tidak boleh terjadi adalah memilih tanpa
 * tahu akibatnya.
 */
class SaranPromoRombongan
{
    /**
     * Separuh dari untung tambahan yang dibawa rekan-rekannya.
     *
     * Aturan sederhananya: hadiahnya tidak boleh memakan lebih dari
     * setengah keuntungan yang ia ciptakan. Dengan begitu rombongan selalu
     * lebih menguntungkan daripada tidak ada rombongan — berapa pun
     * marginnya — dan kalimatnya bisa dijelaskan ke siapa pun dalam satu
     * tarikan napas.
     */
    private const BAGIAN_HADIAH = 0.5;

    /**
     * @return array<string, mixed>
     */
    public static function untuk(int $minPeserta): array
    {
        $n = max(2, $minPeserta);
        $rekan = $n - 1;

        $paket = self::paketPenentu();

        if (! $paket) {
            return [
                'bisa_dihitung' => false,
                'sebab' => 'Belum ada paket ikut promo rombongan yang modalnya terisi, jadi batas amannya tidak bisa dihitung.',
            ];
        }

        $jual = (int) $paket->price;
        $margin = $jual - (int) $paket->harga_modal;

        // Untung tambahan yang dibawa rekan-rekannya — ini sumber hadiahnya.
        $untungTambahan = $rekan * $margin;
        $jatah = (int) floor($untungTambahan * self::BAGIAN_HADIAH);

        /*
         | Batas MAKSIMAL: di atas ini rombongannya tidak lagi menambah
         | untung sama sekali. Bukan "rugi" — hanya tidak ada gunanya.
         */
        $persenMaks = $jual > 0 ? (int) floor($untungTambahan / $jual * 100) : 0;
        $persenSaran = $jual > 0 ? (int) floor($jatah / $jual * 100) : 0;

        // Dibulatkan ke bawah ke kelipatan lima: angka promo dibacakan lewat
        // telepon dan ditulis di poster, dan "7%" tidak pernah terdengar
        // seperti tawaran.
        $persenSaran = (int) (floor($persenSaran / 5) * 5);

        /*
         | Gratis satu orang = kehilangan satu harga jual penuh, sementara
         | orangnya tetap makan dan menempati kursi. Jadi ia hanya masuk
         | akal bila untung tambahannya memang jauh lebih besar.
         */
        $gratisAman = $jatah >= $jual;
        $untungSesudahGratis = $untungTambahan - $jual;

        return [
            'bisa_dihitung' => true,
            'min_peserta' => $n,
            'jumlah_rekan' => $rekan,
            'paket' => $paket->name,
            'jual' => $jual,
            'jual_teks' => RincianBiaya::rupiah($jual),
            'margin_per_orang' => $margin,
            'margin_per_orang_teks' => RincianBiaya::rupiah($margin),
            'untung_tambahan' => $untungTambahan,
            'untung_tambahan_teks' => RincianBiaya::rupiah($untungTambahan),
            'persen_maksimal' => max(0, $persenMaks),
            'persen_saran' => max(0, min($persenSaran, $persenMaks)),
            'potongan_saran_teks' => RincianBiaya::rupiah((int) round($jual * max(0, min($persenSaran, $persenMaks)) / 100)),
            'gratis_aman' => $gratisAman,
            'untung_sesudah_gratis' => $untungSesudahGratis,
            'untung_sesudah_gratis_teks' => RincianBiaya::rupiah($untungSesudahGratis),
            'alasan' => self::alasan($paket->name, $rekan, $margin, $untungTambahan, $gratisAman, $jual),
        ];
    }

    /**
     * Paket paling tipis marginnya yang ikut promo.
     *
     * Dialah yang menentukan: tingkatnya berlaku untuk semua, jadi yang
     * aman bagi paket tertipis aman bagi seluruhnya. Memakai rata-rata
     * akan membuat paket rapuh membiayai kemurahan hati paket yang tebal.
     */
    private static function paketPenentu(): ?TravelPackage
    {
        return TravelPackage::query()
            ->where('promo_rombongan', true)
            ->whereNotNull('harga_modal')
            ->where('price', '>', 0)
            ->get()
            ->sortBy(fn (TravelPackage $p) => (int) $p->price - (int) $p->harga_modal)
            ->first();
    }

    private static function alasan(
        string $paket,
        int $rekan,
        int $margin,
        int $untungTambahan,
        bool $gratisAman,
        int $jual,
    ): string {
        $dasar = "Dihitung dari paket bermargin paling tipis yang ikut promo: {$paket}, untung "
            .RincianBiaya::rupiah($margin).' per orang. '
            ."Mengajak {$rekan} rekan menambah untung ".RincianBiaya::rupiah($untungTambahan).'.';

        return $gratisAman
            ? $dasar.' Menggratiskan satu orang ('.RincianBiaya::rupiah($jual).') masih menyisakan untung tambahan.'
            : $dasar.' Menggratiskan satu orang ('.RincianBiaya::rupiah($jual)
                .') memakan lebih dari separuhnya — potongan persen lebih aman di tingkat ini.';
    }
}
