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
            // Keterangan unitnya ikut dikirim supaya admin lemon melihat hal yang
            // sama dengan yang tertulis di surat penyewa: sebutan lengkap,
            // kapasitas, keterangan sopir, dan pos biaya yang termasuk. Tanpa itu
            // admin membaca "HiAce Commuter" sementara penyewa memegang surat
            // yang menyebut merek, tipe, tahun, dan siapa menanggung BBM.
            'kendaraan' => [
                'id' => $this->car_id,
                // nama_kendaraan disimpan pada penyewaannya sebagai jejak: unit
                // boleh berganti nama, catatan penyewaan lama tidak ikut berubah.
                'nama' => $this->nama_kendaraan,
                'transmisi' => $this->transmisi,
                'sebutan' => $this->kendaraan?->sebutan_lengkap,
                'kapasitas' => $this->kendaraan?->capacity,
                'kursi_total' => $this->kendaraan?->kursi_total,
                'sopir_label' => $this->kendaraan?->sopir_label,
                'operasional_label' => $this->kendaraan?->operasional_label,
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
            'tujuan' => $this->tujuan,
            'luar_kota' => (bool) $this->luar_kota,

            // Kapan unit ditunggu kembali, dan apakah sudah lewat
            'tanggal_selesai' => $this->jadwal_selesai?->toDateString(),
            'jam_selesai' => $this->jadwal_selesai?->format('H:i'),
            'jadwal_selesai' => $this->jadwal_selesai?->toIso8601String(),
            'terlambat' => $this->terlambat,
            'terlambat_menit' => $this->terlambat_menit,
            'denda_keterlambatan_usulan' => $this->denda_keterlambatan_usulan,
            'denda_kerusakan_usulan' => $this->denda_kerusakan_usulan,
            'rincian_denda_kerusakan' => $this->rincian_denda_kerusakan,
            // Yang di atas usulan, yang ini keputusan. Keduanya dikirim karena
            // artinya berbeda: usulan hilang sendiri begitu kondisi unit
            // diperbarui, sedangkan yang ditetapkan harus tetap bisa
            // ditunjukkan selama tagihannya masih berlaku.
            'rincian_denda' => $this->rincian_denda ?? [],

            // Serah terima
            'diserahkan_pada' => $this->diserahkan_pada?->toIso8601String(),
            'dikembalikan_pada' => $this->dikembalikan_pada?->toIso8601String(),
            'kilometer_awal' => $this->kilometer_awal,
            'kilometer_akhir' => $this->kilometer_akhir,
            'bahan_bakar_awal' => $this->bahan_bakar_awal,
            'bahan_bakar_akhir' => $this->bahan_bakar_akhir,
            'jaminan' => $this->jaminan,
            'berkas_jaminan' => $this->berkas_jaminan,
            'kondisi_awal' => $this->kondisi_awal ?? [],
            'kondisi_akhir' => $this->kondisi_akhir ?? [],
            // Hanya bagian yang memburuk selama masa sewa — lecet lama tidak
            // ikut, dan itulah yang membedakan tagihan yang adil dari sengketa.
            'kerusakan_baru' => $this->kerusakan_baru,
            // Kondisi terakhir unit yang tercatat, dipakai lemon mengisi kolom
            // "saat diserahkan" tanpa admin mengetik ulang lecet lama.
            'kondisi_unit_terkini' => $this->kendaraan?->kondisi_terkini ?? [],

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
