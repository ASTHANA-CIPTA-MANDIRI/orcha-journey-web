<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Faktur tagihan yang dikirim saat halaman pembayaran dibuat.
 *
 * Sengaja BUKAN memakai PemberitahuanFormulir. Surat itu berbentuk deretan
 * "label : nilai" — bentuk yang tepat untuk memberitahu, tetapi bukan untuk
 * menagih. Faktur punya bagian yang tidak dimiliki pemberitahuan: siapa
 * menagih siapa, rincian barang berikut jumlahnya, dan satu angka total yang
 * harus terbaca lebih besar dari yang lain.
 *
 * Yang digantikannya adalah faktur otomatis dari DOKU. Isi faktur DOKU benar,
 * tetapi pengirimnya tidak bisa diubah: selalu noreply@doku.com, berbahasa
 * Inggris, dari nama yang tidak dikenal pelanggan. Untuk orang yang baru
 * memesan trip ke Orcha, surat semacam itu lebih mirip penipuan daripada
 * tagihan — dan yang dilakukan orang yang ragu bukan bertanya, melainkan
 * tidak membayar.
 */
class TagihanPembayaran extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $kode  kode pesanan (OT-… / SK-…)
     * @param  string  $invoice  nomor tagihan yang dikenal DOKU
     * @param  string  $barang  nama layanan yang ditagihkan
     * @param  int  $pokok  harga sebelum kode unik
     * @param  int  $kodeUnik  penanda pembayaran, 500–1.500
     * @param  int  $nominal  yang benar-benar ditagihkan
     * @param  string  $url  halaman pembayaran DOKU
     */
    public function __construct(
        public string $kode,
        public string $invoice,
        public string $namaPelanggan,
        public ?string $emailPelanggan,
        public ?string $teleponPelanggan,
        public string $jenisLabel,
        public string $barang,
        public int $pokok,
        public int $kodeUnik,
        public int $nominal,
        public string $url,
        public ?Carbon $kedaluwarsa = null,
    ) {}

    public function envelope(): Envelope
    {
        // Kodenya ikut di subjek supaya pelanggan bisa menemukannya kembali
        // dengan mencari, dan supaya balasannya jelas menunjuk pesanan mana.
        return new Envelope(subject: "Orcha Journey — Tagihan Pembayaran ({$this->kode})");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tagihan');
    }
}
