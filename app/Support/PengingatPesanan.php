<?php

namespace App\Support;

use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\AngsuranTermin;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Services\DokuCheckout;
use Illuminate\Support\Facades\Log;

/**
 * Dua surat yang selama ini tidak pernah dikirim siapa pun.
 *
 * Batas pelunasan H-5 tertulis di enam halaman publik dan di syarat &
 * ketentuan. Sistem menghitung tanggalnya, menampilkannya di halaman paket dan
 * di formulir pendaftaran — lalu tidak melakukan apa pun saat tanggal itu
 * tiba. Janjinya benar-benar tanpa gigi: yang sudah membayar uang muka tidak
 * pernah ditagih sisanya, dan kursinya tidak bisa dilepas juga karena orangnya
 * memang sudah membayar. Uangnya bukan hilang; cuma tidak pernah diminta.
 *
 * Yang kedua lebih sederhana tetapi lebih sering terasa: tidak ada yang
 * memberi tahu peserta jam berapa berkumpul dan menunggu di mana. Titik jemput
 * sudah tersimpan per orang sejak mereka mendaftar, tetapi tidak pernah
 * dikirim balik. Itu yang membuat WhatsApp penuh pertanyaan yang sama pada
 * H-1 malam.
 *
 * KEDUANYA DIKIRIM SEKALI SAJA, ditandai di basis data. Cron gagal lalu
 * diulang, jam server bergeser, atau seseorang menjalankan perintahnya manual
 * untuk memeriksa — dan tanpa penanda, pelanggan menerima surat yang sama tiga
 * kali. Untuk penagihan, kiriman ganda lebih buruk daripada tidak terkirim:
 * surat yang menagih hal yang sudah dibayar membuat orang berhenti membaca
 * surat kita sama sekali.
 */
class PengingatPesanan
{
    /**
     * @return array{pelunasan: array<int, string>, briefing: array<int, string>}
     */
    public static function jalankan(bool $percobaan = false): array
    {
        return [
            'pelunasan' => self::tagihPelunasan($percobaan),
            'angsuran' => self::tagihAngsuran($percobaan),
            'angsuran_telat' => self::laporkanAngsuranTelat($percobaan),
            'briefing' => self::kirimBriefing($percobaan),
        ];
    }

    /**
     * Menagih sisa pembayaran menjelang batas pelunasan.
     *
     * @return array<int, string> kode yang dikirimi
     */
    private static function tagihPelunasan(bool $percobaan): array
    {
        $jarak = (int) config('orcha.pembayaran.pelunasan_hari_sebelum', 5)
            + (int) config('orcha.pengingat.pelunasan_hari_sebelum_batas', 3);

        /*
         | Yang ditagih HANYA yang sudah membayar uang muka.
         |
         | Yang masih 'baru' belum membayar sama sekali, dan kursinya sudah
         | diurus LepaskanKursiTertahan — menagih pelunasan kepada orang yang
         | belum membayar sepeser pun terbaca seperti sistem yang tidak tahu
         | apa-apa soal pesanannya sendiri.
         |
         | Yang 'lunas' jelas tidak, dan yang 'batal' apalagi.
         */
        $calon = PendaftaranOpenTrip::query()
            ->where('status', 'dp_masuk')
            ->whereNull('pengingat_pelunasan_pada')
            ->whereNotNull('tanggal_berangkat')

            /*
             | Jendela, bukan tanggal persis.
             |
             | Mencocokkan satu tanggal berarti pendaftaran yang tanggalnya
             | terlewat — cron mati sehari, hosting sedang bermasalah —
             | tidak pernah ditagih sama sekali. Batas bawahnya hari ini:
             | yang tanggal berangkatnya sudah lewat tidak ditagih lagi
             | lewat jalur ini.
             */
            ->whereDate('tanggal_berangkat', '<=', now()->addDays($jarak)->toDateString())
            ->whereDate('tanggal_berangkat', '>=', now()->toDateString())

            ->with('paket')
            ->get();

        $terkirim = [];

        foreach ($calon as $daftar) {
            if (! $percobaan) {
                // Ditandai LEBIH DULU, sebelum suratnya berangkat.
                //
                // Kalau penandaannya menyusul dan pengiriman melempar galat di
                // tengah jalan, perintah berikutnya menagih orang yang sama
                // lagi. Surat yang gagal terkirim tercatat di log dan bisa
                // dikirim ulang dengan sengaja; surat ganda tidak bisa ditarik.
                $daftar->update(['pengingat_pelunasan_pada' => now()]);

                self::suratPelunasan($daftar);
            }

            $terkirim[] = $daftar->kode;
        }

        return $terkirim;
    }

