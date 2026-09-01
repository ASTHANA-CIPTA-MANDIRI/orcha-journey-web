<?php

namespace App\Support;

use App\Models\JejakAudit;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\DaftarTunggu;
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
            self::kabariAntrean($daftar);

            $dilepas[] = $daftar->kode;
        }

        return [
            'diperiksa' => $calon->count(),
            'dilepas' => count($dilepas),
            'kode' => $dilepas,
        ];
    }

    /**
     * Mengabari yang menunggu bahwa kursinya terbuka.
     *
     * Inilah yang mengubah pelepasan kursi dari sekadar pembersihan jadi
     * penjualan. Kursi yang kembali tersedia tidak berguna kalau tidak ada
     * yang tahu — dan yang paling mungkin langsung mengambilnya adalah orang
     * yang sudah menyatakan minat pada trip yang sama.
     *
     * Yang dikabari HANYA sebanyak kursi yang benar-benar terbuka, urut dari
     * yang paling lama menunggu. Mengabari seluruh antrean untuk dua kursi
     * membuat sebagian besar dari mereka datang ke kursi yang sudah diambil
     * orang lain — dan kekecewaan itu lebih mahal daripada satu email yang
     * tidak terkirim.
     */
    private static function kabariAntrean(PendaftaranOpenTrip $daftar): void
    {
        if (! $daftar->travel_package_id) {
            return;
        }

        $terbuka = (int) $daftar->jumlah_peserta;

        $antre = DaftarTunggu::query()
            ->where('travel_package_id', $daftar->travel_package_id)
            ->belumDikabari()
            ->get()
            // Rombongan yang lebih besar daripada kursi yang terbuka dilewati,
            // bukan dikabari setengah: mengabarinya berarti menawarkan sesuatu
            // yang belum tentu bisa ditepati.
            ->filter(fn (DaftarTunggu $a) => $a->jumlah_peserta <= $terbuka);

        foreach ($antre as $satu) {
            if ($terbuka <= 0) {
                break;
            }

            $terbuka -= $satu->jumlah_peserta;
            $satu->update(['dikabari_pada' => now()]);

            if (blank($satu->email)) {
                // Tanpa surel ia tidak bisa dikabari lewat jalur ini, tetapi
                // tetap ditandai supaya tim melihatnya di daftar dan bisa
                // menghubunginya lewat WhatsApp.
                continue;
            }

            try {
                KirimPemberitahuan::kirim(
                    'Kursi Terbuka — Ada yang Menunggu',
                    $daftar->kode,
                    [
                        'Trip' => $daftar->nama_paket ?: '—',
                        'Yang menunggu' => $satu->nama.' · '.$satu->jumlah_peserta.' orang',
                        'WhatsApp' => $satu->whatsapp,
                    ],
                    pelanggan: new SalinanPelanggan(
                        email: $satu->email,
                        judul: 'Kursi yang Anda Tunggu Sudah Terbuka',
                        langkah: 'Ada kursi yang terbuka di '.($daftar->nama_paket ?: 'trip yang Anda tunggu')
                            .". Kami kabari Anda lebih dulu karena Anda yang paling lama menunggu.\n\n"
                            .'Kursinya belum dikunci untuk siapa pun — yang mendaftar dan membayar '
                            .'lebih dulu yang mendapatkannya.',
                        tautan: route('pendaftaran-open-trip', ['paket' => $daftar->paket?->uuid]),
                        labelTautan: 'Daftar Sekarang',
                    ),
                );
            } catch (\Throwable $e) {
                Log::error('Kabar kursi terbuka gagal dikirim', [
                    'antrean' => $satu->id,
                    'galat' => $e->getMessage(),
                ]);
            }
        }
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
