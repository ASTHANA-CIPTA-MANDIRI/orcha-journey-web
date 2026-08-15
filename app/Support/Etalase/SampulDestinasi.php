<?php

namespace App\Support\Etalase;

use Illuminate\Support\Str;

/**
 * Pembuat gambar sampul destinasi.
 *
 * Menghasilkan ilustrasi vektor (SVG) bergaya sama dengan logo Orcha: bidang
 * warna datar, matahari, siluet pulau, dan lapisan ombak. Ini SENGAJA berupa
 * ilustrasi, bukan foto — supaya tidak ada gambar yang seolah-olah memotret
 * tempat aslinya. Begitu admin mengunggah foto asli lewat panel, foto itulah
 * yang dipakai dan sampul ini tidak lagi tampil.
 *
 * Bentuknya ditentukan dari nama destinasi, jadi satu tempat selalu mendapat
 * ilustrasi yang sama setiap kali dibuat ulang.
 */
class SampulDestinasi
{
    /**
     * Tiga suasana langit. Warnanya diambil dari palet brand.
     */
    private const SUASANA = [
        'siang' => [
            'langit' => ['#1AB0E2', '#8CDCF6'],
            'laut' => ['#0086C3', '#004E80'],
            'matahari' => '#FFC74E',
            'pulau' => ['#003D63', '#00263F', '#001220'],
            'awan' => '#FFFFFF',
        ],
        'senja' => [
            'langit' => ['#F2A33B', '#FFD98A'],
            'laut' => ['#0E5C8F', '#001B2E'],
            'matahari' => '#FFE3A3',
            'pulau' => ['#0A3350', '#04223A', '#001220'],
            'awan' => '#FFE9C7',
        ],
        'pagi' => [
            'langit' => ['#7FD4F3', '#E8F7FD'],
            'laut' => ['#0E9BD4', '#00527F'],
            'matahari' => '#FFC74E',
            'pulau' => ['#005084', '#00344F', '#001220'],
            'awan' => '#FFFFFF',
        ],
    ];

