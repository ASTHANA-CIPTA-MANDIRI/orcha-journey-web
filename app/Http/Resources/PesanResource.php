<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\PesanKontak $resource
 */
class PesanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'keperluan' => $this->keperluan,
            'keperluan_label' => $this->keperluan_label,
            'pesan' => $this->pesan,
            'sudah_dibaca' => $this->sudah_dibaca,
            'dibaca_pada' => $this->dibaca_pada?->toIso8601String(),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
