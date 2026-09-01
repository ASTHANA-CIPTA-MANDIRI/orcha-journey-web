<?php

namespace App\Support;

use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\SewaKendaraan\PenyewaanKendaraan;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Memastikan yang membuka sebuah pesanan memang pemiliknya.
 *
 * Sebelumnya kode pesanan adalah SATU-SATUNYA kunci. Siapa pun yang
 * mengetikkan kode yang benar langsung melihat nama pemesan, trip yang
 * dipesan, tanggal berangkat, dan sisa tagihannya — lalu boleh mengajukan
 * pembatalan berikut nomor rekening tujuan pengembalian, tanpa satu pun
 * pemeriksaan bahwa ia orang yang sama.
 *
 * Dan kodenya bisa ditebak: 'OT-' + tanggal-bulan + empat karakter yang, karena
 * Str::upper melipat huruf kecil ke besar, hanya punya 36 kemungkinan per
 * huruf. Tidak ada pembatasan percobaan di grup web, jadi menebaknya cuma soal
 * waktu — dan tidak ada satu pun jejak bahwa ada yang mencoba.
 *
 * Kunci keduanya EMPAT DIGIT TERAKHIR NOMOR WHATSAPP yang dipakai saat
 * memesan. Dipilih karena pemiliknya selalu tahu tanpa perlu mencari:
 * nomornya sendiri. Sengaja bukan kata sandi — pemesanan di sini tidak berakun,
 * dan memaksa orang membuat akun demi satu trip akan lebih banyak
 * menggagalkan pemesanan daripada menggagalkan penipuan.
 */
class PemilikPesanan
{
    /** Percobaan gagal per jam, per pengetik. */
    private const BATAS_GAGAL = 15;

    /**
     * Pesanan yang kodenya cocok DAN nomornya cocok. Selain itu null.
     *
     * Nullnya disengaja tidak membedakan "kode tidak ada" dari "nomor salah".
     * Pembeda apa pun antara keduanya mengubah halaman ini menjadi alat
     * pemeriksa kode: penebak cukup melihat pesan mana yang muncul untuk tahu
     * kodenya sudah benar, dan sisa pekerjaannya tinggal empat digit.
     */
    public static function cari(?string $kode, ?string $empatDigit): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        $pesanan = self::tanpaPeriksa($kode);

        if (! $pesanan) {
            return null;
        }

        if (! self::nomorCocok($pesanan->whatsapp, $empatDigit)) {
            return null;
        }

