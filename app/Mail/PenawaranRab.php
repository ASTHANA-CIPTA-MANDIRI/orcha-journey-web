<?php

namespace App\Mail;

use App\Models\Rab\Rab;
use App\Support\Rab\BerkasRab;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Penawaran perjalanan, berikut PDF-nya.
 *
 * Sebelum ini email pelanggan dikumpulkan di borang RAB lalu tidak pernah
 * dipakai: satu-satunya jalur keluar adalah WhatsApp, dan penawarannya
 * dikirim sebagai berkas yang diunduh admin lalu dilampirkan sendiri.
 *
 * Yang dilampirkan SELALU berkas penawaran, tidak pernah yang internal.
 * Keduanya dirakit dari templat berbeda justru karena yang internal memuat
 * modal dan margin — angka yang tidak boleh sampai ke pelanggan. Pilihannya
 * karena itu tidak diserahkan ke pemanggil: satu parameter yang salah isi
 * sekali saja sudah cukup untuk membocorkan seluruh struktur harga kantor.
 */
class PenawaranRab extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Rab $rab) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Penawaran Perjalanan '.$this->rab->judul.' — '.$this->rab->kode,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.penawaran-rab');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => BerkasRab::buat($this->rab, 'penawaran'),
                $this->rab->namaBerkas('penawaran'),
            )->withMime('application/pdf'),
        ];
    }
}
