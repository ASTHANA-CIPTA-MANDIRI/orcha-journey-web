<?php

namespace App\Support;

use App\Mail\PemberitahuanFormulir;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pengirim pemberitahuan formulir.
 *
 * Ada dua penerima yang berbeda kepentingan:
 *
 * 1. Kotak kantor (halo@orchajourney.com) — perlu tahu ada pekerjaan masuk,
 *    lengkap dengan lampiran bukti supaya bisa dicocokkan dari kotak masuk.
 * 2. Pelanggan yang baru mengisi formulir — perlu pegangan hitam di atas
 *    putih bahwa datanya benar-benar masuk, berikut langkah berikutnya.
 *    Tanpa ini, satu-satunya bukti pelanggan cuma tulisan di layar yang
 *    hilang begitu halamannya ditutup.
 *
 * Aturan terpenting di sini: kegagalan mengirim surat TIDAK BOLEH membatalkan
 * apa yang sudah dikerjakan pelanggan. Data pendaftaran atau bukti transfer
 * sudah tersimpan sebelum ini dipanggil; kalau server surat sedang mati,
 * kejadiannya cukup dicatat di log — bukan dilempar ke layar pelanggan yang
 * mengira pendaftarannya gagal lalu mengirim ulang. Karena itu pula kedua
 * surat dikirim terpisah: surat pelanggan yang gagal tidak ikut menggagalkan
 * surat kantor, dan sebaliknya.
 */
class KirimPemberitahuan
{
    /**
     * @param  array<string, string|null>  $rincian
     * @param  array<int, string>  $lampiran
     * @param  array<string, string>  $berkasPdf
     * @return bool berhasil-tidaknya surat ke kotak kantor
     */
    public static function kirim(
        string $judul,
        string $kode,
        array $rincian,
        ?string $catatan = null,
        array $lampiran = [],
        array $berkasPdf = [],
        ?SalinanPelanggan $pelanggan = null,
    ): bool {
        $keKantor = self::keKantor($judul, $kode, $rincian, $catatan, $lampiran, $berkasPdf);

        if ($pelanggan) {
            self::kePelanggan($pelanggan, $kode, $rincian, $berkasPdf);
        }

        return $keKantor;
    }

    /**
     * @param  array<string, string|null>  $rincian
     * @param  array<int, string>  $lampiran
     * @param  array<string, string>  $berkasPdf
     */
    private static function keKantor(
        string $judul,
        string $kode,
        array $rincian,
        ?string $catatan,
        array $lampiran,
        array $berkasPdf,
    ): bool {
        $tujuan = config('orcha.email_pemberitahuan');

        // Alamat kosong = pemberitahuan memang dimatikan (mis. saat pengujian).
        if (blank($tujuan)) {
            return false;
        }

        return self::coba($tujuan, $judul, $kode, fn () => new PemberitahuanFormulir(
            $judul, $kode, $rincian, $catatan, $lampiran, $berkasPdf
        ));
    }

    /**
     * Salinan untuk pelanggan.
     *
     * Bukti transfer dari disk sengaja TIDAK ikut dilampirkan: berkas itu
     * berasal dari pelanggan sendiri, jadi mengirimkannya balik hanya
     * memperbesar surat. Yang ikut cuma berkas PDF yang kita terbitkan.
     *
     * @param  array<string, string|null>  $rincian
     * @param  array<string, string>  $berkasPdf
     */
    private static function kePelanggan(
        SalinanPelanggan $pelanggan,
        string $kode,
        array $rincian,
        array $berkasPdf,
    ): bool {
        if (! config('orcha.email_salinan_pelanggan', true)) {
            return false;
        }

        // Alamat email di formulir kami memang boleh kosong — nomor WhatsApp
        // yang wajib. Salah ketik pun tidak perlu diributkan ke pelanggan.
        if (blank($pelanggan->email) || ! filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return self::coba($pelanggan->email, $pelanggan->judul, $kode, fn () => new PemberitahuanFormulir(
            $pelanggan->judul,
            $kode,
            $pelanggan->rincian ?? $rincian,
            $pelanggan->langkah,
            [],
            $berkasPdf,
            untukPelanggan: true,
            tautan: $pelanggan->tautan,
            labelTautan: $pelanggan->labelTautan,
        ));
    }

    /**
     * Mencatat surat yang gagal terkirim ke jejak audit.
     *
     * Gagalnya pencatatan ini pun diam: yang sedang ditangani sudah sebuah
     * kegagalan, dan menambahkan kegagalan kedua di atasnya tidak menolong
     * siapa pun.
     */
    private static function catatGagal(string $judul, string $kode, \Throwable $e): void
    {
        try {
            $permintaan = \Illuminate\Http\Request::create('/', 'POST');
            $permintaan->attributes->set('admin_pemanggil', 'Sistem');

            \App\Models\JejakAudit::catat(
                $permintaan,
                'surat gagal terkirim',
                'Surat "'.$judul.'" gagal terkirim. Pelanggan kemungkinan besar '
                    .'belum menerima kabar apa pun. Sebab: '.$e->getMessage(),
                $kode,
            );
        } catch (\Throwable) {
            // Sengaja dibiarkan — lihat alasannya di atas.
        }
    }

    private static function coba(string $tujuan, string $judul, string $kode, \Closure $surat): bool
    {
        try {
            Mail::to($tujuan)->send($surat());

            return true;
        } catch (\Throwable $e) {
            Log::error('Pemberitahuan formulir gagal dikirim', [
                'judul' => $judul,
                'kode' => $kode,
                'tujuan' => $tujuan,
                'galat' => $e->getMessage(),
            ]);

            /*
             | Kegagalannya ikut masuk JEJAK AUDIT, bukan berhenti di berkas log.
             |
             | Sebelum ini nilai balik false-nya tidak pernah dibaca siapa pun
             | dan lognya tidak pernah dibuka. Akibatnya: saat SMTP mati,
             | pendaftaran tetap tersimpan, pelanggan tidak pernah menerima kode
             | pesanannya, dan tidak ada satu pun tanda di layar admin. Kodenya
             | cuma ada di layar pelanggan saat itu — tertutup tab, hilang — dan
             | ia lalu menghubungi WhatsApp menanyakan kode yang seharusnya
             | sudah dikirim.
             |
             | Jejak audit dipilih karena halamannya SUDAH ada dan sudah bisa
             | dicari per kode pesanan. Tabel baru berikut layarnya sendiri
             | hanya menambah tempat yang harus diingat orang untuk dibuka.
             |
             | Alamat tujuannya sengaja TIDAK ikut dicatat: itu data pribadi
             | pelanggan, dan jejak audit dibaca lebih banyak orang daripada
             | yang perlu melihat alamat surelnya. Kodenya sudah cukup untuk
             | menemukan orangnya.
             */
            self::catatGagal($judul, $kode, $e);

            return false;
        }
    }
}
