<?php

namespace App\Http\Resources\SewaKendaraan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\SewaKendaraan\PenyewaanKendaraan $resource
 */
class PenyewaanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'kendaraan' => [
                'id' => $this->car_id,
                'nama' => $this->nama_kendaraan,
                'transmisi' => $this->transmisi,
            ],
            'satuan' => $this->satuan,
            'satuan_label' => $this->satuan_label,
            'durasi' => $this->durasi,
            'durasi_label' => $this->durasi_label,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'jam_mulai' => $this->jam_mulai ? substr((string) $this->jam_mulai, 0, 5) : null,
            'dengan_sopir' => (bool) $this->dengan_sopir,
            'lokasi_antar' => $this->lokasi_antar,
            'estimasi_biaya' => $this->estimasi_biaya,
            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
