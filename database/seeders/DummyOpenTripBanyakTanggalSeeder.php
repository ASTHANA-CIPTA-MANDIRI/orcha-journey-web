<?php

namespace Database\Seeders;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\PaketWisata\Keberangkatan;
use App\Models\PaketWisata\TravelPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data UJI COBA: satu open trip dengan beberapa tanggal keberangkatan.
 *
 * Dipakai untuk mencoba jalur "satu paket, banyak tanggal" dari ujung ke
 * ujung — pemilih tanggal di halaman detail, kuota yang dihitung per
 * tanggal, harga yang berbeda per tanggal, sampai formulir pendaftaran.
 *
 * Tanggalnya dihitung RELATIF terhadap hari ini, bukan ditulis tetap.
 * Tanggal tetap akan lewat sendiri dalam beberapa minggu, dan sesudah itu
 * paketnya hilang dari katalog tanpa ada yang menyadari kenapa — persis
 * saat seseorang ingin mencoba lagi.
 *
 * Aman dijalankan berulang: paket lamanya dihapus dulu, jadi tidak pernah
 * menumpuk jadi lima salinan.
 *
 *   php artisan db:seed --class=DummyOpenTripBanyakTanggalSeeder
 *
 * Membuangnya kembali:
 *
 *   php artisan db:seed --class=HapusDummyOpenTripSeeder
 */
class DummyOpenTripBanyakTanggalSeeder extends Seeder
{
    /**
     * Namanya menyebut UJI COBA terang-terangan.
     *
     * Paket ini tayang di katalog publik seperti paket sungguhan, dan tanpa
     * penanda di namanya ia tidak bisa dibedakan dari jualan nyata — baik
     * oleh admin yang membuka panel, maupun oleh tim yang kebetulan membuka
     * situsnya. Penanda itu juga yang dipakai untuk menghapusnya lagi.
     */
    public const NAMA = 'Open Trip Dieng Sunrise (UJI COBA)';

    /** Penanda di catatan_internal pendaftaran uji coba, supaya bisa dicari lagi. */
    public const PENANDA = 'DATA UJI COBA — dibuat DummyOpenTripBanyakTanggalSeeder';

