<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyalin seluruh basis data jadi satu berkas .sql.gz — murni PHP.
 *
 * TIDAK memanggil mysqldump, dan itu bukan pilihan gaya. Hosting mematikan
 * proc_open, sehingga apa pun yang menjalankan perintah luar gagal diam-diam:
 * itu sebab penjadwal Laravel di sini juga tidak bisa dipakai dan semua
 * perintah Orcha dipanggil cron langsung. Paket cadangan yang beredar semuanya
 * bersandar pada mysqldump, jadi tidak satu pun bisa dipakai.
 *
 * Yang paling penting dari sebuah cadangan bukan bahwa ia dibuat, melainkan
 * bahwa ia bisa DIKEMBALIKAN. Berkasnya sengaja berupa .sql biasa yang bisa
 * ditempel ke phpMyAdmin — bukan bentuk khusus yang menuntut alat kami sendiri
 * untuk membacanya. Pada hari kita benar-benar membutuhkannya, alat itu
 * mungkin ikut hilang.
 */
class CadanganBasisData
{
    /**
     * Berapa baris ditarik sekali jalan.
     *
     * Menarik seluruh tabel sekaligus membuat proses mati kehabisan memori
     * justru pada tabel yang paling berharga — yang paling banyak isinya.
     * Seribu baris cukup kecil untuk hosting bersama dan cukup besar untuk
     * tidak menghabiskan waktu di perjalanan bolak-balik.
     */
    private const SEKALI_TARIK = 1000;

    /**
     * @return string jalur berkas .sql.gz yang dihasilkan
     */
    public static function buat(?string $keFolder = null): string
    {
        $folder = $keFolder ?: storage_path('app/cadangan');

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $nama = 'orcha-'.now()->format('Y-m-d-His').'.sql.gz';
        $jalur = $folder.'/'.$nama;

        // Ditulis langsung terkompresi, bukan ditulis penuh lalu dikompresi.
        // Basis data yang isinya besar berarti dua berkas besar sekaligus di
        // cakram hosting yang kuotanya pas-pasan.
        $berkas = gzopen($jalur, 'wb9');

        if ($berkas === false) {
            throw new \RuntimeException('Tidak bisa membuat berkas cadangan di '.$folder);
        }

        try {
            self::tulisKepala($berkas);

            foreach (self::daftarTabel() as $tabel) {
                self::tulisTabel($berkas, $tabel);
            }

            self::tulisKaki($berkas);
        } finally {
            gzclose($berkas);
        }

        return $jalur;
    }

