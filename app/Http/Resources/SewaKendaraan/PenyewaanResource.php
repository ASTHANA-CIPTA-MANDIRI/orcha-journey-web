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
            'lokasi_kembali' => $this->lokasi_kembali,

            // Kapan unit ditunggu kembali, dan apakah sudah lewat
            'tanggal_selesai' => $this->jadwal_selesai?->toDateString(),
            'jam_selesai' => $this->jadwal_selesai?->format('H:i'),
            'jadwal_selesai' => $this->jadwal_selesai?->toIso8601String(),
            'terlambat' => $this->terlambat,
            'terlambat_menit' => $this->terlambat_menit,
            'denda_keterlambatan_usulan' => $this->denda_keterlambatan_usulan,

            // Serah terima
            'diserahkan_pada' => $this->diserahkan_pada?->toIso8601String(),
            'dikembalikan_pada' => $this->dikembalikan_pada?->toIso8601String(),
            'kilometer_awal' => $this->kilometer_awal,
            'kilometer_akhir' => $this->kilometer_akhir,
            'bahan_bakar_awal' => $this->bahan_bakar_awal,
            'bahan_bakar_akhir' => $this->bahan_bakar_akhir,
            'jaminan' => $this->jaminan,
            'kondisi_awal' => $this->kondisi_awal ?? [],
            'kondisi_akhir' => $this->kondisi_akhir ?? [],
            // Hanya bagian yang memburuk selama masa sewa — lecet lama tidak
            // ikut, dan itulah yang membedakan tagihan yang adil dari sengketa.
            'kerusakan_baru' => $this->kerusakan_baru,

            'estimasi_biaya' => $this->estimasi_biaya,
            'denda_keterlambatan' => $this->denda_keterlambatan,
            'denda_kerusakan' => $this->denda_kerusakan,
            'denda_lain' => $this->denda_lain,
            'catatan_denda' => $this->catatan_denda,
            'total_denda' => $this->total_denda,
            'total_tagihan' => $this->total_tagihan,

            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