    /**
     * Mengingatkan termin angsuran yang akan jatuh tempo.
     *
     * Penandanya di TERMIN, bukan di rencananya. Penanda di tingkat rencana
     * hanya bisa menjawab "sudah pernah diingatkan" — dan akibatnya pelanggan
     * diingatkan sekali lalu tidak pernah lagi sampai lunas, padahal justru
     * jatuh tempo kedua dan ketiga yang paling mudah terlupa.
     *
     * @return array<int, string> kode yang dikirimi
     */
    private static function tagihAngsuran(bool $percobaan): array
    {
        $hari = (int) config('orcha.pembayaran.angsuran.ingatkan_hari_sebelum', 3);

        /*
         | Jendela, bukan tanggal persis — sama seperti pengingat pelunasan.
         | Cron yang mati sehari tidak boleh membuat satu termin terlewat
         | selamanya.
         */
        $calon = AngsuranTermin::query()
            ->whereNull('diingatkan_pada')
            ->whereDate('jatuh_tempo', '<=', now()->addDays($hari)->toDateString())
            ->whereDate('jatuh_tempo', '>=', now()->toDateString())
            ->whereHas('angsuran', fn ($q) => $q->whereNull('dibatalkan_pada'))
            ->with('angsuran.termin')
            ->get();

        $terkirim = [];

        foreach ($calon as $termin) {
            $posisi = collect(RencanaAngsuran::posisi($termin->angsuran))
                ->firstWhere('urutan', $termin->urutan);

            // Termin yang sudah tertutup pembayaran tidak perlu ditagih.
            // Pelanggan yang membayar lebih awal justru yang paling tidak
            // pantas menerima surat tagihan.
            if (! $posisi || $posisi['status'] === 'lunas') {
                continue;
            }

            $pesanan = $termin->angsuran->pesanan();

            if (! $pesanan instanceof PendaftaranOpenTrip) {
                continue;
            }

            if (! $percobaan) {
                // Ditandai lebih dulu, sama alasannya dengan pengingat
                // pelunasan: surat ganda tidak bisa ditarik.
                $termin->update(['diingatkan_pada' => now()]);

                self::suratAngsuran($pesanan, $termin, (int) $posisi['kurang']);
            }

            $terkirim[] = $termin->angsuran->kode.' termin '.$termin->urutan;
        }

        return $terkirim;
    }

    /**
     * Melaporkan termin yang sudah lewat jatuh tempo ke kotak kantor.
     *
     * Ke KANTOR, bukan ke pelanggan. Yang telat sudah menerima pengingatnya
     * sebelum jatuh tempo; surat kedua yang isinya menagih lagi tidak menambah
     * kemampuannya membayar. Yang belum tahu justru admin — dan tanpa laporan
     * ini ia baru menyadarinya saat membuka pesanannya satu per satu.
     *
     * Sekali per termin, ditandai supaya tidak berulang tiap hari.
     *
     * @return array<int, string>
     */
    private static function laporkanAngsuranTelat(bool $percobaan): array
    {
        $calon = AngsuranTermin::query()
            ->whereNull('dilaporkan_telat_pada')
            ->whereDate('jatuh_tempo', '<', now()->toDateString())
            ->whereHas('angsuran', fn ($q) => $q->whereNull('dibatalkan_pada'))
            ->with('angsuran.termin')
            ->get();

        $dilaporkan = [];

        foreach ($calon as $termin) {
            $posisi = collect(RencanaAngsuran::posisi($termin->angsuran))
                ->firstWhere('urutan', $termin->urutan);

            if (! $posisi || $posisi['status'] !== 'telat') {
                continue;
            }

            $pesanan = $termin->angsuran->pesanan();

            if (! $pesanan instanceof PendaftaranOpenTrip) {
                continue;
            }

            if (! $percobaan) {
                $termin->update(['dilaporkan_telat_pada' => now()]);

                KirimPemberitahuan::kirim(
                    'Angsuran Lewat Jatuh Tempo',
                    $termin->angsuran->kode,
                    [
                        'Pemesan' => $pesanan->nama,
                        'WhatsApp' => $pesanan->whatsapp,
                        'Termin' => 'Ke-'.$termin->urutan.' dari '.$termin->angsuran->jumlah_termin,
                        'Jatuh tempo' => $termin->jatuh_tempo->translatedFormat('j F Y'),
                        'Masih kurang' => 'Rp '.number_format((int) $posisi['kurang'], 0, ',', '.'),
                        'Berangkat' => $pesanan->tanggal_berangkat?->translatedFormat('j F Y'),
                    ],
                );
            }

            $dilaporkan[] = $termin->angsuran->kode.' termin '.$termin->urutan;
        }

        return $dilaporkan;
    }