    public function run(): void
    {
        /*
         | Pendaftarannya dihapus LEBIH DULU dan secara eksplisit.
         |
         | Menghapus paketnya saja belum tentu ikut menghapus pendaftaran yang
         | menunjuk ke sana: kalau tidak ada cascade di basis data, yang
         | tertinggal adalah baris pendaftaran tanpa paket — dan baris semacam
         | itu muncul di laporan sebagai pesanan yang paketnya hilang. Dicari
         | lewat penanda di catatan_internal, bukan lewat nama paket, supaya
         | tetap ketemu meski paketnya sudah lenyap duluan.
         */
        PendaftaranOpenTrip::where('catatan_internal', self::PENANDA)->delete();

        TravelPackage::where('name', self::NAMA)->each(fn (TravelPackage $p) => $p->delete());

        $paket = TravelPackage::create([
            'name' => self::NAMA,
            'category' => 'open_trip',
            'status' => 'terbit',
            /*
             | berakhir_otomatis dimatikan.
             |
             | Kalau menyala, paketnya berhenti tayang begitu tanggal terakhir
             | lewat — dan data uji coba yang hilang sendiri justru merepotkan
             | orang yang sedang mencoba: ia mengira ada yang rusak.
             */
            'berakhir_otomatis' => false,
            'duration' => '2 hari 1 malam',
            'titik_jemput' => 'Jogja, Magelang, Wonosobo',
            'minimal_peserta' => 6,

            // Kuota & harga di tingkat PAKET. Tanggal yang kolomnya kosong
            // ikut angka ini — itu yang diuji oleh tanggal pertama di bawah.
            'kuota' => 20,
            'price' => 675_000,
            'original_price' => 825_000,
            'discount_percentage' => 18,
            'catatan_promo' => 'Promo uji coba',

            'destination_list' => [
                'Bukit Sikunir',
                'Telaga Warna',
                'Kawah Sikidang',
                'Candi Arjuna',
                'Batu Ratapan Angin',
            ],
            'fasilitas' => [
                'Transport Hiace AC',
                'Penginapan 1 malam',
                'Makan 3x',
                'Tiket masuk wisata',
                'Dokumentasi',
                'Asuransi perjalanan',
            ],
            // Diisi supaya panel "Yang sudah termasuk, dan yang belum" tampil
            // dua lajur — bentuk itu tidak pernah terlihat kalau salah satu
            // daftarnya kosong.
            'tidak_termasuk' => [
                'Pengeluaran pribadi',
                'Tip driver & guide',
                'Sewa jaket/sarung tangan di Sikunir',
            ],
            'itinerary' => [
                [
                    'hari' => 'Hari 1',
                    'agenda' => [
                        ['jam' => '20.00', 'kegiatan' => 'Kumpul & berangkat dari Jogja'],
                        ['jam' => '23.30', 'kegiatan' => 'Tiba di homestay Dieng, istirahat'],
                    ],
                ],
                [
                    'hari' => 'Hari 2',
                    'agenda' => [
                        ['jam' => '03.00', 'kegiatan' => 'Menuju Bukit Sikunir'],
                        ['jam' => '05.15', 'kegiatan' => 'Sunrise di Sikunir'],
                        ['jam' => '08.00', 'kegiatan' => 'Sarapan di homestay'],
                        ['jam' => '10.00', 'kegiatan' => 'Telaga Warna & Batu Ratapan Angin'],
                        ['jam' => '13.00', 'kegiatan' => 'Kawah Sikidang & Candi Arjuna'],
                        ['jam' => '16.00', 'kegiatan' => 'Perjalanan pulang ke Jogja'],
                    ],
                ],
            ],
        ]);

        $keberangkatan = collect($this->tanggal())
            ->map(fn (array $baris) => Keberangkatan::create(['travel_package_id' => $paket->id] + $baris));

        $this->penuhkan($paket, $keberangkatan[3]);

        $this->command?->info('Paket uji coba dibuat: '.self::NAMA);
        $this->command?->line('  '.config('app.url').'/paket/'.$paket->uuid);
        $this->command?->line('  Keberangkatan: '.$paket->keberangkatan()->count()
            .' baris, '.$paket->keberangkatanTersedia()->count().' di antaranya tampil ke pengunjung.');
    }

    /**
     * Menghabiskan kursi satu tanggal dengan pendaftaran uji coba.
     *
     * Nomor WhatsApp-nya memakai awalan 62899000 yang tidak dialokasikan ke
     * operator mana pun, supaya tidak ada nomor orang sungguhan yang
     * kebetulan tertulis di basis data dan sewaktu-waktu dihubungi.
     */
    private function penuhkan(TravelPackage $paket, Keberangkatan $k): void
    {
        $jumlah = (int) $k->kuota_dipakai;

        PendaftaranOpenTrip::create([
            'travel_package_id' => $paket->id,
            'keberangkatan_id' => $k->id,
            'nama_paket' => $paket->name,
            'nama' => 'Pendaftar Uji Coba',
            'whatsapp' => '62899000000001',
            'jumlah_peserta' => $jumlah,
            'tanggal_berangkat' => $k->tanggal_berangkat?->toDateString(),
            'titik_jemput' => 'Jogja',
            'harga_jual' => $k->harga_dipakai,
            'total_tagihan' => (int) $k->harga_dipakai * $jumlah,
            'status' => 'dp',
            'daftar_peserta' => collect(range(1, $jumlah))
                ->map(fn (int $i) => ['nama' => 'Peserta Uji '.$i, 'jenis_kelamin' => $i % 2 ? 'L' : 'P'])
                ->all(),
            'catatan_internal' => self::PENANDA,
        ]);
    }

