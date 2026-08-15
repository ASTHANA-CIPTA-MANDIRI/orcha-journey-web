<?php

namespace App\Http\Resources\SewaKendaraan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\SewaKendaraan\Car $resource
 */
class KendaraanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'nama' => $this->name,
            'merek' => $this->brand,
            'jenis' => $this->type,
            'jenis_label' => $this->type_label,
            'nopol' => $this->nopol,
            'kapasitas' => $this->capacity,
            'transmisi_tersedia' => $this->transmisi_tersedia_list,
            'transmisi_label' => $this->transmisi_label,
            'tarif' => [
                'jam' => $this->harga_per_jam,
                '12jam' => $this->harga_12_jam,
                'hari' => $this->price_per_day,
                'sopir_per_hari' => $this->harga_sopir,
            ],
            'gambar' => $this->image,
            'tersedia' => (bool) $this->is_available,
            'jumlah_penyewaan' => $this->whenCounted('penyewaan'),
        ];
    }
}
