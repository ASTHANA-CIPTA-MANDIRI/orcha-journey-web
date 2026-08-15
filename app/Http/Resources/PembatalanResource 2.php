<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Pembatalan $resource
 */
class PembatalanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_pendaftaran' => $this->kode_pendaftaran,
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
