<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\TravelPackage $resource
 */
class PaketWisataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'nama' => $this->name,
            'kategori' => $this->category,
            'kategori_label' => $this->category_label,
            'durasi' => $this->duration,
            'jadwal_label' => $this->jadwal_label,
            'tanggal_berangkat' => $this->tanggal_berangkat?->toDateString(),
            'tanggal_pulang' => $this->tanggal_pulang?->toDateString(),
            'batas_pelunasan' => $this->batas_pelunasan?->toDateString(),
            'sudah_lewat' => $this->sudah_lewat,
            'titik_jemput' => $this->titik_jemput,
            'minimal_peserta' => $this->minimal_peserta,
            'harga' => $this->price,
            'harga_asli' => $this->original_price,
            'diskon_persen' => $this->discount_percentage,
            'catatan_promo' => $this->catatan_promo,
            'pilihan_terbaik' => (bool) $this->is_best_choice,
            'destinasi' => $this->destination_list ?? [],
            'fasilitas' => $this->fasilitas ?? [],
            'itinerary' => $this->itinerary ?? [],
            'sampul' => $this->sampul,
            'jumlah_pendaftar' => $this->whenCounted('pendaftaran'),
            'tautan_publik' => url('/paket/'.$this->uuid),
        ];
    }
}