    /**
     * Daftar tabelnya diambil lewat Schema, bukan lewat SHOW FULL TABLES.
     *
     * Sasarannya memang MySQL — itu yang berjalan di server. Tetapi seluruh
     * uji berjalan di sqlite, dan perintah khusus MySQL membuat isi berkasnya
     * tidak bisa diperiksa satu baris pun. Yang tersisa hanya uji yang
     * memastikan berkasnya ADA, dan cadangan yang ada tetapi tidak bisa
     * diimpor terlihat persis sama dengan cadangan yang baik.
     *
     * @return array<int, string>
     */
    private static function daftarTabel(): array
    {
        return collect(Schema::getTables())

            /*
             | DISARING KE BASIS DATA INI SAJA.
             |
             | Schema::getTables() tanpa argumen mengembalikan tabel dari
             | SELURUH skema yang bisa dilihat sambungannya — dan di server ini
             | Orcha berbagi satu mesin MySQL dengan aplikasi lain. Tanpa
             | saringan ini perintah cadangan mencoba membaca tabel milik
             | aplikasi tetangga, gagal di tabel pertama yang bukan miliknya,
             | dan tidak menghasilkan cadangan sama sekali.
             |
             | Yang lebih buruk kalau kebetulan berhasil: berkas cadangan
             | Orcha akan memuat isi basis data aplikasi lain — dan berkas itu
             | kita unggah ke Drive.
             */
            ->where('schema', self::skemaSendiri())

            ->pluck('name')
            // Tabel bawaan sqlite tidak pernah ada di MySQL dan tidak perlu
            // ikut dicadangkan di mana pun.
            ->reject(fn ($nama) => str_starts_with($nama, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Nama skema milik sambungan ini sendiri.
     *
     * MySQL menamai skemanya sama dengan nama basis datanya. sqlite selalu
     * menyebut basis data utamanya 'main', apa pun nama berkasnya — sementara
     * getDatabaseName() di sana mengembalikan jalur berkas atau ':memory:'.
     * Menyamakan keduanya berarti saringannya tidak pernah cocok dan
     * cadangannya kosong.
     */
    private static function skemaSendiri(): string
    {
        return DB::getDriverName() === 'sqlite' ? 'main' : DB::getDatabaseName();
    }

    /**
     * Perintah CREATE TABLE untuk satu tabel.
     *
     * Bercabang menurut penggerak basis datanya. MySQL menjawabnya lewat SHOW
     * CREATE TABLE; sqlite menyimpannya apa adanya di sqlite_master.
     */
    private static function skema(string $tabel): ?string
    {
        if (DB::getDriverName() === 'sqlite') {
            $baris = DB::selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $tabel]);

            return $baris->sql ?? null;
        }

        $buat = (array) DB::selectOne('SHOW CREATE TABLE `'.$tabel.'`');

        return $buat['Create Table'] ?? array_values($buat)[1] ?? null;
    }

    /**
     * @param  resource  $berkas
     */
    private static function tulisKepala($berkas): void
    {
        gzwrite($berkas, "-- Cadangan Orcha Journey\n"
            .'-- Dibuat '.now()->translatedFormat('l, j F Y H:i:s')."\n"
            .'-- Basis data: '.DB::getDatabaseName()."\n"
            ."--\n"
            ."-- Cara memulihkan: buka phpMyAdmin, pilih basis datanya, tab Impor,\n"
            ."-- unggah berkas ini. Berkasnya .sql biasa yang dikompresi gzip.\n\n"

            /*
             | Pemeriksaan kunci asing dimatikan selama impor.
             |
             | Tabel dipulihkan berurutan abjad, bukan berurutan
             | ketergantungan — dan tanpa ini, tabel yang menunjuk tabel yang
             | belum dibuat ditolak. Yang menemukannya nanti orang yang sedang
             | memulihkan data setelah kehilangan, yaitu saat paling buruk
             | untuk menemukan apa pun.
             */
            ."SET FOREIGN_KEY_CHECKS = 0;\n"
            ."SET NAMES utf8mb4;\n"
            ."SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
    }

    /**
     * @param  resource  $berkas
     */
    private static function tulisKaki($berkas): void
    {
        gzwrite($berkas, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /**
     * @param  resource  $berkas
     */
    private static function tulisTabel($berkas, string $tabel): void
    {
        $skema = self::skema($tabel);

        /*
         | Tabel yang skemanya tidak terbaca menggagalkan SELURUH cadangan,
         | bukan dilewati.
         |
         | Melewatinya menghasilkan berkas yang ukurannya masuk akal, isinya
         | rapi, dan kehilangan satu tabel penuh. Cadangan seperti itu tidak
         | bisa dibedakan dari cadangan yang utuh sampai hari pemulihan — dan
         | pada hari itu tabel yang hilang tidak ada di mana pun lagi.
         |
         | Kegagalan yang berisik selalu lebih baik: perintahnya keluar dengan
         | kode bukan-nol, cron mengirim surat, dan kita masih punya cadangan
         | kemarin.
         */
        if (! $skema) {
            throw new \RuntimeException(
                'Skema tabel "'.$tabel.'" tidak terbaca. Cadangan dibatalkan supaya '
                .'tidak menghasilkan berkas yang kehilangan satu tabel tanpa ketahuan.'
            );
        }

        gzwrite($berkas, "\n-- ----------------------------\n"
            .'-- Tabel: '.$tabel."\n"
            ."-- ----------------------------\n"
            .'DROP TABLE IF EXISTS `'.$tabel."`;\n"
            .$skema.";\n\n");

        $lewat = 0;

        while (true) {
            $baris = DB::table($tabel)->offset($lewat)->limit(self::SEKALI_TARIK)->get();

            if ($baris->isEmpty()) {
                break;
            }

            foreach ($baris as $satu) {
                gzwrite($berkas, self::perintahIsi($tabel, (array) $satu));
            }

            $lewat += self::SEKALI_TARIK;
        }
    }

    /**
     * @param  array<string, mixed>  $baris
     */
    private static function perintahIsi(string $tabel, array $baris): string
    {
        $kolom = collect(array_keys($baris))
            ->map(fn ($k) => '`'.$k.'`')
            ->implode(', ');

        $nilai = collect($baris)
            ->map(fn ($isi) => match (true) {
                $isi === null => 'NULL',
                is_bool($isi) => $isi ? '1' : '0',
                is_int($isi), is_float($isi) => (string) $isi,

                /*
                 | Dikutip lewat PDO, bukan dengan addslashes.
                 |
                 | Isi basis data ini termasuk catatan yang diketik pelanggan —
                 | apostrof, tanda kutip, baris baru, emoji. Melarikannya
                 | sendiri berarti menebak-nebak aturan MySQL, dan satu tebakan
                 | yang meleset menghasilkan berkas cadangan yang GAGAL
                 | diimpor. Kegagalannya baru ketahuan saat dipulihkan.
                 */
                default => DB::getPdo()->quote((string) $isi),
            })
            ->implode(', ');

        return 'INSERT INTO `'.$tabel.'` ('.$kolom.') VALUES ('.$nilai.");\n";
    }

    /**
     * Membuang cadangan lama, menyisakan sekian yang terbaru.
     *
     * @return array<int, string> berkas yang dibuang
     */
    public static function rapikan(string $folder, int $sisakan): array
    {
        $berkas = collect(glob($folder.'/orcha-*.sql.gz') ?: [])
            // Namanya bertanggal dan berjam, jadi urutan abjad = urutan waktu.
            // Membaca waktu ubah berkas keliru di sini: menyalin folder
            // cadangan menyamakan seluruh waktu ubahnya.
            ->sort()
            ->values();

        $buang = $berkas->slice(0, max(0, $berkas->count() - $sisakan));

        foreach ($buang as $satu) {
            @unlink($satu);
        }

        return $buang->values()->all();
    }
}
