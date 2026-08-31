<?php

namespace App\Http\Resources\OpenTrip;

use App\Support\TagihanPesanan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\OpenTrip\KonfirmasiPembayaran $resource
 */
class PembayaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pesanan = $this->pesanan();

        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'jenis' => $this->jenis,
            'jenis_label' => $this->jenis_label,
            'nominal' => $this->nominal,
            'nominal_formatted' => $this->nominal_formatted,
            'tanggal_transfer' => $this->tanggal_transfer?->toDateString(),
            'bank_pengirim' => $this->bank_pengirim,
            'atas_nama_pengirim' => $this->atas_nama_pengirim,
            'bukti' => \App\Support\BerkasRahasia::tautan($this->bukti),
            'catatan' => $this->catatan,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'catatan_admin' => $this->catatan_admin,

            // Kode bisa salah ketik; bila tidak ketemu, admin tetap melihat
            // buktinya dan mencocokkan sendiri.
            'pesanan' => $pesanan ? [
                'nama' => $pesanan->nama,
                'whatsapp' => $pesanan->whatsapp,
                'keterangan' => $pesanan->nama_paket ?? $pesanan->nama_kendaraan ?? null,

                // Sisa tagihannya ikut dikirim karena itulah yang ditanyakan
                // pelanggan begitu buktinya diterima — "berarti kurang berapa
                // lagi?". Tanpa ini admin harus membuka halaman pesanan dulu
                // sebelum bisa menjawabnya.
                'tagihan' => TagihanPesanan::untuk($pesanan, hanyaDiterima: true),
            ] : null,

            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
