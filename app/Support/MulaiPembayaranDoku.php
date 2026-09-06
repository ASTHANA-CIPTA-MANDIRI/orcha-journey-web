<?php

namespace App\Support;

use App\Mail\TagihanPembayaran;
use App\Models\OpenTrip\Angsuran;
use App\Models\OpenTrip\PembayaranDoku;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use App\Services\DokuCheckout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Membuka halaman pembayaran DOKU untuk sebuah pesanan.
 *
 * Nominalnya dihitung DI SINI, dari tagihan yang tersimpan — tidak pernah
 * dari angka yang dikirim peramban. Ini pergeseran penting dari formulir
 * lama: dulu pelanggan mengetik sendiri berapa yang ia transfer, dan angka
 * itu memang cuma laporan yang nanti dicek admin. Sekarang angkanya adalah
 * yang benar-benar akan ditagihkan gerbang pembayaran, jadi membiarkannya
 * datang dari peramban sama saja membiarkan orang menentukan harganya
 * sendiri.
 */
class MulaiPembayaranDoku
{
    /**
     * @param  string  $jenis  dp | pelunasan | sewa | lainnya
     *
     * @throws \RuntimeException bila tagihannya tidak bisa dihitung, sudah
     *                           lunas, atau DOKU menolak
     */
    public static function untuk(
        PendaftaranOpenTrip|PenyewaanKendaraan $pesanan,
        string $jenis,
    ): PembayaranDoku {
        $tagihan = TagihanPesanan::untuk($pesanan);

        if ($tagihan === []) {
            throw new \RuntimeException('Tagihan pesanan ini belum bisa dihitung. Silakan hubungi kami lewat WhatsApp.');
        }

        if ($tagihan['lunas']) {
            throw new \RuntimeException('Pesanan ini sudah lunas — tidak ada yang perlu dibayar.');
        }

        /*
         | Angsuran punya sumber angkanya sendiri.
         |
         | TagihanPesanan menjawab "berapa seluruh sisanya"; yang ditagih di
         | sini cuma termin yang jatuh tempo berikutnya. Menyerahkan
         | perhitungannya ke TagihanPesanan berarti menitipkan pengetahuan
         | tentang jadwal ke kelas yang tidak tahu apa-apa tentang jadwal.
         */
        $pokok = $jenis === 'angsuran'
            ? self::nominalTermin($pesanan)
            : TagihanPesanan::nominalUntukJenis($tagihan, $jenis);

        if (! $pokok || $pokok < 1000) {
            throw new \RuntimeException('Tidak ada tagihan yang bisa dibayar untuk pilihan itu.');
        }

        /*
         | Tagihan yang MASIH HIDUP dipakai ulang, bukan diganti yang baru.
         |
         | Sejak jendela bayarnya dipendekkan jadi setengah jam, membuat ulang
         | tagihan berubah dari kejadian langka menjadi alur biasa — dan tanpa
         | penjagaan ini tiap klik "Bayar Sekarang" melahirkan satu Virtual
         | Account lagi yang hidup berdampingan dengan yang sebelumnya. Sudah
         | terjadi: satu pesanan meninggalkan tiga baris "menunggu" sekaligus.
         |
         | Dua akibatnya nyata. Pertama, NOMINALNYA BERBEDA di tiap tagihan,
         | karena kode uniknya diacak ulang — pelanggan yang menyalin angka ke
         | m-banking lalu menyegarkan halaman kami menemukan angka yang lain,
         | dan tidak ada di layar yang menjelaskan kenapa. Kedua, dua VA hidup
         | untuk satu tagihan berarti keduanya bisa dibayar; yang membayar dua
         | kali bukan kesalahan yang bisa kita bantah, dan uangnya harus kita
         | kembalikan.
         |
         | Yang dipakai ulang harus benar-benar setara: pesanan, jenis, dan
         | POKOK yang sama. Pokok berubah bila admin mencatat pembayaran lain
         | di tengah — dan tagihan lama yang nominalnya sudah basi tidak boleh
         | disodorkan lagi.
         |
         | Yang sudah kedaluwarsa tidak ikut, jadi alur "mulai lagi dari
         | halaman pembayaran untuk mendapat tautan baru" tetap bekerja persis
         | seperti yang diharapkan.
         |
         | YANG BERUMUR LEBIH PANJANG DARIPADA ATURAN HARI INI juga tidak ikut,
         | dan itu penjagaan tersendiri.
         |
         | Tagihan membawa umurnya sejak lahir: yang dibuat saat batasnya masih
         | 24 jam tetap hidup 24 jam meski config sudah dipendekkan jadi 30
         | menit. Tanpa baris terakhir ini, memakainya ulang berarti
         | menyerahkan kembali tautan berumur sehari sesudah kita memutuskan
         | tautan tidak boleh hidup lebih dari setengah jam — dan aturan
         | barunya baru benar-benar berlaku setelah sisa tagihan lama habis
         | sendiri, tanpa seorang pun tahu kenapa.
         |
         | Terukur: satu pesanan masih memegang tautan 1440 menit yang lahir
         | semenit sebelum config diubah.
         */
        $hidup = PembayaranDoku::where('kode', $pesanan->kode)
            ->where('jenis', $jenis)
            ->where('status', 'menunggu')
            ->where('nominal_pokok', $pokok)
            ->whereNotNull('url')
            ->where('kedaluwarsa_pada', '>', now())
            ->where('kedaluwarsa_pada', '<=', now()->addMinutes((int) config('doku.batas_bayar_menit')))
            ->latest('id')
            ->first();

        if ($hidup) {
            return $hidup;
        }

        // Diacak, dan baru ditempelkan DI SINI — bukan saat pilihan bayarnya
        // digambar. Halaman itu menampilkan harga apa adanya; kode uniknya
        // muncul sesudah pelanggan memilih, berikut keterangan asal angkanya.
        $kodeUnik = PembayaranDoku::kodeUnikAcak();
        $nominal = $pokok + $kodeUnik;

        /*
         | Barisnya ditulis SEBELUM DOKU dipanggil.
         |
         | Kalau dibalik, panggilan yang berhasil tetapi jawabannya tidak
         | sempat tersimpan — koneksi putus, proses mati — menghasilkan halaman
         | pembayaran yang hidup di DOKU tanpa satu pun catatan di sini. Uang
         | yang masuk lewat halaman itu tidak akan bisa dicocokkan dengan
         | apa pun.
         |
         | Urutan ini menghasilkan sisa yang lebih tidak berbahaya: baris
         | "menunggu" yang tidak pernah punya URL. Ia tidak mempengaruhi
         | tagihan sama sekali, dan tidak ada uang yang bisa masuk lewatnya.
         */
        $bayar = PembayaranDoku::create([
            'invoice' => PembayaranDoku::nomorInvoice($pesanan->kode, $jenis),
            'kode' => $pesanan->kode,
            'jenis' => $jenis,
            'nominal_pokok' => $pokok,
            'kode_unik' => $kodeUnik,
            'nominal' => $nominal,
            'status' => 'menunggu',
            'kedaluwarsa_pada' => now()->addMinutes((int) config('doku.batas_bayar_menit')),
        ]);

        $hasil = app(DokuCheckout::class)->buatHalamanBayar(
            invoice: $bayar->invoice,
            nominal: $nominal,
            judul: self::judul($pesanan, $jenis),

            /*
             | Kode unik dikirim sebagai barisnya sendiri.
             |
             | DOKU mengirimkan surel tagihan ke pelanggan, dan isi surel itu
             | disusun dari rincian ini. Selama kode uniknya dilebur ke dalam
             | harga, surel itu cuma menyebut "Uang Muka (DP) 859.257" —
             | pelanggan yang membacanya kembali seminggu kemudian tidak punya
             | cara tahu 257-nya dari mana, dan yang ia lakukan adalah bertanya
             | lewat WhatsApp.
             |
             | Halaman kita sudah menerangkannya sebelum ia membayar, tetapi
             | halaman itu ditutup. Surel DOKU yang tinggal.
             */
            rincian: [
                ['nama' => self::judul($pesanan, $jenis), 'harga' => $pokok],
                ['nama' => 'Kode unik pembayaran', 'harga' => $kodeUnik],
            ],
            pelanggan: [
                'nama' => $pesanan->nama,
                'email' => $pesanan->email,
                'telepon' => NomorTelepon::angka($pesanan->whatsapp) ?: null,
            ],
            kembali: [
                'selesai' => route('pembayaran-selesai', ['invoice' => $bayar->invoice]),
                'batal' => route('pembayaran-selesai', ['invoice' => $bayar->invoice, 'batal' => 1]),
            ],
        );

        $bayar->update([
            'url' => $hasil['url'],
            'token_id' => $hasil['token_id'],
        ]);

        self::kirimTagihan($pesanan, $bayar->fresh());

        Log::info('Halaman pembayaran DOKU dibuka', [
            'invoice' => $bayar->invoice,
            'kode' => $bayar->kode,
            'jenis' => $jenis,
            'nominal' => $nominal,
        ]);

        return $bayar->fresh();
    }