    /**
     * Enam keberangkatan yang masing-masing menguji hal berbeda.
     *
     * Bukan enam tanggal yang isinya sama: tanggal yang semuanya normal cuma
     * membuktikan satu keadaan, dan keadaan yang TIDAK normal justru yang
     * paling sering salah tampil — tanggal penuh, tanggal berharga sendiri,
     * tanggal yang dimatikan, dan tanggal yang sudah lewat.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tanggal(): array
    {
        $hariIni = Carbon::today();

        return [
            /*
             | 1. NORMAL — kuota dan harga DIKOSONGKAN.
             |
             | Baris seperti inilah yang menguji aturan "kosong berarti ikut
             | paket". Kalau suatu saat angkanya diam-diam disalin dari paket
             | saat dibuat, tanggal ini tetap terlihat benar hari ini dan baru
             | salah saat harga paketnya diubah.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->addDays(10)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->addDays(11)->toDateString(),
                'kuota' => null,
                'price' => null,
                'aktif' => true,
                'catatan' => 'Ikut harga & kuota paket',
            ],

            /*
             | 2. HARGA SENDIRI — akhir pekan panjang, lebih mahal.
             |
             | Menguji bahwa angka yang tampil di pemilih tanggal memang angka
             | TANGGAL ITU, bukan angka paket yang dipajang berulang.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->addDays(24)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->addDays(25)->toDateString(),
                'kuota' => 20,
                'price' => 785_000,
                'original_price' => 925_000,
                'aktif' => true,
                'catatan' => 'Akhir pekan panjang — harga naik',
            ],

            /*
             | 3. KURSI TINGGAL SEDIKIT — kuota 2.
             |
             | Halaman publik sengaja TIDAK menyebut sisa kursinya; yang perlu
             | diuji di sini bahwa tanggal berkuota kecil tetap bisa dipilih
             | sampai benar-benar habis, bukan ditutup lebih awal.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->addDays(31)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->addDays(32)->toDateString(),
                'kuota' => 2,
                'price' => null,
                'aktif' => true,
                'catatan' => 'Sisa sedikit',
            ],

            /*
             | 4. PENUH — kuota 2 yang DIHABISKAN pendaftar, bukan kuota 0.
             |
             | Kuota 0 TIDAK membuat tanggal jadi penuh. Keberangkatan::
             | getSisaKursiAttribute() memakai `if (! $kuota) return null`, dan
             | `! 0` itu true — jadi tanggal berkuota nol diperlakukan sebagai
             | "kuota belum ditetapkan", yang artinya TIDAK PERNAH penuh.
             | Kebalikan dari maksudnya. (Lewat jalur biasa angka itu tidak
             | bisa terjadi: validasinya min:1.)
             |
             | Jadi penuhnya dibuat seperti di dunia nyata — ada yang sudah
             | mendaftar sebanyak kuotanya. Itu sekaligus menguji jalur yang
             | sebenarnya dipakai produksi: kursi_terpakai dihitung dari
             | pendaftaran, bukan dari angka yang disetel tangan.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->addDays(45)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->addDays(46)->toDateString(),
                'kuota' => 2,
                'price' => null,
                'aktif' => true,
                'catatan' => 'Penuh — kursinya sudah diambil pendaftar uji coba',
            ],

            /*
             | 5. DIMATIKAN — aktif = false.
             |
             | TIDAK boleh tampil sama sekali. Dibuat supaya yang mencoba bisa
             | melihat bedanya dengan "Penuh": yang satu ada tapi tertutup,
             | yang satu tidak pernah ada.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->addDays(52)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->addDays(53)->toDateString(),
                'kuota' => 20,
                'price' => null,
                'aktif' => false,
                'catatan' => 'Sengaja dimatikan — tidak boleh tampil',
            ],

            /*
             | 6. SUDAH LEWAT.
             |
             | Juga tidak boleh tampil, tetapi lewat jalan yang BERBEDA dari
             | nomor 5: yang ini disaring tanggalnya, bukan sakelarnya. Dua
             | penyaring yang berbeda perlu dua baris yang berbeda — kalau
             | salah satunya rusak, yang lain tidak akan menutupinya.
             */
            [
                'tanggal_berangkat' => $hariIni->copy()->subDays(14)->toDateString(),
                'tanggal_pulang' => $hariIni->copy()->subDays(13)->toDateString(),
                'kuota' => 20,
                'price' => null,
                'aktif' => true,
                'catatan' => 'Sudah lewat — tidak boleh tampil',
            ],
        ];
    }
}