    private static function suratAngsuran(
        PendaftaranOpenTrip $daftar,
        AngsuranTermin $termin,
        int $kurang,
    ): void {
        $urutan = $termin->urutan === 1
            ? 'Uang muka'
            : 'Angsuran ke-'.($termin->urutan - 1);

        $rincian = [
            'Kode pemesanan' => $daftar->kode,
            'Trip' => $daftar->nama_paket ?: '—',
            'Termin' => $urutan.' dari '.$termin->angsuran->jumlah_termin,
            'Jumlah' => 'Rp '.number_format($kurang, 0, ',', '.'),
            'Jatuh tempo' => $termin->jatuh_tempo->translatedFormat('j F Y'),
        ];

        self::kirim($daftar, 'Pengingat Angsuran Terkirim', $rincian, new SalinanPelanggan(
            email: $daftar->email,
            judul: $urutan.' — '.($daftar->nama_paket ?: 'Trip Anda'),

            /*
             | Menyebut angkanya, tanggalnya, dan sisa terminnya.
             |
             | Yang sedang mengangsur perlu tahu ini yang keberapa dari berapa.
             | Tanpa itu tiap surat terasa seperti tagihan baru yang tidak
             | pernah habis — dan itu justru yang membuat orang berhenti
             | membuka surelnya.
             */
            langkah: $urutan.' Anda sebesar Rp '.number_format($kurang, 0, ',', '.')
                .' jatuh tempo '.$termin->jatuh_tempo->translatedFormat('j F Y').".\n\n"
                .'Ini termin ke-'.$termin->urutan.' dari '.$termin->angsuran->jumlah_termin
                .'. Tekan tombol di bawah untuk membayar lewat bank, QRIS, atau dompet digital — '
                ."pembayarannya tercatat sendiri dalam hitungan detik.\n\n"
                .'Sedang kesulitan? Balas lewat WhatsApp sebelum jatuh temponya, jangan sesudah. '
                .'Yang bisa kami bantu adalah yang memberi tahu lebih dulu.',
            tautan: route('konfirmasi-pembayaran', ['kode' => $daftar->kode]),
            labelTautan: 'Bayar Sekarang',
        ));
    }

    /**
     * Briefing keberangkatan: jam, titik jemput, dan bawaan.
     *
     * @return array<int, string> kode yang dikirimi
     */
    private static function kirimBriefing(bool $percobaan): array
    {
        $hari = (int) config('orcha.pengingat.briefing_hari_sebelum', 1);

        $calon = PendaftaranOpenTrip::query()
            // Yang belum membayar sama sekali tidak dibriefing: ia belum punya
            // kursi, dan surat berisi "sampai jumpa besok" akan membuatnya
            // datang ke titik kumpul untuk sesuatu yang tidak dipesannya.
            ->whereIn('status', ['dp_masuk', 'lunas', 'berjalan'])
            ->whereNull('briefing_pada')
            ->whereDate('tanggal_berangkat', now()->addDays($hari)->toDateString())
            ->with('paket')
            ->get();

        $terkirim = [];

        foreach ($calon as $daftar) {
            if (! $percobaan) {
                $daftar->update(['briefing_pada' => now()]);

                self::suratBriefing($daftar);
            }

            $terkirim[] = $daftar->kode;
        }

        return $terkirim;
    }

