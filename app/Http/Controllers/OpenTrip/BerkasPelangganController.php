<?php

namespace App\Http\Controllers\OpenTrip;

use App\Http\Controllers\Api\OpenTrip\PendaftaranController;
use App\Http\Controllers\Controller;
use App\Models\OpenTrip\PendaftaranOpenTrip;
use App\Models\Umum\TautanPendek;
use Illuminate\Http\Request;

/**
 * Berkas pendaftaran yang boleh dibuka pelanggan sendiri.
 *
 * Kwitansinya sudah bisa diunduh admin lewat API bertkunci, tetapi berkas itu
 * tidak bisa dibagikan: alamatnya menuntut X-Orcha-Key, dan pelanggan yang
 * mengetuknya dari WhatsApp cuma menerima penolakan. Selama ini admin
 * mengunduhnya dulu lalu melampirkannya satu per satu.
 *
 * Alamatnya ditandatangani dan berumur, bukan sekadar sulit ditebak: kode
 * pendaftaran tercetak di setiap berkas dan tersebar di percakapan, jadi
 * memakainya sebagai satu-satunya kunci berarti siapa pun yang pernah melihat
 * kodenya bisa menarik kwitansi orang lain kapan saja.
 */
class BerkasPelangganController extends Controller
{
    /**
     * Satu pintu untuk semua berkas yang dibagikan lewat tautan pendek.
     *
     * Jenis berkasnya yang disimpan, bukan alamat tujuannya: menyimpan URL
     * berarti tautan yang sudah telanjur dikirim ke pelanggan menunjuk rute
     * lama selamanya, dan rute berubah lebih sering daripada yang diakui
     * siapa pun.
     */
    public function pendek(string $kode, Request $request)
    {
        $tautan = TautanPendek::where('kode', $kode)->first();

        abort_if($tautan === null || ! $tautan->masihBerlaku(), 404,
            'Tautan ini sudah tidak berlaku. Mintalah tautan baru ke admin.');

        $pendaftaran = PendaftaranOpenTrip::findOrFail($tautan->pendaftaran_id);

        return match ($tautan->jenis) {
            'kwitansi' => $this->kwitansi($pendaftaran, $request),
            'surat-penggantian' => $this->suratPenggantian($pendaftaran, $request),
            default => abort(404),
        };
    }

    public function kwitansi(PendaftaranOpenTrip $pendaftaran, Request $request)
    {
        // Isinya dibuat kelas yang sama dengan yang dipakai admin, supaya
        // berkas yang dipegang pelanggan tidak pernah berbeda dari yang
        // dilihat kantor.
        return app(PendaftaranController::class)->kwitansi($pendaftaran, $request);
    }

    /**
     * Surat pernyataan penggantian yang masih menunggu tanda tangan.
     *
     * Berbeda dari salinan bertanda tangan yang tersimpan di arsip: yang ini
     * berkas kosong untuk dicetak, ditandatangani, lalu dikirim balik. Itu
     * langkah pertamanya, dan sebelum ini admin harus mengunduhnya sendiri lalu
     * melampirkannya satu per satu.
     */
    public function suratPenggantian(PendaftaranOpenTrip $pendaftaran, Request $request)
    {
        return app(PendaftaranController::class)->suratPenggantian($pendaftaran, $request);
    }
}
