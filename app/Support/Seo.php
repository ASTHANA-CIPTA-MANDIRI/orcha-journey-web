<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Keterangan halaman untuk mesin pencari dan pratinjau tautan.
 *
 * Sebelumnya seluruh halaman publik memakai SATU keterangan yang sama —
 * kalimat tentang Orcha Journey secara umum. Google menampilkan keterangan itu
 * di bawah tiap tautan, jadi hasil pencarian untuk "sewa hiace jogja" dan
 * "study tour" terbaca persis sama, dan tidak satu pun menjawab yang dicari.
 * Pratinjau tautan di WhatsApp pun menampilkan kalimat yang sama untuk halaman
 * apa pun yang dikirim.
 *
 * Keterangannya dikumpulkan DI SINI, berkunci nama rute, bukan disebar ke
 * delapan belas berkas Volt. Dua alasannya:
 *
 *   1. Kalimat pemasaran ditulis dan diperiksa sekaligus. Yang tersebar akan
 *      berbeda-beda nadanya dan sebagian tidak pernah diisi.
 *   2. Halaman yang lupa didaftarkan tetap mendapat keterangan bawaan yang
 *      masuk akal, bukan kosong.
 */
class Seo
{
    /**
     * Halaman yang TIDAK boleh masuk hasil pencarian.
     *
     * Semuanya formulir atau halaman sekali pakai: tidak ada yang mencarinya
     * lewat Google, dan yang muncul di hasil pencarian justru menyesatkan —
     * "Konfirmasi Pembayaran" yang dibuka orang asing hanya membuat bingung.
     * Halaman riwayat kesehatan bahkan memuat pertanyaan medis peserta.
     */
    private const JANGAN_DIINDEKS = [
        'pendaftaran-open-trip',
        'riwayat-kesehatan',
        'pembatalan',
        'konfirmasi-pembayaran',
        'sewa-kendaraan.pesan',
        'tautan.pendek',
    ];

    /**
     * Keterangan per halaman.
     *
     * Panjangnya dijaga di kisaran 120–160 huruf: lebih pendek menyisakan
     * ruang yang diisi Google sendiri dengan potongan kalimat acak, lebih
     * panjang dipenggal di tengah kata.
     */
    private const KETERANGAN = [
        'home' => 'Open trip, private trip, study tour, dan sewa mobil, HiAce, serta bus pariwisata dari Yogyakarta. Armada terawat, sopir berpengalaman, harga jelas di muka.',
        'tentang-kami' => 'Kenali Orcha Journey — penyedia open trip, private trip, study tour, dan sewa kendaraan pariwisata yang berbasis di Yogyakarta.',
        'paket-wisata' => 'Daftar paket open trip, private trip, dan study tour Orcha Journey lengkap dengan jadwal keberangkatan, harga per orang, dan fasilitas yang sudah termasuk.',
        'sewa-kendaraan' => 'Sewa mobil, HiAce, dan bus pariwisata di Yogyakarta. Tarif per jam, per 12 jam, dan harian, dengan atau tanpa sopir — ketersediaan unit bisa dicek langsung.',
        'destinasi' => 'Destinasi wisata populer yang dilayani Orcha Journey di Yogyakarta, Jawa Tengah, dan Jawa Timur, berikut gambaran singkat tiap tempatnya.',
        'testimoni' => 'Cerita pelanggan yang sudah berangkat bersama Orcha Journey — open trip, private trip, study tour, maupun sewa kendaraan.',
        'kontak' => 'Hubungi Orcha Journey lewat WhatsApp, email, atau formulir kontak untuk menanyakan paket wisata dan ketersediaan armada.',
        'faq' => 'Pertanyaan yang paling sering diajukan seputar pemesanan, uang muka, pelunasan, pembatalan, dan sewa kendaraan di Orcha Journey.',
        'syarat-ketentuan' => 'Syarat dan ketentuan pemesanan paket wisata serta sewa kendaraan di Orcha Journey — hak dan kewajiban kedua pihak.',
        'ketentuan-pembayaran' => 'Ketentuan uang muka, batas pelunasan, dan cara pembayaran untuk paket wisata dan sewa kendaraan Orcha Journey.',
        'kebijakan-pengembalian' => 'Kebijakan pembatalan dan pengembalian dana Orcha Journey, lengkap dengan besaran potongan menurut jarak waktu ke keberangkatan.',
        'kebijakan-privasi' => 'Bagaimana Orcha Journey mengumpulkan, memakai, dan menjaga data pribadi pelanggan.',
        'blog' => 'Panduan perjalanan, cerita destinasi, dan tips persiapan dari tim Orcha Journey — untuk yang sedang menimbang mau berangkat ke mana.',
    ];

    private const BAWAAN = 'Orcha Journey melayani open trip, private trip, study tour, serta sewa mobil, HiAce, dan bus pariwisata di Yogyakarta dan sekitarnya.';

    /** Keterangan untuk halaman yang sedang dibuka. */
    public static function keterangan(?string $rute = null, ?string $khusus = null): string
    {
        if (filled($khusus)) {
            return self::rapikan($khusus);
        }

        return self::KETERANGAN[$rute ?? request()->route()?->getName()] ?? self::BAWAAN;
    }

    /** Boleh masuk hasil pencarian? */
    public static function bolehDiindeks(?string $rute = null): bool
    {
        return ! in_array($rute ?? request()->route()?->getName(), self::JANGAN_DIINDEKS, true);
    }

    /**
     * Alamat kanonis: tanpa parameter pencarian dan tanpa garis miring di ujung.
     *
     * Halaman yang sama bisa dicapai lewat beberapa alamat — ?halaman=1,
     * ?utm_source=..., dengan atau tanpa garis miring. Tanpa kanonis, mesin
     * pencari memperlakukannya sebagai halaman berbeda dan nilai satu halaman
     * terpecah ke beberapa alamat.
     */
    public static function kanonis(): string
    {
        return rtrim(url()->current(), '/') ?: url('/');
    }

    /** Potong pada batas kata, bukan di tengah kata. */
    private static function rapikan(string $teks): string
    {
        $teks = trim(preg_replace('/\s+/', ' ', strip_tags($teks)));

        return Str::limit($teks, 157, '…');
    }
}
