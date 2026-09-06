<?php

namespace App\Support;

use App\Models\OpenTrip\KonfirmasiPembayaran;
use App\Models\OpenTrip\PembayaranDoku;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mengubah notifikasi DOKU menjadi pembayaran yang tercatat.
 *
 * Dipanggil HANYA setelah tanda tangannya diverifikasi (lihat
 * DokuCheckout::notifikasiSah). Kelas ini menganggap isinya sudah tepercaya
 * dan tidak memeriksanya lagi; yang diperiksa di sini adalah hal lain —
 * apakah pembayaran ini sudah pernah dicatat.
 *
 * Sebab DOKU MENGULANG notifikasinya bila server kita tidak menjawab 200,
 * dan pengulangan itu wajar: jaringan putus, deploy sedang berjalan, proses
 * mati di tengah. Yang tidak boleh terjadi adalah satu pembayaran tercatat
 * dua kali — pelanggan yang membayar DP sekali akan terlihat sudah lunas,
 * dan sisa tagihannya menghilang tanpa pernah dibayar.
 */
class TerimaNotifikasiDoku
{
    /**
     * @param  array  $badan  Isi notifikasi DOKU, apa adanya
     * @return bool false hanya bila nomor tagihannya tidak dikenali
     */
    public static function proses(array $badan): bool
    {
        $invoice = (string) data_get($badan, 'order.invoice_number');
        $status = strtoupper((string) data_get($badan, 'transaction.status'));

        if (blank($invoice)) {
            Log::warning('Notifikasi DOKU tanpa nomor tagihan');

            return false;
        }

        /*
         | Seluruh pemeriksaan dan penulisan dalam satu transaksi.
         |
         | Dua notifikasi yang tiba bersamaan untuk tagihan yang sama —
         | pengulangan DOKU yang menyusul jawaban kita yang lambat — akan
         | sama-sama membaca status "menunggu" bila dibaca di luar kunci, dan
         | sama-sama merasa berhak mencatat pembayarannya.
         */
        return DB::transaction(function () use ($invoice, $status, $badan) {
            $bayar = PembayaranDoku::where('invoice', $invoice)->lockForUpdate()->first();

            if (! $bayar) {
                // Bukan kesalahan yang bisa kita perbaiki, tetapi WAJIB dicatat:
                // artinya ada uang masuk di DOKU untuk tagihan yang tidak kita
                // kenal, dan itu harus ditelusuri manusia.
                Log::error('Notifikasi DOKU untuk tagihan yang tidak dikenal', [
                    'invoice' => $invoice,
                    'status' => $status,
                ]);

                return false;
            }

            if ($bayar->status === 'berhasil') {
                Log::info('Notifikasi DOKU diulang untuk tagihan yang sudah lunas', ['invoice' => $invoice]);

                return true;
            }

            if ($status !== 'SUCCESS') {
                $bayar->update([
                    'status' => $status === 'EXPIRED' ? 'kedaluwarsa' : 'gagal',
                    'channel' => data_get($badan, 'channel.id'),
                    'notifikasi' => $badan,
                ]);

                return true;
            }

            /*
             | Nominal yang dicatat adalah yang DIBAYAR, bukan yang ditagihkan.
             |
             | Keduanya seharusnya sama — halaman DOKU tidak bisa ditawar. Bila
             | ternyata berbeda, yang benar adalah angka dari DOKU: itu uang
             | yang nyata-nyata berpindah. Selisihnya dicatat sebagai peringatan
             | supaya ada yang memeriksanya, bukan diam-diam diseragamkan.
             */
            $dibayar = (int) (data_get($badan, 'order.amount') ?: $bayar->nominal);

            if ($dibayar !== $bayar->nominal) {
                Log::warning('Nominal notifikasi DOKU berbeda dari yang ditagihkan', [
                    'invoice' => $invoice,
                    'ditagihkan' => $bayar->nominal,
                    'dibayar' => $dibayar,
                ]);
            }

            /*
             | KODE UNIK TIDAK MENGURANGI TAGIHAN.
             |
             | Uang yang benar-benar masuk memang Rp 858.889, dan angka itu
             | tersimpan utuh di tbl_pembayaran_doku. Tetapi yang DIBAYARKAN
             | KE TAGIHAN cuma Rp 858.000; Rp 889 sisanya adalah penanda untuk
             | mengenali pembayarannya, bukan cicilan.
             |
             | Menghitungnya sebagai pembayaran membuat sisa tagihan meleset
             | sebesar kode uniknya — Rp 2.001.111 alih-alih Rp 2.002.000 —
             | dan kesalahan itu MENUMPUK: pelanggan yang membayar tiga kali
             | akhirnya ditagih kurang beberapa ribu, lalu pesanannya dinyatakan
             | lunas padahal belum. Kelebihannya diserap, persis seperti pada
             | kode unik transfer bank pada umumnya.
             */
            $keTagihan = max(0, $dibayar - (int) $bayar->kode_unik);

            $pesanan = $bayar->pesanan();
            $channel = (string) (data_get($badan, 'channel.id') ?: data_get($badan, 'service.id') ?: 'DOKU');

            $konfirmasi = KonfirmasiPembayaran::create([
                'kode' => $bayar->kode,
                'jenis' => $bayar->jenis,
                'kanal' => 'doku',
                'nominal' => $keTagihan,
                'tanggal_transfer' => self::tanggal($badan),

                /*
                 | Kolom bank dan atas nama tetap diisi, bukan dikosongkan.
                 |
                 | Keduanya wajib di tabelnya, tetapi bukan itu alasan
                 | utamanya: daftar pembayaran di lemon menampilkan kedua kolom
                 | ini berdampingan, dan baris berkolom kosong terbaca sebagai
                 | data yang hilang — bukan sebagai pembayaran yang memang
                 | tidak lewat rekening. Yang diisikan adalah keterangan yang
                 | sebenarnya: lewat channel apa, dan atas nama siapa
                 | pesanannya.
                 */
                'bank_pengirim' => Str::limit(self::labelChannel($channel), 60, ''),
                'atas_nama_pengirim' => Str::limit((string) ($pesanan?->nama ?: 'Pembayaran daring'), 120, ''),

                // Tidak ada bukti untuk diunggah, dan memang tidak diperlukan:
                // buktinya adalah notifikasi bertanda tangan yang tersimpan
                // utuh di tbl_pembayaran_doku.
                'bukti' => null,

                /*
                 | Langsung DITERIMA, tanpa menunggu dicek admin.
                 |
                 | Ini bedanya yang paling besar dengan bukti unggahan. Gambar
                 | yang dikirim pelanggan baru klaim, jadi statusnya menunggu
                 | sampai ada yang mencocokkannya dengan mutasi. Notifikasi
                 | DOKU yang tanda tangannya sah adalah uang yang sudah ada di
                 | rekening kami — menahannya di "menunggu" cuma menyuruh admin
                 | memeriksa hal yang sudah pasti, dan menahan kursi pelanggan
                 | selama pemeriksaan itu.
                 */
                'status' => 'diterima',
                /*
                 | Ditulis untuk dibaca manusia, bukan disalin dari kabel.
                 |
                 | "VIRTUAL_ACCOUNT_BCA" adalah nama yang dipakai mesin. Admin
                 | yang membacanya di kolom catatan menyangkanya kode galat,
                 | dan yang paling sering ia lakukan berikutnya adalah bertanya
                 | ke orang lain apakah ada yang salah.
                 */
                /*
                 | Pendek, karena angkanya sudah punya tempatnya sendiri.
                 |
                 | Versi panjangnya dulu mengulang nominal, kode unik, dan
                 | nomor tagihan — padahal ketiganya sudah tampil sebagai baris
                 | terstruktur tepat di atasnya. Satu baris pembayaran jadi
                 | memuat fakta yang sama tiga kali, dan yang membacanya
                 | berhenti membaca sebelum sampai ke yang penting.
                 |
                 | Yang tersisa di sini keterangan yang TIDAK ada di tempat
                 | lain: bahwa baris ini tidak pernah disentuh manusia.
                 */
                'catatan_admin' => 'Masuk sendiri lewat gerbang pembayaran — '
                    .'tidak dicatat manual oleh admin.',
            ]);

            $bayar->update([
                'status' => 'berhasil',
                'channel' => $channel,
                'dibayar_pada' => now(),
                'notifikasi' => $badan,
                'konfirmasi_id' => $konfirmasi->id,
            ]);

            /*
             | Status pesanannya ikut maju, dan pelanggannya dikabari.
             |
             | Dijalankan lewat jalur yang sama persis dengan persetujuan admin
             | di lemon, supaya pembayaran lewat DOKU dan pembayaran yang
             | disetujui manual menghasilkan akibat yang identik. Dua jalur
             | dengan akibat yang berbeda adalah asal perbedaan yang tidak ada
             | yang bisa menjelaskan enam bulan kemudian.
             */
            StatusPendaftaran::selaraskan($pesanan);
            KabarPembayaran::kirim($konfirmasi->fresh(), $pesanan);

            KirimPemberitahuan::kirim(
                'Pembayaran Masuk lewat DOKU',
                $bayar->kode,
                [
                    'Jenis' => $konfirmasi->jenis_label,
                    'Nominal' => $konfirmasi->nominal_formatted,
                    'Metode' => self::labelChannel($channel),
                    'Nomor tagihan' => $invoice,
                    'Pemesan' => $pesanan?->nama ?? '— kode tidak dikenal —',
                ],
            );

            Log::info('Pembayaran DOKU tercatat', [
                'invoice' => $invoice,
                'kode' => $bayar->kode,
                'diterima' => $dibayar,
                'ke_tagihan' => $keTagihan,
            ]);

            return true;
        });
    }

    /** Tanggal transaksi dari DOKU; hari ini bila tidak terbaca. */
    private static function tanggal(array $badan): string
    {
        $mentah = data_get($badan, 'transaction.date');

        if (blank($mentah)) {
            return now()->toDateString();
        }

        try {
            // Waktunya UTC; yang dicatat tanggal setempat, karena itu yang
            // dibaca admin saat mencocokkan dengan hari kerjanya sendiri.
            return Carbon::parse($mentah)->timezone(config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    /** "VIRTUAL_ACCOUNT_BCA" → "Virtual Account BCA". */
    private static function labelChannel(string $channel): string
    {
        $kata = explode(' ', strtolower(str_replace('_', ' ', $channel)));

        $rapi = array_map(
            // Singkatan bank tetap huruf besar: "Bca" salah baca, "BCA" tidak.
            fn (string $k) => strlen($k) <= 4 ? strtoupper($k) : ucfirst($k),
            $kata,
        );

        return implode(' ', $rapi);
    }
}