    private static function suratPelunasan(PendaftaranOpenTrip $daftar): void
    {
        $tagihan = TagihanPesanan::untuk($daftar, hanyaDiterima: true);
        $sisa = (int) ($tagihan['sisa'] ?? 0);
        $batas = $daftar->paket?->batas_pelunasan;

        $rincian = [
            'Kode pemesanan' => $daftar->kode,
            'Trip' => $daftar->nama_paket ?: '—',
            'Berangkat' => $daftar->tanggal_berangkat?->translatedFormat('j F Y'),
            'Sisa pembayaran' => $sisa > 0 ? 'Rp '.number_format($sisa, 0, ',', '.') : '—',
            'Batas pelunasan' => $batas?->translatedFormat('j F Y') ?: '—',
        ];

        self::kirim($daftar, 'Pengingat Pelunasan Terkirim', $rincian, new SalinanPelanggan(
            email: $daftar->email,
            judul: 'Sisa Pembayaran '.($daftar->nama_paket ?: 'Trip Anda'),

            /*
             | Menyebut ANGKANYA dan APA YANG TERJADI kalau lewat.
             |
             | "Segera lakukan pelunasan" tidak menggerakkan siapa pun: yang
             | membacanya tidak tahu berapa, tidak tahu kapan, dan tidak tahu
             | akibatnya. Ketiganya ada di sini, dan akibatnya ditulis apa
             | adanya — kursinya bisa dilepas — karena itulah yang tertulis di
             | ketentuan yang sudah disetujuinya.
             */
            /*
             | Kalimatnya mengikuti jalur yang benar-benar berlaku.
             |
             | Sejak pembayaran publik lewat gerbang, "kirimkan buktinya"
             | menyuruh pelanggan mencari halaman unggah yang sudah tidak ada —
             | dan yang menuruti perintah itulah yang paling dirugikan.
             */
            langkah: $sisa > 0
                ? 'Sisa pembayaran Anda Rp '.number_format($sisa, 0, ',', '.')
                    .', paling lambat '.($batas?->translatedFormat('j F Y') ?: 'sebelum keberangkatan').".\n\n"
                    .(app(DokuCheckout::class)->aktif()
                        ? 'Tekan tombol di bawah untuk membayar lewat bank, QRIS, atau dompet digital. '
                            .'Pembayarannya tercatat sendiri dalam hitungan detik.'
                        : 'Kalau sudah ditransfer, kirimkan buktinya lewat halaman konfirmasi supaya '
                            .'kursinya kami kunci.')
                    .' Lewat dari batas itu, kursinya bisa kami lepas untuk pemesan lain.'
                : 'Menjelang batas pelunasan, kami cek ulang pembayaran Anda. '
                    .'Kalau menurut catatan Anda sudah lunas, abaikan surat ini — atau balas '
                    .'supaya kami periksa bersama.',

            rincian: $rincian,
            tautan: route('konfirmasi-pembayaran', ['kode' => $daftar->kode]),
            labelTautan: 'Kirim Bukti Pembayaran',
        ));
    }

    private static function suratBriefing(PendaftaranOpenTrip $daftar): void
    {
        /*
         | Titik jemputnya disusun per titik, bukan per orang.
         |
         | Rombongan sepuluh orang yang naik dari tiga titik berbeda menerima
         | tiga baris, bukan sepuluh — dan yang membacanya si pemesan, yang
         | perlu memastikan tiap temannya menunggu di tempat yang benar.
         */
        $perTitik = $daftar->jemput_per_titik;

        $jemput = $perTitik !== []
            ? collect($perTitik)
                ->map(fn (array $orang, string $titik) => $titik.' — '.implode(', ', $orang))
                ->implode(' | ')
            : ($daftar->titik_jemput ?: 'akan dikabari tim kami');

        $rincian = [
            'Kode pemesanan' => $daftar->kode,
            'Trip' => $daftar->nama_paket ?: '—',
            'Berangkat' => $daftar->tanggal_berangkat?->translatedFormat('l, j F Y'),
            'Jumlah peserta' => $daftar->jumlah_peserta.' orang',
            'Titik jemput' => $jemput,
        ];

        $bawaan = collect(config('orcha.pengingat.bawaan', []))
            ->map(fn ($satu) => '• '.$satu)
            ->implode("\n");

        self::kirim($daftar, 'Briefing Keberangkatan Terkirim', $rincian, new SalinanPelanggan(
            email: $daftar->email,
            judul: 'Besok Berangkat — '.($daftar->nama_paket ?: 'Trip Anda'),

            langkah: 'Titik jemput Anda: '.$jemput.".\n\n"
                ."Yang perlu dibawa:\n".$bawaan."\n\n"
                .'Tim kami menghubungi Anda lewat WhatsApp untuk jam pastinya — nomor '
                .'yang Anda daftarkan sudah kami catat. Ada yang berubah? Balas surat ini '
                .'atau hubungi kami sekarang, jangan menunggu pagi hari.',

            rincian: $rincian,
            tautan: route('lacak-pesanan', ['kode' => $daftar->kode]),
            labelTautan: 'Lihat Pesanan Anda',
        ));
    }

    /**
     * @param  array<string, string|null>  $rincian
     */
    private static function kirim(
        PendaftaranOpenTrip $daftar,
        string $judul,
        array $rincian,
        SalinanPelanggan $pelanggan,
    ): void {
        try {
            KirimPemberitahuan::kirim($judul, $daftar->kode, $rincian, pelanggan: $pelanggan);
        } catch (\Throwable $e) {
            /*
             | Surat yang gagal tidak boleh menghentikan sisanya.
             |
             | Satu alamat yang salah ketik akan membatalkan seluruh putaran
             | pengingat malam itu, dan yang tidak terkirim tidak akan pernah
             | dicoba lagi — penandanya sudah telanjur dipasang.
             */
            Log::error('Pengingat gagal dikirim', [
                'kode' => $daftar->kode,
                'judul' => $judul,
                'galat' => $e->getMessage(),
            ]);
        }
    }
}
