<?php

namespace App\Support;

use App\Models\JejakAudit;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Melepas kursi yang ditahan pemesanan yang tidak pernah membayar.
 *
 * Sebelum ini tidak ada apa pun yang melepasnya. Satu pendaftaran di data
 * nyata menahan 46 kursi selama dua minggu tanpa satu rupiah pun masuk — dan
 * sejak kuota berlaku, kursi seperti itu membuat admin melihat "penuh" pada
 * trip yang sebenarnya kosong.
 *
 * Halaman Ketentuan Pembayaran bahkan sudah menjanjikannya sejak lama:
 * "kursi atau armada otomatis dilepas kembali untuk pemesan lain tanpa
 * pemberitahuan ulang". Kelas ini yang akhirnya menepatinya.
 */
class LepaskanKursiTertahan
{
    /**
     * @return array{diperiksa: int, dilepas: int, kode: array<int, string>}
     */
    public static function jalankan(bool $percobaan = false): array
    {
        $jam = (int) config('orcha.pembayaran.dp_lepas_jam', 72);
        $batas = now()->subHours($jam);

        $calon = PendaftaranOpenTrip::query()
            /*
             | Hanya yang BELUM membayar sama sekali.
             |
             | 'dp_masuk' dan 'lunas' jelas sudah. Yang perlu dijaga
             | 'dihubungi': admin sudah bicara dengan orangnya, dan itu justru
             | pemesanan yang paling hidup — tetapi tanpa uang masuk, kursinya
             | tetap kursi yang tidak bisa dijual. Ia ikut, hanya dengan
             | penjagaan di bawah.
             */
            ->whereIn('status', ['baru', 'dihubungi'])
            ->where('created_at', '<=', $batas)

            /*
             | Yang pernah mengirim bukti TIDAK disentuh, apa pun statusnya —
             | termasuk yang buktinya ditolak.
             |
             | Orang yang sudah mengunggah bukti sedang berusaha membayar.
             | Buktinya bisa saja salah nominal atau salah rekening, dan
             | jawabannya memperbaiki bukti itu, bukan kehilangan kursinya
             | diam-diam. Yang dilepas hanya yang benar-benar tidak pernah
             | muncul lagi setelah mendaftar.
             */
            ->whereDoesntHave('konfirmasiPembayaran')

            ->with('paket')
            ->get();

        $dilepas = [];

        foreach ($calon as $daftar) {
            if ($percobaan) {
                $dilepas[] = $daftar->kode;

                continue;
            }

            $catatan = trim(($daftar->catatan ? $daftar->catatan."\n" : '')
                .'[Sistem] Kursi dilepas otomatis pada '.now()->translatedFormat('j F Y, H:i')
                ." — tidak ada pembayaran dalam {$jam} jam sejak pendaftaran.");

            $daftar->update(['status' => 'batal', 'catatan' => $catatan]);

            self::catat($daftar, $jam);
            self::kabari($daftar);

            $dilepas[] = $daftar->kode;
        }

        return [
            'diperiksa' => $calon->count(),
            'dilepas' => count($dilepas),
            'kode' => $dilepas,
        ];
    }

    /**
     * Masuk jejak audit seperti perubahan status lainnya.
     *
     * Pelakunya ditulis "Sistem", bukan dikosongkan: yang membaca jejak nanti
     * perlu tahu bahwa pembatalan ini bukan keputusan seseorang — kalau tidak,
     * ia akan mencari admin yang melakukannya dan tidak menemukan siapa pun.
     */
    private static function catat(PendaftaranOpenTrip $daftar, int $jam): void
    {
        $permintaan = Request::create('/', 'POST');
        $permintaan->attributes->set('admin_pemanggil', 'Sistem');

        JejakAudit::catat(
            $permintaan,
            'lepas kursi otomatis',
            "Kursi dilepas otomatis — tidak ada pembayaran dalam {$jam} jam. "
                .$daftar->jumlah_peserta.' kursi kembali tersedia.',
            $daftar->kode,
            'baru',
            'batal',
        );
    }

    /**
     * Pelanggan dikabari, dan kabarnya membuka jalan kembali.
     *
     * Halaman ketentuan menulis "tanpa pemberitahuan ulang" — itu soal
     * kewajiban, bukan larangan. Orang yang kursinya lepas karena lupa
     * mentransfer adalah orang yang sudah memilih trip dan mengisi seluruh
     * formulirnya; menutup pintunya tanpa sepatah kata membuang calon yang
     * paling dekat dari semua calon yang ada.
     */
    private static function kabari(PendaftaranOpenTrip $daftar): void
    {
        // Surel pelanggan boleh kosong — nomor WhatsApp yang wajib di formulir.
        // Salinan pelanggannya diabaikan sendiri oleh KirimPemberitahuan bila
        // alamatnya tidak sah; surat ke kantor tetap jalan.
        $rincian = [
            'Kode pemesanan' => $daftar->kode,
            'Trip' => $daftar->nama_paket ?: '—',
            'Jumlah peserta' => $daftar->jumlah_peserta.' orang',
            'Didaftarkan' => $daftar->created_at?->translatedFormat('j F Y, H:i'),
        ];

        try {
            /*
             | Kantor ikut menerima salinannya.
             |
             | Kursi yang kembali tersedia adalah kabar yang bisa langsung
             | dipakai: ada trip yang tadinya penuh dan sekarang bisa
             | ditawarkan lagi ke orang yang sempat ditolak. Jejak audit
             | mencatatnya juga, tetapi jejak dibaca saat ditelusuri —
             | surat datang saat kejadiannya.
             */
            KirimPemberitahuan::kirim(
                'Kursi Dilepas Otomatis',
                $daftar->kode,
                $rincian,
                pelanggan: new SalinanPelanggan(
                    email: $daftar->email,
                    judul: 'Pemesanan Anda Kami Lepas',
                    langkah: 'Kursi untuk '.($daftar->nama_paket ?: 'trip ini').' kami lepaskan kembali '
                        .'karena belum ada pembayaran yang masuk. Masih ingin ikut? Balas pesan ini atau '
                        .'hubungi kami lewat WhatsApp — selama kursinya masih ada, kami bantu daftarkan ulang.',
                    rincian: $rincian,
                ),
            );
        } catch (\Throwable $e) {
            // Kabar yang gagal terkirim tidak boleh menggagalkan pelepasan
            // kursinya — kursinya yang lebih mendesak, dan kegagalannya tetap
            // tercatat untuk ditelusuri.
            Log::error('Kabar pelepasan kursi gagal dikirim', [
                'kode' => $daftar->kode,
                'galat' => $e->getMessage(),
            ]);
        }
    }
}
