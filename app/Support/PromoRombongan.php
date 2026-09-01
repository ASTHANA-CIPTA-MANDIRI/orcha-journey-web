<?php

namespace App\Support;

use App\Models\PaketWisata\PromoRombonganTingkat;

/**
 * Potongan yang berlaku menurut jumlah peserta dalam satu pendaftaran.
 *
 * Berlaku DI ATAS harga paket yang sudah berlaku — termasuk harga early bird.
 * Paket yang sudah turun dari 1.700.000 ke 1.430.000 dihitung promo
 * rombongannya dari 1.430.000.
 *
 * Tingkatnya diatur admin lewat tabel tbl_promo_rombongan, bukan per paket.
 * Alasannya skemanya memang seragam untuk seluruh trip; menaruhnya per paket
 * berarti admin harus mengisinya berulang-ulang dan lupa satu berarti satu
 * rombongan tidak mendapat haknya tanpa ada yang tahu.
 */
class PromoRombongan
{
    /**
     * Tingkat terbaik yang berlaku untuk jumlah orang ini; null bila belum ada.
     *
     * TIDAK BERTUMPUK — yang dipakai satu tingkat saja, yang paling tinggi
     * syaratnya. Bertumpuk terdengar lebih murah hati, tetapi angkanya jadi
     * sulit dijelaskan di WhatsApp dan lebih sulit lagi diperiksa saat ada
     * yang protes.
     *
     * @return array<string, mixed>|null
     */
    public static function tingkat(int $orang): ?array
    {
        return collect(PromoRombonganTingkat::daftar())
            ->filter(fn ($t) => $orang >= (int) ($t['min'] ?? 0))
            ->sortByDesc(fn ($t) => (int) ($t['min'] ?? 0))
            ->first();
    }

    /**
     * Hitungan lengkapnya untuk sejumlah orang pada satu harga satuan.
     *
     * @return array{orang_dibayar: int, gratis_orang: int, potongan: int, total: int, label: ?string}
     */
    public static function hitung(float $satuan, int $orang): array
    {
        $orang = max(1, $orang);
        $tingkat = self::tingkat($orang);

        $gratis = (int) ($tingkat['gratis_orang'] ?? 0);
        $persen = (int) ($tingkat['potongan_persen'] ?? 0);

        /*
         | Orang gratis dipotong dari JUMLAH YANG DIBAYAR, bukan diubah jadi
         | persen. Itu yang membuat rinciannya bisa menulis "10 orang, 1
         | gratis — dibayar 9" alih-alih "potongan 10%", dan kalimat pertama
         | itulah yang diceritakan ulang orang ke temannya.
         */
        $dibayar = max(1, $orang - $gratis);
        $sebelum = $satuan * $dibayar;

        /*
         | POTONGAN PERSEN HANYA UNTUK SATU KURSI — kursi yang mengajak.
         |
         | Sebelumnya persennya dikalikan ke seluruh rombongan, sehingga yang
         | diajak ikut menikmati potongan yang bukan haknya: si A mengajak lima
         | temannya, tetapi keenam-enamnya membayar lebih murah. Pada rombongan
         | dua puluh orang, potongan 10% yang dimaksudkan sebagai hadiah untuk
         | satu orang berubah jadi dua kali lipat harga satu kursi yang hilang.
         |
         | Yang benar: hadiahnya melekat pada SATU kursi. Bentuk "gratis orang"
         | sudah begitu sejak awal — satu kursi tidak dibayar — dan bentuk
         | persen kini mengikutinya.
         */
        $potonganPersen = (int) round($satuan * $persen / 100);

        return [
            'orang_dibayar' => $dibayar,
            'gratis_orang' => $gratis,
            // Potongan yang ditampilkan mencakup nilai orang yang digratiskan,
            // supaya "hemat sekian" menyebut seluruh keuntungannya.
            'potongan' => (int) round($satuan * $gratis) + $potonganPersen,
            'total' => (int) round($sebelum - $potonganPersen),
            'label' => $tingkat['label'] ?? null,
        ];
    }

    /**
     * Hitungan untuk paket yang TIDAK ikut promo.
     *
     * Bentuknya sengaja sama persis dengan hitung(), supaya pemanggilnya tidak
     * perlu bercabang dua kali — sekali untuk memilih, sekali untuk membaca
     * hasilnya. Cabang yang terlewat di salah satu tempat itulah yang biasanya
     * membuat harga di layar berbeda dari harga yang ditagih.
     *
     * @return array{orang_dibayar: int, gratis_orang: int, potongan: int, total: int, label: ?string}
     */
    public static function tanpaPromo(float $satuan, int $orang): array
    {
        $orang = max(1, $orang);

        return [
            'orang_dibayar' => $orang,
            'gratis_orang' => 0,
            'potongan' => 0,
            'total' => (int) round($satuan * $orang),
            'label' => null,
        ];
    }

    /**
     * Ajakan untuk tingkat BERIKUTNYA yang belum tercapai.
     *
     * Ini yang mengubah promo dari keterangan jadi dorongan: orang yang sedang
     * mengisi 4 peserta perlu tahu bahwa satu orang lagi mengubah harganya.
     * Tanpa kalimat ini, promonya hanya menguntungkan yang kebetulan sudah
     * ramai.
     */
    public static function ajakanBerikutnya(int $orang): ?string
    {
        $berikut = collect(PromoRombonganTingkat::daftar())
            ->filter(fn ($t) => $orang < (int) ($t['min'] ?? 0))
            ->sortBy(fn ($t) => (int) ($t['min'] ?? 0))
            ->first();

        if (! $berikut) {
            return null;
        }

        $kurang = (int) $berikut['min'] - $orang;

        return $kurang.' orang lagi: '.($berikut['ajakan'] ?? $berikut['label'] ?? '');
    }
}