        return self::masihBerlaku($pesanan) ? $pesanan : null;
    }

    /**
     * Kode berhenti berlaku di halaman publik begitu perjalanannya dimulai.
     *
     * Sesudah hari keberangkatan, tidak ada lagi yang wajar dikerjakan
     * pengunjung dengan kode itu: pembatalan sudah tidak berlaku, formulir
     * kesehatan sudah dipakai di lapangan, dan pelunasan seharusnya beres
     * sejak H-5. Yang tersisa hanyalah jendela terbuka — dan kode yang sudah
     * beredar berbulan-bulan di grup WhatsApp adalah kode yang paling mungkin
     * jatuh ke tangan lain.
     *
     * Menutupnya tidak menghilangkan satu pun kemampuan yang dipakai orang;
     * ia hanya memperpendek umur kunci yang tidak lagi membuka apa-apa.
     *
     * Yang TIDAK ikut tertutup: jalur admin. Tim tetap bisa membuka pesanan
     * lama lewat lemon, dan justru di situlah urusan susulan diselesaikan.
     */
    public static function masihBerlaku(PendaftaranOpenTrip|PenyewaanKendaraan|null $pesanan): bool
    {
        if (! $pesanan) {
            return false;
        }

        $mulai = $pesanan instanceof PenyewaanKendaraan
            ? $pesanan->tanggal_mulai
            : $pesanan->tanggal_berangkat;

        // Pesanan yang tanggalnya belum ditetapkan tetap terbuka: menutupnya
        // berarti menghukum pemesan atas data yang belum kita isi sendiri.
        if (! $mulai) {
            return true;
        }

        /*
         | Batasnya AWAL hari keberangkatan, bukan jamnya.
         |
         | Jam berangkat tidak tersimpan di sistem, dan menebaknya akan
         | menghasilkan penutupan yang meleset ke dua arah. Awal hari adalah
         | satu-satunya garis yang bisa dipastikan benar untuk semua pesanan.
         */
        return now()->startOfDay()->lt($mulai->copy()->startOfDay());
    }

    /**
     * Pesanan menurut kodenya saja, TANPA memeriksa pemiliknya.
     *
     * Hanya untuk pemakaian di sisi dalam — mengirim surat, menyusun berkas —
     * tempat kodenya datang dari data kita sendiri, bukan dari yang mengetik.
     * Jangan dipakai untuk memutuskan apa yang boleh dilihat di halaman
     * publik; itu justru lubang yang ditutup kelas ini.
     */
    public static function tanpaPeriksa(?string $kode): PendaftaranOpenTrip|PenyewaanKendaraan|null
    {
        $kode = strtoupper(trim((string) $kode));

        if (blank($kode)) {
            return null;
        }

        return str_starts_with($kode, 'SK-')
            ? PenyewaanKendaraan::with('kendaraan')->where('kode', $kode)->first()
            : PendaftaranOpenTrip::with('paket')->where('kode', $kode)->first();
    }

    /**
     * Seperti cari(), tetapi percobaan yang GAGAL ikut dihitung.
     *
     * Keenam formulir publik sudah membatasi PENGIRIMAN (8 per jam). Yang
     * tidak terbatas adalah PENCARIANNYA: kode diketik dengan
     * wire:model.live, dan tiap ketikan memanggil pencarian tanpa pernah
     * menyentuh pembatas mana pun. Justru jalur itulah yang dipakai untuk
     * menebak kode satu per satu.
     *
     * Yang dihitung hanya yang gagal. Pemilik pesanan yang benar mengetik
     * kodenya sekali lalu berhasil, jadi ia tidak pernah mendekati batas —
     * sedangkan penebak hampir selalu gagal dan berhenti dalam belasan
     * percobaan. Ini juga yang membuat batasnya boleh ketat tanpa menyusahkan
     * orang di jaringan bersama (kantor, kafe) yang IP-nya sama.
     *
     * Batas tercapai mengembalikan null, sama seperti kode salah — halaman
     * pemanggil tidak perlu bercabang, dan penebak tidak mendapat isyarat
     * bahwa ia sedang dibatasi.
     */
    public static function cariTerbatas(?string $kode, ?string $empatDigit, string $sidikJari): ?object
    {
        $kunci = 'cari-pesanan:'.$sidikJari;

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_GAGAL)) {
            return null;
        }

        $pesanan = self::cari($kode, $empatDigit);

        if (! $pesanan) {
            // Ketikan yang belum selesai jangan ikut dihukum: kode yang masih
            // separuh diketik pasti tidak ketemu, dan menghitungnya membuat
            // pemilik sah kehabisan jatah sebelum selesai mengetik.
            if (self::layakDihitung($kode, $empatDigit)) {
                RateLimiter::hit($kunci, 3600);
            }

            return null;
        }

        return $pesanan;
    }

    /** Percobaan yang bentuknya sudah lengkap — itu saja yang dihitung. */
    private static function layakDihitung(?string $kode, ?string $empatDigit): bool
    {
        return strlen(trim((string) $kode)) >= 10
            && strlen(preg_replace('/\D/', '', (string) $empatDigit)) === 4;
    }

    /** Empat digit terakhir, dibandingkan setelah kedua nomor diseragamkan. */
    public static function nomorCocok(?string $tersimpan, ?string $diketik): bool
    {
        $benar = substr(NomorTelepon::angka($tersimpan), -4);
        $coba = substr(preg_replace('/\D/', '', (string) $diketik), -4);

        // Nomor tersimpan yang lebih pendek dari empat digit berarti datanya
        // memang tidak utuh. Yang dilakukan: menolak, bukan meloloskan —
        // perbandingan terhadap potongan kosong akan cocok dengan apa pun.
        if (strlen($benar) < 4 || strlen($coba) < 4) {
            return false;
        }

        // hash_equals: lamanya perbandingan tidak lagi bergantung pada berapa
        // digit pertama yang sudah benar.
        return hash_equals($benar, $coba);
    }
}
