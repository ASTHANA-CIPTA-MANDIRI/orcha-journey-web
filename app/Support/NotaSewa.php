<?php

namespace App\Support;

use App\Models\SewaKendaraan\PenyewaanKendaraan;

/**
 * Baris nota sewa: perincian biaya sewanya, lalu tiap denda yang benar-benar ada.
 *
 * Denda yang nol tidak ditampilkan — nota yang penuh baris "Rp 0" membuat yang
 * benar-benar ditagih jadi sulit ditemukan.
 *
 * Dipakai dua kali dan oleh dua pihak: berkas yang dikirim otomatis begitu
 * pesanan masuk, dan kwitansi yang diunduh admin dari lemon. Dulu tinggal di
 * dalam pengendali API, sehingga formulir publik tidak bisa memakainya tanpa
 * memanggil pengendali — dan akibatnya berkas pertama yang diterima penyewa
 * satu-satunya yang tidak punya perincian.
 */
class NotaSewa
{
    /** @return array<string, mixed> */
    public static function untuk(PenyewaanKendaraan $penyewaan): array
    {
        $rp = fn ($angka) => 'Rp '.number_format((int) $angka, 0, ',', '.');

        $baris = self::barisSewa($penyewaan, $rp);

        foreach ([
            ['Denda keterlambatan', $penyewaan->denda_keterlambatan, $penyewaan->terlambat
                ? floor($penyewaan->terlambat_menit / 60).' jam '.($penyewaan->terlambat_menit % 60).' menit lewat tenggat'
                : null],
            // Bagian mana yang ditagih diambil dari rincian yang sudah
            // ditetapkan admin lebih dulu. Perbandingan kondisi hanya dipakai
            // bila belum ada ketetapan — sesudah unit diperiksa ulang,
            // perbandingan itu kosong, dan kwitansi tanpa keterangan bagian
            // adalah kwitansi yang tidak bisa dijelaskan ke penyewa.
            ['Denda kerusakan', $penyewaan->denda_kerusakan, collect($penyewaan->rincian_denda ?: $penyewaan->kerusakan_baru)
                ->pluck('bagian')->filter()->implode(', ') ?: null],
            ['Denda lain', $penyewaan->denda_lain, null],
        ] as [$label, $nilai, $keterangan]) {
            if ((int) $nilai <= 0) {
                continue;
            }

            $baris[] = [
                'label' => $label,
                'keterangan' => $keterangan,
                'nilai' => $rp($nilai),
                'denda' => true,
            ];
        }

        /*
         | Uang yang sudah diterima ikut dikurangkan.
         |
         | Tanpa ini nota berhenti di total dan menagih seluruhnya — padahal
         | penyewa sudah membayar uang muka. Yang dibacanya: diminta membayar
         | DP untuk kedua kalinya. Itu bukan salah paham yang bisa diluruskan
         | belakangan; nota adalah dokumen yang ia pegang.
         |
         | Hanya yang bukti transfernya SUDAH DITERIMA. Yang masih menunggu
         | dicek belum uang, dan mengurangkannya berarti mengakui pembayaran
         | berdasarkan gambar yang belum diperiksa siapa pun.
         */
        $diterima = collect(TagihanPesanan::diterimaPerJenis($penyewaan));
        $sudah = (int) $diterima->sum('nominal');
        $sisa = max(0, (int) $penyewaan->total_tagihan - $sudah);

        return [
            'baris' => $baris,
            'total' => $rp($penyewaan->total_tagihan),
            'label_total' => 'Total tagihan',
            'pembayaran' => $diterima->map(fn ($bayar) => [
                'label' => $bayar['label'],
                'keterangan' => $bayar['berkas'] > 1 ? $bayar['berkas'].' bukti transfer' : null,
                'nilai' => $rp($bayar['nominal']),
            ])->all(),
            'sudah' => $sudah,
            'sisa' => $rp($sisa),
            'lunas' => $sudah > 0 && $sisa <= 0,
        ];
    }

    /**
     * Bagian biaya sewanya sendiri, sebisa mungkin dirinci.
     *
     * Perincian yang dipakai adalah yang DISALIN saat pesanan dibuat, bukan
     * hitungan ulang dari tarif unit hari ini: tarif berubah, dan perincian
     * yang jumlahnya tidak sama dengan total yang dipesan lebih membingungkan
     * daripada satu baris tanpa penjelasan.
     *
     * Pesanan lama — dibuat sebelum perinciannya ikut disimpan — tetap dapat
     * satu baris seperti sedia kala. Nota mereka tidak berubah bentuk.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function barisSewa(PenyewaanKendaraan $penyewaan, \Closure $rp): array
    {
        $rincian = collect($penyewaan->rincian_estimasi ?: []);

        // Jumlahnya harus sama dengan yang dipesan. Kalau tidak, perinciannya
        // milik hitungan lain dan menampilkannya berarti menagih dua angka
        // yang berbeda di berkas yang sama.
        $cocok = $rincian->isNotEmpty()
            && $rincian->sum('jumlah') === (int) $penyewaan->estimasi_biaya;

        if (! $cocok) {
            return [[
                'label' => 'Biaya sewa',
                'keterangan' => $penyewaan->durasi_label.' · '.($penyewaan->dengan_sopir ? 'dengan sopir' : 'lepas kunci'),
                'nilai' => $rp($penyewaan->estimasi_biaya),
            ]];
        }

        return $rincian->map(fn ($pos) => [
            'label' => $pos['label'],
            'keterangan' => $pos['keterangan'] ?? null,
            'nilai' => $rp($pos['jumlah']),
        ])->all();
    }
}
