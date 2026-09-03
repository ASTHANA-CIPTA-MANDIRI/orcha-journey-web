<?php

namespace App\Console\Commands;

use App\Models\Etalase\Testimoni;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Support\KirimPemberitahuan;
use App\Support\SalinanPelanggan;
use Illuminate\Console\Command;

/**
 * Mengajak peserta yang baru pulang menuliskan pengalamannya.
 *
 * Sebelum ini trip berakhir lalu senyap: tidak ada yang mengundang testimoni,
 * tidak ada yang menawarkan trip berikutnya. Padahal formulir testimoni untuk
 * pelanggan sudah ada — hanya tidak ada satu pun yang mengajak mengisinya, dan
 * fitur yang menunggu ditemukan sendiri hampir tidak pernah ditemukan.
 *
 * Dipanggil cron langsung, sama seperti perintah Orcha lain — hosting
 * mematikan proc_open sehingga schedule:run tidak bisa diandalkan:
 *
 *     0 9 * * * cd /jalur/ke/orcha && php artisan orcha:ajak-testimoni >> storage/logs/cron.log 2>&1
 *
 * Sekali sehari pukul sembilan pagi: jam yang orang membuka ponselnya dengan
 * santai, bukan tengah malam saat suratnya tenggelam sebelum dibaca.
 */
class AjakTestimoni extends Command
{
    protected $signature = 'orcha:ajak-testimoni
                            {--percobaan : Hanya menampilkan siapa yang akan diajak}';

    protected $description = 'Mengajak peserta yang baru pulang menuliskan testimoni';

    /**
     * Berapa hari setelah pulang ajakannya dikirim.
     *
     * Bukan hari-H: orang masih di perjalanan pulang, lelah, dan kesannya
     * belum mengendap. Bukan pula sebulan kemudian, saat yang diingat tinggal
     * garis besarnya. Dua hari cukup untuk sampai rumah dan foto-fotonya
     * sudah dipindahkan.
     */
    private const HARI_SETELAH = 2;

    public function handle(): int
    {
        $tanggal = now()->subDays(self::HARI_SETELAH)->toDateString();

        /*
         | Hanya yang benar-benar berangkat DAN membayar.
         |
         | Yang batal jelas tidak, dan yang belum lunas pun tidak: mengirim
         | "bagaimana perjalanannya?" kepada orang yang tagihannya belum
         | selesai terbaca sebagai penagihan yang menyamar.
         */
        $calon = PendaftaranOpenTrip::query()
            ->where('status', 'lunas')
            ->whereDate('tanggal_berangkat', $tanggal)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            // Yang sudah menulis testimoni tidak diajak lagi. Ajakan kedua
            // untuk hal yang sudah dikerjakan terbaca sebagai sistem yang
            // tidak memperhatikan.
            ->reject(fn ($d) => Testimoni::where('kode_pesanan', $d->kode)->exists());

        if ($calon->isEmpty()) {
            $this->info('Tidak ada yang perlu diajak hari ini (trip '.self::HARI_SETELAH.' hari lalu).');

            return self::SUCCESS;
        }

        foreach ($calon as $daftar) {
            if (! $this->option('percobaan')) {
                $this->ajak($daftar);
            }

            $this->line('  - '.$daftar->kode.' · '.$daftar->nama);
        }

        $this->info(($this->option('percobaan') ? '[PERCOBAAN] ' : '')
            .$calon->count().' peserta diajak menulis testimoni.');

        return self::SUCCESS;
    }

    private function ajak(PendaftaranOpenTrip $daftar): void
    {
        $trip = $daftar->nama_paket ?: 'perjalanan bersama kami';

        /*
         | Kode rujukannya dibuat SEKARANG, bukan saat ia mendaftar.
         |
         | Kode yang diberikan kepada orang yang belum berangkat tidak punya
         | cerita untuk dijual — ia belum tahu apakah tripnya bagus. Yang baru
         | pulang punya, dan hari-hari ini justru saat ceritanya paling sering
         | ditanyakan orang.
         |
         | Dititipkan pada surat yang memang sudah berangkat, bukan surat
         | tersendiri. Dua surat dalam dua hari untuk orang yang sama membuat
         | keduanya terbaca sebagai gangguan.
         */
        $rujukan = \App\Support\Rujukan::untukAlumni($daftar);

        KirimPemberitahuan::kirim(
            'Ajakan Testimoni Terkirim',
            $daftar->kode,
            [
                'Pemesan' => $daftar->nama,
                'Trip' => $trip,
                'Berangkat' => $daftar->tanggal_berangkat?->translatedFormat('j F Y'),
            ],
            pelanggan: new SalinanPelanggan(
                email: $daftar->email,
                judul: 'Bagaimana Perjalanannya?',

                /*
                 | Satu permintaan saja, dan permintaan yang kecil.
                 |
                 | Surat yang sekaligus meminta testimoni, menawarkan trip
                 | berikutnya, dan mengajak mengikuti media sosial biasanya
                 | tidak menghasilkan satu pun dari ketiganya. Tawaran trip
                 | berikutnya menyusul di surat lain, setelah orangnya sempat
                 | bercerita.
                 */
                langkah: 'Terima kasih sudah ikut '.$trip.". Kami ingin tahu bagaimana rasanya.\n\n"
                    .'Satu atau dua kalimat sudah sangat membantu — dan yang membacanya '
                    .'orang yang sedang menimbang trip yang sama seperti Anda dulu. '
                    .'Kodenya sudah kami sertakan di tautan, jadi tinggal menulis.'

                    ."\n\n"

                    /*
                     | Disebut sesudah permintaan testimoninya, bukan sebelum.
                     |
                     | Yang diminta tetap satu: menulis pengalaman. Kode
                     | rujukannya PEMBERIAN, bukan permintaan kedua — dan
                     | urutannya menentukan mana yang terbaca sebagai apa.
                     */
                    .'Oh ya, ini kode rujukan Anda: '.$rujukan->kode.'. '
                    .'Teman yang mendaftar dengan kode ini dapat potongan '
                    .\App\Support\RincianBiaya::rupiah(\App\Support\Rujukan::potongan())
                    .', dan Anda dapat '
                    .\App\Support\RincianBiaya::rupiah(\App\Support\Rujukan::imbalan())
                    .' untuk tiap pendaftaran yang memakainya.',

                tautan: route('testimoni', ['kode' => $daftar->kode]),
                labelTautan: 'Tulis Pengalaman Anda',
            ),
        );
    }
}
