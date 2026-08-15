<?php

namespace App\Http\Resources\OpenTrip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\PendaftaranOpenTrip $resource
 */
class PendaftaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'jumlah_peserta' => $this->jumlah_peserta,
            'daftar_peserta' => $this->daftar_peserta ?? [],
            'kesehatan_terisi' => $this->kesehatan_terisi,
            'kesehatan_lengkap' => $this->kesehatan_lengkap,
            'peserta_belum_isi' => $this->peserta_belum_isi,
            'paket' => [
                'id' => $this->travel_package_id,
                'nama' => $this->nama_paket,
            ],
            'tanggal_berangkat' => $this->tanggal_berangkat?->toDateString(),
            'titik_jemput' => $this->titik_jemput,
            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'jumlah_riwayat_kesehatan' => $this->whenCounted('riwayatKesehatan'),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