    /**
     * Buat SVG untuk satu destinasi.
     *
     * @param  int  $variasi  0 = sampul utama, 1..n = foto pendamping
     */
    public function render(string $nama, int $variasi = 0, int $lebar = 1200, int $tinggi = 900): string
    {
        $acak = $this->pengacak($nama, $variasi);

        $kunciSuasana = array_keys(self::SUASANA);
        $suasana = self::SUASANA[$kunciSuasana[$acak(0, count($kunciSuasana) - 1)]];

        $id = Str::slug($nama).'-'.$variasi;
        $cakrawala = (int) round($tinggi * ($acak(52, 62) / 100));

        $bagian = [
            $this->definisi($id, $suasana, $cakrawala, $tinggi),
            $this->langit($id, $lebar, $cakrawala),
            $this->matahari($acak, $suasana, $lebar, $cakrawala),
            $this->awan($acak, $suasana, $lebar, $cakrawala),
            $this->pulau($acak, $suasana, $lebar, $cakrawala),
            $this->laut($id, $lebar, $tinggi, $cakrawala),
            $this->ombak($acak, $lebar, $tinggi, $cakrawala),
            $this->latarDepan($acak, $suasana, $lebar, $tinggi),
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$lebar.' '.$tinggi.'" '
            .'width="'.$lebar.'" height="'.$tinggi.'" role="img" '
            .'aria-label="Ilustrasi pemandangan '.htmlspecialchars($nama, ENT_QUOTES).'">'
            .implode('', $bagian)
            .'</svg>';
    }

    /**
     * Angka acak yang selalu sama untuk nama + variasi yang sama.
     */
    private function pengacak(string $nama, int $variasi): callable
    {
        $benih = crc32(Str::lower($nama).'#'.$variasi);

        return function (int $min, int $max) use (&$benih): int {
            // Generator kongruensial sederhana — cukup untuk memilih bentuk.
            $benih = ($benih * 1103515245 + 12345) & 0x7FFFFFFF;

            return $min + (int) ($benih % max(1, $max - $min + 1));
        };
    }

    private function definisi(string $id, array $suasana, int $cakrawala, int $tinggi): string
    {
        return '<defs>'
            .'<linearGradient id="langit-'.$id.'" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0%" stop-color="'.$suasana['langit'][0].'"/>'
            .'<stop offset="100%" stop-color="'.$suasana['langit'][1].'"/>'
            .'</linearGradient>'
            .'<linearGradient id="laut-'.$id.'" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0%" stop-color="'.$suasana['laut'][0].'"/>'
            .'<stop offset="100%" stop-color="'.$suasana['laut'][1].'"/>'
            .'</linearGradient>'
            .'</defs>';
    }

    private function langit(string $id, int $lebar, int $cakrawala): string
    {
        return '<rect x="0" y="0" width="'.$lebar.'" height="'.$cakrawala.'" fill="url(#langit-'.$id.')"/>';
    }

    private function matahari(callable $acak, array $suasana, int $lebar, int $cakrawala): string
    {
        $x = $acak((int) ($lebar * 0.18), (int) ($lebar * 0.82));
        $y = $acak((int) ($cakrawala * 0.22), (int) ($cakrawala * 0.62));
        $r = $acak((int) ($lebar * 0.05), (int) ($lebar * 0.085));

        return '<circle cx="'.$x.'" cy="'.$y.'" r="'.($r * 1.7).'" fill="'.$suasana['matahari'].'" opacity="0.18"/>'
            .'<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="'.$suasana['matahari'].'"/>';
    }

    private function awan(callable $acak, array $suasana, int $lebar, int $cakrawala): string
    {
        $keluaran = '';
        $jumlah = $acak(2, 4);

        for ($i = 0; $i < $jumlah; $i++) {
            $x = $acak(0, $lebar);
            $y = $acak((int) ($cakrawala * 0.12), (int) ($cakrawala * 0.5));
            $skala = $acak(70, 150) / 100;
            $opasitas = $acak(16, 34) / 100;

            $keluaran .= '<g fill="'.$suasana['awan'].'" opacity="'.$opasitas.'" '
                .'transform="translate('.$x.','.$y.') scale('.$skala.')">'
                .'<ellipse cx="0" cy="0" rx="90" ry="26"/>'
                .'<ellipse cx="-42" cy="8" rx="52" ry="20"/>'
                .'<ellipse cx="46" cy="10" rx="60" ry="18"/>'
                .'</g>';
        }

        return $keluaran;
    }

    /**
     * Dua sampai tiga lapis siluet pulau/bukit di belakang garis laut.
     */
    private function pulau(callable $acak, array $suasana, int $lebar, int $cakrawala): string
    {
        $keluaran = '';
        $lapis = $acak(2, 3);

        for ($i = 0; $i < $lapis; $i++) {
            $warna = $suasana['pulau'][min($i, count($suasana['pulau']) - 1)];
            $dasar = $cakrawala + 2;
            $tinggiPuncak = $acak((int) ($cakrawala * 0.16), (int) ($cakrawala * 0.42)) - $i * 12;
            $geser = $acak(-(int) ($lebar * 0.2), (int) ($lebar * 0.2));

            $titik = [];
            $jumlahPuncak = $acak(2, 4);
            $langkah = $lebar / ($jumlahPuncak + 1);

            $titik[] = '-40,'.$dasar;
            for ($p = 1; $p <= $jumlahPuncak; $p++) {
                $px = (int) ($langkah * $p) + $geser;
                $py = $dasar - $acak((int) ($tinggiPuncak * 0.45), $tinggiPuncak);
                $lebarPuncak = (int) ($langkah * ($acak(45, 80) / 100));

                $titik[] = ($px - $lebarPuncak).','.$dasar;
                $titik[] = $px.','.$py;
                $titik[] = ($px + $lebarPuncak).','.$dasar;
            }
            $titik[] = ($lebar + 40).','.$dasar;

            $keluaran .= '<polygon points="'.implode(' ', $titik).'" fill="'.$warna.'" opacity="'
                .(0.9 - $i * 0.08).'"/>';
        }

        return $keluaran;
    }

    private function laut(string $id, int $lebar, int $tinggi, int $cakrawala): string
    {
        return '<rect x="0" y="'.$cakrawala.'" width="'.$lebar.'" height="'.($tinggi - $cakrawala)
            .'" fill="url(#laut-'.$id.')"/>';
    }

    /**
     * Lapisan ombak putih di permukaan laut.
     */
    private function ombak(callable $acak, int $lebar, int $tinggi, int $cakrawala): string
    {
        $keluaran = '';
        $sisa = $tinggi - $cakrawala;
        $jumlah = 4;

        for ($i = 0; $i < $jumlah; $i++) {
            $y = $cakrawala + (int) ($sisa * (0.18 + $i * 0.2));
            $amplitudo = $acak(10, 26);
            $opasitas = round(0.10 + $i * 0.06, 2);

            $d = 'M -20 '.$y;
            $langkah = (int) ($lebar / 4);
            for ($x = -20; $x < $lebar + 40; $x += $langkah) {
                $d .= ' q '.(int) ($langkah / 2).' -'.$amplitudo.' '.$langkah.' 0';
            }
            $d .= ' L '.($lebar + 40).' '.$tinggi.' L -20 '.$tinggi.' Z';

            $keluaran .= '<path d="'.$d.'" fill="#FFFFFF" opacity="'.$opasitas.'"/>';
        }

        return $keluaran;
    }

    /**
     * Siluet di paling depan: pohon kelapa, perahu, atau karang.
     */
    private function latarDepan(callable $acak, array $suasana, int $lebar, int $tinggi): string
    {
        $warna = $suasana['pulau'][count($suasana['pulau']) - 1];
        $pilihan = $acak(0, 2);

        if ($pilihan === 0) {
            // Pohon kelapa di sisi kiri atau kanan
            $kiri = $acak(0, 1) === 0;
            $x = $kiri ? (int) ($lebar * 0.12) : (int) ($lebar * 0.88);
            $y = $tinggi;
            $h = $acak((int) ($tinggi * 0.42), (int) ($tinggi * 0.6));
            $lengkung = $kiri ? -1 : 1;

            // Pelepah menyebar seperti kipas ke arah atas, bukan melingkar penuh
            $daun = '';
            $jumlahDaun = 6;
            for ($i = 0; $i < $jumlahDaun; $i++) {
                $sudut = -168 + $i * (156 / ($jumlahDaun - 1));
                $panjang = 96 + $acak(0, 34);
                $daun .= '<path d="M 0 0 q '.$panjang.' -26 '.($panjang * 2).' 6 '
                    .'q -'.$panjang.' -8 -'.($panjang * 2).' 8 Z" fill="'.$warna.'" '
                    .'transform="rotate('.round($sudut, 1).')"/>';
            }

            return '<g fill="'.$warna.'">'
                .'<path d="M '.$x.' '.$y.' q '.(18 * $lengkung).' -'.(int) ($h / 2).' '
                .(44 * $lengkung).' -'.$h.' l 26 8 q -'.(30 * $lengkung).' '.(int) ($h / 2).' -'
                .(40 * $lengkung).' '.$h.' Z"/>'
                .'<g transform="translate('.($x + 50 * $lengkung).','.($y - $h).')">'.$daun
                .'<circle cx="0" cy="4" r="14"/></g>'
                .'</g>';
        }

        if ($pilihan === 1) {
            // Perahu kecil di permukaan laut
            $x = $acak((int) ($lebar * 0.2), (int) ($lebar * 0.8));
            $y = (int) ($tinggi * ($acak(72, 86) / 100));
            $s = $acak(70, 120) / 100;

            return '<g fill="'.$warna.'" transform="translate('.$x.','.$y.') scale('.$s.')">'
                .'<path d="M -70 0 L 70 0 L 48 26 L -48 26 Z"/>'
                .'<rect x="-4" y="-72" width="8" height="72"/>'
                .'<path d="M 6 -68 L 62 -8 L 6 -8 Z"/>'
                .'</g>';
        }

        // Karang / batu di tepi bawah
        $x = $acak(0, 1) === 0 ? (int) ($lebar * 0.1) : (int) ($lebar * 0.9);
        $y = $tinggi + 10;
        $r = $acak((int) ($lebar * 0.06), (int) ($lebar * 0.11));

        return '<g fill="'.$warna.'">'
            .'<ellipse cx="'.$x.'" cy="'.$y.'" rx="'.$r * 1.6.'" ry="'.$r.'"/>'
            .'<ellipse cx="'.($x + $r).'" cy="'.($y + 6).'" rx="'.$r.'" ry="'.$r * 0.7.'"/>'
            .'</g>';
    }
}
