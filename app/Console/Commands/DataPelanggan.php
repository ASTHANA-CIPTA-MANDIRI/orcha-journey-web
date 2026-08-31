<?php

namespace App\Console\Commands;

use App\Models\JejakAudit;
use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\Pembatalan;
use App\Models\OpenTrip\RiwayatKesehatan;
use App\Support\PemilikPesanan;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Menepati dua janji di halaman Kebijakan Privasi.
 *
 * Halaman itu menuliskan hak pelanggan atas "salinan data yang kami simpan"
 * dan "penghapusan data" — tetapi tidak ada satu pun mekanisme untuk
 * memenuhinya. Kalau ada yang menuntut haknya, tim harus membukanya manual di
 * basis data, satu tabel demi satu tabel, dan hampir pasti ada yang terlewat.
 *
 * Sengaja perintah untuk ADMIN, bukan tombol di halaman publik. Dua alasan:
 *
 *   1. Penghapusan tidak bisa ditarik kembali. Tombol yang bisa ditekan siapa
 *      pun yang memegang kode pesanan adalah cara paling mudah menghapus data
 *      orang lain.
 *   2. Permintaan seperti ini datangnya lewat percakapan — WhatsApp atau surel
 *      — dan identitasnya diperiksa manusia lebih dulu. Perintah ini yang
 *      dikerjakan SETELAH pemeriksaan itu, bukan menggantikannya.
 */
class DataPelanggan extends Command
{
    protected $signature = 'orcha:data-pelanggan
                            {kode : Kode pesanan, mis. OT-3108-K7QMXV}
                            {--hapus : Hapus datanya, bukan menyalinnya}
                            {--paksa : Lewati pertanyaan konfirmasi}';

    protected $description = 'Menyalin atau menghapus seluruh data seorang pelanggan menurut kode pesanannya';

    public function handle(): int
    {
        $kode = strtoupper(trim($this->argument('kode')));
        $pesanan = PemilikPesanan::tanpaPeriksa($kode);

        if (! $pesanan) {
            $this->error("Pesanan {$kode} tidak ditemukan.");

            return self::FAILURE;
        }

        return $this->option('hapus')
            ? $this->hapus($pesanan, $kode)
            : $this->salin($pesanan, $kode);
    }

    /** Salinan data, dalam JSON supaya bisa dikirimkan apa adanya. */
    private function salin(object $pesanan, string $kode): int
    {
        $data = [
            'pesanan' => $pesanan->toArray(),
            'pembayaran' => KonfirmasiPembayaran::where('kode', $kode)->get()->toArray(),
            'pembatalan' => Pembatalan::where('kode', $kode)->get()->toArray(),
            'riwayat_kesehatan' => RiwayatKesehatan::where('kode_pendaftaran', $kode)->get()->toArray(),
        ];

        $nama = 'data-pelanggan-'.strtolower($kode).'-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($nama, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info('Salinan disimpan: '.Storage::disk('local')->path($nama));
        $this->line('  Pembayaran        : '.count($data['pembayaran']));
        $this->line('  Pengajuan batal   : '.count($data['pembatalan']));
        $this->line('  Data kesehatan    : '.count($data['riwayat_kesehatan']));

        $this->catat('salin data pelanggan', 'Salinan data pelanggan dibuat atas permintaan.', $kode);

        /*
         | Disimpan di disk 'local', BUKAN 'public'.
         |
         | Berkasnya memuat seluruh data pribadi orang itu termasuk riwayat
         | medisnya. Di disk publik ia bisa diunduh siapa saja yang menebak
         | namanya — dan namanya memuat kode pesanan, yang justru sudah beredar.
         */
        $this->warn('Berkas ini memuat data pribadi. Kirimkan lewat jalur yang aman, lalu hapus dari server.');

        return self::SUCCESS;
    }

    private function hapus(object $pesanan, string $kode): int
    {
        $kesehatan = RiwayatKesehatan::where('kode_pendaftaran', $kode)->count();
        $bayar = KonfirmasiPembayaran::where('kode', $kode)->count();

        $this->warn("Akan dihapus untuk {$kode} ({$pesanan->nama}):");
        $this->line("  Data kesehatan   : {$kesehatan}");
        $this->line("  Bukti pembayaran : {$bayar}");
        $this->line('  Pesanan itu sendiri: TIDAK dihapus, lihat keterangan di bawah.');

        if (! $this->option('paksa') && ! $this->confirm('Lanjutkan? Tidak bisa dibatalkan.')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        RiwayatKesehatan::where('kode_pendaftaran', $kode)->delete();
        KonfirmasiPembayaran::where('kode', $kode)->delete();

        /*
         | Barisan pesanannya sendiri TIDAK dihapus, melainkan disamarkan.
         |
         | Kebijakan privasi menyebutnya sendiri: "penghapusan data, sepanjang
         | tidak bertentangan dengan kewajiban pembukuan kami". Nilai transaksi
         | dan tanggalnya adalah catatan keuangan yang wajib disimpan; yang
         | menjadikannya data pribadi adalah nama, nomor, dan surelnya.
         |
         | Jadi yang dibuang justru bagian yang menunjuk orangnya, dan yang
         | tinggal angka yang tidak bisa dikembalikan ke siapa pun.
         */
        $pesanan->update([
            'nama' => '[dihapus atas permintaan]',
            'whatsapp' => '',
            'email' => null,
            'daftar_peserta' => null,
            'catatan' => '[Sistem] Data pribadi dihapus atas permintaan pelanggan pada '
                .now()->translatedFormat('j F Y').'.',
        ]);

        $this->catat('hapus data pelanggan',
            "Data pribadi dihapus atas permintaan. {$kesehatan} data kesehatan dan {$bayar} bukti pembayaran dibuang; "
            .'baris pesanannya disamarkan dan angka pembukuannya dipertahankan.',
            $kode);

        $this->info('Selesai. Data pribadi dihapus, catatan pembukuan dipertahankan tanpa identitas.');

        return self::SUCCESS;
    }

    private function catat(string $aksi, string $ringkasan, string $kode): void
    {
        $permintaan = Request::create('/', 'POST');
        $permintaan->attributes->set('admin_pemanggil', 'Sistem (perintah admin)');

        JejakAudit::catat($permintaan, $aksi, $ringkasan, $kode);
    }
}
