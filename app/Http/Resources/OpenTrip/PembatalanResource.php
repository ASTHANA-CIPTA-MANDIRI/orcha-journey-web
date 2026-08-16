<?php

namespace App\Http\Resources\OpenTrip;

use App\Support\PerkiraanPotongan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\Pembatalan $resource
 */
class PembatalanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_pendaftaran' => $this->kode_pendaftaran,

            // Jenisnya dibaca dari kodenya sendiri, tanpa query tambahan —
            // daftar pembatalan bisa panjang, dan satu query per baris hanya
            // untuk menampilkan satu kata tidak sebanding.
            //
            // Perlu disebut karena "1 peserta dibatalkan" pada sewa kendaraan
            // membingungkan: yang dibatalkan unitnya, bukan orangnya.
            'jenis' => str_starts_with((string) $this->kode_pendaftaran, 'SK-') ? 'sewa_kendaraan' : 'open_trip',
            'jenis_label' => str_starts_with((string) $this->kode_pendaftaran, 'SK-') ? 'Sewa Kendaraan' : 'Open Trip',
            'nama_pemohon' => $this->nama_pemohon,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'alasan' => $this->alasan,
            'alasan_label' => $this->alasan_label,
            'penjelasan' => $this->penjelasan,
            'jumlah_dibatalkan' => $this->jumlah_dibatalkan,
            'rekening' => [
                'bank' => $this->bank,
                'nomor' => $this->nomor_rekening,
                'atas_nama' => $this->atas_nama_rekening,
            ],
            'status' => $this->status,
            'status_label' => $this->status_label,
            'catatan_admin' => $this->catatan_admin,

            // Perkiraan potongan menurut tangga yang berlaku. Dikirim supaya
            // admin tidak menghitungnya ulang satu per satu — pertanyaan
            // pertama pada tiap pengajuan selalu "kembalinya berapa".
            //
            // Tetap perkiraan: yang menetapkan tim, karena ada hal yang tidak
            // diketahui sistem (biaya yang sudah terlanjur dibayarkan ke pihak
            // ketiga, kesepakatan menjadwal ulang).
            'perkiraan' => PerkiraanPotongan::untuk($this->pesanan()),
            'pendaftaran' => $this->whenLoaded('pendaftaran', fn () => [
                'kode' => $this->pendaftaran?->kode,
                'nama_paket' => $this->pendaftaran?->nama_paket,
                'tanggal_berangkat' => $this->pendaftaran?->tanggal_berangkat?->toDateString(),
                'jumlah_peserta' => $this->pendaftaran?->jumlah_peserta,
            ]),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
