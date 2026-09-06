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

        /*
         | Pembayaran lewat gerbang punya DUA angka, dan keduanya harus disebut.
         |
         | Yang keluar dari rekening pelanggan Rp 858.889; yang masuk ke
         | tagihannya Rp 858.000. Menyebut yang kecil saja membuat surat ini
         | seolah mengurangi jumlah yang ia bayar — dan yang paling mungkin
         | menyadarinya adalah pelanggan yang mencocokkan dengan mutasinya
         | sendiri, yaitu orang yang paling tidak ingin kita bingungkan.
         */
        $gerbang = $bayar->percobaanDoku;

        $rincian = array_filter([
            'Kode pesanan' => $bayar->kode,
            'Jenis' => $bayar->jenis_label,
            'Nominal dibayar' => $gerbang
                ? 'Rp '.number_format((int) $gerbang->nominal, 0, ',', '.')
                : $bayar->nominal_formatted,
            'Masuk ke tagihan' => $gerbang ? $bayar->nominal_formatted : null,
            'Kode unik' => $gerbang
                ? number_format((int) $gerbang->kode_unik, 0, ',', '.').' (penanda, bukan cicilan)'
                : null,
            // "Tanggal transfer" hanya benar untuk uang yang memang ditransfer
            // sendiri oleh pelanggan.
            ($gerbang ? 'Tanggal pembayaran' : 'Tanggal transfer') => $bayar->tanggal_transfer?->translatedFormat('j F Y'),
            'Status' => $bayar->status_label,
        ] + self::barisAngsuran($pesanan), fn ($nilai) => $nilai !== null);

        $catatanUntukPelanggan = self::catatanUntukPelanggan($bayar);

        $berkas = BerkasKwitansi::buat(
            'Tanda Terima Pembayaran',
            $bayar->kode,
            $rincian,
            $catatanUntukPelanggan,
            $gerbang ? 'Rp '.number_format((int) $gerbang->nominal, 0, ',', '.') : $bayar->nominal_formatted,
            'Nominal diterima',
            $cap,
            tagihan: $tagihan,
            lewatGerbang: (bool) $gerbang,
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

    /**
     * Baris jadwal angsuran untuk tabel rincian — surat maupun kwitansi PDF.
     *
     * Kosong untuk pesanan biasa, jadi tidak ada yang berubah bagi mayoritas.
     *
     * Ada di TABEL, bukan cuma di kalimat langkah berikutnya, karena tabel
     * itulah yang dibuka lagi berminggu-minggu kemudian saat pelanggan lupa
     * kapan termin berikutnya jatuh tempo. Kalimat dibaca sekali; tabel
     * dicari.
     *
     * @return array<string, string|null>
     */
    private static function barisAngsuran(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): array
    {
        $ringkas = RencanaAngsuran::ringkasUntukPelanggan($pesanan?->kode);

        if (! $ringkas) {
            return [];
        }

        return [
            'Angsuran' => $ringkas['lunas'].' dari '.$ringkas['jumlah'].' termin lunas',
            'Termin berikutnya' => $ringkas['label'].' · '.$ringkas['kurang_teks']
                .' · jatuh tempo '.$ringkas['jatuh_tempo_teks'],
        ];
    }

    /**
     * Bagian catatan admin yang boleh dibaca pelanggan.
     *
     * Layar admin menjanjikan kolom itu "ikut terbaca oleh admin lain" —
     * kalimat yang mengundang orang menuliskan hal-hal internal: dugaan,
     * pengingat, nama rekan yang harus mengecek. Selama isinya diteruskan ke
     * kwitansi, janji itu bohong, dan yang menanggung akibatnya adalah
     * pelanggan yang membaca catatan yang tidak pernah ditujukan padanya.
     *
     * PENOLAKAN adalah satu-satunya pengecualian, dan itu memang disengaja:
     * di sana catatannya JUSTRU alasan yang harus ia baca supaya tahu apa yang
     * perlu diperbaiki. Layar admin menyebutkannya terang-terangan tepat saat
     * "Ditolak" dipilih.
     *
     * Untuk pembayaran gerbang, catatannya lagipula tidak berguna baginya — ia
     * berisi nomor tagihan dan keterangan kode unik untuk rekonsiliasi admin,
     * sementara angka yang ia butuhkan sudah berdiri sendiri sebagai baris
     * rincian di atasnya.
     */
    public static function catatanUntukPelanggan(KonfirmasiPembayaran $bayar): ?string
    {
        return $bayar->status === 'ditolak' ? $bayar->catatan_admin : null;
    }

    private static function langkahDiterima(array $tagihan, PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): string
    {
        if ($tagihan === []) {
            return 'Terima kasih, pembayaran Anda sudah kami terima dan tercatat.';
        }

        if ($tagihan['lunas']) {
            return 'Terima kasih, pembayaran Anda sudah lunas. Tidak ada sisa yang perlu dibayar lagi.';
        }

        /*
         | Pesanan berangsur punya tenggatnya sendiri, dan menyebut tenggat
         | umum kepadanya adalah menagih hal yang tidak kami janjikan.
         |
         | Sebelum ini surat tanda terima selalu menulis "Sisa yang perlu
         | dilunasi Rp 4.004.000, paling lambat H-5 sebelum berangkat" — untuk
         | pelanggan yang layar pembayarannya justru menampilkan tiga termin
         | dengan tanggalnya masing-masing. Dua janji yang bertentangan, dari
         | sistem yang sama, kepada orang yang sedang kesulitan keuangan.
         |
         | Yang mana yang dipercaya pelanggan tidak bisa ditebak, dan keduanya
         | membuatnya menghubungi kami untuk bertanya mana yang benar.
         */
        if ($angsuran = self::langkahAngsuran($tagihan, $pesanan)) {
            return $angsuran;
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

    /**
     * Kalimat langkah berikutnya untuk pesanan yang punya rencana angsuran.
     *
     * null berarti pesanan ini memang tidak berangsur — pemanggil lalu memakai
     * kalimat pelunasan biasa. Fakta angkanya datang dari RencanaAngsuran,
     * bukan dihitung ulang di sini: berkas pendaftaran menyusun kalimat yang
     * sama dalam bentuk HTML, dan dua hitungan terhadap satu jadwal akan
     * berbeda suatu hari.
     */
    private static function langkahAngsuran(array $tagihan, PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): ?string
    {
        $ringkas = RencanaAngsuran::ringkasUntukPelanggan($pesanan?->kode);

        if (! $ringkas) {
            return null;
        }

        /*
         | Kemajuannya disebut lebih dulu.
         |
         | Yang membaca surat ini baru saja mengeluarkan uang, dan pertanyaan
         | pertamanya bukan "berapa lagi" melainkan "sudah sampai mana saya".
         | Angka itu juga yang membuat sisa tagihan terbaca sebagai jalan yang
         | sedang ditempuh, bukan sebagai tumpukan yang tidak berkurang.
         */
        $kalimat = 'Terima kasih, pembayaran Anda sudah kami terima — '
            .$ringkas['lunas'].' dari '.$ringkas['jumlah'].' termin lunas. '
            .$ringkas['label'].' berikutnya '.$ringkas['kurang_teks']
            .', jatuh tempo '.$ringkas['jatuh_tempo_teks'].'. ';

        if ($ringkas['telat']) {
            // Disebut apa adanya, tetapi tanpa menghakimi: yang menunggak
            // sudah tahu ia menunggak, dan kalimat yang menyudutkan membuatnya
            // menghindari kami — persis kebalikan dari yang kita butuhkan.
            $kalimat .= 'Termin ini sudah lewat jatuh temponya; hubungi kami bila perlu '
                .'penyesuaian jadwal. ';
        }

        return $kalimat.'Sisa seluruhnya '.$tagihan['sisa_teks'].'.';
    }
}