    /**
     * Faktur tagihan dari Orcha, bukan dari gerbang pembayaran.
     *
     * DOKU bisa mengirim fakturnya sendiri, dan isinya benar. Yang tidak bisa
     * diubah adalah pengirimnya: ia selalu datang sebagai noreply@doku.com,
     * berbahasa Inggris, dari nama yang tidak dikenal pelanggan. Untuk orang
     * yang baru memesan trip ke Orcha, surat semacam itu lebih mirip penipuan
     * daripada tagihan — dan yang dilakukan orang yang ragu bukan bertanya,
     * melainkan tidak membayar.
     *
     * Matikan faktur DOKU di dashboard (Settings -> Checkout Page
     * Notifications) supaya pelanggan tidak menerima dua.
     *
     * Hanya ke PELANGGAN, tidak ke kotak kantor. Tiap penekanan tombol bayar
     * membuat satu tagihan, termasuk yang ditinggalkan setengah jalan; kotak
     * kantor yang menerima semuanya akan berisi surat yang tak satu pun perlu
     * dikerjakan. Kantor dikabari saat uangnya benar-benar masuk, oleh
     * TerimaNotifikasiDoku.
     *
     * Kegagalan mengirim TIDAK boleh menggagalkan pembayarannya. Halaman
     * bayarnya sudah hidup di DOKU dan pelanggan sudah menunggu; surat yang
     * tidak terkirim adalah kerugian yang jauh lebih kecil daripada tombol
     * bayar yang berakhir dengan pesan galat.
     */
    private static function kirimTagihan(
        PendaftaranOpenTrip|PenyewaanKendaraan $pesanan,
        PembayaranDoku $bayar,
    ): void {
        $email = $pesanan->email;

        // Alamat email di formulir kami memang boleh kosong — nomor WhatsApp
        // yang wajib. Salah ketik pun tidak perlu diributkan ke pelanggan.
        if (blank($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (! config('orcha.email_salinan_pelanggan', true)) {
            return;
        }

        try {
            Mail::to($email)->send(new TagihanPembayaran(
                kode: $bayar->kode,
                invoice: $bayar->invoice,
                namaPelanggan: (string) ($pesanan->nama ?: 'Pelanggan'),
                emailPelanggan: $email,
                teleponPelanggan: $pesanan->whatsapp,
                jenisLabel: config('orcha.jenis_pembayaran')[$bayar->jenis] ?? 'Pembayaran',
                barang: self::barang($pesanan),
                pokok: $bayar->nominal_pokok,
                kodeUnik: $bayar->kode_unik,
                nominal: $bayar->nominal,
                url: (string) $bayar->url,
                kedaluwarsa: $bayar->kedaluwarsa_pada,
            ));
        } catch (\Throwable $e) {
            Log::warning('Faktur tagihan gagal dikirim', [
                'invoice' => $bayar->invoice,
                'sebab' => $e->getMessage(),
            ]);
        }
    }

    /** Nama barang yang tampil di halaman DOKU dan di faktur tagihannya. */
    private static function judul(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan, string $jenis): string
    {
        $label = config('orcha.jenis_pembayaran')[$jenis] ?? 'Pembayaran';

        // Tanda pisahnya "-", bukan "—": DOKU menolak em dash, dan pembersih
        // di DokuCheckout akan menggantinya dengan spasi kosong yang canggung.
        return $label.' - '.self::barang($pesanan);
    }

    /**
     * Yang masih kurang untuk menutup termin terdekat.
     *
     * Yang ditagih KEKURANGANNYA, bukan nominal terminnya. Pelanggan yang
     * sempat membayar sebagian termin ini tidak boleh diminta membayar penuh
     * lagi — sisa hitungannya sudah benar di posisi(), dan menagih nominal
     * penuh berarti menagih uang yang sudah masuk untuk kedua kalinya.
     */
    private static function nominalTermin(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): ?int
    {
        $termin = RencanaAngsuran::terminBerikutnya(Angsuran::aktifUntuk($pesanan->kode));

        return $termin['kurang'] ?? null;
    }

    /** Layanan yang ditagihkan, tanpa embel-embel jenis pembayarannya. */
    private static function barang(PendaftaranOpenTrip|PenyewaanKendaraan $pesanan): string
    {
        return $pesanan instanceof PenyewaanKendaraan
            ? ($pesanan->nama_kendaraan ?: 'Sewa Kendaraan')
            : ($pesanan->nama_paket ?: 'Open Trip');
    }
}
