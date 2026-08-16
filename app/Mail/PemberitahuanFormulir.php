<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Satu surat untuk semua formulir publik.
 *
 * Isinya cuma judul, kode, dan deretan baris "label : nilai" — bentuk yang
 * sama untuk pendaftaran, bukti bayar, riwayat kesehatan, dan pembatalan.
 * Membuat empat kelas hampir kembar hanya menambah tempat untuk lupa
 * memperbarui salah satunya.
 */
class PemberitahuanFormulir extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|null>  $rincian
     * @param  array<int, string>  $lampiran  jalur berkas di disk publik
     * @param  array<string, string>  $berkasPdf  nama berkas => isi PDF
     * @param  bool  $untukPelanggan  surat yang dibaca pelanggan, bukan kotak kantor
     * @param  string|null  $tautan  tujuan tombol utama; kosong = tombol bawaan
     */
    public function __construct(
        public string $judul,
        public string $kode,
        public array $rincian,
        public ?string $catatan = null,
        public array $lampiran = [],
        public array $berkasPdf = [],
        public bool $untukPelanggan = false,
        public ?string $tautan = null,
        public ?string $labelTautan = null,
    ) {}

    /**
     * Sebutan berkas lampiran, diambil dari nama berkasnya sendiri.
     *
     * Menyebut semuanya "kwitansi" pernah membuat surat pendaftaran mengaku
     * melampirkan kwitansi padahal isinya tagihan — pelanggan bisa mengira
     * pembayarannya sudah lunas.
     */
    public function labelLampiran(): string
    {
        $nama = (string) array_key_first($this->berkasPdf);

        return match (true) {
            str_contains($nama, 'RINCIAN-BIAYA') => 'Rincian biaya lengkap terlampir sebagai PDF',
            str_contains($nama, 'TANDA-TERIMA') => 'Tanda terima terlampir sebagai PDF',
            str_contains($nama, 'PEMBATALAN') => 'Tanda terima pengajuan terlampir sebagai PDF',
            default => 'Berkasnya terlampir di surat ini',
        };
    }

    public function envelope(): Envelope
    {
        // Kode ikut di subjek supaya mudah dicari dan dibalas di kotak masuk.
        //
        // Awalan "[Orcha]" hanya untuk kotak kantor — di sana subjeknya dipakai
        // menyaring pekerjaan masuk. Di kotak pelanggan awalan seperti itu
        // terbaca seperti surat sebaran, jadi namanya ditulis utuh.
        $subjek = $this->untukPelanggan
            ? trim("Orcha Journey — {$this->judul}".(filled($this->kode) ? " ({$this->kode})" : ''))
            : trim("[Orcha] {$this->judul} — {$this->kode}");

        return new Envelope(subject: $subjek);
    }

    public function content(): Content
    {
        // Sifat turunan dikirim lewat with(): tampilan surat hanya menerima
        // properti publik, bukan metodenya.
        return new Content(
            view: 'emails.pemberitahuan',
            with: ['labelLampiran' => $this->labelLampiran()],
        );
    }

    public function attachments(): array
    {
        // Berkas dari disk (mis. foto bukti transfer)
        $dariDisk = collect($this->lampiran)
            ->filter(fn ($jalur) => filled($jalur) && file_exists(public_path(ltrim($jalur, '/'))))
            ->map(fn ($jalur) => \Illuminate\Mail\Mailables\Attachment::fromPath(
                public_path(ltrim($jalur, '/'))
            ));

        // PDF yang dibuat saat itu juga (kwitansi/tanda terima)
        $pdf = collect($this->berkasPdf)
            ->filter()
            ->map(fn ($isi, $nama) => \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $isi, $nama
            )->withMime('application/pdf'));

        return $dariDisk->merge($pdf)->values()->all();
    }
}
